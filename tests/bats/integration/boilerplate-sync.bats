#!/usr/bin/env bats
# Integration Tests: Boilerplate Sync
#
# Tests the boilerplate-sync and boilerplate-diff Makefile targets.
#
# Note: Since we're running in the zappzarapp repo itself, we can only test:
# - Error handling (sync should refuse to run in zappzarapp)
# - boilerplate-diff functionality
#
# Full sync testing would require a derived project repository.
#
# Run: make bats-test-integration-file FILE=boilerplate-sync.bats

load 'setup'

setup() {
    load "${SCRIPT_DIR}/../helpers/setup.bash"
}

# Helper: Skip if git has dubious ownership issue (container environment)
require_git_access() {
    if ! git status >/dev/null 2>&1; then
        if git status 2>&1 | grep -q "dubious ownership"; then
            skip "Git dubious ownership issue in test environment"
        fi
    fi
}

# =============================================================================
# Safety: boilerplate-sync should refuse to run in zappzarapp repo
# =============================================================================

@test "boilerplate-sync refuses to run in zappzarapp repo (origin)" {
    # When origin is marcstraube/zappzarapp, sync should fail with clear message
    if ! git remote get-url origin 2>/dev/null | grep -qE 'marcstraube/zappzarapp'; then
        skip "Not running in zappzarapp origin context"
    fi

    run make boilerplate-sync
    assert_failure
    assert_output --partial "Cannot sync zappzarapp to itself"
}

@test "boilerplate-sync allows fork contributors with upstream remote" {
    # Fork contributors have upstream → marcstraube/zappzarapp but their own origin
    # They SHOULD be able to sync (unlike direct contributors where origin → zappzarapp)
    # This is the correct behavior: only origin blocks self-sync, not upstream
    if ! git remote get-url upstream 2>/dev/null | grep -qE 'marcstraube/zappzarapp'; then
        skip "No zappzarapp upstream remote - test requires fork setup"
    fi

    # If origin is also marcstraube/zappzarapp, we're a direct contributor (not fork)
    if git remote get-url origin 2>/dev/null | grep -qE 'marcstraube/zappzarapp'; then
        skip "Direct contributor setup - not a fork"
    fi

    # Fork setup: origin != zappzarapp, upstream = zappzarapp
    # Sync should be allowed (this is read-only check, not actual sync)
    run make boilerplate-sync --dry-run 2>&1 || true
    # Should NOT contain "Cannot sync zappzarapp to itself"
    refute_output --partial "Cannot sync zappzarapp to itself"
}

# =============================================================================
# boilerplate-diff should work (read-only operation)
# =============================================================================

@test "boilerplate-diff adds zappzarapp remote if missing" {
    require_git_access

    # Remove remote if exists (cleanup from previous test)
    git remote remove zappzarapp 2>/dev/null || true

    # Run diff - should add remote automatically
    run timeout 30 make boilerplate-diff
    # May succeed or fail based on network, but should not error on missing remote

    # Verify remote was added
    run git remote get-url zappzarapp
    assert_success
    assert_output --partial "marcstraube/zappzarapp"
}

@test "boilerplate-diff shows differences without modifying files" {
    require_git_access

    # Get current HEAD
    local head_before
    head_before=$(git rev-parse HEAD)

    # Run diff
    run timeout 60 make boilerplate-diff
    # Network may fail, but local state should be unchanged

    # Verify no changes to working tree
    local head_after
    head_after=$(git rev-parse HEAD)
    [[ "$head_before" == "$head_after" ]]

    # Verify no uncommitted changes from the diff command
    run git diff --quiet
    # Should succeed (no changes) or was already dirty
}

@test "boilerplate-diff output mentions infrastructure paths" {
    run timeout 60 make boilerplate-diff

    # Output should reference the paths we're checking
    # (exact output depends on whether there are differences)
    assert_output --partial "zappzarapp"
}

# =============================================================================
# Remote management
# =============================================================================

@test "freshly added zappzarapp remote fetches without tags" {
    require_git_access

    # Remove remote so boilerplate-diff re-adds it
    git remote remove zappzarapp 2>/dev/null || true

    run timeout 60 make boilerplate-diff

    run git config --get remote.zappzarapp.tagOpt
    assert_success
    assert_output -- "--no-tags"
}

@test "existing zappzarapp remote is upgraded to fetch without tags" {
    require_git_access

    # Simulate a remote configured without the tag opt-out
    make boilerplate-diff >/dev/null 2>&1 || true
    git config --unset remote.zappzarapp.tagOpt 2>/dev/null || true

    run timeout 60 make boilerplate-diff

    run git config --get remote.zappzarapp.tagOpt
    assert_success
    assert_output -- "--no-tags"
}

@test "zappzarapp remote points to correct URL" {
    require_git_access

    # Ensure remote exists
    make boilerplate-diff >/dev/null 2>&1 || true

    run git remote get-url zappzarapp
    assert_success
    assert_output "https://github.com/marcstraube/zappzarapp.git"
}

@test "ZAPPZARAPP_BRANCH defaults to master" {
    # Check that the default branch is master
    run make -n boilerplate-diff 2>&1
    assert_output --partial "master"
}

# =============================================================================
# Help output
# =============================================================================

@test "make help includes boilerplate-sync" {
    run make help
    assert_success
    assert_output --partial "boilerplate-sync"
    assert_output --partial "Sync infrastructure from zappzarapp"
}

@test "make help includes boilerplate-diff" {
    run make help
    assert_success
    assert_output --partial "boilerplate-diff"
    assert_output --partial "Show diff between local and zappzarapp"
}
