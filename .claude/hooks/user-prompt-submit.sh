#!/usr/bin/env bash
# User Prompt Submit Hook
# Called by Claude Code on UserPromptSubmit event
#
# Plain stdout from this hook is added to Claude's context (exit 0).
#
# Check on every prompt:
# 1. Branch check - warns when an implementation-style prompt arrives while
#    the working copy sits on a protected branch (develop/main/master)

set -euo pipefail

PROJECT_DIR="${CLAUDE_PROJECT_DIR:-.}"

# Read input from stdin (JSON format)
INPUT=$(cat)
PROMPT=$(echo "$INPUT" | jq -r '.prompt // empty' 2>/dev/null || echo "")

# --- Branch check: implementation task on a protected branch ---
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

exit 0
