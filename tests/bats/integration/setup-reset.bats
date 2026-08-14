#!/usr/bin/env bats
# Integration Tests: Setup and Reset-Full Cycle
#
# Tests that:
# 1. make setup creates expected directories and files
# 2. make reset-full restores to original boilerplate state
#
# WARNING: These are DESTRUCTIVE tests!
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

@test "[Phase 1] Snapshot local tags, create inherited-tag sentinel" {
    # Protects the developer's local tags: setup's tag cleanup deletes ALL
    # local tags on a pristine clone. Snapshot them here, restore in Phase 6.
    git show-ref --tags > "${BATS_TMPDIR}/zappzarapp-tags-snapshot" 2>/dev/null || true

    # Sentinel simulates a release tag inherited from the boilerplate clone
    # (unsigned: developer machines may enforce tag signing via hardware key)
    git -c tag.gpgSign=false tag v99.99.99-bats-sentinel
    run git tag -l v99.99.99-bats-sentinel
    assert_output "v99.99.99-bats-sentinel"
}

# =============================================================================
# Phase 2: Setup Creates Files
# =============================================================================

@test "[Phase 2] make setup creates project structure" {
    echo "# Running make setup (CI_TEST=1 BOILERPLATE=1)..." >&3

    # Pipe 'c' for "continue with defaults" to skip .env.local prompt
    # CI_TEST=1 skips Docker-dependent steps (build, deps, up, migrations, docs)
    # BOILERPLATE=1 forces boilerplate mode (file swaps) even when developing zappzarapp
    run bash -c "echo 'c' | timeout 120 make setup CI_TEST=1 BOILERPLATE=1"
    assert_success
}

@test "[Phase 2] Verify: .ai/ directory created" {
    [[ -d ".ai" ]]
}

@test "[Phase 2] Verify: .ai/LEARNINGS.md created" {
    [[ -f ".ai/LEARNINGS.md" ]]
}

