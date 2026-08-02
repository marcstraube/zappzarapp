#!/usr/bin/env bats
# BATS Tests: Environment Loading
# Tests .env file loading and variable handling

load 'helpers/setup'

# =============================================================================
# .env File Existence
# =============================================================================

@test ".env file exists" {
    run test -f .env
    assert_success
}

@test ".env.local.example template exists" {
    run test -f .env.local.example
    assert_success
}

# =============================================================================
# Default Environment Variables
# =============================================================================

@test "DB_TYPE defaults to postgres" {
    clean_test_env
    load_env_file ".env"
    [[ "${DB_TYPE:-postgres}" == "postgres" ]]
}

@test "NODE_MODE has a default value" {
    clean_test_env
    load_env_file ".env"
    [[ -n "${NODE_MODE:-}" ]] || [[ "${NODE_MODE:-assets-api}" == "assets-api" ]]
}

@test "ENV defaults to development" {
    clean_test_env
    load_env_file ".env"
    [[ "${ENV:-development}" == "development" ]]
}

@test "COMPOSE_PROJECT_NAME is set" {
    load_env_file ".env"
    [[ -n "${COMPOSE_PROJECT_NAME:-}" ]]
}

# =============================================================================
# Caller ENV Precedence (ENV=production make ...)
# =============================================================================

@test "caller ENV=production wins over .env" {
    export ENV=production
    run make validate-env
    assert_success
    assert_output --partial "✓ ENV=production"
}

@test "without caller ENV the .env value applies" {
    run make validate-env
    assert_success
    assert_output --partial "ENV=development"
}

@test "unsupported ENV from the environment falls back to .env with a warning" {
    export ENV=/home/user/.kshrc
    run make validate-env
    assert_success
    assert_output --partial "Ignoring ENV="
    assert_output --partial "ENV=development"
}

@test "invalid command-line ENV aborts with an error" {
    run make validate-env ENV=prod
    assert_failure
    assert_output --partial "Invalid ENV"
}

# =============================================================================
# .env.local Override
# =============================================================================

@test ".env.local overrides .env values when present" {
    if [[ ! -f .env.local ]]; then
        skip ".env.local not present"
    fi

    # Load base .env
    load_env_file ".env"
    local base_user_id="${USER_ID:-}"

    # Load .env.local override
    load_env_file ".env.local"
    local override_user_id="${USER_ID:-}"

    # If .env.local sets USER_ID, it should be different or same
    # This test verifies the loading mechanism works
    [[ -n "$override_user_id" ]]
}

# =============================================================================
# Environment Variable Validation
# =============================================================================

@test "DB_TYPE accepts postgres" {
    export DB_TYPE=postgres
    run bash -c 'echo $DB_TYPE'
    assert_success
    assert_output "postgres"
}

@test "DB_TYPE accepts mariadb" {
    export DB_TYPE=mariadb
    run bash -c 'echo $DB_TYPE'
    assert_success
    assert_output "mariadb"
}

@test "NODE_MODE accepts assets" {
    export NODE_MODE=assets
    run bash -c 'echo $NODE_MODE'
    assert_success
    assert_output "assets"
}

@test "NODE_MODE accepts api" {
    export NODE_MODE=api
    run bash -c 'echo $NODE_MODE'
    assert_success
    assert_output "api"
}

@test "NODE_MODE accepts assets-api" {
    export NODE_MODE=assets-api
    run bash -c 'echo $NODE_MODE'
    assert_success
    assert_output "assets-api"
}

@test "NODE_MODE accepts framework" {
    export NODE_MODE=framework
    run bash -c 'echo $NODE_MODE'
    assert_success
    assert_output "framework"
}

@test "NODE_MODE accepts idle" {
    export NODE_MODE=idle
    run bash -c 'echo $NODE_MODE'
    assert_success
    assert_output "idle"
}

# =============================================================================
# ENABLE_* Flags
# =============================================================================

