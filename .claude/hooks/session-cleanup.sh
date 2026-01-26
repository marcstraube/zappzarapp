#!/usr/bin/env bash
# Session Cleanup Hook - Removes stale pending sessions
# Can be run manually or via Make target
#
# Removes sessions that are:
# - Named *-pending.md
# - Older than 24 hours
# - Contain <= 34 lines (template size, no real content)

set -euo pipefail

PROJECT_DIR="${CLAUDE_PROJECT_DIR:-.}"
SESSION_BASE="${PROJECT_DIR}/.claude/sessions"

# Parse arguments
DRY_RUN=false
VERBOSE=false
MAX_AGE_HOURS=24
MAX_LINES=34

while [[ $# -gt 0 ]]; do
    case "$1" in
        --dry-run|-n)
            DRY_RUN=true
            shift
            ;;
        --verbose|-v)
            VERBOSE=true
            shift
            ;;
        --max-age)
            MAX_AGE_HOURS="$2"
            shift 2
            ;;
        --max-lines)
            MAX_LINES="$2"
            shift 2
            ;;
        *)
            echo "Unknown option: $1"
            echo "Usage: session-cleanup.sh [--dry-run] [--verbose] [--max-age HOURS] [--max-lines N]"
            exit 1
            ;;
    esac
done

# Calculate max age in minutes
MAX_AGE_MINS=$((MAX_AGE_HOURS * 60))

# Find stale pending sessions
cleanup_count=0
kept_count=0

$VERBOSE && echo "Scanning for stale pending sessions..."
$VERBOSE && echo "  Max age: ${MAX_AGE_HOURS}h, Max lines: ${MAX_LINES}"
$VERBOSE && echo ""

while IFS= read -r session_file; do
    [[ -z "$session_file" ]] && continue

    # Get line count
    lines=$(wc -l < "$session_file" 2>/dev/null || echo "9999")

    # Check if it's small enough to be considered empty/unused
    if [[ "$lines" -le "$MAX_LINES" ]]; then
        if $DRY_RUN; then
            echo "Would remove: ${session_file##*/} (${lines} lines)"
        else
            $VERBOSE && echo "Removing: ${session_file##*/} (${lines} lines)"
            rm "$session_file"
        fi
        ((cleanup_count++))
    else
        $VERBOSE && echo "Keeping: ${session_file##*/} (${lines} lines - has content)"
        ((kept_count++))
    fi
done < <(find "${SESSION_BASE}" -name "*-pending.md" -type f -mmin "+${MAX_AGE_MINS}" 2>/dev/null)

# Output summary
if $DRY_RUN; then
    echo ""
    echo "Dry run complete: would remove ${cleanup_count} file(s), keep ${kept_count}"
else
    if [[ $cleanup_count -gt 0 ]]; then
        echo "Cleaned up ${cleanup_count} stale pending session(s)"
    else
        echo "No stale pending sessions found"
    fi
fi

# Clean up empty year/month directories
if ! $DRY_RUN; then
    find "${SESSION_BASE}" -type d -empty -delete 2>/dev/null || true
fi
