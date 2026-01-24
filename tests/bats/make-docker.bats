#!/usr/bin/env bats
# BATS Tests: Docker Commands
# Tests Docker-related make targets (status, up, down, build)

load 'helpers/setup'

# =============================================================================
# Status Command (Read-Only)
# =============================================================================

@test "make status target exists" {
    run target_exists "status"
    assert_success
}

@test "make status --dry-run validates" {
    run make -n status
    assert_success
}

# =============================================================================
# Docker Compose Validation
# =============================================================================

@test "compose.yaml exists" {
    run test -f compose.yaml
    assert_success
}

@test "compose.override.yaml exists" {
    run test -f compose.override.yaml
    assert_success
}

@test "docker compose config validates" {
    run docker compose config --quiet
    assert_success
}

@test "docker compose config shows nginx service" {
    run docker compose config --services
    assert_success
    assert_output --partial "nginx"
}

@test "docker compose with php profile shows php service" {
    run docker compose --profile php config --services
    assert_success
    assert_output --partial "php"
}

# =============================================================================
# Build Commands (Dry Run)
# =============================================================================

@test "make build --dry-run validates" {
    run make -n build
    assert_success
}

@test "make build-no-cache --dry-run validates" {
    run make -n build-no-cache
    assert_success
}

# =============================================================================
# Meta-Target Service Name Leak Prevention
# =============================================================================

# Usage: assert_no_service_leak "rebuild" "build"
assert_no_service_leak() {
    local meta_target="$1"
    local sub_target="$2"

    run make -n "$meta_target"
    assert_success

    # The meta-target name should NOT appear as argument to docker compose commands
    # after the sub-target (build/up/down/restart) command
    refute_output --regexp "docker compose[^;]*${sub_target}[^;]*${meta_target}"
    refute_output --regexp "docker compose[^;]* ${meta_target}\$"
    refute_output --regexp "docker compose[^;]* ${meta_target};"
    refute_output --regexp "docker compose[^;]* ${meta_target} "
}

@test "make rebuild does not pass 'rebuild' as service to build" {
    assert_no_service_leak "rebuild" "build"
}

@test "make rebuild does not pass 'rebuild' as service to up" {
    assert_no_service_leak "rebuild" "up"
}

@test "make down-all does not pass 'down-all' as service to down" {
    assert_no_service_leak "down-all" "down"
}

@test "make down-all does not pass 'goss-cleanup' as service to down" {
    # down-all: down goss-cleanup - both could leak
    run make -n down-all
    assert_success
    refute_output --regexp "docker compose[^;]*down[^;]*goss-cleanup"
}

# =============================================================================
# Up/Down Commands (Dry Run)
# =============================================================================

@test "make up --dry-run validates" {
    run make -n up
    assert_success
}

@test "make down --dry-run validates" {
    run make -n down
    assert_success
}

@test "make restart --dry-run validates" {
    run make -n restart
    assert_success
}

# =============================================================================
# Logs Commands
# =============================================================================

@test "make logs --dry-run validates" {
    run make -n logs
    assert_success
}

@test "make logs-php --dry-run validates" {
    run make -n logs-php
    assert_success
}

@test "make logs-nginx --dry-run validates" {
    run make -n logs-nginx
    assert_success
}

@test "make logs-node --dry-run validates" {
    run make -n logs-node
    assert_success
}

# =============================================================================
# Shell Commands
# =============================================================================

@test "make shell-php --dry-run validates" {
    run make -n shell-php
    assert_success
}

@test "make shell-nginx --dry-run validates" {
    run make -n shell-nginx
    assert_success
}

@test "make shell-node --dry-run validates" {
    run make -n shell-node
    assert_success
}

# =============================================================================
# Container State Detection
# =============================================================================

@test "is_container_running helper works" {
    # This tests the helper function itself
    # Result depends on whether containers are running
    run is_container_running "nonexistent-container-12345"
    assert_failure
}

# =============================================================================
# Docker Image Management
# =============================================================================

@test "make prune --dry-run validates" {
    run make -n prune
    assert_success
}

@test "make clean --dry-run validates" {
    run make -n clean
    assert_success
}

# =============================================================================
# Dependency Installation Commands
# =============================================================================

@test "make composer-install --dry-run validates" {
    run make -n composer-install
    assert_success
}

@test "make pnpm-install --dry-run validates" {
    run make -n pnpm-install
    assert_success
}

# =============================================================================
# Database Commands
# =============================================================================

@test "make postgres-cli --dry-run validates" {
    run make -n postgres-cli
    assert_success
}

@test "make redis-cli --dry-run validates" {
    run make -n redis-cli
    assert_success
}

# =============================================================================
# Special Targets
# =============================================================================

@test "make fresh --dry-run validates" {
    run make -n fresh
    assert_success
}

@test "make fresh does not pass 'fresh' as service to docker compose" {
    run make -n fresh
    assert_success
    # fresh has its own inline build logic, verify it doesn't leak
    refute_output --regexp "docker compose[^;]* fresh[^a-z]"
}

@test "make rebuild --dry-run validates" {
    run make -n rebuild
    assert_success
}

# =============================================================================
# Service-Specific Targets
# =============================================================================

@test "make node-dev --dry-run validates" {
    run make -n node-dev
    assert_success
}

@test "make node-build --dry-run validates" {
    run make -n node-build
    assert_success
}

# =============================================================================
# Profile Detection
# =============================================================================

@test "docker compose shows postgres profile" {
    run docker compose config --profiles
    assert_success
    assert_output --partial "postgres"
}

@test "docker compose shows php profile" {
    run docker compose config --profiles
    assert_success
    assert_output --partial "php"
}

@test "docker compose shows node profile" {
    run docker compose config --profiles
    assert_success
    assert_output --partial "node"
}
