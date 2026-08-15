#!/usr/bin/env bash
# Enforce the BATS skip budget: every "# skip" reason in the given suite logs
# must match a pattern from allowed-skips.txt. A skipped test still reports
# "ok", so unexpected skips silently erode coverage - treat them as failures.
#
# Usage: check-skips.sh <logfile> [<logfile> ...]
# Escape hatch: SKIP_BUDGET=off skips the check entirely.

set -euo pipefail

if [[ "${SKIP_BUDGET:-on}" == "off" ]]; then
    echo "Skip budget check disabled (SKIP_BUDGET=off)"
    exit 0
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ALLOWLIST="${SCRIPT_DIR}/allowed-skips.txt"

if [[ ! -f "$ALLOWLIST" ]]; then
    echo "Error: allowlist not found: $ALLOWLIST" >&2
    exit 1
fi

# Strip comments and blank lines from the allowlist
patterns="$(grep -vE '^\s*(#|$)' "$ALLOWLIST")"

unexpected=0
for log in "$@"; do
    if [[ ! -f "$log" ]]; then
        echo "Error: log file not found: $log" >&2
        exit 1
    fi
    # Extract the skip reason behind "# skip" on TAP result lines; a bare
    # "# skip" without a reason yields an empty string and never matches
    # the anchored allowlist patterns, so it fails as unexpected.
    while IFS= read -r reason; do
        if ! grep -qE -f <(printf '%s\n' "$patterns") <<<"$reason"; then
            echo "Unexpected skip: '${reason}' (${log})" >&2
            unexpected=$((unexpected + 1))
        fi
    done < <(grep -oP '^(not )?ok .*# skip\s*\K.*' "$log" | sed 's/\s*$//')
done

if [[ $unexpected -gt 0 ]]; then
    echo "" >&2
    echo "Skip budget violated: ${unexpected} unexpected skip(s)." >&2
    echo "A skip that reports a broken environment hides missing coverage -" >&2
    echo "fix the environment or, if the reason is legitimately" >&2
    echo "configuration-dependent, add it to tests/bats/allowed-skips.txt." >&2
    exit 1
fi

echo "Skip budget OK"
