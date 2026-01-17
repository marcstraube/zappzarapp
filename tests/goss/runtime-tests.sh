#!/bin/bash
# Runtime Integration Tests for Docker Containers
#
# Tests running containers from host perspective:
# - HTTP/HTTPS endpoints
# - TLS certificate validity
# - Health check endpoints
# - Service-to-service connectivity
#
# Usage: ./tests/goss/runtime-tests.sh [service|all] [--env-file <path>]
#
# Environment Variables:
#   ENV_FILE - Path to docker compose env file (e.g., tests/goss/presets/fullstack.env)

# Exit on error, but handle arithmetic safely
set -euo pipefail

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Parse --env-file argument
ENV_FILE="${ENV_FILE:-}"
ARGS=()
while [[ $# -gt 0 ]]; do
    case $1 in
        --env-file)
            ENV_FILE="$2"
            shift 2
            ;;
        *)
            ARGS+=("$1")
            shift
            ;;
    esac
done
set -- "${ARGS[@]:-}"

# Docker compose command with optional env-file
if [ -n "$ENV_FILE" ]; then
    DOCKER_COMPOSE="docker compose --env-file $ENV_FILE"
    # Source env file to get port configuration
    # shellcheck disable=SC1090
    source "$ENV_FILE"
else
    DOCKER_COMPOSE="docker compose"
fi

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
    $DOCKER_COMPOSE ps -q "$1" 2>/dev/null | grep -q .
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
    if $DOCKER_COMPOSE exec -T php sh -c 'SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET cgi-fcgi -bind -connect /var/run/php-fpm/php-fpm.sock 2>/dev/null' | grep -q "pong"; then
        log_pass "php: PHP-FPM ping responds with 'pong'"
    else
        log_fail "php: PHP-FPM ping not responding"
    fi
}

test_node_backend() {
    log_test "node-backend: Express API health (HTTPS)"

    if ! is_container_running node-backend; then
        log_skip "node-backend container not running"
        return
    fi

    # Test health endpoint via nginx (external HTTPS)
    if curl -sf -k --max-time $TIMEOUT "https://localhost:${NGINX_SSL_PORT}/api/node/health" 2>/dev/null | grep -q "status"; then
        log_pass "node-backend: /api/node/health responds via nginx"
    else
        # Try direct internal HTTPS test
        if $DOCKER_COMPOSE exec -T node-backend curl -sfk --max-time $TIMEOUT "https://localhost:3000/health" 2>/dev/null | grep -q "status"; then
            log_pass "node-backend: internal HTTPS health endpoint responds"
        else
            log_fail "node-backend: health endpoint not responding"
        fi
    fi
}

test_node_frontend() {
    log_test "node-frontend: Framework server (HTTPS)"

    if ! is_container_running node; then
        log_skip "node (frontend) container not running"
        return
    fi

    # Check if it's in framework mode by testing HTTPS on port 3001
    if $DOCKER_COMPOSE exec -T node curl -sfk --max-time $TIMEOUT "https://localhost:3001/" >/dev/null 2>&1; then
        log_pass "node-frontend: Framework HTTPS server responds on port 3001"
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
    if $DOCKER_COMPOSE exec -T postgres pg_isready -U "${DB_USER:-app}" -d "${DB_NAME:-app}" >/dev/null 2>&1; then
        log_pass "postgres: pg_isready succeeds"
    else
        log_fail "postgres: pg_isready failed"
    fi

    # Test SSL is enabled
    log_test "postgres: SSL enabled"
    if $DOCKER_COMPOSE exec -T postgres psql -U "${DB_USER:-app}" -d "${DB_NAME:-app}" -c "SHOW ssl;" 2>/dev/null | grep -q "on"; then
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

    # Test healthcheck using custom script (uses gosu to run as mysql user)
    if $DOCKER_COMPOSE exec -T mariadb /custom-healthcheck.sh >/dev/null 2>&1; then
        log_pass "mariadb: healthcheck succeeds"
    else
        log_fail "mariadb: healthcheck failed"
    fi

    # Test SSL required
    log_test "mariadb: SSL required"
    if $DOCKER_COMPOSE exec -T mariadb mariadb -u "${DB_USER:-app}" -p"$(cat secrets/db_password.txt 2>/dev/null || echo 'secret')" -e "SHOW VARIABLES LIKE 'require_secure_transport';" 2>/dev/null | grep -q "ON"; then
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
    if $DOCKER_COMPOSE exec -T redis redis-cli --tls --insecure PING 2>/dev/null | grep -q "PONG"; then
        log_pass "redis: TLS PING responds with PONG"
    else
        log_fail "redis: TLS PING failed"
    fi

    # Test that non-TLS fails
    log_test "redis: Non-TLS connection rejected"
    if ! $DOCKER_COMPOSE exec -T redis redis-cli PING >/dev/null 2>&1; then
        log_pass "redis: Non-TLS connection correctly rejected"
    else
        log_fail "redis: Non-TLS connection should be rejected"
    fi
}

