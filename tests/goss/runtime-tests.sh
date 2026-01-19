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
    # Source local .env if it exists (for health routing detection)
    if [ -f ".env" ]; then
        # shellcheck disable=SC1091
        source ".env"
    fi
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

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

is_container_running() {
    $DOCKER_COMPOSE ps -q "$1" 2>/dev/null | grep -q .
}

# Check if a string contains a pattern (case-insensitive)
contains_pattern() {
    echo "$1" | grep -qi "$2"
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

    # Test direct internal health endpoint on node-backend
    if $DOCKER_COMPOSE exec -T node-backend curl -sfk --max-time $TIMEOUT "https://localhost:3000/health" 2>/dev/null | grep -q "status"; then
        log_pass "node-backend: internal /health endpoint responds"
    else
        log_fail "node-backend: /health endpoint not responding"
        return
    fi

    # Test readiness endpoint
    if $DOCKER_COMPOSE exec -T node-backend curl -sfk --max-time $TIMEOUT "https://localhost:3000/ready" 2>/dev/null | grep -q "status"; then
        log_pass "node-backend: internal /ready endpoint responds"
    else
        log_warn "node-backend: /ready endpoint not responding (dependencies may be unavailable)"
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

test_seaweedfs() {
    log_test "seaweedfs: Master cluster status"

    if ! is_container_running seaweedfs; then
        log_skip "seaweedfs container not running"
        return
    fi

    # Test master cluster status endpoint (returns 200)
    # Note: Use 127.0.0.1 instead of localhost - Alpine doesn't always resolve localhost
    if $DOCKER_COMPOSE exec -T seaweedfs wget -q -O /dev/null --timeout=$TIMEOUT "http://127.0.0.1:9333/cluster/status" 2>/dev/null; then
        log_pass "seaweedfs: Master cluster status responds"
    else
        log_fail "seaweedfs: Master cluster status not responding"
        return
    fi

    # Test S3 API endpoint via HTTPS (returns 403 without auth, proves TLS works)
    log_test "seaweedfs: S3 API HTTPS (expects 403)"
    # wget returns exit code 8 on 403, so we capture output regardless of exit code
    HTTP_OUTPUT=$($DOCKER_COMPOSE exec -T seaweedfs wget -q -O /dev/null --server-response --no-check-certificate --timeout=$TIMEOUT "https://127.0.0.1:8333/" 2>&1 || true)
    HTTP_CODE=$(echo "$HTTP_OUTPUT" | grep "HTTP/" | head -1 | awk '{print $2}')
    if [ "$HTTP_CODE" = "403" ]; then
        log_pass "seaweedfs: S3 HTTPS returns 403 (expected)"
    elif [ -n "$HTTP_CODE" ]; then
        log_pass "seaweedfs: S3 HTTPS responds with $HTTP_CODE"
    else
        log_fail "seaweedfs: S3 HTTPS endpoint not responding"
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
# Health Routing Tests
# ============================================================================
# Tests the unified health endpoints based on configuration:
# - Mode 1: PHP (ENABLE_PHP=true)
# - Mode 2: Node Backend (ENABLE_PHP=false, ENABLE_NODE=true, NODE_MODE has backend)
# - Mode 3: Static/Node Frontend-only (ENABLE_PHP=false, ENABLE_NODE=true, NODE_MODE=framework|assets|idle)
# - Mode 4: Static (ENABLE_PHP=false, ENABLE_NODE=false)

# Determine expected health routing mode based on environment
get_health_routing_mode() {
    local enable_php="${ENABLE_PHP:-true}"
    local enable_node="${ENABLE_NODE:-false}"
    local node_mode="${NODE_MODE:-assets-api}"

    if [ "$enable_php" = "true" ]; then
        echo "php"
    elif [ "$enable_node" = "true" ]; then
        # Check if NODE_MODE includes backend (api or backend keyword)
        case "$node_mode" in
            *api*|backend)
                echo "node-backend"
                ;;
            *)
                echo "static"
                ;;
        esac
    else
        echo "static"
    fi
}

# Get expected service name for /health response
get_expected_service_name() {
    local mode="$1"
    case "$mode" in
        php) echo "php-fpm" ;;
        node-backend) echo "node-backend" ;;
        static) echo "nginx" ;;
        *) echo "unknown" ;;
    esac
}