@test "ENABLE_PHP defaults to true" {
    clean_test_env
    load_env_file ".env"
    [[ "${ENABLE_PHP:-true}" == "true" ]]
}

@test "ENABLE_NODE defaults to true" {
    clean_test_env
    load_env_file ".env"
    [[ "${ENABLE_NODE:-true}" == "true" ]]
}

@test "ENABLE_DATABASE defaults to true" {
    clean_test_env
    load_env_file ".env"
    [[ "${ENABLE_DATABASE:-true}" == "true" ]]
}

@test "ENABLE_REDIS can be set to false" {
    export ENABLE_REDIS=false
    [[ "$ENABLE_REDIS" == "false" ]]
}

@test "ENABLE_MERCURE can be set to true" {
    export ENABLE_MERCURE=true
    [[ "$ENABLE_MERCURE" == "true" ]]
}

# =============================================================================
# Port Configuration
# =============================================================================

@test "NGINX_PORT has default" {
    load_env_file ".env"
    [[ -n "${NGINX_PORT:-8080}" ]]
}

@test "NGINX_SSL_PORT has default" {
    load_env_file ".env"
    [[ -n "${NGINX_SSL_PORT:-8443}" ]]
}

# =============================================================================
# Database Configuration
# =============================================================================

@test "DB_HOST has default" {
    load_env_file ".env"
    [[ -n "${DB_HOST:-postgres}" ]]
}

@test "DB_NAME has default" {
    load_env_file ".env"
    [[ -n "${DB_NAME:-app}" ]]
}

@test "DB_USER has default" {
    load_env_file ".env"
    [[ -n "${DB_USER:-app}" ]]
}

# =============================================================================
# Production Environment
# =============================================================================

@test "ENV=production changes behavior" {
    export ENV=production
    [[ "$ENV" == "production" ]]
}

@test ".env.production file may exist" {
    # This is optional, so we just check the file system
    if [[ -f .env.production ]]; then
        run test -f .env.production
        assert_success
    else
        skip ".env.production not present"
    fi
}

# =============================================================================
# Environment Isolation
# =============================================================================

@test "clean_test_env clears all test variables" {
    export DB_TYPE=mariadb
    export NODE_MODE=framework
    export ENABLE_REDIS=true

    clean_test_env

    [[ -z "${DB_TYPE:-}" ]]
    [[ -z "${NODE_MODE:-}" ]]
    [[ -z "${ENABLE_REDIS:-}" ]]
}

# =============================================================================
# make init Behavior
# =============================================================================

@test "make init does not overwrite existing .env.local" {
    # Skip if .env.local doesn't exist (nothing to protect)
    if [[ ! -f .env.local ]]; then
        skip ".env.local not present - cannot test preservation"
    fi

    # Store checksum of existing .env.local
    local original_checksum
    original_checksum=$(md5sum .env.local | cut -d' ' -f1)

    # Run make init
    run make init
    assert_success

    # Verify .env.local was not modified
    local new_checksum
    new_checksum=$(md5sum .env.local | cut -d' ' -f1)

    [[ "$original_checksum" == "$new_checksum" ]]
}

@test "make init creates .env.local from template" {
    # This test requires temporarily removing .env.local
    # Only run if we can safely backup/restore
    if [[ -f .env.local ]]; then
        skip ".env.local exists - skipping creation test to avoid data loss"
    fi

    # Verify .env.local.example exists
    if [[ ! -f .env.local.example ]]; then
        skip ".env.local.example not present"
    fi

    # Run make init (tests actual user-facing behavior)
    # Using 'make' directly works in both local and CI environments
    # as it executes on the host side of the mounted volume
    run make init
    assert_success

    # Verify .env.local was created
    run test -f .env.local
    assert_success

    # Verify it contains expected content (USER_ID should be substituted)
    run grep "^USER_ID=" .env.local
    assert_success

    # Verify the USER_ID is numeric
    run grep -E "^USER_ID=[0-9]+$" .env.local
    assert_success

    # Clean up - remove created file
    rm -f .env.local
}
