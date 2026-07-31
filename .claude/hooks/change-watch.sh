#!/usr/bin/env bash
# Change Watch - Detect when changes might require doc/test updates
# Called by user-prompt-submit.sh when uncommitted changes exist
#
# Checks two categories:
# 1. Documentation - code changes that might need doc updates
# 2. Tests - infrastructure changes that might need GOSS/BATS updates
#
# Usage: change-watch.sh [project_dir]
# Output: Markdown-formatted list of affected files

set -euo pipefail

PROJECT_DIR="${1:-.}"

# Get list of changed files (staged + unstaged)
CHANGED_FILES=$(cd "$PROJECT_DIR" && git diff --name-only HEAD 2>/dev/null || git diff --name-only 2>/dev/null || echo "")

if [[ -z "$CHANGED_FILES" ]]; then
    exit 0
fi

# ============================================================================
# CONFIGURATION
# ============================================================================

# Format: file_pattern|regex_pattern|search_template|use_filename
# Note: regex uses lookbehind/lookahead since grep -oP returns matches only
# use_filename: "false" = extract via regex, "true" = use the file name,
#               "dirname" = use the parent directory name
# search_template: {1} is replaced with the extracted value

# --- Documentation Extractors ---
DOC_EXTRACTORS=(
    'Makefile|(?<=^\+)[a-z][-a-z0-9_]+(?=:\s)|make {1}|false'
    'compose.yaml|(?<=^\+  )[a-z][-a-z0-9_]+(?=:$)|{1}|false'
    '.claude/skills/*/SKILL.md||/{1}|dirname'
    '.env.example|(?<=^\+)[A-Z][A-Z0-9_]+(?==)|{1}|false'
)

DOC_SEARCH_PATHS=(
    ".zappzarapp/docs/"
    ".claude/CLAUDE.md"
    ".zappzarapp/ai/templates/CLAUDE.md"
    "AGENTS.md"
    "README.md"
)

DOC_EXTENSIONS="*.md"

# --- Test Extractors ---
# Dockerfile/compose changes → GOSS tests
# Makefile changes → BATS tests
TEST_EXTRACTORS=(
    # Dockerfile changes - search for container name in GOSS
    'docker/*/Dockerfile||{1}|dirname'
    # Compose service changes - search for service name in GOSS/BATS
    'compose.yaml|(?<=^\+  )[a-z][-a-z0-9_]+(?=:$)|{1}|false'
    # Makefile target changes - search in BATS tests
    'Makefile|(?<=^\+)[a-z][-a-z0-9_]+(?=:\s)|make {1}|false'
)

TEST_SEARCH_PATHS=(
    "tests/goss/"
    "tests/bats/"
)

TEST_EXTENSIONS="*.yaml *.bats"

# ============================================================================
# FUNCTIONS
# ============================================================================

# Check if file matches a glob pattern
matches_pattern() {
    local file="$1"
    local pattern="$2"

    # Handle glob patterns with fnmatch-style matching
    case "$file" in
        $pattern) return 0 ;;
    esac

    # Also try with regex for ** patterns
    local regex_pattern
    regex_pattern=$(echo "$pattern" | sed 's/\./\\./g; s/\*\*/DOUBLESTAR/g; s/\*/[^\/]*/g; s/DOUBLESTAR/.*/g')
    [[ "$file" =~ ^${regex_pattern}$ ]] && return 0

    return 1
}

# Extract patterns from a changed file
# Args: file, regex_pattern, search_template, use_filename, results_array_name
extract_patterns() {
    local file="$1"
    local regex_pattern="$2"
    local search_template="$3"
    local use_filename="$4"
    local -n results_ref="$5"

    local patterns=()

    if [[ "$use_filename" == "true" ]]; then
        # Use the filename itself (without extension)
        local name
        name=$(basename "$file" .md)
        patterns+=("$name")
    elif [[ "$use_filename" == "dirname" ]]; then
        # Use the parent directory name (e.g., docker/php/Dockerfile → php)
        local dir
        dir=$(dirname "$file")
        dir=$(basename "$dir")
        patterns+=("$dir")
    elif [[ -n "$regex_pattern" ]]; then
        # Extract from git diff using the pattern
        while IFS= read -r match; do
            [[ -n "$match" ]] && patterns+=("$match")
        done < <(cd "$PROJECT_DIR" && git diff "$file" 2>/dev/null | grep -oP "$regex_pattern" | sort -u || true)
    fi

    # Build search terms and store
    for pattern in "${patterns[@]}"; do
        local search_term
        search_term="${search_template//\{1\}/$pattern}"

        # Store pattern for this file
        if [[ -n "${results_ref[$file]:-}" ]]; then
            results_ref[$file]+=$'\n'"$search_term"
        else
            results_ref[$file]="$search_term"
        fi
    done
}

