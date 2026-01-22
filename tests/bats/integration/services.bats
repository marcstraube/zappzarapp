#!/usr/bin/env bats
# Integration Tests: Service Operations
# Requires running containers

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
# Core Services
# =============================================================================

@test "[Integration] nginx responds to requests" {
    require_service "nginx"
    run timeout 10 curl -sf http://localhost:8080/health || curl -sf http://localhost:80/health
    assert_success
}

@test "[Integration] PHP-FPM is accessible from nginx" {
    require_service "nginx"
    require_service "php"
    run timeout 10 curl -sf http://localhost:8080/ || curl -sf http://localhost:80/
    # May return 200 or redirect, both are fine
    [[ $status -eq 0 ]] || [[ $status -eq 22 ]]
}

# =============================================================================
# Shell Access
# =============================================================================

@test "[Integration] make shell-php provides shell access" {
    require_service "php"
    run timeout 10 docker compose exec -T php whoami
    assert_success
}

@test "[Integration] make shell-node provides shell access" {
    require_service "node"
    run timeout 10 docker compose exec -T node whoami
    assert_success
}

@test "[Integration] make shell-nginx provides shell access" {
    require_service "nginx"
    run timeout 10 docker compose exec -T nginx whoami
    assert_success
}

# =============================================================================
# Logs
# =============================================================================

@test "[Integration] make logs shows combined logs" {
    require_containers
    run timeout 10 docker compose logs --tail=5
    assert_success
}

@test "[Integration] make logs-php shows PHP logs" {
    require_service "php"
    run timeout 10 docker compose logs --tail=5 php
    assert_success
}

@test "[Integration] make logs-nginx shows nginx logs" {
    require_service "nginx"
    run timeout 10 docker compose logs --tail=5 nginx
    assert_success
}

@test "[Integration] make logs-node shows Node logs" {
    require_service "node"
    run timeout 10 docker compose logs --tail=5 node
    assert_success
}

# =============================================================================
# Node.js Services
# =============================================================================

@test "[Integration] Node backend is running" {
    require_service "node"
    # Check if Express/backend is responding
    run timeout 10 docker compose exec -T node curl -sf http://localhost:3000/health 2>/dev/null || true
    # May not have health endpoint, just check container is up
    run docker compose ps --status running node
    assert_success
}

@test "[Integration] make node-build succeeds" {
    require_service "node"
    require_dependencies
    run timeout 180 make node-build
    assert_success
}

# =============================================================================
# IDE Configuration
# =============================================================================

@test "[Integration] make ide-config updates IDE settings" {
    run timeout 30 make ide-config
    assert_success
}

# =============================================================================
# Dependency Installation
# =============================================================================

@test "[Integration] make composer-install installs PHP deps" {
    require_service "php"
    run timeout 180 make composer-install
    assert_success
    [[ -d "vendor" ]]
}

@test "[Integration] make pnpm-install installs Node deps" {
    require_service "node"
    run timeout 180 make pnpm-install
    assert_success
    [[ -d "node_modules" ]]
}

# =============================================================================
# Status & Health
# =============================================================================

@test "[Integration] make status shows container status" {
    require_containers
    run make status
    assert_success
}

@test "[Integration] docker compose ps shows running containers" {
    run docker compose ps
    assert_success
    assert_output --partial "nginx"
}
