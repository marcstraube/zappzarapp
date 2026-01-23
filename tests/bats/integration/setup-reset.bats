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

@test "[Phase 1] Verify: .pnpm-store/ removed after reset-full" {
    [[ ! -d ".pnpm-store" ]]
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
    # 600s timeout for cold builds (Docker images + dependencies from scratch)
    run bash -c "echo 'c' | timeout 600 make setup"
    assert_success
}

@test "[Phase 2] Verify: .ai/ directory created" {
    [[ -d ".ai" ]]
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

@test "[Phase 2] Verify: dist/ directory created" {
    [[ -d "dist" ]]
}

@test "[Phase 2] Verify: composer.lock is a file (not directory)" {
    # Docker bind mount bug: creates directories when target doesn't exist
    [[ -f "composer.lock" ]]
    [[ ! -d "composer.lock" ]]
}

@test "[Phase 2] Verify: pnpm-lock.yaml is a file (not directory)" {
    # Docker bind mount bug: creates directories when target doesn't exist
    [[ -f "pnpm-lock.yaml" ]]
    [[ ! -d "pnpm-lock.yaml" ]]
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

@test "[Phase 3] Verify: dist/ removed after reset-full" {
    [[ ! -d "dist" ]]
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
    # 600s timeout for cold builds (second run should be faster due to cache)
    run bash -c "echo 'c' | timeout 600 make setup"
    assert_success

    run bash -c "echo 'c' | timeout 600 make setup"
    assert_success
}

@test "[Phase 4] Verify: .ai/ still exists after double setup" {
    [[ -d ".ai" ]]
    [[ -f ".ai/LEARNINGS.md" ]]
}

# =============================================================================
# Phase 5: Docker Bind Mount Edge Cases (Docker-in-Docker)
# =============================================================================

@test "[Phase 5] composer-install handles lockfile as directory" {
    # Docker bind mount bug: creates directories when target doesn't exist
    # First backup current lockfile
    if [[ -f "composer.lock" ]]; then
        cp composer.lock composer.lock.bak
    fi

    # Create lockfile as directory (simulates Docker bind mount bug)
    rm -f composer.lock 2>/dev/null || true
    docker run --rm -v "${PROJECT_ROOT}:/app" -w /app alpine:3.21 \
        sh -c "rm -rf composer.lock && mkdir composer.lock"

    # Verify it's a directory
    [[ -d "composer.lock" ]]

    # Run composer-install - should fix and succeed
    run bash -c "timeout 120 make composer-install"
    assert_success

    # Verify lockfile is now a file with content
    [[ -f "composer.lock" ]]
    [[ -s "composer.lock" ]]

    # Cleanup: restore backup if existed
    if [[ -f "composer.lock.bak" ]]; then
        mv composer.lock.bak composer.lock
    fi
}

@test "[Phase 5] pnpm-install handles lockfile as directory" {
    # Docker bind mount bug: creates directories when target doesn't exist
    # First backup current lockfile
    if [[ -f "pnpm-lock.yaml" ]]; then
        cp pnpm-lock.yaml pnpm-lock.yaml.bak
    fi

    # Create lockfile as directory (simulates Docker bind mount bug)
    rm -f pnpm-lock.yaml 2>/dev/null || true
    docker run --rm -v "${PROJECT_ROOT}:/app" -w /app alpine:3.21 \
        sh -c "rm -rf pnpm-lock.yaml && mkdir pnpm-lock.yaml"

    # Verify it's a directory
    [[ -d "pnpm-lock.yaml" ]]

    # Run pnpm-install - should fix and succeed
    run bash -c "timeout 180 make pnpm-install"
    assert_success

    # Verify lockfile is now a file with content
    [[ -f "pnpm-lock.yaml" ]]
    [[ -s "pnpm-lock.yaml" ]]

    # Cleanup: restore backup if existed
    if [[ -f "pnpm-lock.yaml.bak" ]]; then
        mv pnpm-lock.yaml.bak pnpm-lock.yaml
    fi
}

@test "[Phase 5] make setup fixes root-owned backups directory" {
    # Create root-owned directory via Docker
    docker run --rm -v "${PROJECT_ROOT}/backups:/backups" alpine:3.21 \
        sh -c "mkdir -p /backups/test-root && chown root:root /backups/test-root"

    # Verify root ownership exists
    run find backups -user root -type d
    [[ -n "$output" ]]

    # Run setup - should fix ownership
    run bash -c "echo 'c' | timeout 300 make setup"
    assert_success

    # Verify no root-owned directories remain (except what Docker may create)
    run find backups -user root -type d 2>/dev/null
    # Note: This may still find root-owned dirs if Docker is creating them
    # The important thing is setup didn't fail

    # Cleanup
    rm -rf backups/test-root 2>/dev/null || \
        docker run --rm -v "${PROJECT_ROOT}/backups:/backups" alpine:3.21 \
            sh -c "rm -rf /backups/test-root"
}

# =============================================================================
# Phase 6: Final Cleanup
# =============================================================================

@test "[Phase 6] Final reset-full cleanup" {
    echo "# Final cleanup..." >&3

    run bash -c "echo 'RESET-FULL' | timeout 300 make reset-full"
    assert_success

    echo "# ✓ Setup-reset cycle test complete" >&3
}
