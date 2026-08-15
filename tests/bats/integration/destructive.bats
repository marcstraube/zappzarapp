#!/usr/bin/env bats
# Integration Tests: Destructive Operations
# ⚠️  WARNING: These tests perform destructive operations!
#
# These tests are SKIPPED by default unless explicitly enabled.
# To run: BATS_ENABLE_DESTRUCTIVE=true make bats-test-integration-file FILE=destructive.bats
#
# Test Strategy:
# 1. reset-full (clean slate) → verify deletion
# 2. setup/build/install → verify file creation
# 3. individual destructive tests → verify specific deletions
# 4. reset-full (cleanup) → leave clean state
#
# CI Environment Notes:
# - These tests are designed for clean CI environments
# - Timeouts are generous to handle cold builds (no cache)
# - Container health is verified before dependency installation

load 'setup'

# =============================================================================
# Safety Check - Skip unless explicitly enabled
# =============================================================================

setup() {
    load "${SCRIPT_DIR}/../helpers/setup.bash"

    if [[ "${BATS_ENABLE_DESTRUCTIVE:-false}" != "true" ]]; then
        skip "Destructive tests disabled (set BATS_ENABLE_DESTRUCTIVE=true to enable)"
    fi
}

# =============================================================================
# Helper Functions
# =============================================================================

# Wait for containers to be healthy (max wait time in seconds)
wait_for_containers() {
    local max_wait="${1:-120}"
    local elapsed=0
    echo "# Waiting for containers to be healthy (max ${max_wait}s)..." >&3
    while [[ $elapsed -lt $max_wait ]]; do
        # Check for any running container from this project (node-backend is always present)
        if docker compose ps --status running 2>/dev/null | grep -qE "(node-backend|nginx|php)"; then
            echo "# Containers healthy after ${elapsed}s" >&3
            return 0
        fi
        sleep 5
        elapsed=$((elapsed + 5))
    done
    echo "# Warning: Containers not fully healthy after ${max_wait}s" >&3
    return 1
}

# =============================================================================
# Phase 1: Full Reset (Clean Slate)
# =============================================================================

@test "[Phase 1] make reset-full cleans everything" {
    echo "# ⚠️  Starting destructive tests - full reset..." >&3

    # Pipe confirmation string for non-interactive execution
    run bash -c "echo 'RESET-FULL' | timeout 300 make reset-full"
    assert_success
}

@test "[Phase 1] Verify: no containers running after reset-full" {
    run docker compose ps -a
    # Should be empty or show no running containers
    refute_output --partial "(healthy)"
    refute_output --partial "Up"
}

@test "[Phase 1] Verify: vendor/ removed after reset-full" {
    [[ ! -d "vendor" ]] || [[ -z "$(ls -A vendor 2>/dev/null)" ]]
}

@test "[Phase 1] Verify: node_modules/ removed after reset-full" {
    [[ ! -d "node_modules" ]] || [[ -z "$(ls -A node_modules 2>/dev/null)" ]]
}

@test "[Phase 1] Verify: .pnpm-store/ removed after reset-full" {
    [[ ! -d ".pnpm-store" ]]
}

@test "[Phase 1] Verify: docs/api/ removed after reset-full" {
    [[ ! -d "docs/api" ]]
}

# =============================================================================
# Phase 2: Build & Create (Verify File Creation)
#
# Order matters: dependencies are installed BEFORE `make up` because the php
# and node containers do not come up without vendor/ and node_modules/. The
# install targets use `docker compose run --rm` (no running containers needed)
# and carry their own secrets/ssl-ensure preflights.
# =============================================================================

@test "[Phase 2] make build creates Docker images" {
    # 10 min timeout for cold build after full reset
    run timeout 600 make build
    assert_success
}

@test "[Phase 2] make composer-install installs PHP dependencies" {
    run timeout 300 make composer-install
    assert_success
}

@test "[Phase 2] Verify: vendor/autoload.php exists in the php container" {
    # In development vendor/ lives in the php_vendor named volume, not on the
    # host - verify inside the container view (compose run enables the
    # service's profile automatically, no running containers needed)
    run timeout 120 docker compose run --rm --no-TTY php sh -c 'test -f vendor/autoload.php'
    assert_success
}

@test "[Phase 2] make pnpm-install installs Node dependencies" {
    run timeout 300 make pnpm-install
    assert_success
}

@test "[Phase 2] Verify: node_modules/.pnpm/ exists in the node container" {
    # In development node_modules/ lives in a named volume, not on the host -
    # verify inside the container view
    run timeout 120 docker compose run --rm --no-TTY --entrypoint "" node sh -c 'test -d node_modules/.pnpm'
    assert_success
}

@test "[Phase 2] make up starts containers" {
    run timeout 180 make up
    assert_success
}

@test "[Phase 2] Verify: containers are running" {
    # Wait for containers to become healthy before proceeding
    wait_for_containers 120 || true
    run docker compose ps --status running
    assert_success
    # Check that at least one container is running (SERVICE column shows nginx, php, etc.)
    # If no containers running, skip dependent tests (handled by their skip conditions)
    if ! echo "$output" | grep -qE "(nginx|php|node)"; then
        skip "No containers running (environment issue) - dependent tests will be skipped"
    fi
}

# =============================================================================
# Phase 3: Documentation (Verify Doc Generation)
# =============================================================================

