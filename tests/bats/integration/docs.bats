#!/usr/bin/env bats
# Integration Tests: Documentation Generation
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
# PHP Documentation
# =============================================================================

@test "[Integration] make docs-php generates PHP documentation" {
    require_php
    require_dependencies
    run timeout 180 make docs-php
    assert_success
    # Verify docs created
    [[ -d "docs/api/php" ]]
}

# =============================================================================
# Node.js Documentation
# =============================================================================

@test "[Integration] make docs-node-backend generates backend docs" {
    require_node
    require_dependencies
    run timeout 180 make docs-node-backend
    assert_success
    # Verify docs created
    [[ -d "docs/api/node-backend" ]]
}

@test "[Integration] make docs-node-frontend generates frontend docs" {
    require_node
    require_dependencies
    # Only run if frontend exists
    if [[ ! -d "src/node/frontend" ]]; then
        skip "No frontend directory"
    fi
    run timeout 180 make docs-node-frontend
    assert_success
}

@test "[Integration] make docs-node generates all Node docs" {
    require_node
    require_dependencies
    run timeout 300 make docs-node
    assert_success
}

# =============================================================================
# Combined Documentation
# =============================================================================

@test "[Integration] make docs generates all documentation" {
    require_php
    require_node
    require_dependencies
    run timeout 600 make docs
    assert_success
    # Verify docs directory exists
    [[ -d "docs/api" ]]
}

@test "[Integration] make docs-clean removes documentation" {
    run make docs-clean
    assert_success
    # Verify docs removed
    [[ ! -d "docs/api" ]]
}