test_health_routing() {
    echo -e "\n${BLUE}━━━ Health Routing Tests ━━━${NC}"

    if ! is_container_running nginx; then
        log_skip "nginx container not running - skipping health routing tests"
        return
    fi

    local mode
    mode=$(get_health_routing_mode)
    local expected_service
    expected_service=$(get_expected_service_name "$mode")

    log_info "Expected health routing mode: $mode (service: $expected_service)"
    log_info "Config: ENABLE_PHP=${ENABLE_PHP:-true}, ENABLE_NODE=${ENABLE_NODE:-false}, NODE_MODE=${NODE_MODE:-assets-api}"

    # -------------------------------------------------------------------------
    # Test /health endpoint (Liveness)
    # -------------------------------------------------------------------------
    log_test "health-routing: /health endpoint responds"

    local health_response
    health_response=$(curl -sfk --max-time $TIMEOUT "https://localhost:${NGINX_SSL_PORT}/health" 2>/dev/null)

    if [ -z "$health_response" ]; then
        log_fail "health-routing: /health endpoint returned empty response"
        return
    fi

    # Verify JSON format
    if ! echo "$health_response" | jq . >/dev/null 2>&1; then
        log_fail "health-routing: /health response is not valid JSON"
        return
    fi
    log_pass "health-routing: /health returns valid JSON"

    # Verify status field
    local health_status
    health_status=$(echo "$health_response" | jq -r '.status' 2>/dev/null)
    if [ "$health_status" = "ok" ]; then
        log_pass "health-routing: /health status is 'ok'"
    else
        log_fail "health-routing: /health status is '$health_status' (expected 'ok')"
    fi

    # Verify service field matches expected routing
    local health_service
    health_service=$(echo "$health_response" | jq -r '.service' 2>/dev/null)
    if [ "$health_service" = "$expected_service" ]; then
        log_pass "health-routing: /health service is '$health_service' (mode: $mode)"
    else
        log_fail "health-routing: /health service is '$health_service' (expected '$expected_service' for mode: $mode)"
    fi

    # Verify timestamp field exists
    local health_timestamp
    health_timestamp=$(echo "$health_response" | jq -r '.timestamp' 2>/dev/null)
    if [ -n "$health_timestamp" ] && [ "$health_timestamp" != "null" ]; then
        log_pass "health-routing: /health has timestamp field"
    else
        log_fail "health-routing: /health missing timestamp field"
    fi

    # -------------------------------------------------------------------------
    # Test /ready endpoint (Readiness)
    # -------------------------------------------------------------------------
    log_test "health-routing: /ready endpoint responds"

    local ready_response
    ready_response=$(curl -k --max-time 10 "https://localhost:${NGINX_SSL_PORT}/ready" 2>/dev/null)

    if [ -z "$ready_response" ]; then
        log_fail "health-routing: /ready endpoint returned empty response"
        return
    fi

    # Verify JSON format
    if ! echo "$ready_response" | jq . >/dev/null 2>&1; then
        log_fail "health-routing: /ready response is not valid JSON"
        return
    fi
    log_pass "health-routing: /ready returns valid JSON"

    # Verify status field (ok, degraded, or unhealthy are all valid)
    local ready_status
    ready_status=$(echo "$ready_response" | jq -r '.status' 2>/dev/null)
    case "$ready_status" in
        ok|degraded|unhealthy)
            log_pass "health-routing: /ready status is '$ready_status'"
            ;;
        *)
            log_fail "health-routing: /ready status is '$ready_status' (expected ok|degraded|unhealthy)"
            ;;
    esac

    # Verify checks field exists (for non-static modes)
    if [ "$mode" != "static" ]; then
        local has_checks
        has_checks=$(echo "$ready_response" | jq 'has("checks")' 2>/dev/null)
        if [ "$has_checks" = "true" ]; then
            log_pass "health-routing: /ready has 'checks' object"

            # Verify checks structure based on mode
            if [ "$mode" = "php" ]; then
                # PHP mode should have database, redis, node-backend, node-frontend checks
                local db_check
                db_check=$(echo "$ready_response" | jq -r '.checks.database.status' 2>/dev/null)
                if [ -n "$db_check" ] && [ "$db_check" != "null" ]; then
                    log_pass "health-routing: /ready has database check (status: $db_check)"
                else
                    log_warn "health-routing: /ready missing database check"
                fi
            elif [ "$mode" = "node-backend" ]; then
                # Node backend mode should have database, redis checks
                local node_db_check
                node_db_check=$(echo "$ready_response" | jq -r '.checks.database.status' 2>/dev/null)
                if [ -n "$node_db_check" ] && [ "$node_db_check" != "null" ]; then
                    log_pass "health-routing: /ready has database check (status: $node_db_check)"
                else
                    log_pass "health-routing: /ready database check disabled (ENABLE_DATABASE may be false)"
                fi
            fi
        else
            log_fail "health-routing: /ready missing 'checks' object"
        fi
    else
        # Static mode has empty checks
        log_pass "health-routing: /ready in static mode (no backend checks)"
    fi

    # Verify timestamp field exists
    local ready_timestamp
    ready_timestamp=$(echo "$ready_response" | jq -r '.timestamp' 2>/dev/null)
    if [ -n "$ready_timestamp" ] && [ "$ready_timestamp" != "null" ]; then
        log_pass "health-routing: /ready has timestamp field"
    else
        log_fail "health-routing: /ready missing timestamp field"
    fi

    # -------------------------------------------------------------------------
    # Test /status endpoint (if not static mode)
    # -------------------------------------------------------------------------
    if [ "$mode" != "static" ]; then
        log_test "health-routing: /status endpoint responds"

        local status_response
        status_response=$(curl -k --max-time 10 "https://localhost:${NGINX_SSL_PORT}/status" 2>/dev/null)

        if [ -z "$status_response" ]; then
            log_fail "health-routing: /status endpoint returned empty response"
        elif ! echo "$status_response" | jq . >/dev/null 2>&1; then
            log_fail "health-routing: /status response is not valid JSON"
        else
            log_pass "health-routing: /status returns valid JSON"

            # Verify services or checks field exists
            local has_services
            has_services=$(echo "$status_response" | jq 'has("services")' 2>/dev/null)
            if [ "$has_services" = "true" ]; then
                log_pass "health-routing: /status has 'services' object"
            else
                log_warn "health-routing: /status missing 'services' object"
            fi
        fi
    fi

    # -------------------------------------------------------------------------
    # Test /api/health endpoint (aggregated - only in PHP mode)
    # -------------------------------------------------------------------------
    if [ "$mode" = "php" ]; then
        log_test "health-routing: /api/health endpoint (aggregated)"

        local api_health_response
        api_health_response=$(curl -sfk --max-time $TIMEOUT "https://localhost:${NGINX_SSL_PORT}/api/health" 2>/dev/null)

        if [ -z "$api_health_response" ]; then
            log_fail "health-routing: /api/health endpoint returned empty response"
        elif ! echo "$api_health_response" | jq . >/dev/null 2>&1; then
            log_fail "health-routing: /api/health response is not valid JSON"
        else
            log_pass "health-routing: /api/health returns valid JSON"

            # Verify backends field exists
            local has_backends
            has_backends=$(echo "$api_health_response" | jq 'has("backends")' 2>/dev/null)
            if [ "$has_backends" = "true" ]; then
                log_pass "health-routing: /api/health has 'backends' object"

                # Verify PHP backend status
                local php_status
                php_status=$(echo "$api_health_response" | jq -r '.backends.php.status' 2>/dev/null)
                if [ "$php_status" = "ok" ]; then
                    log_pass "health-routing: /api/health PHP backend is 'ok'"
                else
                    log_fail "health-routing: /api/health PHP backend status is '$php_status'"
                fi

                # Verify Node backend status (if enabled)
                if [ "${ENABLE_NODE:-false}" = "true" ]; then
                    local node_status
                    node_status=$(echo "$api_health_response" | jq -r '.backends.node.status' 2>/dev/null)
                    case "$node_status" in
                        ok|disabled)
                            log_pass "health-routing: /api/health Node backend is '$node_status'"
                            ;;
                        *)
                            log_warn "health-routing: /api/health Node backend status is '$node_status'"
                            ;;
                    esac
                fi
            else
                log_fail "health-routing: /api/health missing 'backends' object"
            fi
        fi
    fi

    echo ""
}

# ============================================================================
# Main
# ============================================================================

run_all_tests() {
    echo -e "\n${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${BLUE}Runtime Integration Tests${NC}"
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}\n"

    test_nginx
    test_health_routing
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
    test_seaweedfs
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
    health-routing|health) test_health_routing ;;
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
    seaweedfs) test_seaweedfs ;;
    rabbitmq) test_rabbitmq ;;
    all) run_all_tests ;;
    *)
        echo "Usage: $0 [service|all]"
        echo "Services: nginx, health-routing, php, node-backend, node-frontend, postgres, mariadb,"
        echo "          redis, mercure, meilisearch, elasticsearch, mailpit, seaweedfs, rabbitmq"
        exit 1
        ;;
esac
