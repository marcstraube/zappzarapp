#!/bin/bash
# docker/hooks/install-worktree-guard.sh
# Injects a vendor/ guard into CaptainHook-generated Git hooks.
#
# Fresh git worktrees share .git/hooks with the main checkout but have no
# vendor/ until composer install runs. The generated hooks call
# vendor/bin/captainhook unconditionally and hard-fail there - even
# `git commit --no-verify` dies, because prepare-commit-msg is not skipped
# by --no-verify. The guard makes hooks exit 0 with a notice instead.
#
# Idempotent: hooks that already contain the guard are left untouched.
# Called by: make hooks-install (after `captainhook install`)

set -euo pipefail

HOOKS_DIR="${1:-.git/hooks}"
GUARD_MARKER="captainhook missing (no vendor/"

for hook in commit-msg post-checkout post-commit post-merge post-rewrite \
    pre-commit pre-push prepare-commit-msg; do
    file="${HOOKS_DIR}/${hook}"
    [ -f "$file" ] || continue
    if grep -qF "$GUARD_MARKER" "$file"; then
        continue
    fi
    awk -v hook="$hook" '
        !injected && $0 ~ /^vendor\/bin\/captainhook / {
            print "# Fresh worktrees have no vendor/ - skip instead of hard-failing."
            print "# Injected by docker/hooks/install-worktree-guard.sh (make hooks-install)."
            print "if [ ! -x vendor/bin/captainhook ]; then"
            print "    echo \"[" hook "] captainhook missing (no vendor/ in this checkout?) - skipping hook\" >&2"
            print "    exit 0"
            print "fi"
            injected = 1
        }
        { print }
    ' "$file" >"$file.tmp"
    mv "$file.tmp" "$file"
    chmod 755 "$file"
    echo "  guard injected: ${hook}"
done
