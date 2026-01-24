#!/usr/bin/env bash
# Session End Hook - Reminds about session cleanup
# Called by Claude Code on SessionEnd event

set -euo pipefail

PROJECT_DIR="${CLAUDE_PROJECT_DIR:-.}"
SESSION_BASE="${PROJECT_DIR}/.claude/sessions"

# Find pending sessions
PENDING=$(find "${SESSION_BASE}" -name "session-*-pending.md" -type f 2>/dev/null | head -5)

# Build reminder message
REMINDERS=()

if [[ -n "${PENDING}" ]]; then
    REMINDERS+=("Pending session(s) not renamed - update with task slug")
fi

REMINDERS+=("Update session Summary if work was done")
REMINDERS+=("Write learnings to .ai/LEARNINGS.md")
REMINDERS+=("Write decisions to .ai/DECISIONS.md")

# Output as JSON
cat << EOF
{
  "message": "[SessionEnd] Session ending. Checklist:\\n- ${REMINDERS[0]}\\n- ${REMINDERS[1]}\\n- ${REMINDERS[2]}${REMINDERS[3]:+\\n- ${REMINDERS[3]}}"
}
EOF
