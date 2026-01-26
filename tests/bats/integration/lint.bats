#!/usr/bin/env bats
# Integration Tests: Lint & Code Quality
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
# PHP Lint Tests
# =============================================================================

@test "[Integration] vendor/bin is accessible from PHP container" {
    require_php
    require_dependencies
    # Verify the PHP container can see vendor/bin/ after potential bind mount refresh
    run docker compose exec -T php sh -c 'ls -la /var/www/html/vendor/bin/ 2>&1 | head -5'
    echo "Container vendor/bin/: $output" >&3
    run docker compose exec -T php sh -c 'test -e /var/www/html/vendor/bin/php-cs-fixer && echo "EXISTS" || echo "NOT FOUND"'
    assert_output "EXISTS"
}

@test "[Integration] make cs-check runs successfully" {
    require_php
    require_dependencies
    run timeout 120 make cs-check
    assert_success
}

@test "[Integration] make analyse runs successfully" {
    require_php
    require_node
    require_dependencies
    run timeout 120 make analyse
    assert_success
}

@test "[Integration] make analyse-php (PHPStan) runs successfully" {
    require_php
    require_dependencies
    run timeout 120 make analyse-php
    assert_success
}

@test "[Integration] make phpmd runs (allow warnings)" {
    require_php
    require_dependencies
    run timeout 120 make phpmd
    # PHPMD may return warnings, which is acceptable
    [[ $status -eq 0 ]] || [[ $status -eq 2 ]]
}

@test "[Integration] make rector-check runs successfully" {
    require_php
    require_dependencies
    run timeout 120 make rector-check
    assert_success
}

# =============================================================================
# Node.js Lint Tests
# =============================================================================

@test "[Integration] make lint-node runs successfully" {
    require_node
    require_dependencies
    run timeout 120 make lint-node
    assert_success
}

@test "[Integration] make analyse-node runs successfully" {
    require_node
    require_dependencies
    run timeout 120 make analyse-node
    assert_success
}

@test "[Integration] make prettier-check runs successfully" {
    require_node
    require_dependencies
    run timeout 60 make prettier-check
    assert_success
}

@test "[Integration] make lint-md runs successfully" {
    require_node
    require_dependencies
    run timeout 60 make lint-md
    assert_success
}

# =============================================================================
# SQL Lint Tests
# =============================================================================

@test "[Integration] make lint-sql runs successfully" {
    require_node
    require_dependencies
    run timeout 60 make lint-sql
    assert_success
}

# =============================================================================
# Docker Lint Tests
# =============================================================================

@test "[Integration] make lint-docker runs successfully" {
    require_node
    require_dependencies
    run timeout 60 make lint-docker
    assert_success
}

# =============================================================================
# Config Lint Tests
# =============================================================================

@test "[Integration] make lint-config runs successfully" {
    require_node
    require_dependencies
    run timeout 60 make lint-config
    assert_success
}

# =============================================================================
# Dependency Checks
# =============================================================================

@test "[Integration] make knip runs successfully" {
    require_node
    require_dependencies
    run timeout 120 make knip
    # Knip may find unused exports (0=clean, 1=warnings, 2=errors found)
    [[ $status -eq 0 ]] || [[ $status -eq 1 ]] || [[ $status -eq 2 ]]
}

@test "[Integration] make depcheck runs successfully" {
    require_node
    require_dependencies
    run timeout 60 make depcheck
    # depcheck may find unused dependencies (0=clean, 2=issues found)
    [[ $status -eq 0 ]] || [[ $status -eq 2 ]]
}

@test "[Integration] make outdated runs successfully" {
    require_containers
    run timeout 120 make outdated
    # outdated returns non-zero if updates available, which is fine
    [[ $status -eq 0 ]] || [[ $status -eq 1 ]]
}

# =============================================================================
# Combined Check
# =============================================================================

@test "[Integration] make check runs all quality checks" {
    require_php
    require_node
    require_dependencies
    run timeout 300 make check
    assert_success
}
