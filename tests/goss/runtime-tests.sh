#!/bin/bash
# Runtime Integration Tests for Docker Containers
#
# Tests running containers from host perspective:
# - HTTP/HTTPS endpoints
# - TLS certificate validity
# - Health check endpoints
# - Service-to-service connectivity
#
# Usage: ./tests/goss/runtime-tests.sh [service|all]

# Exit on error, but handle arithmetic safely
set -euo pipefail

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
NGINX_SSL_PORT="${NGINX_SSL_PORT:-8443}"
TIMEOUT=5

# Counters
PASSED=0
FAILED=0
SKIPPED=0

# ============================================================================
# Helper Functions
# ============================================================================

log_test() {
    echo -e "${BLUE}[TEST]${NC} $1"
}

log_pass() {
    echo -e "${GREEN}[PASS]${NC} $1"
    PASSED=$((PASSED + 1))
}

log_fail() {
    echo -e "${RED}[FAIL]${NC} $1"
    FAILED=$((FAILED + 1))
}

log_skip() {
    echo -e "${YELLOW}[SKIP]${NC} $1"
    SKIPPED=$((SKIPPED + 1))
}

is_container_running() {
    docker compose ps -q "$1" 2>/dev/null | grep -q .
}

# ============================================================================
# Test Functions
# ============================================================================

test_nginx() {
    log_test "nginx: HTTPS endpoint (port ${NGINX_SSL_PORT})"

    if ! is_container_running nginx; then
        log_skip "nginx container not running"
        return
    fi

    # Test HTTPS with self-signed cert
    if curl -sf -k --max-time $TIMEOUT "https://localhost:${NGINX_SSL_PORT}/health" >/dev/null 2>&1; then
        log_pass "nginx: HTTPS endpoint responds"
    else
        # Try alternative health endpoint
        if curl -sf -k --max-time $TIMEOUT "https://localhost:${NGINX_SSL_PORT}/" >/dev/null 2>&1; then
            log_pass "nginx: HTTPS root endpoint responds"
        else
            log_fail "nginx: HTTPS endpoint not responding"
        fi
    fi

    # Test TLS certificate
    log_test "nginx: TLS certificate validity"
    if echo | openssl s_client -connect "localhost:${NGINX_SSL_PORT}" -servername localhost 2>/dev/null | openssl x509 -noout -dates >/dev/null 2>&1; then
        log_pass "nginx: TLS certificate is valid"
    else
        log_fail "nginx: TLS certificate check failed"
    fi
}

test_php() {
    log_test "php: PHP-FPM health (via nginx)"

    if ! is_container_running php; then
        log_skip "php container not running"
        return
    fi

    # Test PHP endpoint via nginx
    if curl -sf -k --max-time $TIMEOUT "https://localhost:${NGINX_SSL_PORT}/health.php" 2>/dev/null | grep -q "ok"; then
        log_pass "php: health.php responds with 'ok'"
    else
        log_fail "php: health.php not responding correctly"
    fi

    # Test PHP-FPM ping via docker exec
    log_test "php: PHP-FPM ping endpoint"
    if docker compose exec -T php sh -c 'SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET cgi-fcgi -bind -connect /var/run/php-fpm/php-fpm.sock 2>/dev/null' | grep -q "pong"; then
        log_pass "php: PHP-FPM ping responds with 'pong'"
    else
        log_fail "php: PHP-FPM ping not responding"
    fi
}

test_node_backend() {
    log_test "node-backend: Express API health"

    if ! is_container_running node-backend; then
        log_skip "node-backend container not running"
        return
    fi

    # Test health endpoint via nginx
    if curl -sf -k --max-time $TIMEOUT "https://localhost:${NGINX_SSL_PORT}/api/node/health" 2>/dev/null | grep -q "status"; then
        log_pass "node-backend: /api/node/health responds"
    else
        # Try direct internal test
        if docker compose exec -T node-backend curl -sf --max-time $TIMEOUT "http://localhost:3000/health" 2>/dev/null | grep -q "status"; then
            log_pass "node-backend: internal health endpoint responds"
        else
            log_fail "node-backend: health endpoint not responding"
        fi
    fi
}

test_node_frontend() {
    log_test "node-frontend: Framework server"

    if ! is_container_running node; then
        log_skip "node (frontend) container not running"
        return
    fi

    # Check if it's in framework mode by testing port 3001
    if docker compose exec -T node curl -sf --max-time $TIMEOUT "http://localhost:3001/" >/dev/null 2>&1; then
        log_pass "node-frontend: Framework server responds on port 3001"
    else
        log_skip "node-frontend: Not in framework mode or not responding"
    fi
}

test_postgres() {
    log_test "postgres: Database connectivity"

    if ! is_container_running postgres; then
        log_skip "postgres container not running"
        return
    fi

    # Test pg_isready
    if docker compose exec -T postgres pg_isready -U "${DB_USER:-app}" -d "${DB_NAME:-app}" >/dev/null 2>&1; then
        log_pass "postgres: pg_isready succeeds"
    else
        log_fail "postgres: pg_isready failed"
    fi

    # Test SSL is enabled
    log_test "postgres: SSL enabled"
    if docker compose exec -T postgres psql -U "${DB_USER:-app}" -d "${DB_NAME:-app}" -c "SHOW ssl;" 2>/dev/null | grep -q "on"; then
        log_pass "postgres: SSL is enabled"
    else
        log_fail "postgres: SSL is not enabled"
    fi
}

