#!/bin/bash
# docker/hooks/hook-runner.sh
# Smart container execution wrapper for Git hooks
#
# Usage: ./docker/hooks/hook-runner.sh <service> <command...>
#
# Examples:
#   ./docker/hooks/hook-runner.sh php php -l /var/www/html/src/file.php
#   ./docker/hooks/hook-runner.sh dev-tools pnpm exec lint-staged
#   ./docker/hooks/hook-runner.sh php vendor/bin/php-cs-fixer fix --config=...
#
# Behavior:
#   - If service container is running: uses "docker compose exec" (fast)
#   - If not running: uses "docker compose run --rm" (starts temporary container)
#
# Environment:
#   HOOK_RUNNER_VERBOSE=1  - Enable debug output

set -euo pipefail

SERVICE="$1"
shift
COMMAND=("$@")

# Check if container is running
# --status running is required: plain "ps -q" also lists created/exited
# containers, which would route into "exec" against a dead container
is_running() {
    docker compose ps -q --status running "$1" 2>/dev/null | grep -q .
}

# Debug output
debug() {
    if [[ "${HOOK_RUNNER_VERBOSE:-}" == "1" ]]; then
        echo "[hook-runner] $*" >&2
    fi
}

if is_running "$SERVICE"; then
    debug "Using 'exec' for $SERVICE (container running)"
    exec docker compose exec -T "$SERVICE" "${COMMAND[@]}"
else
    debug "Using 'run' for $SERVICE (container not running)"
    # For dev-tools, we need to include the profile
    if [[ "$SERVICE" == "dev-tools" ]]; then
        export COMPOSE_PROFILES=tools
        exec docker compose run --rm -T "$SERVICE" "${COMMAND[@]}"
    else
        exec docker compose run --rm -T "$SERVICE" "${COMMAND[@]}"
    fi
fi
