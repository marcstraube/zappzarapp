#!/bin/bash
# docker/hooks/change-detector.sh
# Change detection and branch detection for Git hooks
#
# Usage:
#   ./change-detector.sh has-changes <type>      → Exit 0 if file type changed
#   ./change-detector.sh on-branch <branch...>   → Exit 0 if on any of the branches
#   ./change-detector.sh list <type>             → List changed files of type
#
# Types:
#   php, node, sql, shell, docker, compose, markdown, config, composer, package
#
# Examples:
#   ./change-detector.sh has-changes php        → Check if PHP files changed
#   ./change-detector.sh on-branch develop master → Check if on develop or master
#   ./change-detector.sh list node              → List changed Node files

set -euo pipefail

# Show usage
usage() {
    cat <<EOF
Usage: $0 <command> [args...]

Commands:
  has-changes <type>      Check if file type changed (exit 0 if yes)
  on-branch <branch...>   Check if on any of the branches (exit 0 if yes)
  list <type>             List changed files of type (one per line)

Types:
  php         → *.php
  node        → *.ts, *.js, *.cjs
  sql         → *.sql
  shell       → *.sh
  docker      → Dockerfile*, *.dockerignore
  compose     → docker-compose*.yml, docker-compose*.yaml
  markdown    → *.md
  config      → *.yml, *.yaml, *.neon
  composer    → composer.json, composer.lock
  package     → package.json, pnpm-lock.yaml

Change Detection Base (fallback chain):
  1. HEAD@{push}...HEAD              (since last push to remote)
  2. origin/\$(branch)...HEAD         (against remote branch)
  3. origin/develop...HEAD           (against develop)
  4. HEAD                            (staged + unstaged)

EOF
    exit 1
}

# Get list of changed files using fallback chain
get_changed_files() {
    local files=""

    # Try: since last push to remote
    files=$(git diff --name-only 'HEAD@{push}'...HEAD 2>/dev/null || true)

    # Fallback: against remote branch
    if [[ -z "$files" ]]; then
        local branch
        branch=$(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo "develop")
        files=$(git diff --name-only "origin/${branch}...HEAD" 2>/dev/null || true)
    fi

    # Fallback: against develop
    if [[ -z "$files" ]]; then
        files=$(git diff --name-only origin/develop...HEAD 2>/dev/null || true)
    fi

    # Last fallback: staged + unstaged
    if [[ -z "$files" ]]; then
        files=$(git diff --name-only HEAD 2>/dev/null || true)
    fi

    echo "$files"
}

# Get file pattern for type
get_pattern() {
    local type="$1"

    case "$type" in
        php)
            echo '\.php$'
            ;;
        node)
            echo '\.(ts|js|cjs)$'
            ;;
        sql)
            echo '\.sql$'
            ;;
        shell)
            echo '\.sh$'
            ;;
        docker)
            echo '(^|/)Dockerfile|\.dockerignore$'
            ;;
        compose)
            echo 'docker-compose.*\.(yml|yaml)$'
            ;;
        markdown)
            echo '\.md$'
            ;;
        config)
            echo '\.(yml|yaml|neon)$'
            ;;
        composer)
            echo '^composer\.(json|lock)$'
            ;;
        package)
            echo '^(package\.json|pnpm-lock\.yaml)$'
            ;;
        *)
            echo "Unknown type: $type" >&2
            exit 1
            ;;
    esac
}

# Command: has-changes <type>
cmd_has_changes() {
    [[ $# -lt 1 ]] && usage

    local type="$1"
    local pattern
    pattern=$(get_pattern "$type")

    local changed_files
    changed_files=$(get_changed_files)

    # Check if any file matches pattern
    if echo "$changed_files" | grep -qE "$pattern"; then
        exit 0
    else
        exit 1
    fi
}

# Command: list <type>
cmd_list() {
    [[ $# -lt 1 ]] && usage

    local type="$1"
    local pattern
    pattern=$(get_pattern "$type")

    local changed_files
    changed_files=$(get_changed_files)

    # Filter files by pattern
    echo "$changed_files" | grep -E "$pattern" || true
}

# Command: on-branch <branch...>
cmd_on_branch() {
    [[ $# -lt 1 ]] && usage

    local current_branch
    current_branch=$(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo "")

    # Check if current branch matches any of the provided branches
    for branch in "$@"; do
        if [[ "$current_branch" == "$branch" ]]; then
            exit 0
        fi
    done

    exit 1
}

# Main
[[ $# -lt 1 ]] && usage

COMMAND="$1"
shift

case "$COMMAND" in
    has-changes)
        cmd_has_changes "$@"
        ;;
    list)
        cmd_list "$@"
        ;;
    on-branch)
        cmd_on_branch "$@"
        ;;
    *)
        echo "Unknown command: $COMMAND" >&2
        usage
        ;;
esac
