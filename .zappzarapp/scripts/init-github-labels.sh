#!/bin/sh
# Initialize GitHub labels for /tasks command
#
# This script creates the standard labels used by the /tasks command.
# Run this once when setting up a new repository.
#
# Usage: ./.zappzarapp/scripts/init-github-labels.sh [--dry-run]
#
# Prerequisites:
#   - gh CLI installed and authenticated (gh auth login)
#   - Write access to the repository

set -e

DRY_RUN=false
if [ "$1" = "--dry-run" ]; then
    DRY_RUN=true
    echo "Dry run mode - no changes will be made"
    echo ""
fi

# Check if gh is available
if ! command -v gh >/dev/null 2>&1; then
    echo "Error: GitHub CLI (gh) is not installed"
    echo "Install it from: https://cli.github.com/"
    exit 1
fi

# Check if authenticated
if ! gh auth status >/dev/null 2>&1; then
    echo "Error: Not authenticated with GitHub CLI"
    echo "Run: gh auth login"
    exit 1
fi

# Get current repo
REPO=$(gh repo view --json nameWithOwner -q '.nameWithOwner' 2>/dev/null)
if [ -z "$REPO" ]; then
    echo "Error: Not in a GitHub repository"
    exit 1
fi

echo "Repository: $REPO"
echo ""

# Function to create or update a label
create_label() {
    name="$1"
    color="$2"
    description="$3"

    if [ "$DRY_RUN" = true ]; then
        echo "[dry-run] Would create: $name ($color) - $description"
        return
    fi

    # Check if label exists
    if gh label list --json name -q '.[].name' | grep -qx "$name"; then
        echo "Updating: $name"
        gh label edit "$name" --color "$color" --description "$description" 2>/dev/null || true
    else
        echo "Creating: $name"
        gh label create "$name" --color "$color" --description "$description" 2>/dev/null || true
    fi
}

echo "=== Type Labels ==="
# Note: bug, enhancement, documentation usually exist by default
create_label "chore" "fef2c0" "Maintenance, tooling, dependencies"
create_label "refactor" "d4c5f9" "Code refactoring"

echo ""
echo "=== Status Labels ==="
create_label "status:in-progress" "fbca04" "Currently being worked on"
create_label "status:blocked" "d93f0b" "Blocked by external dependency"
create_label "status:review" "0e8a16" "Ready for review"

echo ""
echo "=== Effort Labels (optional) ==="
create_label "effort:xs" "c2e0c6" "Extra small (<15 min)"
create_label "effort:s" "c2e0c6" "Small (15-30 min)"
create_label "effort:m" "fef2c0" "Medium (30 min - 2h)"
create_label "effort:l" "f9d0c4" "Large (2-4h)"
create_label "effort:xl" "d73a4a" "Extra large (>4h)"

echo ""
echo "=== Contributor Labels ==="
create_label "good first issue" "7057ff" "Good for newcomers"
create_label "help wanted" "008672" "Extra attention is needed"

echo ""
echo "Done!"

if [ "$DRY_RUN" = true ]; then
    echo ""
    echo "Run without --dry-run to apply changes."
fi
