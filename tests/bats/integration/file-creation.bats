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
#
# vendor/ and node_modules/ are verified through the CONTAINER view: in
# development they live in named volumes (host paths stay empty), in CI they
# are bind mounts - the container view is authoritative in both. Never touch
# the HOST vendor/ here: it holds the captainhook binary the git hooks run
# from (and composer-install would not repopulate it in development anyway).
# =============================================================================

@test "make composer-install populates the php vendor volume" {
    require_php

    # Empty the vendor volume inside the container view, then reinstall
    docker compose run --rm --no-deps --no-TTY php sh -c \
        'rm -rf vendor/* vendor/.[!.]*' 2>/dev/null || true

    run timeout 300 make composer-install
    assert_success
}

@test "Verify: vendor contents in the php container" {
    require_php
    run timeout 120 docker compose run --rm --no-deps --no-TTY php sh -c '
        set -e
        test -f vendor/autoload.php
        test -d vendor/composer
        test -d vendor/bin
        test -e vendor/bin/php-cs-fixer
        test -e vendor/bin/phpstan
        test -e vendor/bin/phpunit'
    assert_success
}

# =============================================================================
# Node Dependencies
# =============================================================================

@test "make pnpm-install populates the node_modules volumes" {
    require_node

    # Empty the node_modules volume inside the container view, then reinstall
    docker compose run --rm --no-deps --no-TTY --user root --entrypoint "" node sh -c \
        'rm -rf node_modules/* node_modules/.[!.]*' 2>/dev/null || true

    run timeout 300 make pnpm-install
    assert_success
}

@test "Verify: node_modules contents in the node container" {
    require_node
    run timeout 120 docker compose run --rm --no-deps --no-TTY --entrypoint "" node sh -c '
        set -e
        test -d node_modules/.pnpm
        test -f node_modules/.modules.yaml'
    assert_success
}

# =============================================================================
# Documentation Generation
# =============================================================================

@test "make docs creates docs/api/" {
    require_php
    require_node

    # Clean via Docker to handle cross-UID ownership issues in CI
    docker run --rm -v "$(pwd):/app" -w /app alpine:3.21 rm -rf /app/docs/api 2>/dev/null || true

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

@test "make ssl-internal creates CA and certificates" {
    # Clean first
    rm -rf docker/certs/ca docker/certs/nginx docker/certs/internal 2>/dev/null || true

    run timeout 60 make ssl-internal
    assert_success

    # Verify CA
    [[ -f "docker/certs/ca/ca.crt" ]]
    [[ -f "docker/certs/ca/ca.key" ]]
    # Verify nginx certs
    [[ -f "docker/certs/nginx/cert.crt" ]]
    [[ -f "docker/certs/nginx/cert.key" ]]
    # Verify internal certs
    [[ -f "docker/certs/internal/cert.crt" ]]
    [[ -f "docker/certs/internal/cert.key" ]]
    [[ -f "docker/certs/internal/ca.crt" ]]
}

@test "make ssl-clean removes all certificates" {
    [[ -f "docker/certs/nginx/cert.crt" ]] || skip "Certificates not created"
    # ssl-clean requires interactive confirmation (read -p), skip in CI
    [[ -t 0 ]] || skip "Requires interactive terminal for confirmation"

    run make ssl-clean
    assert_success

    [[ ! -d "docker/certs/ca" ]]
    [[ ! -d "docker/certs/nginx" ]]
    [[ ! -d "docker/certs/internal" ]]
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
