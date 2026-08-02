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

@test "[Production] ZAPPZARAPP_ENV=production is required for test-production" {
    # This test verifies the ZAPPZARAPP_ENV check logic exists in Makefile

    # Extract ZAPPZARAPP_ENV check from test-production target
    local env_check
    env_check=$(awk '/^test-production:.*##/,/^test-production-minimal:/ { print }' Makefile | \
        grep "ZAPPZARAPP_ENV must be 'production'" | head -1)

    # Verify the check exists
    if [ -n "$env_check" ]; then
        true  # ZAPPZARAPP_ENV check exists
    else
        echo "# Expected: test-production should check ZAPPZARAPP_ENV=production" >&3
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
    # Extract postgres volume configuration (need --profile to include service in config)
    local postgres_config
    postgres_config=$(docker compose --profile postgres -f compose.yaml -f compose.production.yaml config 2>/dev/null | \
        grep -A200 "postgres:" || true)

    # Verify it mounts internal certs to /tmp/certs
    # Config format is multi-line YAML with source: and target: on separate lines
    if echo "$postgres_config" | grep -A1 "docker/certs/internal" | grep -q "target: /tmp/certs"; then
        true  # Success - internal certs mounted to /tmp/certs
    else
        echo "# postgres config did not contain expected volume mount" >&3
        echo "# Expected: docker/certs/internal mounted to /tmp/certs" >&3
        echo "# This ensures entrypoint.sh finds certs at /tmp/certs/cert.{crt,key}" >&3
        false
    fi

    # Extract mariadb volume configuration (need --profile to include service in config)
    local mariadb_config
    mariadb_config=$(docker compose --profile mariadb -f compose.yaml -f compose.production.yaml config 2>/dev/null | \
        grep -A200 "mariadb:" || true)

    # Verify it mounts internal certs to /tmp/certs
    if echo "$mariadb_config" | grep -A1 "docker/certs/internal" | grep -q "target: /tmp/certs"; then
        true  # Success - internal certs mounted to /tmp/certs
    else
        echo "# mariadb config did not contain expected volume mount" >&3
        echo "# Expected: docker/certs/internal mounted to /tmp/certs" >&3
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

# =============================================================================
# SSL Enforcement Tests
# =============================================================================
# These tests verify that databases enforce SSL in production mode and
# fail to start when SSL certificates are missing (security-by-default).
# =============================================================================

@test "[Production] Postgres entrypoint enforces SSL in production mode" {
    # Verify entrypoint script has ZAPPZARAPP_ENV-based SSL enforcement logic
    local entrypoint_content
    entrypoint_content=$(cat docker/postgres/entrypoint.sh)

    # Check for production mode detection
    if echo "$entrypoint_content" | grep -q 'ENV_MODE=.*ZAPPZARAPP_ENV.*development'; then
        true  # Found ZAPPZARAPP_ENV mode detection
    else
        echo "# Missing ZAPPZARAPP_ENV mode detection in postgres entrypoint" >&3
        false
    fi

    # Check for exit 1 in production mode without SSL
    if echo "$entrypoint_content" | grep -q 'exit 1.*Fail-Fast\|ABORTED to prevent security'; then
        true  # Found fail-fast exit
    else
        echo "# Missing 'exit 1' for production mode without SSL" >&3
        false
    fi
}

@test "[Production] MariaDB entrypoint enforces SSL in production mode" {
    # Verify entrypoint script has ZAPPZARAPP_ENV-based SSL enforcement logic
    local entrypoint_content
    entrypoint_content=$(cat docker/mariadb/entrypoint.sh)

    # Check for production mode detection
    if echo "$entrypoint_content" | grep -q 'ENV_MODE=.*ZAPPZARAPP_ENV.*development'; then
        true  # Found ZAPPZARAPP_ENV mode detection
    else
        echo "# Missing ZAPPZARAPP_ENV mode detection in mariadb entrypoint" >&3
        false
    fi

    # Check for exit 1 in production mode without SSL
    if echo "$entrypoint_content" | grep -q 'exit 1.*Fail-Fast\|ABORTED to prevent security'; then
        true  # Found fail-fast exit
    else
        echo "# Missing 'exit 1' for production mode without SSL" >&3
        false
    fi
}

@test "[Production] PostgreSQL enforces minimum TLS version" {
    # Verify PostgreSQL is configured with ssl_min_protocol_version (need --profile)
    local postgres_command
    postgres_command=$(docker compose --profile postgres -f compose.yaml -f compose.production.yaml config 2>/dev/null | \
        grep -A100 "postgres:" | \
        grep "ssl_min_protocol_version" || echo "")

    if [ -n "$postgres_command" ]; then
        # Check it's set to TLSv1.2 or higher
        if echo "$postgres_command" | grep -qE "ssl_min_protocol_version.*(TLSv1\.[23]|TLSv1\.3)"; then
            true  # TLS 1.2 or 1.3 configured
        else
            echo "# PostgreSQL ssl_min_protocol_version not set to TLSv1.2+" >&3
            echo "# Found: $postgres_command" >&3
            false
        fi
    else
        echo "# PostgreSQL ssl_min_protocol_version not configured" >&3
        echo "# Add: -c ssl_min_protocol_version=TLSv1.2" >&3
        false
    fi
}

@test "[Production] MariaDB enforces secure transport" {
    # Verify MariaDB has --require-secure-transport=ON (need --profile)
    local mariadb_command
    mariadb_command=$(docker compose --profile mariadb -f compose.yaml -f compose.production.yaml config 2>/dev/null | \
        grep -A100 "mariadb:" | \
        grep "require-secure-transport" || echo "")

    if [ -n "$mariadb_command" ]; then
        if echo "$mariadb_command" | grep -q "require-secure-transport.*ON"; then
            true  # Secure transport enforced
        else
            echo "# MariaDB --require-secure-transport not set to ON" >&3
            false
        fi
    else
        echo "# MariaDB --require-secure-transport not configured" >&3
        echo "# Add: --require-secure-transport=ON" >&3
        false
    fi
}

@test "[Production] Redis entrypoint enforces SSL in production mode" {
    # Verify entrypoint script has ZAPPZARAPP_ENV-based SSL enforcement logic
    local entrypoint_content
    entrypoint_content=$(cat docker/redis/entrypoint.sh)

    # Check for production mode detection
    if echo "$entrypoint_content" | grep -q 'ENV_MODE=.*ZAPPZARAPP_ENV.*development'; then
        true  # Found ZAPPZARAPP_ENV mode detection
    else
        echo "# Missing ZAPPZARAPP_ENV mode detection in redis entrypoint" >&3
        false
    fi

    # Check for exit 1 in production mode without SSL
    if echo "$entrypoint_content" | grep -q 'exit 1.*Fail-Fast\|ABORTED to prevent security'; then
        true  # Found fail-fast exit
    else
        echo "# Missing 'exit 1' for production mode without SSL" >&3
        false
    fi
}

@test "[Production] RabbitMQ entrypoint enforces SSL in production mode" {
    # Verify entrypoint script has ZAPPZARAPP_ENV-based SSL enforcement logic
    local entrypoint_content
    entrypoint_content=$(cat docker/rabbitmq/entrypoint.sh)

    # Check for production mode detection
    if echo "$entrypoint_content" | grep -q 'ENV_MODE=.*ZAPPZARAPP_ENV.*development'; then
        true  # Found ZAPPZARAPP_ENV mode detection
    else
        echo "# Missing ZAPPZARAPP_ENV mode detection in rabbitmq entrypoint" >&3
        false
    fi

    # Check for exit 1 in production mode without SSL
    if echo "$entrypoint_content" | grep -q 'exit 1.*Fail-Fast\|ABORTED to prevent security'; then
        true  # Found fail-fast exit
    else
        echo "# Missing 'exit 1' for production mode without SSL" >&3
        false
    fi
}

@test "[Production] SeaweedFS entrypoint enforces SSL in production mode" {
    # Verify entrypoint script has ZAPPZARAPP_ENV-based SSL enforcement logic
    local entrypoint_content
    entrypoint_content=$(cat docker/seaweedfs/entrypoint.sh)

    # Check for production mode detection
    if echo "$entrypoint_content" | grep -q 'ENV_MODE=.*ZAPPZARAPP_ENV.*development'; then
        true  # Found ZAPPZARAPP_ENV mode detection
    else
        echo "# Missing ZAPPZARAPP_ENV mode detection in seaweedfs entrypoint" >&3
        false
    fi

    # Check for exit 1 in production mode without SSL
    if echo "$entrypoint_content" | grep -q 'exit 1.*Fail-Fast\|ABORTED to prevent security'; then
        true  # Found fail-fast exit
    else
        echo "# Missing 'exit 1' for production mode without SSL" >&3
        false
    fi
}

@test "[Production] Nginx entrypoint enforces SSL in production mode" {
    # Verify entrypoint script has ZAPPZARAPP_ENV-based SSL enforcement logic
    local entrypoint_content
    entrypoint_content=$(cat docker/nginx/entrypoint.sh)

    # Check for production mode detection
    if echo "$entrypoint_content" | grep -q 'ENV_MODE=.*ZAPPZARAPP_ENV.*development'; then
        true  # Found ZAPPZARAPP_ENV mode detection
    else
        echo "# Missing ZAPPZARAPP_ENV mode detection in nginx entrypoint" >&3
        false
    fi

    # Check for exit 1 in production mode without SSL
    if echo "$entrypoint_content" | grep -q 'exit 1.*Fail-Fast\|ABORTED to prevent security'; then
        true  # Found fail-fast exit
    else
        echo "# Missing 'exit 1' for production mode without SSL" >&3
        false
    fi
}
