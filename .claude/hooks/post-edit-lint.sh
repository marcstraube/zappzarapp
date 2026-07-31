#!/bin/bash
# PostToolUse Hook: Fast syntax checks using container environment
# Runs after Edit/Write to provide immediate feedback
#
# Philosophy:
#   - Catch syntax/type errors IMMEDIATELY (< 0.5s when containers running)
#   - Use CONTAINER versions (consistent with CI/production)
#   - Style/linting checks wait until pre-commit
#   - Full quality gates in pre-push
#
# Usage: Called by Claude Code PostToolUse hook
# Input: Tool input JSON via stdin (contains file_path)
# Output: Syntax errors or nothing if clean

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
HOOK_RUNNER="$PROJECT_ROOT/docker/hooks/hook-runner.sh"

# Read tool input from stdin
TOOL_INPUT=$(cat)

# Extract file_path from JSON
FILE=$(echo "$TOOL_INPUT" | jq -r '.file_path // empty' 2>/dev/null || echo "")

# Exit if no file path or file doesn't exist
[[ -z "$FILE" || ! -f "$FILE" ]] && exit 0

# Skip non-code files
case "$FILE" in
  *.lock|*.sum|*.map|*.min.*) exit 0 ;;
  */node_modules/*|*/vendor/*|*/build/*) exit 0 ;;
  */.claude/temp/*) exit 0 ;;
esac

# Helper: Check if service container is running
is_running() {
    docker compose ps -q --status running "$1" 2>/dev/null | grep -q .
}

# Helper: Ensure container is running (with smart startup)
ensure_container() {
    local service="$1"

    # Already running? Great!
    if is_running "$service"; then
        return 0
    fi

    # Not running - start in background (non-blocking)
    # shellcheck disable=SC2069
    if [[ "$service" == "dev-tools" ]]; then
        COMPOSE_PROFILES=tools docker compose up -d "$service" >/dev/null 2>&1 &
    else
        docker compose up -d "$service" >/dev/null 2>&1 &
    fi

    # Wait max 5s for container to be ready
    for i in {1..10}; do
        sleep 0.5
        if is_running "$service"; then
            return 0
        fi
    done

    # Timeout - container didn't start in time, skip check
    return 1
}

# Convert host path to container path
to_container_path() {
    local host_path="$1"
    local service="$2"

    case "$service" in
        php)
            echo "/var/www/html/${host_path#$PROJECT_ROOT/}"
            ;;
        dev-tools)
            echo "/app/${host_path#$PROJECT_ROOT/}"
            ;;
        *)
            echo "${host_path#$PROJECT_ROOT/}"
            ;;
    esac
}

# Fast syntax checks using container environment
run_checks() {
case "$FILE" in
  *.php)
    # PHP syntax check using container PHP version (matches CI/production)
    if ensure_container php; then
        CONTAINER_FILE=$(to_container_path "$FILE" php)
        OUTPUT=$("$HOOK_RUNNER" php php -l "$CONTAINER_FILE" 2>&1 || true)
        if echo "$OUTPUT" | grep -qE "Parse error|syntax error"; then
            echo "[Syntax:PHP] Parse error - will fail at commit"
            echo "$OUTPUT" | grep -E "Parse error|syntax error|line [0-9]+" | head -3
        fi
    fi
    ;;

  *.ts|*.tsx)
    # TypeScript type check (single file, fast)
    if ensure_container dev-tools; then
        CONTAINER_FILE=$(to_container_path "$FILE" dev-tools)
        OUTPUT=$(timeout 5 "$HOOK_RUNNER" dev-tools pnpm exec tsc --noEmit "$CONTAINER_FILE" 2>&1 || true)
        if echo "$OUTPUT" | grep -qE "error TS[0-9]+"; then
            echo "[TypeCheck:TS] Type errors - fix before commit"
            echo "$OUTPUT" | grep "error TS" | head -5
        fi
    fi
    ;;

  *.js|*.jsx|*.mjs|*.cjs)
    # JavaScript syntax check using container Node version
    if ensure_container dev-tools; then
        CONTAINER_FILE=$(to_container_path "$FILE" dev-tools)
        OUTPUT=$("$HOOK_RUNNER" dev-tools node --check "$CONTAINER_FILE" 2>&1 || true)
        if echo "$OUTPUT" | grep -qE "SyntaxError"; then
            echo "[Syntax:JS] Parse error - will fail at commit"
            echo "$OUTPUT" | grep "SyntaxError" | head -3
        fi
    fi
    ;;

  *.sh|*.bash)
    # Shell: Use local bash (syntax is version-independent for most cases)
    if ! bash -n "$FILE" > /dev/null 2>&1; then
        echo "[Syntax:Shell] Parse error - will fail at commit"
        bash -n "$FILE" 2>&1 | head -3
    fi
    ;;

  *.json)
    # JSON: Use local jq (version-independent)
    if command -v jq >/dev/null 2>&1; then
        if ! jq empty "$FILE" > /dev/null 2>&1; then
            echo "[Syntax:JSON] Invalid JSON - will fail at commit"
            jq empty "$FILE" 2>&1 | head -3
        fi
    fi
    ;;

  *.yaml|*.yml)
    # YAML: Quick validation with yq if available
    if command -v yq >/dev/null 2>&1; then
        if ! yq eval . "$FILE" > /dev/null 2>&1; then
            echo "[Syntax:YAML] Invalid YAML - will fail at commit"
            yq eval . "$FILE" 2>&1 | head -3
        fi
    fi
    ;;
esac
}

FINDINGS=$(run_checks)
if [[ -n "$FINDINGS" ]]; then
    # PostToolUse contract: exit 2 feeds stderr back to Claude as actionable
    # feedback - plain stdout with exit 0 never reaches the model
    echo "$FINDINGS" >&2
    exit 2
fi

exit 0
