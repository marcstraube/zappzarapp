#!/usr/bin/env bats
# Integration Tests: File Creation Verification (Docker-based)
#
# Tests that Makefile targets correctly create expected files/directories.
# These tests require running containers (make up).
#
# Run: make bats-test-integration-file FILE=file-creation.bats

load 'setup'

# =============================================================================
# Setup/Teardown
# =============================================================================

setup_file() {
    load "${BATS_TEST_DIRNAME}/setup.bash"
    # Ensure containers are running
    integration_setup
}

teardown_file() {
    integration_teardown
}

# =============================================================================
# Composer Dependencies
# =============================================================================

@test "make composer-install creates vendor/" {
    require_php

    # Clean first if exists
    rm -rf vendor 2>/dev/null || true

    run timeout 300 make composer-install
    assert_success

    [[ -d "vendor" ]]
}

@test "make composer-install creates vendor/autoload.php" {
    require_php
    [[ -f "vendor/autoload.php" ]]
}

@test "make composer-install creates vendor/composer/" {
    require_php
    [[ -d "vendor/composer" ]]
}

# =============================================================================
# Node Dependencies
# =============================================================================

@test "make pnpm-install creates node_modules/" {
    require_node

    # Clean first if exists
    rm -rf node_modules 2>/dev/null || true

    run timeout 300 make pnpm-install
    assert_success

    [[ -d "node_modules" ]]
}

@test "make pnpm-install creates node_modules/.pnpm/" {
    require_node
    [[ -d "node_modules/.pnpm" ]]
}

@test "make pnpm-install creates node_modules/.modules.yaml" {
    require_node
    [[ -f "node_modules/.modules.yaml" ]]
}

# =============================================================================
# Documentation Generation
# =============================================================================

@test "make docs creates docs/api/" {
    require_php
    require_node

    # Clean first
    rm -rf docs/api 2>/dev/null || true

    run timeout 300 make docs
    assert_success

    [[ -d "docs/api" ]]
}

@test "make docs creates docs/api/php/" {
    require_php
    [[ -d "docs/api/php" ]]
}

@test "make docs creates docs/api/node-backend/" {
    require_node
    [[ -d "docs/api/node-backend" ]]
}

@test "make docs-clean removes docs/api/ but keeps docs/index.html" {
    # Ensure docs/api exists first
    [[ -d "docs/api" ]] || skip "docs/api not created"

    run make docs-clean
    assert_success

    # API docs should be gone
    [[ ! -d "docs/api" ]]

    # Static files should remain
    [[ -f "docs/index.html" ]]
    [[ -d "docs/assets" ]]
}

# =============================================================================
# SSL Certificates
# =============================================================================

@test "make ssl-selfsigned creates docker/certs/cert.crt" {
    # Clean first
    rm -f docker/certs/cert.crt docker/certs/cert.key 2>/dev/null || true

    run timeout 60 make ssl-selfsigned
    assert_success

    [[ -f "docker/certs/cert.crt" ]]
}

@test "make ssl-selfsigned creates docker/certs/cert.key" {
    [[ -f "docker/certs/cert.key" ]]
}

@test "make ssl-clean removes certificates" {
    [[ -f "docker/certs/cert.crt" ]] || skip "Certificates not created"
    # ssl-clean requires interactive confirmation (read -p), skip in CI
    [[ -t 0 ]] || skip "Requires interactive terminal for confirmation"

    run make ssl-clean
    assert_success

    [[ ! -f "docker/certs/cert.crt" ]]
    [[ ! -f "docker/certs/cert.key" ]]
}

# =============================================================================
# Docker Images
# =============================================================================

@test "make build creates Docker images" {
    run timeout 600 make build
    assert_success
}

@test "Docker image zappzarapp-php exists" {
    run docker images --format "{{.Repository}}" zappzarapp-php
    assert_success
    assert_output --partial "zappzarapp-php"
}

@test "Docker image zappzarapp-nginx exists" {
    run docker images --format "{{.Repository}}" zappzarapp-nginx
    assert_success
    assert_output --partial "zappzarapp-nginx"
}

@test "Docker image zappzarapp-node exists" {
    run docker images --format "{{.Repository}}" zappzarapp-node
    assert_success
    assert_output --partial "zappzarapp-node"
}

# =============================================================================
# Node Build (Vite)
# =============================================================================

@test "make node-build creates public/build/" {
    require_node
    require_dependencies

    # Clean first
    rm -rf public/build 2>/dev/null || true

    run timeout 180 make node-build
    assert_success

    [[ -d "public/build" ]]
}

@test "make node-build creates public/build/manifest.json" {
    require_node
    require_dependencies
    [[ -f "public/build/manifest.json" ]] || [[ -f "public/build/.vite/manifest.json" ]]
}
