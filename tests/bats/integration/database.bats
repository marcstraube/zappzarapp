#!/usr/bin/env bats
# Integration Tests: Database Operations
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
# Database Connectivity
# =============================================================================

@test "[Integration] PostgreSQL is accessible" {
    require_service "postgres"
    run docker compose exec -T postgres pg_isready -U app
    assert_success
}

@test "[Integration] make postgres-cli connects successfully" {
    require_service "postgres"
    run timeout 10 docker compose exec -T postgres psql -U app -c "SELECT 1;"
    assert_success
}

# =============================================================================
# Database Migrations
# =============================================================================

@test "[Integration] make db-migrations runs successfully" {
    require_php
    require_database
    require_dependencies
    run timeout 120 make db-migrations
    assert_success
}

# =============================================================================
# Database Dump/Restore
# =============================================================================

@test "[Integration] make postgres-dump creates backup" {
    require_service "postgres"
    run timeout 60 make postgres-dump
    assert_success
    # Verify backup created
    [[ -d "backups" ]]
}

@test "[Integration] make backup-db-list shows backups" {
    require_service "postgres"
    run make backup-db-list
    assert_success
}

# =============================================================================
# Redis Operations
# =============================================================================

@test "[Integration] Redis is accessible" {
    require_service "redis"
    # Redis is configured with TLS only (--port 0 --tls-port 6379)
    run docker compose exec -T redis redis-cli --tls --insecure ping
    assert_success
    assert_output "PONG"
}

@test "[Integration] make redis-cli connects successfully" {
    require_service "redis"
    # Redis is configured with TLS only (--port 0 --tls-port 6379)
    run timeout 10 docker compose exec -T redis redis-cli --tls --insecure INFO server
    assert_success
    assert_output --partial "redis_version"
}

# =============================================================================
# Optional Services
# =============================================================================

@test "[Integration] Meilisearch is accessible" {
    require_service "meilisearch"
    run timeout 10 docker compose exec -T meilisearch curl -s http://localhost:7700/health
    assert_success
    assert_output --partial "available"
}

@test "[Integration] Elasticsearch is accessible" {
    require_service "elasticsearch"

    # Load API key from secrets
    local api_key
    api_key=$(cat secrets/elasticsearch_api_key.txt 2>/dev/null || echo "")

    if [ -z "$api_key" ]; then
        skip "Elasticsearch API key not configured (run: make es-setup-api-key)"
    fi

    # Use HTTPS with API key authentication
    run timeout 30 docker compose exec -T elasticsearch curl -sk \
        -H "Authorization: ApiKey $api_key" \
        "https://localhost:9200/_cluster/health"
    assert_success
    assert_output --partial "status"
}

@test "[Integration] RabbitMQ is accessible" {
    require_service "rabbitmq"
    run timeout 10 docker compose exec -T rabbitmq rabbitmqctl status
    assert_success
}

@test "[Integration] Mailpit is accessible" {
    require_service "mailpit"
    run timeout 10 docker compose exec -T mailpit curl -s http://localhost:8025/api/v1/info
    assert_success
}

# =============================================================================
# Health Checks
# =============================================================================

@test "[Integration] make check-health passes" {
    require_containers
    run timeout 60 make check-health
    assert_success
}
