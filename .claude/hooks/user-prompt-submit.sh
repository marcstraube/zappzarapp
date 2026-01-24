#!/usr/bin/env bash
# User Prompt Submit Hook - Session analysis
# Called by Claude Code on UserPromptSubmit event
#
# On new session:
# 1. Detects context continuation → shows previous session
# 2. Detects implementation tasks → reminds about workflow

set -euo pipefail

PROJECT_DIR="${CLAUDE_PROJECT_DIR:-.}"
SESSION_BASE="${PROJECT_DIR}/.claude/sessions"
TEMP_DIR="${PROJECT_DIR}/.claude/temp"
LAST_SESSION_FILE="${TEMP_DIR}/last-session-id"

# Ensure temp directory exists
mkdir -p "$TEMP_DIR"

# Read input from stdin (JSON format)
INPUT=$(cat)

# Extract fields from JSON
SESSION_ID=$(echo "$INPUT" | jq -r '.session_id // empty' 2>/dev/null || echo "")
TRANSCRIPT_PATH=$(echo "$INPUT" | jq -r '.transcript_path // empty' 2>/dev/null || echo "")
PROMPT=$(echo "$INPUT" | jq -r '.prompt // empty' 2>/dev/null || echo "")

# Exit early if no session_id (unexpected format)
if [[ -z "$SESSION_ID" ]]; then
    exit 0
fi

# Check if this is a new session
LAST_SESSION_ID=""
if [[ -f "$LAST_SESSION_FILE" ]]; then
    LAST_SESSION_ID=$(cat "$LAST_SESSION_FILE")
fi

# If same session, nothing to do
if [[ "$SESSION_ID" == "$LAST_SESSION_ID" ]]; then
    exit 0
fi

# New session detected - save session_id
echo "$SESSION_ID" > "$LAST_SESSION_FILE"

MESSAGES=()

# --- Detection 1: Context Continuation ---
if [[ -n "$TRANSCRIPT_PATH" && -f "$TRANSCRIPT_PATH" ]]; then
    if head -100 "$TRANSCRIPT_PATH" | grep -qiE "(continued from a previous conversation|context.*(overflow|compress|compact)|ran out of context)"; then
        RECENT_SESSIONS=$(find "${SESSION_BASE}" -name "session-*.md" ! -name "*-pending.md" -type f -mmin -120 2>/dev/null | xargs ls -1t 2>/dev/null | head -3)

        if [[ -n "$RECENT_SESSIONS" ]]; then
            LATEST=$(echo "$RECENT_SESSIONS" | head -1)
            LATEST_NAME=$(basename "$LATEST")
            MESSAGES+=("[Context Continuation] Previous session: $LATEST_NAME")
        else
            MESSAGES+=("[Context Continuation] No recent session files found.")
        fi
    fi
fi

# --- Detection 2: Implementation Task ---
# Check if prompt contains implementation keywords (English only)
if [[ -n "$PROMPT" ]]; then
    # Action keywords + target keywords (flexible matching)
    if echo "$PROMPT" | grep -qiE "\b(implement|add|create|build|refactor|migrate|upgrade|fix|update|change|modify|extend|integrate|write)\b.+\b(function|class|service|component|module|api|endpoint|feature|system|handler|controller|test|authentication|validation|middleware|route|model|view|helper|util|config|hook|command|workflow|pattern|logic|method)\b"; then
        MESSAGES+=("[Implementation Task] Check .claude/agents/workflow.md for scope (Trivial/Small/Medium/Large) before starting.")
    fi
fi

# --- Output ---
if [[ ${#MESSAGES[@]} -gt 0 ]]; then
    # Join messages with newline
    JOINED=$(printf '%s\\n' "${MESSAGES[@]}")
    cat << EOF
{
  "message": "${JOINED%\\n}"
}
EOF
fi

exit 0
