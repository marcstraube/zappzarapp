#!/usr/bin/env bash
# User Prompt Submit Hook - Session analysis
# Called by Claude Code on UserPromptSubmit event
#
# On new session:
# 1. Detects context continuation -> shows previous session
# 2. Detects implementation tasks -> reminds about workflow
# 3. Warns if on main branch during implementation task
# 4. Reminds if session file is still pending

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
        # Fixed: Use -path pattern for nested YYYY/MM structure
        RECENT_SESSIONS=$(find "${SESSION_BASE}" -path "*/[0-9][0-9][0-9][0-9]/[0-9][0-9]/session-*.md" \
            ! -name "*-pending.md" -type f -mmin -120 2>/dev/null | xargs ls -1t 2>/dev/null | head -3)

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
# Check if prompt contains implementation keywords (language-agnostic tech terms)
IMPL_TASK_DETECTED=false
if [[ -n "$PROMPT" ]]; then
    # Action keywords (verbs indicating implementation work)
    ACTION_KEYWORDS="implement|add|create|build|refactor|migrate|upgrade|fix|update|change|modify|extend|integrate|write"
    ACTION_KEYWORDS+="|setup|configure|remove|delete|extract|optimize|convert|generate"

    # Target keywords (nouns indicating code artifacts)
    # Architecture & Patterns
    TARGET_KEYWORDS="function|class|service|component|module|api|endpoint|feature|system|handler|controller"
    TARGET_KEYWORDS+="|repository|entity|dto|interface|enum|trait|provider|facade|decorator|adapter|factory"
    # Web & Frameworks
    TARGET_KEYWORDS+="|middleware|route|router|model|view|template|request|response|form|field|policy|resource"
    # Infrastructure
    TARGET_KEYWORDS+="|container|docker|pipeline|database|schema|migration|cache|queue|job|worker"
    # Testing
    TARGET_KEYWORDS+="|test|spec|mock|fixture|suite"
    # Documentation
    TARGET_KEYWORDS+="|documentation|docs|readme|changelog|guide|tutorial|manual|specification|reference"
    # General
    TARGET_KEYWORDS+="|authentication|validation|helper|util|config|hook|command|workflow|pattern|logic|method"
    TARGET_KEYWORDS+="|script|package|dependency|plugin|extension|linter|formatter"

    if echo "$PROMPT" | grep -qiE "\b(${ACTION_KEYWORDS})\b.+\b(${TARGET_KEYWORDS})\b"; then
        MESSAGES+=("[Implementation Task] Check .claude/agents/workflow.md for scope (Trivial/Small/Medium/Large) before starting.")
        IMPL_TASK_DETECTED=true
    fi
fi

# --- Detection 3: Wrong Branch for Implementation ---
# Warn if implementation task detected and on a protected branch
if [[ "$IMPL_TASK_DETECTED" == "true" ]]; then
    CURRENT_BRANCH=$(cd "$PROJECT_DIR" && git branch --show-current 2>/dev/null || echo "")
    if [[ "$CURRENT_BRANCH" == "develop" || "$CURRENT_BRANCH" == "main" || "$CURRENT_BRANCH" == "master" ]]; then
        MESSAGES+=("⚠️  STOP - BRANCH CHECK FAILED ⚠️

You are on '$CURRENT_BRANCH' but starting an implementation task.

ACTION REQUIRED BEFORE PROCEEDING:
1. Create feature branch: git checkout -b feature/<slug>
   (or: git checkout -b fix/<slug> for bug fixes)
2. Alternative: /worktree --create feature/<slug>

See: .claude/CLAUDE.md (Feature-Branch Workflow)
See: .claude/agents/workflow.md:54-85 (Detailed Workflow)")
    fi
fi

# --- Detection 4: Stale Pending Session ---
# Remind if any session file is still pending after 5 minutes
# Use nested path pattern for YYYY/MM structure
PENDING_SESSION=$(find "${SESSION_BASE}" -path "*/[0-9][0-9][0-9][0-9]/[0-9][0-9]/session-*-pending.md" -mmin +5 -type f 2>/dev/null | head -1)
if [[ -n "$PENDING_SESSION" ]]; then
    PENDING_NAME=$(basename "$PENDING_SESSION")
    MESSAGES+=("[Session Reminder] '$PENDING_NAME' still pending - update title and Changes table")
fi

# --- Detection 5: Change Watch ---
# Check if uncommitted changes might require doc/test updates
CHANGE_WATCH_SCRIPT="${PROJECT_DIR}/.claude/hooks/change-watch.sh"
if [[ -x "$CHANGE_WATCH_SCRIPT" ]]; then
    # Only run if there are uncommitted changes
    if cd "$PROJECT_DIR" && git diff --quiet HEAD 2>/dev/null; then
        : # No changes, skip
    else
        CHANGE_WATCH_OUTPUT=$("$CHANGE_WATCH_SCRIPT" "$PROJECT_DIR" 2>/dev/null || true)
        if [[ -n "$CHANGE_WATCH_OUTPUT" ]]; then
            MESSAGES+=("$CHANGE_WATCH_OUTPUT")
        fi
    fi
fi

# --- Output ---
if [[ ${#MESSAGES[@]} -gt 0 ]]; then
    # Join messages with newline, escape for valid JSON via jq
    JOINED=$(printf '%s\n' "${MESSAGES[@]}")
    jq -n --arg msg "$JOINED" '{"message": $msg}'
fi

exit 0
