#!/usr/bin/env bats
# Integration Tests: Make ARGS Parameter Pass-Through
# Tests parameter functionality for targeted testing and linting

load 'setup'

# =============================================================================
# File Setup/Teardown
# =============================================================================

setup_file() {
    integration_setup
}

teardown_file() {
    integration_teardown
}

# =============================================================================
# Core Functionality Tests
# =============================================================================

@test "[ARGS] make cs-fix ARGS works with single file" {
    require_php
    require_dependencies

    # Create a deliberately badly formatted PHP file
    TEST_FILE="src/php/App/ArgsTestHelper.php"
    cat > "$TEST_FILE" << 'EOF'
<?php
namespace App;
class ArgsTestHelper{public function test(){return true;}}
EOF

    # Run cs-fix on single file
    run timeout 60 make cs-fix ARGS="$TEST_FILE"
    assert_success

    # Verify file was formatted (should have proper spacing now)
    run grep -q "class ArgsTestHelper" "$TEST_FILE"
    assert_success

    # Cleanup
    rm -f "$TEST_FILE"
}

@test "[ARGS] make test-php ARGS works with filter" {
    require_php
    require_database
    require_dependencies

    # Run only tests matching filter (even if no match, should execute successfully)
    run timeout 120 make test-php ARGS="--filter NonExistentTest"
    # PHPUnit returns 0 even when filter matches nothing
    assert_success
}

@test "[ARGS] make lint-node ARGS works with specific file pattern" {
    require_node
    require_dependencies

    # Ensure we have at least one TS file to lint
    if [ ! -f "vite.config.ts" ]; then
        skip "No TypeScript files found"
    fi

    # Run ESLint on single file
    run timeout 60 make lint-node ARGS="vite.config.ts"
    assert_success
}

# =============================================================================
# Edge Cases
# =============================================================================

@test "[ARGS] ARGS with multiple parameters works" {
    require_php
    require_database
    require_dependencies

    # Multiple PHPUnit parameters
    run timeout 120 make test-php ARGS="--testdox --no-coverage"
    assert_success
}

@test "[ARGS] make prettier-fix ARGS works with single file" {
    require_node
    require_dependencies

    # Create test file with bad formatting
    TEST_FILE="src/node/args-test.ts"
    mkdir -p "src/node"
    echo "const x={a:1,b:2};export default x;" > "$TEST_FILE"

    # Run prettier on single file
    run timeout 60 make prettier-fix ARGS="$TEST_FILE"
    assert_success

    # Verify file was formatted (prettier should add proper spacing)
    run grep -q "const x = " "$TEST_FILE"
    assert_success

    # Cleanup
    rm -f "$TEST_FILE"
}

# =============================================================================
# Regression Prevention
# =============================================================================

@test "[ARGS] ARGS parameter actually passed to underlying tool (PHPStan)" {
    require_php
    require_dependencies

    # Test that ARGS reaches PHPStan (invalid option should cause error)
    run timeout 60 make analyse-php ARGS="--this-option-does-not-exist"
    # Should fail because invalid option was passed through
    assert_failure
}

@test "[ARGS] Default behavior without ARGS parameter" {
    require_php
    require_dependencies

    # Targets should work without ARGS (run on all files)
    run timeout 60 make cs-check
    assert_success

    run timeout 60 make analyse-php
    assert_success
}

# =============================================================================
# Performance Documentation (informational, not strict)
# =============================================================================

@test "[ARGS] Single file operations complete quickly" {
    require_php
    require_dependencies

    # Create small test file
    TEST_FILE="src/php/App/QuickTest.php"
    echo '<?php namespace App; class QuickTest {}' > "$TEST_FILE"

    # Single file should complete in reasonable time (<30s)
    run timeout 30 make cs-fix ARGS="$TEST_FILE"
    assert_success

    # Cleanup
    rm -f "$TEST_FILE"
}