# Search for pattern in specified paths
# Args: search_term, search_paths_array, extensions
search_paths() {
    local search_term="$1"
    local -n paths_ref="$2"
    local extensions="$3"
    local results=""

    for search_path in "${paths_ref[@]}"; do
        local full_path="$PROJECT_DIR/$search_path"

        if [[ -d "$full_path" ]]; then
            # Build include flags for extensions
            local include_flags=""
            for ext in $extensions; do
                include_flags+="--include=$ext "
            done

            # Search in directory
            local found
            found=$(grep -rn $include_flags -F "$search_term" "$full_path" 2>/dev/null || true)
            [[ -n "$found" ]] && results+="$found"$'\n'
        elif [[ -f "$full_path" ]]; then
            # Search in single file
            local found
            found=$(grep -n -F "$search_term" "$full_path" 2>/dev/null || true)
            if [[ -n "$found" ]]; then
                while IFS= read -r line; do
                    results+="$full_path:$line"$'\n'
                done <<< "$found"
            fi
        fi
    done

    echo "$results"
}

# Process a category (docs or tests)
# Args: category_name, extractors_array, search_paths_array, extensions
process_category() {
    local category_name="$1"
    local -n extractors_ref="$2"
    local -n search_paths_ref="$3"
    local extensions="$4"

    declare -A file_patterns
    declare -A undocumented

    # Process each extractor
    for extractor in "${extractors_ref[@]}"; do
        IFS='|' read -r file_pattern regex_pattern search_template use_filename <<< "$extractor"

        # Check each changed file against this extractor
        while IFS= read -r changed_file; do
            [[ -z "$changed_file" ]] && continue

            if matches_pattern "$changed_file" "$file_pattern"; then
                extract_patterns "$changed_file" "$regex_pattern" "$search_template" "$use_filename" file_patterns
            fi
        done <<< "$CHANGED_FILES"
    done

    # If no patterns extracted, return
    if [[ -z "${file_patterns[*]+x}" ]] || [[ ${#file_patterns[@]} -eq 0 ]]; then
        return
    fi

    # Search for each pattern
    local has_output=false
    local output=""

    for file in "${!file_patterns[@]}"; do
        local file_output=""
        local patterns="${file_patterns[$file]}"

        while IFS= read -r pattern; do
            [[ -z "$pattern" ]] && continue

            local refs
            refs=$(search_paths "$pattern" search_paths_ref "$extensions")

            if [[ -n "$refs" ]]; then
                # Format references
                local formatted_refs=""
                while IFS= read -r ref; do
                    [[ -z "$ref" ]] && continue
                    local ref_file ref_line
                    ref_file=$(echo "$ref" | cut -d: -f1)
                    ref_line=$(echo "$ref" | cut -d: -f2)
                    ref_file="${ref_file#$PROJECT_DIR/}"
                    formatted_refs+="    → $ref_file:$ref_line"$'\n'
                done <<< "$refs"

                file_output+="  \`$pattern\`"$'\n'"$formatted_refs"
            else
                undocumented[$pattern]=1
            fi
        done <<< "$patterns"

        if [[ -n "$file_output" ]]; then
            output+="**$file:**"$'\n'"$file_output"$'\n'
            has_output=true
        fi
    done

    # Output results for this category
    if [[ "$has_output" == "true" ]]; then
        echo "[$category_name] Möglicherweise Updates erforderlich:"
        echo ""
        echo "$output"
    fi

    # Report undocumented patterns
    if [[ -n "${undocumented[*]+x}" ]] && [[ ${#undocumented[@]} -gt 0 ]]; then
        echo "[$category_name] Neue Patterns (noch nicht abgedeckt):"
        for pattern in "${!undocumented[@]}"; do
            echo "  • \`$pattern\`"
        done
        echo ""
    fi
}

# ============================================================================
# MAIN
# ============================================================================

main() {
    local output=""

    # Check for documentation updates
    doc_output=$(process_category "Docs" DOC_EXTRACTORS DOC_SEARCH_PATHS "$DOC_EXTENSIONS")
    [[ -n "$doc_output" ]] && output+="$doc_output"$'\n'

    # Check for test updates
    test_output=$(process_category "Tests" TEST_EXTRACTORS TEST_SEARCH_PATHS "$TEST_EXTENSIONS")
    [[ -n "$test_output" ]] && output+="$test_output"

    # Print combined output
    if [[ -n "$output" ]]; then
        echo "$output"
    fi
}

main "$@"
