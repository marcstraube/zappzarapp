#!/usr/bin/env bats
# Integration Tests: Setup and Reset-Full Cycle
#
# Tests that:
# 1. make setup creates expected directories and files
# 2. make reset-full restores to original boilerplate state
#
# ⚠️  WARNING: These are DESTRUCTIVE tests!
# They will modify project state and require BATS_ENABLE_DESTRUCTIVE=true.
#
# Run: BATS_ENABLE_DESTRUCTIVE=true make bats-test-integration-file FILE=setup-reset.bats

load 'setup'

# =============================================================================
# Safety Check
# =============================================================================

setup() {
    load "${SCRIPT_DIR}/../helpers/setup.bash"

    if [[ "${BATS_ENABLE_DESTRUCTIVE:-false}" != "true" ]]; then
        skip "Destructive tests disabled (set BATS_ENABLE_DESTRUCTIVE=true)"
    fi
}

# =============================================================================
# Phase 1: Clean State (Pre-Setup)
# =============================================================================

@test "[Phase 1] make reset-full cleans to boilerplate state" {
    echo "# Starting setup-reset cycle test..." >&3

    # Clean everything first
    run bash -c "echo 'RESET-FULL' | timeout 300 make reset-full"
    assert_success
}

@test "[Phase 1] Verify: .ai/ removed after reset-full" {
    [[ ! -d ".ai" ]]
}

@test "[Phase 1] Verify: vendor/ removed after reset-full" {
    [[ ! -d "vendor" ]] || [[ -z "$(ls -A vendor 2>/dev/null)" ]]
}

@test "[Phase 1] Verify: node_modules/ removed after reset-full" {
    [[ ! -d "node_modules" ]] || [[ -z "$(ls -A node_modules 2>/dev/null)" ]]
}

@test "[Phase 1] Verify: storage/ contents removed after reset-full" {
    # storage/ itself may exist but should be empty (except .gitkeep)
    if [[ -d "storage" ]]; then
        local file_count
        file_count=$(find storage -type f ! -name '.gitkeep' 2>/dev/null | wc -l)
        [[ "$file_count" -eq 0 ]]
    fi
}

# =============================================================================
# Phase 2: Setup Creates Files
# =============================================================================

@test "[Phase 2] make setup creates project structure" {
    echo "# Running make setup..." >&3

    # Pipe 'c' for "continue with defaults" to skip .env.local prompt
    # 300s timeout for slow CI environments
    run bash -c "echo 'c' | timeout 300 make setup"
    assert_success
}

@test "[Phase 2] Verify: .ai/ directory created" {
    [[ -d ".ai" ]]
}

@test "[Phase 2] Verify: .ai/BACKLOG.md created" {
    [[ -f ".ai/BACKLOG.md" ]]
}

@test "[Phase 2] Verify: .ai/LEARNINGS.md created" {
    [[ -f ".ai/LEARNINGS.md" ]]
}

@test "[Phase 2] Verify: .ai/DECISIONS.md created" {
    [[ -f ".ai/DECISIONS.md" ]]
}

@test "[Phase 2] Verify: .ai/REFERENCES.md created" {
    [[ -f ".ai/REFERENCES.md" ]]
}

@test "[Phase 2] Verify: storage/ directory created" {
    [[ -d "storage" ]]
}

@test "[Phase 2] Verify: storage/app/ created" {
    [[ -d "storage/app" ]]
}

@test "[Phase 2] Verify: storage/cache/ created" {
    [[ -d "storage/cache" ]]
}

@test "[Phase 2] Verify: storage/logs/ created" {
    [[ -d "storage/logs" ]]
}

@test "[Phase 2] Verify: build/ directory created" {
    [[ -d "build" ]]
}

@test "[Phase 2] Verify: build/coverage/ created" {
    [[ -d "build/coverage" ]]
}

@test "[Phase 2] Verify: docker/certs/ directory created" {
    [[ -d "docker/certs" ]]
}

@test "[Phase 2] Verify: backups/ directory created" {
    [[ -d "backups" ]]
}

@test "[Phase 2] Verify: backups/db/ created" {
    [[ -d "backups/db" ]]
}

@test "[Phase 2] Verify: docs/api/ directory created" {
    [[ -d "docs/api" ]]
}

@test "[Phase 2] Verify: tools/ directory created" {
    [[ -d "tools" ]]
}

# =============================================================================
# Phase 3: Reset-Full Restores Original State
# =============================================================================

@test "[Phase 3] make reset-full restores boilerplate state" {
    echo "# Running make reset-full to restore..." >&3

    run bash -c "echo 'RESET-FULL' | timeout 300 make reset-full"
    assert_success
}

@test "[Phase 3] Verify: .ai/ removed after reset-full" {
    [[ ! -d ".ai" ]]
}

@test "[Phase 3] Verify: storage/ cleaned after reset-full" {
    # storage/ contents should be gone (except .gitkeep)
    if [[ -d "storage" ]]; then
        local file_count
        file_count=$(find storage -type f ! -name '.gitkeep' 2>/dev/null | wc -l)
        [[ "$file_count" -eq 0 ]]
    fi
}

@test "[Phase 3] Verify: build/ removed after reset-full" {
    [[ ! -d "build" ]]
}

@test "[Phase 3] Verify: docs/api/ removed after reset-full" {
    [[ ! -d "docs/api" ]]
}

@test "[Phase 3] Verify: tools/ removed after reset-full" {
    [[ ! -d "tools" ]]
}

@test "[Phase 3] Verify: docs/index.html preserved (not removed)" {
    # Static docs should NOT be removed by reset-full
    [[ -f "docs/index.html" ]]
}

@test "[Phase 3] Verify: docs/assets/ preserved (not removed)" {
    [[ -d "docs/assets" ]]
}

# =============================================================================
# Phase 4: Idempotency Check
# =============================================================================

@test "[Phase 4] make setup is idempotent (can run twice)" {
    # Run setup twice - should not fail
    # 300s timeout for slow CI environments
    run bash -c "echo 'c' | timeout 300 make setup"
    assert_success

    run bash -c "echo 'c' | timeout 300 make setup"
    assert_success
}

@test "[Phase 4] Verify: .ai/ still exists after double setup" {
    [[ -d ".ai" ]]
    [[ -f ".ai/BACKLOG.md" ]]
}

# =============================================================================
# Phase 5: Final Cleanup
# =============================================================================

@test "[Phase 5] Final reset-full cleanup" {
    echo "# Final cleanup..." >&3

    run bash -c "echo 'RESET-FULL' | timeout 300 make reset-full"
    assert_success

    echo "# ✓ Setup-reset cycle test complete" >&3
}
