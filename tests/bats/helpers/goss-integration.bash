#!/usr/bin/env bash
# BATS Test Helper - Goss Integration
# Provides helpers for running Goss tests from BATS

# Run Goss test for a specific service
run_goss_test() {
    local service="$1"
    make "goss-test-${service}" 2>&1
}

# Run Goss test with a specific preset
run_goss_preset() {
    local preset="$1"
    make goss-test-preset PRESET="$preset" 2>&1
}

# Check if Goss test passed
goss_test_passed() {
    local output="$1"
    echo "$output" | grep -qE "(Count: [0-9]+, Failed: 0|All tests passed)"
}

# Extract Goss test count from output
get_goss_test_count() {
    local output="$1"
    echo "$output" | grep -oE "Count: [0-9]+" | grep -oE "[0-9]+" | head -1
}

# Extract Goss failed count from output
get_goss_failed_count() {
    local output="$1"
    echo "$output" | grep -oE "Failed: [0-9]+" | grep -oE "[0-9]+" | head -1
}

# Run Goss validation and assert success
assert_goss_passes() {
    local service="$1"
    run run_goss_test "$service"
    assert_success
    assert goss_test_passed "$output"
}

# Wait for service and run Goss test
wait_and_test_service() {
    local service="$1"
    local timeout="${2:-60}"

    # Wait for container to be healthy
    if ! wait_for_healthy "$service" "$timeout"; then
        echo "Timeout waiting for $service to be healthy"
        return 1
    fi

    # Run Goss test
    run_goss_test "$service"
}

# Cleanup Goss test containers
cleanup_goss() {
    make goss-cleanup 2>/dev/null || true
}

# Get list of available Goss presets
get_goss_presets() {
    ls -1 tests/goss/presets/*.env 2>/dev/null | xargs -I{} basename {} .env
}

# Check if preset exists
preset_exists() {
    local preset="$1"
    [[ -f "tests/goss/presets/${preset}.env" ]]
}