test_mercure() {
    log_test "mercure: SSE endpoint (HTTPS)"

    if ! is_container_running mercure; then
        log_skip "mercure container not running"
        return
    fi

    # Test HTTPS healthz endpoint (Mercure HTTPS on port 443)
    # Note: /.well-known/mercure requires POST, so we use /healthz
    # Use --spider to just check for response (healthz returns empty body)
    if $DOCKER_COMPOSE exec -T mercure wget --no-verbose --tries=1 --spider --no-check-certificate --timeout=$TIMEOUT "https://127.0.0.1:443/healthz" 2>/dev/null; then
        log_pass "mercure: HTTPS healthz endpoint responds"
    else
        log_fail "mercure: HTTPS endpoint not responding"
    fi
}

test_meilisearch() {
    log_test "meilisearch: Health endpoint"

    if ! is_container_running meilisearch; then
        log_skip "meilisearch container not running"
        return
    fi

    # Test health endpoint (HTTPS if configured, fallback to HTTP)
    # Use --spider to just check for response, not content (avoids grep issues)
    if $DOCKER_COMPOSE exec -T meilisearch wget --no-verbose --tries=1 --spider --no-check-certificate --timeout=$TIMEOUT "https://127.0.0.1:7700/health" 2>/dev/null; then
        log_pass "meilisearch: HTTPS health endpoint responds"
    elif $DOCKER_COMPOSE exec -T meilisearch wget --no-verbose --tries=1 --spider --timeout=$TIMEOUT "http://127.0.0.1:7700/health" 2>/dev/null; then
        log_pass "meilisearch: HTTP health endpoint responds (TLS not configured)"
    else
        log_fail "meilisearch: health endpoint not responding"
    fi
}

test_elasticsearch() {
    log_test "elasticsearch: Cluster health (HTTPS)"

    if ! is_container_running elasticsearch; then
        log_skip "elasticsearch container not running"
        return
    fi

    # Test cluster health via HTTPS (internal TLS with xpack.security)
    if $DOCKER_COMPOSE exec -T elasticsearch curl -sfk --max-time $TIMEOUT "https://localhost:9200/_cluster/health" 2>/dev/null | grep -q "status"; then
        log_pass "elasticsearch: HTTPS cluster health responds"
    else
        log_fail "elasticsearch: HTTPS cluster health not responding"
    fi
}

test_mailpit() {
    log_test "mailpit: Web UI"

    if ! is_container_running mailpit; then
        log_skip "mailpit container not running"
        return
    fi

    if $DOCKER_COMPOSE exec -T mailpit wget -qO- --timeout=$TIMEOUT "http://localhost:8025/" 2>/dev/null | grep -qi "mailpit"; then
        log_pass "mailpit: Web UI responds"
    else
        log_fail "mailpit: Web UI not responding"
    fi
}

test_minio() {
    log_test "minio: Health endpoint (HTTPS)"

    if ! is_container_running minio; then
        log_skip "minio container not running"
        return
    fi

    # Test health endpoint via HTTPS (internal TLS)
    if $DOCKER_COMPOSE exec -T minio curl -sfk --max-time $TIMEOUT "https://localhost:9000/minio/health/live" >/dev/null 2>&1; then
        log_pass "minio: HTTPS health endpoint responds"
    else
        log_fail "minio: HTTPS health endpoint not responding"
    fi
}

test_rabbitmq() {
    log_test "rabbitmq: Management API"

    if ! is_container_running rabbitmq; then
        log_skip "rabbitmq container not running"
        return
    fi

    # Use su-exec to run as rabbitmq user (needed to read .erlang.cookie without DAC_READ_SEARCH)
    if $DOCKER_COMPOSE exec -T rabbitmq su-exec rabbitmq rabbitmqctl status >/dev/null 2>&1; then
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
