#!/usr/bin/env bash
# User Prompt Submit Hook - Session analysis
# Called by Claude Code on UserPromptSubmit event
#
# On new session:
# 1. Detects context continuation → shows previous session
# 2. Detects implementation tasks → reminds about workflow
# 3. Warns if on main branch during implementation task

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
# Check if prompt contains implementation keywords (language-agnostic tech terms)
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
if [[ "${IMPL_TASK_DETECTED:-false}" == "true" ]]; then
    CURRENT_BRANCH=$(cd "$PROJECT_DIR" && git branch --show-current 2>/dev/null || echo "")
    if [[ "$CURRENT_BRANCH" == "develop" || "$CURRENT_BRANCH" == "main" || "$CURRENT_BRANCH" == "master" ]]; then
        MESSAGES+=("[Branch Warning] On '$CURRENT_BRANCH' - create feature branch first: git checkout -b fix/<slug> or feature/<slug>")
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
