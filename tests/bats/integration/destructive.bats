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

@test "[Phase 1] Verify: docs/api/ removed after reset-full" {
    [[ ! -d "docs/api" ]]
}

# =============================================================================
# Phase 2: Build & Create (Verify File Creation)
# =============================================================================

@test "[Phase 2] make build creates Docker images" {
    run timeout 300 make build
    assert_success
}

@test "[Phase 2] make up starts containers" {
    run timeout 120 make up
    assert_success
}

@test "[Phase 2] Verify: containers are running" {
    run docker compose ps --status running
    assert_success
    assert_output --partial "nginx"
}

@test "[Phase 2] make composer-install installs PHP dependencies" {
    run timeout 180 make composer-install
    assert_success
}

@test "[Phase 2] Verify: vendor/ directory created" {
    [[ -d "vendor" ]]
}

@test "[Phase 2] Verify: vendor/autoload.php exists" {
    [[ -f "vendor/autoload.php" ]]
}

@test "[Phase 2] make pnpm-install installs Node dependencies" {
    run timeout 180 make pnpm-install
    assert_success
}

@test "[Phase 2] Verify: node_modules/ directory created" {
    [[ -d "node_modules" ]]
}

@test "[Phase 2] Verify: node_modules/.pnpm/ exists" {
    [[ -d "node_modules/.pnpm" ]]
}

# =============================================================================
# Phase 3: Documentation (Verify Doc Generation)
# =============================================================================

@test "[Phase 3] make docs generates documentation" {
    run timeout 300 make docs
    assert_success
}

@test "[Phase 3] Verify: docs/api/ directory created" {
    [[ -d "docs/api" ]]
}

@test "[Phase 3] Verify: docs/api/php/ exists" {
    [[ -d "docs/api/php" ]]
}

@test "[Phase 3] Verify: docs/api/node-backend/ exists" {
    [[ -d "docs/api/node-backend" ]]
}

# =============================================================================
# Phase 4: SSL Certificates (Create & Delete)
# =============================================================================

@test "[Phase 4] make ssl-selfsigned generates certificates" {
    # Remove existing certs first
    rm -f docker/certs/cert.crt docker/certs/cert.key 2>/dev/null || true

    run timeout 60 make ssl-selfsigned
    assert_success
}

@test "[Phase 4] Verify: cert.crt created" {
    [[ -f "docker/certs/cert.crt" ]]
}

@test "[Phase 4] Verify: cert.key created" {
    [[ -f "docker/certs/cert.key" ]]
}

@test "[Phase 4] make ssl-clean removes certificates" {
    run make ssl-clean
    assert_success
}

@test "[Phase 4] Verify: cert.crt removed" {
    [[ ! -f "docker/certs/cert.crt" ]]
}

# =============================================================================
# Phase 5: Documentation Cleanup
# =============================================================================

@test "[Phase 5] make docs-clean removes documentation" {
    run make docs-clean
    assert_success
}

@test "[Phase 5] Verify: docs/api/ removed" {
    [[ ! -d "docs/api" ]]
}

# =============================================================================
# Phase 6: Redis Operations
# =============================================================================

@test "[Phase 6] Redis is accessible" {
    if ! docker compose ps --status running redis 2>/dev/null | grep -q redis; then
        skip "Redis not running"
    fi
    run docker compose exec -T redis redis-cli PING
    assert_success
    assert_output "PONG"
}

@test "[Phase 6] make redis-flush clears data" {
    if ! docker compose ps --status running redis 2>/dev/null | grep -q redis; then
        skip "Redis not running"
    fi

    # Add test data
    docker compose exec -T redis redis-cli SET destructive_test "value" >/dev/null

    # Flush
    run timeout 30 make redis-flush
    assert_success

    # Verify gone
    run docker compose exec -T redis redis-cli GET destructive_test
    assert_output ""
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
    refute_output --partial "nginx"
}

@test "[Phase 7] make reset removes containers and volumes" {
    # First bring up containers again
    make up 2>/dev/null || true
    sleep 5

    run timeout 120 make reset
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
