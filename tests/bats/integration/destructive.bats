#!/usr/bin/env bats
# Integration Tests: Destructive Operations
# ⚠️  WARNING: These tests perform destructive operations!
#
# These tests are SKIPPED by default unless explicitly enabled.
# To run: BATS_ENABLE_DESTRUCTIVE=true make bats-test-integration-file FILE=destructive.bats
#
# Destructive operations tested:
# - make reset (stops containers, removes volumes)
# - make reset-full (full cleanup including images)
# - make prune (removes unused Docker resources)
# - make clean (removes containers and networks)
# - make redis-flush (flushes Redis data)
# - make db-cleanup (removes database data)

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
# File Setup/Teardown
# =============================================================================

setup_file() {
    if [[ "${BATS_ENABLE_DESTRUCTIVE:-false}" != "true" ]]; then
        return 0
    fi

    echo "# ⚠️  WARNING: Running destructive tests!" >&3
    echo "# These tests will modify/delete data!" >&3

    # Ensure we have a clean state to destroy
    integration_setup
}

teardown_file() {
    if [[ "${BATS_ENABLE_DESTRUCTIVE:-false}" != "true" ]]; then
        return 0
    fi

    # Try to restore a working state
    echo "# Attempting to restore clean state after destructive tests..." >&3
    make up 2>&1 | tail -3 >&3 || true
}

# =============================================================================
# Redis Flush (Least Destructive)
# =============================================================================

@test "[Destructive] make redis-flush clears Redis data" {
    require_service "redis"

    # Add some test data
    docker compose exec -T redis redis-cli SET test_key "test_value" >/dev/null

    # Flush
    run timeout 30 make redis-flush
    assert_success

    # Verify data is gone
    run docker compose exec -T redis redis-cli GET test_key
    assert_output ""
}

# =============================================================================
# Clean (Moderate - Removes Containers)
# =============================================================================

@test "[Destructive] make clean removes containers and networks" {
    # Ensure containers are running first
    run make status
    assert_success

    # Run clean
    run timeout 60 make clean
    assert_success

    # Verify containers stopped
    run docker compose ps --status running
    refute_output --partial "nginx"
}

# =============================================================================
# Reset (Destructive - Removes Volumes)
# =============================================================================

@test "[Destructive] make reset stops containers and removes volumes" {
    # First ensure we have containers
    make up 2>/dev/null || true
    sleep 5

    # Run reset
    run timeout 120 make reset
    assert_success

    # Verify containers are gone
    run docker compose ps -a
    # Should show no containers or only exited ones
    refute_output --partial "(healthy)"
}

# =============================================================================
# Prune (Destructive - System-Wide Docker Cleanup)
# =============================================================================

@test "[Destructive] make prune removes unused Docker resources" {
    # This affects system-wide Docker resources!
    run timeout 120 make prune
    assert_success
    assert_output --partial "Total reclaimed space" || assert_output --partial "deleted"
}

# =============================================================================
# Database Cleanup (Very Destructive - Deletes Data)
# =============================================================================

@test "[Destructive] make db-cleanup removes database data" {
    # Ensure database is running
    make up 2>/dev/null || true
    require_database

    # Run cleanup
    run timeout 60 make db-cleanup
    assert_success
}

# =============================================================================
# Reset Full (Most Destructive - Full Cleanup)
# =============================================================================

@test "[Destructive] make reset-full performs complete cleanup" {
    # This is the nuclear option
    run timeout 300 make reset-full
    assert_success

    # Verify everything is gone
    run docker compose ps -a
    # Should be empty or minimal
    [[ -z "$output" ]] || refute_output --partial "Up"
}

# =============================================================================
# Docs Clean (File Destructive)
# =============================================================================

@test "[Destructive] make docs-clean removes generated documentation" {
    # First generate some docs if possible
    make docs 2>/dev/null || true

    # Clean
    run make docs-clean
    assert_success

    # Verify docs removed
    [[ ! -d "docs/api" ]]
}

# =============================================================================
# SSL Clean (File Destructive)
# =============================================================================

@test "[Destructive] make ssl-clean removes SSL certificates" {
    # First generate a cert if needed
    if [[ ! -f "docker/certs/cert.crt" ]]; then
        make ssl-selfsigned 2>/dev/null || true
    fi

    # Clean
    run make ssl-clean
    assert_success

    # Verify certs removed
    [[ ! -f "docker/certs/cert.crt" ]]
}
