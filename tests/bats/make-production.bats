#!/usr/bin/env bats
# BATS Tests: Production Make Targets
# Tests make test-production, test-production-minimal, test-production-full
#
# NOTE: These tests are SLOW (require building production images).
# Run separately with: make bats-test-file FILE=tests/bats/make-production.bats

load 'helpers/setup.bash'

# =============================================================================
# File Setup/Teardown
# =============================================================================

teardown_file() {
    # Clean up production containers
    docker compose -f compose.yaml -f compose.production.yaml down -v 2>/dev/null || true
    docker compose down -v 2>/dev/null || true
}

# =============================================================================
# Production Build Tests
# =============================================================================
# Note: Full production tests are skipped in this file as they require
# building production images (10+ minutes). These are tested in CI/CD via
# .gitlab-ci.yml build:production job.
# =============================================================================

@test "[Production] make test-production target exists" {
    run make -n test-production
    assert_success
}

@test "[Production] make test-production-minimal target exists" {
    run make -n test-production-minimal
    assert_success
}

@test "[Production] make test-production-full target exists" {
    run make -n test-production-full
    assert_success
}

# =============================================================================
# Production Health Check Tests
# =============================================================================
# These tests verify that health checks use correct compose files
# and can connect to production containers
# =============================================================================

@test "[Production] test-production full integration test" {
    skip "Long-running test - requires full production build (10+ minutes). Tested in CI/CD."
    # This test would:
    # 1. Build production images
    # 2. Start production stack
    # 3. Run make test-production
    # 4. Verify health checks pass
}

@test "[Production] Health checks use production compose files (integration check)" {
    # This is a lighter test that just validates the Makefile syntax
    # without actually running the full production stack

    # Extract health check commands from test-production target
    local postgres_check
    postgres_check=$(awk '/^test-production:.*##/,/^test-production-minimal:/ { print }' Makefile | \
        grep "pg_isready" | head -1)

    # Verify it includes production compose files
    if echo "$postgres_check" | grep -q "compose.yaml.*compose.production.yaml"; then
        true  # Success
    else
        echo "# postgres health check: $postgres_check" >&3
        echo "# Expected: Should include -f compose.yaml -f compose.production.yaml" >&3
        false
    fi
}

@test "[Production] Redis health check uses production compose files" {
    # Extract redis health check command
    local redis_check
    redis_check=$(awk '/^test-production:.*##/,/^test-production-minimal:/ { print }' Makefile | \
        grep "redis-cli.*ping" | head -1)

    # Verify it includes production compose files
    if echo "$redis_check" | grep -q "compose.yaml.*compose.production.yaml"; then
        true  # Success
    else
        echo "# redis health check: $redis_check" >&3
        echo "# Expected: Should include -f compose.yaml -f compose.production.yaml" >&3
        false
    fi
}

@test "[Production] MariaDB health check uses production compose files" {
    # Extract mariadb health check command
    local mariadb_check
    mariadb_check=$(awk '/^test-production:.*##/,/^test-production-minimal:/ { print }' Makefile | \
        grep "mariadb -u app" | head -1)

    # Verify it includes production compose files
    if echo "$mariadb_check" | grep -q "compose.yaml.*compose.production.yaml"; then
        true  # Success
    else
        echo "# mariadb health check: $mariadb_check" >&3
        echo "# Expected: Should include -f compose.yaml -f compose.production.yaml" >&3
        false
    fi
}

# =============================================================================
# Production Environment Validation
# =============================================================================

@test "[Production] ENV=production is required for test-production" {
    # This test verifies the ENV check logic exists in Makefile

    # Extract ENV check from test-production target
    local env_check
    env_check=$(awk '/^test-production:.*##/,/^test-production-minimal:/ { print }' Makefile | \
        grep "ENV must be 'production'" | head -1)

    # Verify the check exists
    if [ -n "$env_check" ]; then
        true  # ENV check exists
    else
        echo "# Expected: test-production should check ENV=production" >&3
        false
    fi
}

@test "[Production] compose.production.yaml exists" {
    run test -f compose.production.yaml
    assert_success
}

@test "[Production] compose.production.yaml is valid YAML" {
    run docker compose -f compose.yaml -f compose.production.yaml config --quiet
    assert_success
}

# =============================================================================
# SSL Certificate Validation
# =============================================================================
# These tests verify that SSL certificates are properly configured before
# attempting to start production containers. Catches issues early in local dev.
# =============================================================================

@test "[Production] SSL internal certificates exist" {
    # Verify internal SSL certificates are generated (required for database SSL)
    run test -f docker/certs/internal/cert.crt
    assert_success "Internal certificate not found. Run: make ssl-internal"

    run test -f docker/certs/internal/cert.key
    assert_success "Internal certificate key not found. Run: make ssl-internal"
}

@test "[Production] SSL nginx certificates exist" {
    # Verify nginx SSL certificates are generated
    run test -f docker/certs/nginx/cert.crt
    assert_success "Nginx certificate not found. Run: make ssl-internal"

    run test -f docker/certs/nginx/cert.key
    assert_success "Nginx certificate key not found. Run: make ssl-internal"
}

@test "[Production] Database SSL certificates are correctly mapped in compose.production.yaml" {
    # Extract postgres volume mounts
    local postgres_volumes
    postgres_volumes=$(docker compose -f compose.yaml -f compose.production.yaml config | \
        awk '/^  postgres:/,/^  [a-z]/ {print}' | \
        grep "docker/certs" || true)

    # Verify it mounts internal certs, not root certs directory
    if echo "$postgres_volumes" | grep -q "docker/certs/internal:/tmp/certs"; then
        true  # Success
    else
        echo "# postgres volumes: $postgres_volumes" >&3
        echo "# Expected: Should mount ./docker/certs/internal:/tmp/certs:ro" >&3
        echo "# This ensures entrypoint.sh finds certs at /tmp/certs/cert.{crt,key}" >&3
        false
    fi

    # Extract mariadb volume mounts
    local mariadb_volumes
    mariadb_volumes=$(docker compose -f compose.yaml -f compose.production.yaml config | \
        awk '/^  mariadb:/,/^  [a-z]/ {print}' | \
        grep "docker/certs" || true)

    # Verify it mounts internal certs
    if echo "$mariadb_volumes" | grep -q "docker/certs/internal:/tmp/certs"; then
        true  # Success
    else
        echo "# mariadb volumes: $mariadb_volumes" >&3
        echo "# Expected: Should mount ./docker/certs/internal:/tmp/certs:ro" >&3
        false
    fi
}

@test "[Production] Internal certificates are readable" {
    # Verify file permissions allow reading
    run test -r docker/certs/internal/cert.crt
    assert_success "Internal certificate not readable"

    run test -r docker/certs/internal/cert.key
    assert_success "Internal certificate key not readable"
}