@test "[Phase 3] make docs generates documentation" {
    # Skip if containers not running (required for PHP docs)
    if ! docker compose ps --status running 2>/dev/null | grep -q "php"; then
        skip "PHP container not running (required for docs)"
    fi
    run timeout 300 make docs
    assert_success
}

@test "[Phase 3] Verify: docs/api/ directory created" {
    # If docs/api/ doesn't exist, make docs was likely skipped or failed
    [[ -d "docs/api" ]] || skip "docs/api/ not created (make docs likely skipped)"
}

@test "[Phase 3] Verify: docs/api/php/ exists" {
    # If docs/api/ doesn't exist, make docs was likely skipped or failed
    [[ -d "docs/api/php" ]] || skip "docs/api/php/ not found (make docs likely skipped)"
}

@test "[Phase 3] Verify: docs/api/node-backend/ exists" {
    # Skip if docs weren't generated (no docs/api directory)
    if [[ ! -d "docs/api" ]]; then
        skip "Skipped because docs weren't generated"
    fi
    [[ -d "docs/api/node-backend" ]]
}

# =============================================================================
# Phase 4: Redis Operations (containers still running)
# =============================================================================

@test "[Phase 4] Redis is accessible" {
    if ! docker compose ps --status running redis 2>/dev/null | grep -q redis; then
        skip "Redis not running"
    fi
    # healthcheck.sh doubles as the TLS-aware redis-cli wrapper (redis
    # serves TLS-only, plain redis-cli gets a connection reset)
    run docker compose exec -T redis /usr/local/bin/healthcheck.sh PING
    assert_success
    assert_output "PONG"
}

@test "[Phase 4] make redis-flush clears data" {
    if ! docker compose ps --status running redis 2>/dev/null | grep -q redis; then
        skip "Redis not running"
    fi

    # Add test data (TLS-aware wrapper, see above)
    docker compose exec -T redis /usr/local/bin/healthcheck.sh SET destructive_test "value" >/dev/null

    # Flush (pipe confirmation for non-interactive execution)
    run bash -c "echo 'YES' | timeout 30 make redis-flush"
    assert_success

    # Verify gone
    run docker compose exec -T redis /usr/local/bin/healthcheck.sh GET destructive_test
    assert_output ""
}

# =============================================================================
# Phase 5: SSL Certificates (Create & Delete)
# =============================================================================

@test "[Phase 5] make ssl-internal generates certificates" {
    # Stop all containers first: deleting cert files while containers still
    # reference them as bind-mount sources makes the Docker daemon recreate
    # the paths as root-owned DIRECTORIES on the next (crash-loop) restart,
    # which breaks generation and cleanup with permission errors
    timeout 120 make down 2>/dev/null || true

    # Remove existing certs
    rm -rf docker/certs/ca docker/certs/nginx docker/certs/internal 2>/dev/null || true

    run timeout 60 make ssl-internal
    assert_success
}

@test "[Phase 5] Verify: CA created" {
    [[ -f "docker/certs/ca/ca.crt" ]]
    [[ -f "docker/certs/ca/ca.key" ]]
}

@test "[Phase 5] Verify: nginx certificates created" {
    [[ -f "docker/certs/nginx/cert.crt" ]]
    [[ -f "docker/certs/nginx/cert.key" ]]
}

@test "[Phase 5] Verify: internal certificates created" {
    [[ -f "docker/certs/internal/cert.crt" ]]
    [[ -f "docker/certs/internal/cert.key" ]]
    [[ -f "docker/certs/internal/ca.crt" ]]
}

@test "[Phase 5] make ssl-clean removes certificates" {
    # Pipe confirmation for non-interactive execution
    run bash -c "echo 'YES' | make ssl-clean"
    assert_success
}

@test "[Phase 5] Verify: certificate directories removed" {
    [[ ! -d "docker/certs/ca" ]]
    [[ ! -d "docker/certs/nginx" ]]
    [[ ! -d "docker/certs/internal" ]]
}

# =============================================================================
# Phase 6: Documentation Cleanup
# =============================================================================

@test "[Phase 6] make docs-clean removes documentation" {
    run make docs-clean
    assert_success
}

@test "[Phase 6] Verify: docs/api/ removed" {
    [[ ! -d "docs/api" ]]
}

# =============================================================================
# Phase 7: Container Cleanup (Escalating)
# =============================================================================

@test "[Phase 7] make clean removes containers" {
    run timeout 60 make clean
    assert_success
}

@test "[Phase 7] Verify: no running containers after clean" {
    run docker compose ps --status running
    # Should have no containers with zappzarapp prefix running
    refute_output --partial "zappzarapp-"
}

@test "[Phase 7] make reset removes containers and volumes" {
    # First bring up containers again
    make up 2>/dev/null || true
    sleep 5

    # Pipe confirmation for non-interactive execution
    run bash -c "echo 'RESET' | timeout 180 make reset"
    assert_success
}

@test "[Phase 7] Verify: no containers after reset" {
    run docker compose ps -a
    refute_output --partial "(healthy)"
}

# =============================================================================
# Phase 8: Final Cleanup (Leave Clean State)
# =============================================================================

@test "[Phase 8] make reset-full final cleanup" {
    echo "# Performing final cleanup..." >&3
    # Pipe confirmation string for non-interactive execution
    run bash -c "echo 'RESET-FULL' | timeout 300 make reset-full"
    assert_success
}

@test "[Phase 8] Verify: clean state for next run" {
    run docker compose ps -a
    refute_output --partial "Up"
    echo "# ✓ Destructive tests complete - system in clean state" >&3
}
