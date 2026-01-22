#!/usr/bin/env bash
# BATS Integration Test Helper
# Provides setup/teardown for tests requiring running containers

# Load base setup
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
load "${SCRIPT_DIR}/../helpers/setup.bash"

# Integration test configuration
export INTEGRATION_PRESET="${INTEGRATION_PRESET:-dev-fullstack-optional}"
export INTEGRATION_TIMEOUT="${INTEGRATION_TIMEOUT:-300}"
export INTEGRATION_STARTUP_WAIT="${INTEGRATION_STARTUP_WAIT:-60}"

# Track if containers were started by us
export CONTAINERS_STARTED_BY_TEST=""

# =============================================================================
# File-Level Setup/Teardown
# =============================================================================

# Start containers for integration tests (call from setup_file)
integration_setup() {
    echo "# Integration setup: Starting containers with preset ${INTEGRATION_PRESET}" >&3

    # Check if containers are already running
    if docker compose ps --status running 2>/dev/null | grep -q "nginx"; then
        echo "# Containers already running, skipping startup" >&3
        CONTAINERS_STARTED_BY_TEST="false"
        return 0
    fi

    CONTAINERS_STARTED_BY_TEST="true"

    # Load preset environment
    if [[ -f "tests/goss/presets/${INTEGRATION_PRESET}.env" ]]; then
        set -a
        # shellcheck source=/dev/null
        source "tests/goss/presets/${INTEGRATION_PRESET}.env"
        set +a
    fi

    # Build images if needed
    echo "# Building images..." >&3
    make build 2>&1 | head -20 >&3 || true

    # Start containers
    echo "# Starting containers..." >&3
    make up 2>&1 | head -20 >&3

    # Wait for health
    echo "# Waiting for containers to be healthy..." >&3
    local elapsed=0
    while [[ $elapsed -lt $INTEGRATION_STARTUP_WAIT ]]; do
        if make check-health 2>/dev/null; then
            echo "# Containers healthy after ${elapsed}s" >&3
            break
        fi
        sleep 5
        elapsed=$((elapsed + 5))
    done

    # Install dependencies
    echo "# Installing PHP dependencies..." >&3
    make composer-install 2>&1 | tail -5 >&3 || true

    echo "# Installing Node dependencies..." >&3
    make pnpm-install 2>&1 | tail -5 >&3 || true

    echo "# Integration setup complete" >&3
}

# Stop containers after integration tests (call from teardown_file)
integration_teardown() {
    if [[ "$CONTAINERS_STARTED_BY_TEST" == "true" ]]; then
        echo "# Integration teardown: Stopping containers..." >&3
        make down 2>&1 | head -5 >&3 || true
    else
        echo "# Skipping teardown (containers were already running)" >&3
    fi
}

# =============================================================================
# Integration Test Helpers
# =============================================================================

# Skip test if containers not running
require_containers() {
    if ! docker compose ps --status running 2>/dev/null | grep -q "nginx"; then
        skip "Containers not running"
    fi
}

# Skip test if PHP container not running
require_php() {
    if ! docker compose ps --status running php 2>/dev/null | grep -q "php"; then
        skip "PHP container not running"
    fi
}

# Skip test if Node container not running
require_node() {
    if ! docker compose ps --status running node 2>/dev/null | grep -q "node"; then
        skip "Node container not running"
    fi
}

# Skip test if database not running
require_database() {
    local db_type="${DB_TYPE:-postgres}"
    if ! docker compose ps --status running "$db_type" 2>/dev/null | grep -q "$db_type"; then
        skip "Database container ($db_type) not running"
    fi
}

# Skip test if service not running
require_service() {
    local service="$1"
    if ! docker compose ps --status running "$service" 2>/dev/null | grep -q "$service"; then
        skip "Service $service not running"
    fi
}

# Run command with timeout
run_with_timeout() {
    local timeout="${1:-60}"
    shift
    timeout "$timeout" "$@"
}

# Check if dependencies are installed
dependencies_installed() {
    # Check PHP vendor
    if [[ ! -d "vendor" ]] || [[ ! -f "vendor/autoload.php" ]]; then
        return 1
    fi
    # Check Node modules
    if [[ ! -d "node_modules" ]] || [[ ! -d "node_modules/.pnpm" ]]; then
        return 1
    fi
    return 0
}

# Skip if dependencies not installed
require_dependencies() {
    if ! dependencies_installed; then
        skip "Dependencies not installed (run make composer-install && make pnpm-install)"
    fi
}
