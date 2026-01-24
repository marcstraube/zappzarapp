#!/bin/bash
# PostToolUse Hook: Auto-lint after Edit
# Runs the appropriate linter based on file extension
#
# Usage: Called by Claude Code PostToolUse hook
# Input: Tool input JSON via stdin (contains file_path)
# Output: Lint errors (filtered) or nothing if clean

set -euo pipefail

# Read tool input from stdin
TOOL_INPUT=$(cat)

# Extract file_path from JSON
FILE=$(echo "$TOOL_INPUT" | jq -r '.file_path // empty' 2>/dev/null || echo "")

# Exit if no file path
[[ -z "$FILE" ]] && exit 0

# Skip non-code files
case "$FILE" in
  *.lock|*.sum|*.map|*.min.*) exit 0 ;;
  */node_modules/*|*/vendor/*) exit 0 ;;
esac

# Check if containers are running (required for make targets)
if ! docker compose ps --format "{{.Status}}" 2>/dev/null | grep -q "running"; then
  # Silent exit - don't block workflow if containers aren't up
  exit 0
fi

# Lint based on file type
# Uses timeout to prevent blocking, filters output to show only errors
case "$FILE" in
  *.php)
    OUTPUT=$(timeout 30 make cs-check 2>&1 || true)
    if echo "$OUTPUT" | grep -qE "(ERROR|FOUND [0-9]+ error)"; then
      echo "[Lint:PHP] Errors found. Run: make cs-fix"
      echo "$OUTPUT" | grep -E "^\s+[0-9]+\s+\|" | head -5
    fi
    ;;

  *.ts|*.tsx|*.js|*.jsx|*.mjs|*.cjs)
    OUTPUT=$(timeout 30 make lint-node 2>&1 || true)
    if echo "$OUTPUT" | grep -qE "error|warning"; then
      echo "[Lint:Node] Issues found. Run: make lint-node-fix"
      echo "$OUTPUT" | grep -E "^\s+[0-9]+:[0-9]+" | head -10
    fi
    ;;

  *.sql)
    OUTPUT=$(timeout 15 make lint-sql 2>&1 || true)
    if echo "$OUTPUT" | grep -qE "L[0-9]+.*violation"; then
      echo "[Lint:SQL] Issues found. Run: make lint-sql-fix"
      echo "$OUTPUT" | grep -E "L[0-9]+" | head -5
    fi
    ;;

  *.sh|*.bash)
    OUTPUT=$(timeout 15 make lint-shell 2>&1 || true)
    if echo "$OUTPUT" | grep -qE "SC[0-9]+"; then
      echo "[Lint:Shell] ShellCheck warnings:"
      echo "$OUTPUT" | grep -E "^In|SC[0-9]+" | head -10
    fi
    ;;

  *.md)
    OUTPUT=$(timeout 15 make lint-md 2>&1 || true)
    if echo "$OUTPUT" | grep -qE "MD[0-9]+"; then
      echo "[Lint:Markdown] Issues found. Run: make lint-md-fix"
      echo "$OUTPUT" | grep -E "MD[0-9]+" | head -5
    fi
    ;;

  *Dockerfile*)
    OUTPUT=$(timeout 15 make lint-docker 2>&1 || true)
    if echo "$OUTPUT" | grep -qE "(DL|SC)[0-9]+"; then
      echo "[Lint:Docker] Hadolint issues:"
      echo "$OUTPUT" | grep -E "(DL|SC)[0-9]+" | head -5
    fi
    ;;

  compose*.yaml)
    OUTPUT=$(timeout 10 make lint-config 2>&1 || true)
    if [[ $? -ne 0 ]]; then
      echo "[Lint:Compose] Validation errors found"
      echo "$OUTPUT" | head -5
    fi
    ;;
esac

exit 0