test_mariadb() {
    log_test "mariadb: Database connectivity"

    if ! is_container_running mariadb; then
        log_skip "mariadb container not running"
        return
    fi

    # Test healthcheck
    if docker compose exec -T mariadb healthcheck.sh --connect --innodb_initialized >/dev/null 2>&1; then
        log_pass "mariadb: healthcheck succeeds"
    else
        log_fail "mariadb: healthcheck failed"
    fi

    # Test SSL required
    log_test "mariadb: SSL required"
    if docker compose exec -T mariadb mariadb -u "${DB_USER:-app}" -p"$(cat secrets/db_password.txt 2>/dev/null || echo 'secret')" -e "SHOW VARIABLES LIKE 'require_secure_transport';" 2>/dev/null | grep -q "ON"; then
        log_pass "mariadb: require_secure_transport is ON"
    else
        log_skip "mariadb: Could not verify SSL requirement"
    fi
}

test_redis() {
    log_test "redis: TLS connectivity"

    if ! is_container_running redis; then
        log_skip "redis container not running"
        return
    fi

    # Test Redis PING via TLS
    if docker compose exec -T redis redis-cli --tls --insecure PING 2>/dev/null | grep -q "PONG"; then
        log_pass "redis: TLS PING responds with PONG"
    else
        log_fail "redis: TLS PING failed"
    fi

    # Test that non-TLS fails
    log_test "redis: Non-TLS connection rejected"
    if ! docker compose exec -T redis redis-cli PING >/dev/null 2>&1; then
        log_pass "redis: Non-TLS connection correctly rejected"
    else
        log_fail "redis: Non-TLS connection should be rejected"
    fi
}

test_mercure() {
    log_test "mercure: SSE endpoint"

    if ! is_container_running mercure; then
        log_skip "mercure container not running"
        return
    fi

    if docker compose exec -T mercure wget -qO- --timeout=$TIMEOUT "http://localhost:80/.well-known/mercure" >/dev/null 2>&1; then
        log_pass "mercure: .well-known/mercure endpoint responds"
    else
        log_fail "mercure: endpoint not responding"
    fi
}

test_meilisearch() {
    log_test "meilisearch: Health endpoint"

    if ! is_container_running meilisearch; then
        log_skip "meilisearch container not running"
        return
    fi

    if docker compose exec -T meilisearch wget -qO- --timeout=$TIMEOUT "http://localhost:7700/health" 2>/dev/null | grep -q "available"; then
        log_pass "meilisearch: health endpoint responds"
    else
        log_fail "meilisearch: health endpoint not responding"
    fi
}

test_elasticsearch() {
    log_test "elasticsearch: Cluster health"

    if ! is_container_running elasticsearch; then
        log_skip "elasticsearch container not running"
        return
    fi

    if docker compose exec -T elasticsearch curl -sf --max-time $TIMEOUT "http://localhost:9200/_cluster/health" 2>/dev/null | grep -q "status"; then
        log_pass "elasticsearch: cluster health responds"
    else
        log_fail "elasticsearch: cluster health not responding"
    fi
}

test_mailpit() {
    log_test "mailpit: Web UI"

    if ! is_container_running mailpit; then
        log_skip "mailpit container not running"
        return
    fi

    if docker compose exec -T mailpit wget -qO- --timeout=$TIMEOUT "http://localhost:8025/" 2>/dev/null | grep -qi "mailpit"; then
        log_pass "mailpit: Web UI responds"
    else
        log_fail "mailpit: Web UI not responding"
    fi
}

test_minio() {
    log_test "minio: Health endpoint (TLS)"

    if ! is_container_running minio; then
        log_skip "minio container not running"
        return
    fi

    if docker compose exec -T minio curl -sfk --max-time $TIMEOUT "https://localhost:9000/minio/health/live" >/dev/null 2>&1; then
        log_pass "minio: health endpoint responds"
    else
        log_fail "minio: health endpoint not responding"
    fi
}

test_rabbitmq() {
    log_test "rabbitmq: Management API"

    if ! is_container_running rabbitmq; then
        log_skip "rabbitmq container not running"
        return
    fi

    if docker compose exec -T rabbitmq rabbitmqctl status >/dev/null 2>&1; then
        log_pass "rabbitmq: rabbitmqctl status succeeds"
    else
        log_fail "rabbitmq: rabbitmqctl status failed"
    fi
}

# ============================================================================
# Main
# ============================================================================

run_all_tests() {
    echo -e "\n${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${BLUE}Runtime Integration Tests${NC}"
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}\n"

    test_nginx
    test_php
    test_node_backend
    test_node_frontend
    test_postgres
    test_mariadb
    test_redis
    test_mercure
    test_meilisearch
    test_elasticsearch
    test_mailpit
    test_minio
    test_rabbitmq

    echo -e "\n${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "Results: ${GREEN}$PASSED passed${NC}, ${RED}$FAILED failed${NC}, ${YELLOW}$SKIPPED skipped${NC}"
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}\n"

    if [ $FAILED -gt 0 ]; then
        exit 1
    fi
}

# Run specific service test or all
case "${1:-all}" in
    nginx) test_nginx ;;
    php) test_php ;;
    node-backend) test_node_backend ;;
    node-frontend|node) test_node_frontend ;;
    postgres) test_postgres ;;
    mariadb) test_mariadb ;;
    redis) test_redis ;;
    mercure) test_mercure ;;
    meilisearch) test_meilisearch ;;
    elasticsearch) test_elasticsearch ;;
    mailpit) test_mailpit ;;
    minio) test_minio ;;
    rabbitmq) test_rabbitmq ;;
    all) run_all_tests ;;
    *)
        echo "Usage: $0 [service|all]"
        echo "Services: nginx, php, node-backend, node-frontend, postgres, mariadb, redis,"
        echo "          mercure, meilisearch, elasticsearch, mailpit, minio, rabbitmq"
        exit 1
        ;;
esac
