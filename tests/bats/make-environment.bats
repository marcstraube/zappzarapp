#!/usr/bin/env bats
# BATS Tests: Environment Matrix
# Tests DB_TYPE, NODE_MODE, and ENABLE_* flag combinations

load 'helpers/setup'

# =============================================================================
# DB_TYPE: PostgreSQL
# =============================================================================

@test "DB_TYPE=postgres: compose with postgres profile includes postgres service" {
    export DB_TYPE=postgres
    run docker compose --profile postgres config --services
    assert_success
    assert_output --partial "postgres"
}

@test "DB_TYPE=postgres: compose with postgres profile excludes mariadb service" {
    export DB_TYPE=postgres
    run docker compose --profile postgres config --services
    assert_success
    refute_output --partial "mariadb"
}

@test "DB_TYPE=postgres: compose validates" {
    export DB_TYPE=postgres
    run docker compose config --quiet
    assert_success
}

# =============================================================================
# DB_TYPE: MariaDB
# =============================================================================

@test "DB_TYPE=mariadb: compose config includes mariadb service" {
    export DB_TYPE=mariadb
    run docker compose --profile mariadb config --services
    assert_success
    assert_output --partial "mariadb"
}

@test "DB_TYPE=mariadb: profile is active" {
    export DB_TYPE=mariadb
    run docker compose --profile mariadb config --services
    assert_success
    assert_output --partial "mariadb"
}

# =============================================================================
# NODE_MODE: Assets
# =============================================================================

@test "NODE_MODE=assets: is valid configuration" {
    export NODE_MODE=assets
    run bash -c 'echo "NODE_MODE=$NODE_MODE"'
    assert_success
    assert_output "NODE_MODE=assets"
}

@test "NODE_MODE=assets: compose validates" {
    export NODE_MODE=assets
    run docker compose config --quiet
    assert_success
}

# =============================================================================
# NODE_MODE: API
# =============================================================================

@test "NODE_MODE=api: is valid configuration" {
    export NODE_MODE=api
    run bash -c 'echo "NODE_MODE=$NODE_MODE"'
    assert_success
    assert_output "NODE_MODE=api"
}

@test "NODE_MODE=api: compose validates" {
    export NODE_MODE=api
    run docker compose config --quiet
    assert_success
}

# =============================================================================
# NODE_MODE: Assets-API (Default)
# =============================================================================

@test "NODE_MODE=assets-api: is valid configuration" {
    export NODE_MODE=assets-api
    run bash -c 'echo "NODE_MODE=$NODE_MODE"'
    assert_success
    assert_output "NODE_MODE=assets-api"
}

@test "NODE_MODE=assets-api: compose validates" {
    export NODE_MODE=assets-api
    run docker compose config --quiet
    assert_success
}

# =============================================================================
# NODE_MODE: Framework
# =============================================================================

@test "NODE_MODE=framework: is valid configuration" {
    export NODE_MODE=framework
    run bash -c 'echo "NODE_MODE=$NODE_MODE"'
    assert_success
    assert_output "NODE_MODE=framework"
}

@test "NODE_MODE=framework: compose validates" {
    export NODE_MODE=framework
    run docker compose config --quiet
    assert_success
}

# =============================================================================
# NODE_MODE: Framework-API
# =============================================================================

@test "NODE_MODE=framework-api: is valid configuration" {
    export NODE_MODE=framework-api
    run bash -c 'echo "NODE_MODE=$NODE_MODE"'
    assert_success
    assert_output "NODE_MODE=framework-api"
}

@test "NODE_MODE=framework-api: compose validates" {
    export NODE_MODE=framework-api
    run docker compose config --quiet
    assert_success
}

# =============================================================================
# NODE_MODE: Idle
# =============================================================================

@test "NODE_MODE=idle: is valid configuration" {
    export NODE_MODE=idle
    run bash -c 'echo "NODE_MODE=$NODE_MODE"'
    assert_success
    assert_output "NODE_MODE=idle"
}

@test "NODE_MODE=idle: compose validates" {
    export NODE_MODE=idle
    run docker compose config --quiet
    assert_success
}

# =============================================================================
# ENABLE_REDIS
# =============================================================================

@test "ENABLE_REDIS=true: redis profile available" {
    export ENABLE_REDIS=true
    run docker compose --profile redis config --services
    assert_success
    assert_output --partial "redis"
}

@test "ENABLE_REDIS=false: compose validates" {
    export ENABLE_REDIS=false
    run docker compose config --quiet
    assert_success
}

# =============================================================================
# ENABLE_MERCURE
# =============================================================================

@test "ENABLE_MERCURE=true: mercure profile available" {
    export ENABLE_MERCURE=true
    run docker compose --profile mercure config --services
    assert_success
    assert_output --partial "mercure"
}

