#!/bin/bash
# PostToolUse Hook: Auto-fix formatting after Edit/Write
# Runs after post-edit-lint.sh to automatically apply code style fixes
#
# Philosophy:
#   - Auto-fix ONLY formatting (safe, no logic changes)
#   - Use single-file ARGS for speed (<1s when containers running)
#   - Keep output minimal (brief confirmation)
#   - Skip if containers not running (non-blocking)
#
# Auto-Fixed:
#   - PHP: PHP-CS-Fixer (formatting only)
#   - Node/TS: Prettier (formatting only)
#
# Check-Only (manual fixes):
#   - ESLint (can change semantics)
#   - PHPStan (false positives possible)
#
# Usage: Called by Claude Code PostToolUse hook
# Input: Tool input JSON via stdin (contains file_path)
# Output: Brief confirmation or nothing if skipped

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"

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
  */.claude/sessions/*|*/.claude/temp/*|*/.claude/state/*) exit 0 ;;
esac

# Helper: Check if service container is running
is_running() {
    docker compose ps -q --status running "$1" 2>/dev/null | grep -q .
}

# Convert absolute path to relative path from project root
to_relative_path() {
    local abs_path="$1"
    echo "${abs_path#$PROJECT_ROOT/}"
}

# Get relative path for make commands
REL_FILE=$(to_relative_path "$FILE")

# Auto-fix formatting based on file type
case "$FILE" in
  *.php)
    # PHP: Auto-fix code style (PHP-CS-Fixer)
    if is_running php; then
      if cd "$PROJECT_ROOT" && make cs-fix ARGS="$REL_FILE" >/dev/null 2>&1; then
        echo "[AutoFix:PHP] Code style fixed: $REL_FILE"
      fi
    fi
    ;;

  *.ts|*.tsx|*.js|*.jsx)
    # Node/TS: Auto-fix formatting (Prettier only, not ESLint)
    if is_running dev-tools; then
      if cd "$PROJECT_ROOT" && make prettier-fix ARGS="$REL_FILE" >/dev/null 2>&1; then
        echo "[AutoFix:Node] Prettier formatting applied: $REL_FILE"
      fi
    fi
    ;;
esac

exit 0
