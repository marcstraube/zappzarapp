#!/usr/bin/env bats
# Integration Tests: Unit & Feature Tests
# Requires running containers and installed dependencies

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
# PHP Tests
# =============================================================================

@test "[Integration] make test-php runs PHPUnit tests" {
    require_php
    require_database
    require_dependencies
    run timeout 180 make test-php
    assert_success
}

@test "[Integration] make test-coverage-php generates coverage" {
    require_php
    require_database
    require_dependencies
    run timeout 300 make test-coverage-php
    # Exit 0=success, 2=risky tests (strict coverage mode), both acceptable
    [[ $status -eq 0 ]] || [[ $status -eq 2 ]]
    # Verify coverage files created
    [[ -f "build/coverage/php/index.html" ]] || [[ -f "build/coverage/clover.xml" ]]
}

# =============================================================================
# Node.js Tests
# =============================================================================

@test "[Integration] make test-node runs Vitest tests" {
    require_node
    require_dependencies
    run timeout 180 make test-node
    assert_success
}

@test "[Integration] make test-coverage-node generates coverage" {
    require_node
    require_dependencies
    run timeout 300 make test-coverage-node
    # Exit 0=success, 2=coverage threshold not met, both acceptable for CI
    [[ $status -eq 0 ]] || [[ $status -eq 2 ]]
    # Verify coverage files created
    [[ -d "build/coverage" ]]
}

# =============================================================================
# SQL Tests
# =============================================================================

@test "[Integration] make test-sql validates SQL files" {
    require_database
    run timeout 120 make test-sql
    assert_success
}

# =============================================================================
# Combined Tests
# =============================================================================

@test "[Integration] make test runs all test suites" {
    require_php
    require_node
    require_database
    require_dependencies
    run timeout 360 make test
    assert_success
}

@test "[Integration] make test-coverage runs all coverage reports" {
    require_php
    require_node
    require_database
    require_dependencies
    run timeout 600 make test-coverage
    # Exit 0=success, 2=threshold/risky issues, both acceptable for CI
    [[ $status -eq 0 ]] || [[ $status -eq 2 ]]
}

# =============================================================================
# Validation
# =============================================================================

@test "[Integration] make validate checks composer and pnpm" {
    require_php
    require_node
    require_dependencies
    run timeout 60 make validate
    assert_success
}
