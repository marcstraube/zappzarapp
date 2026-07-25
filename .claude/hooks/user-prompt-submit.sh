#!/usr/bin/env bash
# User Prompt Submit Hook
# Called by Claude Code on UserPromptSubmit event
#
# Plain stdout from this hook is added to Claude's context (exit 0).
#
# Checks on every prompt:
# 1. Branch check - warns when an implementation-style prompt arrives while
#    the working copy sits on a protected branch (develop/main/master)
# 2. Change watch - reminds about doc/test updates for uncommitted changes
#    (deduplicated, so the same reminder is not repeated every prompt)

set -euo pipefail

PROJECT_DIR="${CLAUDE_PROJECT_DIR:-.}"
CACHE_DIR="${PROJECT_DIR}/.claude/cache"
mkdir -p "$CACHE_DIR"

# Read input from stdin (JSON format)
INPUT=$(cat)
PROMPT=$(echo "$INPUT" | jq -r '.prompt // empty' 2>/dev/null || echo "")

# --- Check 1: Implementation task on a protected branch ---
if [[ -n "$PROMPT" ]]; then
    ACTION_KEYWORDS="implement|add|create|build|refactor|migrate|upgrade|fix|update|change|modify|extend|integrate|write"
    ACTION_KEYWORDS+="|setup|configure|remove|delete|extract|optimize|convert|generate"

    if echo "$PROMPT" | grep -qiE "\b(${ACTION_KEYWORDS})\b"; then
        CURRENT_BRANCH=$(cd "$PROJECT_DIR" && git branch --show-current 2>/dev/null || echo "")
        if [[ "$CURRENT_BRANCH" == "develop" || "$CURRENT_BRANCH" == "main" || "$CURRENT_BRANCH" == "master" ]]; then
            cat << EOF
[Branch Check] You are on '$CURRENT_BRANCH'. For implementation work, create a
feature branch first: git checkout -b <type>/<slug>
(or a worktree: git worktree add ../<project>-wt-<slug> -b <type>/<slug> develop)
See .claude/CLAUDE.md (Feature-Branch Workflow). Trivial one-file changes may
stay on develop.
EOF
        fi
    fi
fi

# --- Check 2: Change watch (doc/test drift for uncommitted changes) ---
CHANGE_WATCH_SCRIPT="${PROJECT_DIR}/.claude/hooks/change-watch.sh"
CHANGE_WATCH_HASH_FILE="${CACHE_DIR}/change-watch.hash"
if [[ -x "$CHANGE_WATCH_SCRIPT" ]]; then
    if ! (cd "$PROJECT_DIR" && git diff --quiet HEAD 2>/dev/null); then
        CHANGE_WATCH_OUTPUT=$("$CHANGE_WATCH_SCRIPT" "$PROJECT_DIR" 2>/dev/null || true)
        if [[ -n "$CHANGE_WATCH_OUTPUT" ]]; then
            # Only emit when the finding changed since the last emit
            NEW_HASH=$(echo "$CHANGE_WATCH_OUTPUT" | sha256sum | cut -d' ' -f1)
            OLD_HASH=$(cat "$CHANGE_WATCH_HASH_FILE" 2>/dev/null || echo "")
            if [[ "$NEW_HASH" != "$OLD_HASH" ]]; then
                echo "$NEW_HASH" > "$CHANGE_WATCH_HASH_FILE"
                echo "$CHANGE_WATCH_OUTPUT"
            fi
        else
            rm -f "$CHANGE_WATCH_HASH_FILE"
        fi
    fi
fi

exit 0
