# Boilerplate Update Mechanism

**Status:** Planned **Size:** Medium **Scope:** feature **Created:** 2026-01-20
**Planning:** Not required

## Context

Users need a way to pull boilerplate updates into their projects.

## Goal

Add `make update` target to simplify pulling upstream boilerplate changes.

## Challenge

Boilerplate updates are complex:

| File Type                           | Update Behavior                  |
| ----------------------------------- | -------------------------------- |
| `Makefile`, `docker/*`, `compose.*` | Should be updated                |
| `.env`, `secrets/`, user code       | Never overwrite                  |
| `composer.json`, `package.json`     | Merge needed (user has own deps) |

## Implementation

Git-based approach (requires user to have upstream remote):

```makefile
update: ## Update boilerplate from upstream
    @git remote get-url upstream 2>/dev/null || \
        (echo "Adding upstream remote..." && git remote add upstream https://github.com/xxx/zappzarapp)
    @git fetch upstream
    @echo "Changes from upstream:"
    @git diff --stat HEAD upstream/main
    @echo ""
    @echo "To update, run: git merge upstream/main"
    @echo "Or for rebase: git rebase upstream/main"
```

## Additional Features

- `make update-check` — Show what would change (dry-run)
- `make update-docker` — Update only docker-related files
- Documentation for conflict resolution
- Warning about uncommitted changes before update

## Files

- `Makefile` (new `update` target)
- `documentation/development/UPDATING.md` (new guide)
- `README.md` (mention update workflow)