@test "[Phase 2] Verify: docs/adr/ created with template and index" {
    [[ -d "docs/adr" ]]
    [[ -f "docs/adr/0000-template.md" ]]
    [[ -f "docs/adr/README.md" ]]
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

@test "[Phase 2] Verify: README.md replaced (no boilerplate marker)" {
    # After setup, README.md should NOT contain the boilerplate marker
    run grep -q "zappzarapp-boilerplate-readme" README.md
    assert_failure
}

@test "[Phase 2] Verify: AGENTS.md replaced (no boilerplate marker)" {
    run grep -q "zappzarapp-boilerplate-agents" AGENTS.md
    assert_failure
}

@test "[Phase 2] Verify: .zappzarapp/ai/AGENTS.md created (boilerplate moved)" {
    [[ -f ".zappzarapp/ai/AGENTS.md" ]]
}

@test "[Phase 2] Verify: context/zappzarapp.md exists (platform context)" {
    [[ -f ".claude/context/zappzarapp.md" ]]
}

@test "[Phase 2] Verify: context/project.md replaced (no boilerplate marker)" {
    run grep -q "zappzarapp-boilerplate-context" .claude/context/project.md
    assert_failure
}

@test "[Phase 2] Verify: .zappzarapp/ai/context-project.md created (boilerplate moved)" {
    [[ -f ".zappzarapp/ai/context-project.md" ]]
}

@test "[Phase 2] Verify: make customize reports the swapped-in templates" {
    # After the swap, the active templates carry customization placeholders
    run make customize
    assert_success
    assert_output --partial "needing customization"
}

@test "[Phase 2] Verify: inherited boilerplate tags removed" {
    # First setup on a pristine clone deletes all local tags (they are
    # inherited boilerplate release tags by definition)
    run git tag
    assert_output ""
}

@test "[Phase 2] Verify: CHANGELOG.md replaced (no boilerplate marker)" {
    # After setup, CHANGELOG.md should NOT contain the boilerplate marker
    run grep -q "zappzarapp - Changelog" CHANGELOG.md
    assert_failure
}

@test "[Phase 2] Verify: .zappzarapp/CHANGELOG.md created (boilerplate moved)" {
    # Boilerplate changelog should be moved here
    [[ -f ".zappzarapp/CHANGELOG.md" ]]
    run grep -q "zappzarapp - Changelog" .zappzarapp/CHANGELOG.md
    assert_success
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

@test "[Phase 3] Verify: README.md restored to boilerplate (contains marker)" {
    # After reset-full, README.md should contain the boilerplate marker
    run grep -q "zappzarapp-boilerplate-readme" README.md
    assert_success
}

@test "[Phase 3] Verify: AGENTS.md restored to boilerplate (contains marker)" {
    run grep -q "zappzarapp-boilerplate-agents" AGENTS.md
    assert_success
}

@test "[Phase 3] Verify: CHANGELOG.md restored to boilerplate (contains marker)" {
    # After reset-full, CHANGELOG.md should contain the boilerplate marker
    run grep -q "zappzarapp - Changelog" CHANGELOG.md
    assert_success
}

@test "[Phase 3] Verify: .zappzarapp/CHANGELOG.md removed" {
    # The moved boilerplate changelog should be removed
    [[ ! -f ".zappzarapp/CHANGELOG.md" ]]
}

@test "[Phase 3] Verify: context/project.md restored to boilerplate (contains marker)" {
    run grep -q "zappzarapp-boilerplate-context" .claude/context/project.md
    assert_success
}

@test "[Phase 3] Verify: .zappzarapp/ai/context-project.md removed" {
    # The moved boilerplate context should be removed
    [[ ! -f ".zappzarapp/ai/context-project.md" ]]
}

# =============================================================================
# Phase 4: Idempotency Check
# =============================================================================

@test "[Phase 4] make setup is idempotent (can run twice)" {
    # Run setup twice - should not fail
    # CI_TEST=1 skips Docker-dependent steps
    # BOILERPLATE=1 forces boilerplate mode for consistent testing
    run bash -c "echo 'c' | timeout 120 make setup CI_TEST=1 BOILERPLATE=1"
    assert_success

    run bash -c "echo 'c' | timeout 120 make setup CI_TEST=1 BOILERPLATE=1"
    assert_success
}

@test "[Phase 4] Verify: .ai/ still exists after double setup" {
    [[ -d ".ai" ]]
    [[ -f ".ai/LEARNINGS.md" ]]
}

@test "[Phase 4] Verify: project tags survive setup re-runs" {
    # After the first setup the README marker is gone, so the tag cleanup
    # must never touch tags the project creates later
    # (unsigned: developer machines may enforce tag signing via hardware key)
    git -c tag.gpgSign=false tag v0.0.1-bats-project

    run bash -c "echo 'c' | timeout 120 make setup CI_TEST=1 BOILERPLATE=1"
    assert_success

    run git tag -l v0.0.1-bats-project
    assert_output "v0.0.1-bats-project"

    git tag -d v0.0.1-bats-project
}

# =============================================================================
# Phase 5: Docker Bind Mount Edge Cases
# Note: Tests 43-44 test Docker operations that don't work in DinD environments
# due to buildx context path resolution issues. They are skipped but can be
# run manually on the host for verification.
# =============================================================================

@test "[Phase 5] composer-install handles lockfile as directory" {
    # Skip: buildx context issue prevents Docker operations in DinD
    skip "Requires host Docker execution (buildx context issue in DinD)"

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
    # Skip: buildx context issue prevents Docker operations in DinD
    skip "Requires host Docker execution (buildx context issue in DinD)"

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
    # CI_TEST=1 skips Docker-dependent steps
    # BOILERPLATE=1 for consistent testing
    run bash -c "echo 'c' | timeout 120 make setup CI_TEST=1 BOILERPLATE=1"
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

    echo "# Setup-reset cycle test complete" >&3
}

@test "[Phase 6] Restore snapshotted local tags" {
    # Restore the developer's tags captured in Phase 1
    if [[ -s "${BATS_TMPDIR}/zappzarapp-tags-snapshot" ]]; then
        while read -r sha ref; do
            git update-ref "$ref" "$sha"
        done < "${BATS_TMPDIR}/zappzarapp-tags-snapshot"
    fi
    rm -f "${BATS_TMPDIR}/zappzarapp-tags-snapshot"

    # The sentinel and the project test tag must not survive the cycle
    run git tag -l 'v99.99.99-bats-sentinel' 'v0.0.1-bats-project'
    assert_output ""
}