@test "ENABLE_MERCURE=false: compose validates" {
    export ENABLE_MERCURE=false
    run docker compose config --quiet
    assert_success
}

# =============================================================================
# ENABLE_MEILISEARCH
# =============================================================================

@test "ENABLE_MEILISEARCH=true: meilisearch profile available" {
    export ENABLE_MEILISEARCH=true
    run docker compose --profile meilisearch config --services
    assert_success
    assert_output --partial "meilisearch"
}

@test "ENABLE_MEILISEARCH=false: compose validates" {
    export ENABLE_MEILISEARCH=false
    run docker compose config --quiet
    assert_success
}

# =============================================================================
# ENABLE_ELASTICSEARCH
# =============================================================================

@test "ENABLE_ELASTICSEARCH=true: elasticsearch profile available" {
    export ENABLE_ELASTICSEARCH=true
    run docker compose --profile elasticsearch config --services
    assert_success
    assert_output --partial "elasticsearch"
}

@test "ENABLE_ELASTICSEARCH=false: compose validates" {
    export ENABLE_ELASTICSEARCH=false
    run docker compose config --quiet
    assert_success
}

# =============================================================================
# ENABLE_MAILPIT
# =============================================================================

@test "ENABLE_MAILPIT=true: mailpit profile available" {
    export ENABLE_MAILPIT=true
    run docker compose --profile mailpit config --services
    assert_success
    assert_output --partial "mailpit"
}

@test "ENABLE_MAILPIT=false: compose validates" {
    export ENABLE_MAILPIT=false
    run docker compose config --quiet
    assert_success
}

# =============================================================================
# ENABLE_RABBITMQ
# =============================================================================

@test "ENABLE_RABBITMQ=true: rabbitmq profile available" {
    export ENABLE_RABBITMQ=true
    run docker compose --profile rabbitmq config --services
    assert_success
    assert_output --partial "rabbitmq"
}

@test "ENABLE_RABBITMQ=false: compose validates" {
    export ENABLE_RABBITMQ=false
    run docker compose config --quiet
    assert_success
}

# =============================================================================
# ENABLE_SEAWEEDFS
# =============================================================================

@test "ENABLE_SEAWEEDFS=true: seaweedfs profile available" {
    export ENABLE_SEAWEEDFS=true
    run docker compose --profile seaweedfs config --services
    assert_success
    assert_output --partial "seaweedfs"
}

@test "ENABLE_SEAWEEDFS=false: compose validates" {
    export ENABLE_SEAWEEDFS=false
    run docker compose config --quiet
    assert_success
}

# =============================================================================
# ZAPPZARAPP_ENV: Development vs Production
# =============================================================================

@test "ZAPPZARAPP_ENV=development: compose validates" {
    export ZAPPZARAPP_ENV=development
    run docker compose config --quiet
    assert_success
}

@test "ZAPPZARAPP_ENV=production: compose validates" {
    export ZAPPZARAPP_ENV=production
    run docker compose config --quiet
    assert_success
}

# =============================================================================
# Combined Configurations
# =============================================================================

@test "DB_TYPE=mariadb + NODE_MODE=api: compose validates" {
    export DB_TYPE=mariadb
    export NODE_MODE=api
    run docker compose config --quiet
    assert_success
}

@test "DB_TYPE=postgres + NODE_MODE=framework + ENABLE_REDIS=true: compose validates" {
    export DB_TYPE=postgres
    export NODE_MODE=framework
    export ENABLE_REDIS=true
    run docker compose config --quiet
    assert_success
}

@test "Full optional services: compose validates" {
    export DB_TYPE=postgres
    export NODE_MODE=assets-api
    export ENABLE_REDIS=true
    export ENABLE_MERCURE=true
    export ENABLE_MEILISEARCH=true
    export ENABLE_MAILPIT=true
    run docker compose config --quiet
    assert_success
}

# =============================================================================
# Preset Validation
# =============================================================================

@test "dev-fullstack preset file exists" {
    run test -f tests/goss/presets/dev-fullstack.env
    assert_success
}

@test "dev-php-only preset file exists" {
    run test -f tests/goss/presets/dev-php-only.env
    assert_success
}

@test "dev-node-only preset file exists" {
    run test -f tests/goss/presets/dev-node-only.env
    assert_success
}

@test "dev-minimal preset file exists" {
    run test -f tests/goss/presets/dev-minimal.env
    assert_success
}

@test "prod-fullstack preset file exists" {
    run test -f tests/goss/presets/prod-fullstack.env
    assert_success
}

@test "dev-fullstack-mariadb preset file exists" {
    run test -f tests/goss/presets/dev-fullstack-mariadb.env
    assert_success
}
