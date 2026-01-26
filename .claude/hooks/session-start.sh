#!/usr/bin/env bash
# Session Start Hook - Creates session file with auto-slug
# Called by Claude Code on SessionStart event
#
# Creates: .claude/sessions/YYYY/MM/session-YYYY-MM-DD-HHMM-<slug>.md
# Auto-derives slug from branch name or changed files

set -euo pipefail

# Detect project directory with fallbacks:
# 1. CLAUDE_PROJECT_DIR env var (set by Claude Code)
# 2. Git repository root (most reliable)
# 3. Current directory (last resort)
if [[ -n "${CLAUDE_PROJECT_DIR:-}" ]]; then
    PROJECT_DIR="${CLAUDE_PROJECT_DIR}"
elif PROJECT_DIR=$(git rev-parse --show-toplevel 2>/dev/null); then
    : # Git root found
else
    PROJECT_DIR="."
fi
SESSION_TEMPLATE_PATH="${PROJECT_DIR}/.zappzarapp/ai/templates/SESSION-TEMPLATE.md"
SESSION_BASE="${PROJECT_DIR}/.claude/sessions"

# Generate timestamp components
YEAR=$(date +%Y)
MONTH=$(date +%m)
TIMESTAMP=$(date +%Y-%m-%d-%H%M)

# Session directory
SESSION_DIR="${SESSION_BASE}/${YEAR}/${MONTH}"

# Auto-derive slug from branch name
derive_slug() {
    local branch slug

    branch=$(cd "$PROJECT_DIR" && git branch --show-current 2>/dev/null || echo "")
    slug="pending"

    if [[ -n "$branch" ]]; then
        # feature/foo-bar -> foo-bar, fix/baz -> baz
        slug=$(echo "$branch" | sed -E 's#^(feature|feat|fix|refactor|chore|docs|hotfix)/##' | tr '/' '-' | tr '[:upper:]' '[:lower:]')
    fi

    # Fallback if on develop/main/master or slug is empty
    if [[ "$slug" =~ ^(develop|main|master|pending)$ || -z "$slug" ]]; then
        # Try to derive from recent changes
        local changed_files
        changed_files=$(cd "$PROJECT_DIR" && git diff --name-only HEAD 2>/dev/null | head -1)
        if [[ -n "$changed_files" ]]; then
            # Extract meaningful directory or file name
            local dir_name
            dir_name=$(dirname "$changed_files" | sed 's#.*/##' | tr '[:upper:]' '[:lower:]')
            if [[ -n "$dir_name" && "$dir_name" != "." ]]; then
                slug="$dir_name"
            fi
        fi
    fi

    # Final fallback
    [[ -z "$slug" || "$slug" == "." ]] && slug="pending"

    echo "$slug"
}

SLUG=$(derive_slug)
SESSION_FILE="${SESSION_DIR}/session-${TIMESTAMP}-${SLUG}.md"

# Check if session already exists for this timestamp (avoid duplicates within same minute)
if ls "${SESSION_DIR}/session-${TIMESTAMP}-"*.md 2>/dev/null | head -1 | grep -q .; then
    # Session for this timestamp already exists, skip creation
    exit 0
fi

# Create session directory if needed
mkdir -p "${SESSION_DIR}"

# Get current branch
BRANCH=$(cd "${PROJECT_DIR}" && git branch --show-current 2>/dev/null || echo "unknown")

# Check for template
if [[ ! -f "${SESSION_TEMPLATE_PATH}" ]]; then
    # Fallback: create minimal session file
    cat > "${SESSION_FILE}" << EOF
# Session ${TIMESTAMP}-${SLUG}: [Title]

## Goal

[Update after understanding task]

## Branch

${BRANCH}

## Changes

| Time  | Action | File | Purpose |
| ----- | ------ | ---- | ------- |

## Summary

[Session in progress]
EOF
else
    # Use template and substitute placeholders
    sed -e "s/YYYY-MM-DD-HHMM-<task-slug>/${TIMESTAMP}-${SLUG}/" \
        -e "s/\[Title\]/[Title]/" \
        -e "s/\[branch-name\]/${BRANCH}/" \
        -e "s/\[One-line description\]/[Update after understanding task]/" \
        "${SESSION_TEMPLATE_PATH}" > "${SESSION_FILE}"
fi

# Output for Claude context (JSON format for hooks)
cat << EOF
{
  "message": "[SessionStart] Created session: ${SESSION_FILE##*/}",
  "session_file": "${SESSION_FILE}",
  "timestamp": "${TIMESTAMP}",
  "slug": "${SLUG}"
}
EOF
