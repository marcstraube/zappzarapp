#!/usr/bin/env bash
# BATS Test Helper - Common Setup
# Loads bats-support and bats-assert libraries

# Detect library location (Docker vs local)
if [[ -d "/usr/local/lib/bats" ]]; then
    BATS_LIB_PATH="/usr/local/lib/bats"
elif [[ -d "${BATS_TEST_DIRNAME}/../../../node_modules" ]]; then
    BATS_LIB_PATH="${BATS_TEST_DIRNAME}/../../../node_modules"
else
    BATS_LIB_PATH="${HOME}/.local/lib/bats"
fi

# Load helper libraries
load "${BATS_LIB_PATH}/bats-support/load.bash"
load "${BATS_LIB_PATH}/bats-assert/load.bash"

# Project root - find by looking for Makefile
# Works for tests in any subdirectory (tests/bats/, tests/bats/integration/, etc.)
_find_project_root() {
    local dir="$BATS_TEST_DIRNAME"
    while [[ "$dir" != "/" ]]; do
        if [[ -f "$dir/Makefile" ]] && [[ -f "$dir/compose.yaml" ]]; then
            echo "$dir"
            return 0
        fi
        dir="$(dirname "$dir")"
    done
    # Fallback: use current working directory
    pwd
}
export PROJECT_ROOT="$(_find_project_root)"

# Change to project root for all tests
cd "${PROJECT_ROOT}" || exit 1

# Default environment - use actual project name from .env or default
# Don't override if already set by the project's .env
if [[ -z "${COMPOSE_PROJECT_NAME:-}" ]]; then
    if [[ -f "${PROJECT_ROOT}/.env" ]]; then
        # Source project .env to get COMPOSE_PROJECT_NAME
        # shellcheck source=/dev/null
        COMPOSE_PROJECT_NAME=$(grep -E '^COMPOSE_PROJECT_NAME=' "${PROJECT_ROOT}/.env" | cut -d= -f2 || echo "zappzarapp")
    fi
    export COMPOSE_PROJECT_NAME="${COMPOSE_PROJECT_NAME:-zappzarapp}"
fi

# Timeout for long-running commands (seconds)
# 600s for CI environments with cold builds (Docker images + dependencies)
export BATS_TEST_TIMEOUT="${BATS_TEST_TIMEOUT:-600}"

# Helper: Check if container is running
is_container_running() {
    local container="$1"
    docker compose ps --status running "$container" 2>/dev/null | grep -q "$container"
}

# Helper: Wait for container to be healthy
wait_for_healthy() {
    local container="$1"
    local timeout="${2:-60}"
    local elapsed=0

    while [[ $elapsed -lt $timeout ]]; do
        if docker compose ps "$container" 2>/dev/null | grep -q "(healthy)"; then
            return 0
        fi
        sleep 2
        elapsed=$((elapsed + 2))
    done
    return 1
}

# Helper: Get container health status
get_container_health() {
    local container="$1"
    docker inspect --format='{{.State.Health.Status}}' "${COMPOSE_PROJECT_NAME}-${container}-1" 2>/dev/null || echo "unknown"
}

# Helper: Run make command with timeout
run_make() {
    timeout "${BATS_TEST_TIMEOUT}" make "$@"
}

# Helper: Clean environment for isolated tests
clean_test_env() {
    unset DB_TYPE NODE_MODE ENV
    unset ENABLE_PHP ENABLE_NODE ENABLE_DATABASE ENABLE_REDIS
    unset ENABLE_MERCURE ENABLE_MEILISEARCH ENABLE_ELASTICSEARCH
    unset ENABLE_MAILPIT ENABLE_SEAWEEDFS ENABLE_RABBITMQ
}

# Helper: Source .env file and export variables
load_env_file() {
    local env_file="${1:-.env}"
    if [[ -f "$env_file" ]]; then
        set -a
        # shellcheck source=/dev/null
        source "$env_file"
        set +a
    fi
}

# Helper: Check if make target exists
target_exists() {
    local target="$1"
    make -n "$target" &>/dev/null
}

# Helper: Get make target help text
get_target_help() {
    local target="$1"
    # Strip ANSI codes and find target line
    make help 2>/dev/null | sed 's/\x1b\[[0-9;]*m//g' | grep -E "^\s*$target\s" | head -1
}

# Setup function - runs before each test
setup() {
    # Ensure we're in project root
    cd "${PROJECT_ROOT}" || exit 1

    # Clean environment
    clean_test_env
}

# Teardown function - runs after each test
teardown() {
    # Restore working directory
    cd "${PROJECT_ROOT}" || true
}
