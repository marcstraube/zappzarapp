#!/usr/bin/env bash
# Session Start Hook - Creates placeholder session file
# Called by Claude Code on SessionStart event
#
# Creates: .claude/sessions/YYYY/MM/session-YYYY-MM-DD-HHMM-pending.md
# Claude should rename to proper slug after understanding the task

set -euo pipefail

# Use project directory from env or current directory
PROJECT_DIR="${CLAUDE_PROJECT_DIR:-.}"
SESSION_TEMPLATE_PATH="${PROJECT_DIR}/.zappzarapp/ai/templates/SESSION-TEMPLATE.md"
SESSION_BASE="${PROJECT_DIR}/.claude/sessions"

# Generate timestamp components
YEAR=$(date +%Y)
MONTH=$(date +%m)
TIMESTAMP=$(date +%Y-%m-%d-%H%M)

# Session directory and file paths
SESSION_DIR="${SESSION_BASE}/${YEAR}/${MONTH}"
SESSION_FILE="${SESSION_DIR}/session-${TIMESTAMP}-pending.md"

# Check if pending session already exists (avoid duplicates within same minute)
if ls "${SESSION_DIR}/session-${TIMESTAMP}-"*.md 2>/dev/null | head -1 | grep -q .; then
    # Session for this timestamp already exists, skip creation
    exit 0
fi

# Create session directory if needed
mkdir -p "${SESSION_DIR}"

# Check for template
if [[ ! -f "${SESSION_TEMPLATE_PATH}" ]]; then
    # Fallback: create minimal session file
    cat > "${SESSION_FILE}" << EOF
# Session ${TIMESTAMP}-pending: [Title]

## Goal

[Pending - update after understanding task]

## Branch

$(cd "${PROJECT_DIR}" && git branch --show-current 2>/dev/null || echo "unknown")

## Changes

| Time  | Action | File | Purpose |
| ----- | ------ | ---- | ------- |

## Summary

[Session in progress]
EOF
else
    # Use template and substitute placeholders
    BRANCH=$(cd "${PROJECT_DIR}" && git branch --show-current 2>/dev/null || echo "unknown")
    sed -e "s/YYYY-MM-DD-HHMM-<task-slug>/${TIMESTAMP}-pending/" \
        -e "s/\[Title\]/[Pending]/" \
        -e "s/\[branch-name\]/${BRANCH}/" \
        -e "s/\[One-line description\]/[Update after understanding task]/" \
        "${SESSION_TEMPLATE_PATH}" > "${SESSION_FILE}"
fi

# Output for Claude context (JSON format for hooks)
cat << EOF
{
  "message": "[SessionStart] Created placeholder session: ${SESSION_FILE}\n\nAfter understanding the task, rename with: mv '${SESSION_FILE}' '${SESSION_DIR}/session-${TIMESTAMP}-<slug>.md'",
  "session_file": "${SESSION_FILE}",
  "timestamp": "${TIMESTAMP}"
}
EOF
