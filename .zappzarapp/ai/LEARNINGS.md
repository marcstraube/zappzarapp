# Project Learnings

Fast-capture inbox for insights, gotchas, and patterns discovered during
development. Mature entries graduate into the permanent documentation during
periodic triage (`/optimize --learnings`) and are removed from this file.

---

## Docker & Containers

### Build & Targets

- **Multi-stage builds need explicit targets**: `NODE_TARGET`, `NGINX_TARGET`,
  `PHP_TARGET` must be set based on `NODE_MODE`.
- **Asset source for nginx/php**: Changed from `zappzarapp-node:latest` to
  `zappzarapp-node-backend:latest` for Vite assets.

---

## Development Workflow

### Commits

- **Commit after each completed todo**: Prevents mixed changes that need to be
  split later. Each todo = one focused commit.
- **User review before commit**: After completing a todo, inform user that
  changes are ready for review. Wait for approval before committing.
- **Thematically grouped commits**: Keep commits focused on one topic (feature,
  fix, docs) for cleaner history.

### Bash Commands

- **Single commands > chaining**: `cmd1 && cmd2 && cmd3` requires manual
  confirmation. Single Bash calls are auto-approved.
- **Better error handling**: Single commands allow precise error handling
  instead of entire chain aborting.

---

## Claude Workflow

### Post-Implementation Verification

**After completing each task, automatically run relevant checks:**

| Change Type            | Verification Steps                                       |
| ---------------------- | -------------------------------------------------------- |
| Docker/Compose changes | `docker compose config`, restart containers, test health |
| PHP code changes       | `make check` (includes PHPStan, PHPMD, CS-Fixer)         |
| Node code changes      | `make lint-node`, `make test-node`                       |
| Configuration files    | `docker compose config`, relevant service tests          |
| Database-related       | Restart DB container or `make fresh` if schema changed   |

**Workflow:**

1. Implement change
2. Run relevant checks/tests (e.g., `make lint-node`, execute new make targets)
3. Verify functionality (health endpoints, manual tests)
4. When closing task → add changelog entry immediately (keeps context)
5. Only then report completion to user

**Important:** Database password or credential changes require fresh
initialization:

```bash
make down
docker volume rm <project>-postgres-data
make up
```

---

## Claude Code Configuration

### Permission Pattern Matching

- **Pattern `Bash(X:*)` matches `X <any args>`**: Subcommands ARE treated as
  arguments
- **Tested:** `Bash(docker compose:*)` covers `docker compose logs`,
  `docker compose exec`, etc.
- **Tested:** `Bash(make:*)` covers `make build`, `make help`, etc.
- **Redundant patterns**: Specific subcommand patterns (e.g.,
  `docker compose logs:*`) are redundant with base patterns - remove for cleaner
  config
- **Dangerous patterns to avoid**:
  - `Bash(sudo rm:*)` - allows `sudo rm -rf /`
  - `Bash(bash:*)` - allows arbitrary script execution
  - `Bash(rm:*)` - allows `rm -rf` (use with extreme caution)

### Permission File Organization

- **settings.json** (committed): Project-specific scripts only
  (`./docker/hooks/*`, `./tests/goss/*`, env var combos)
- **settings.local.json** (gitignored): General tools, system utilities,
  personal preferences
- **Organize by category**: Core tools, Docker, Git, File ops, Text processing,
  etc.
- **Sort alphabetically** within each category for maintainability

### Hooks Format (CRITICAL)

**Old format (BROKEN - silently breaks ALL settings loading!):**

```json
{
  "hooks": {
    "PostToolUse": [
      {
        "matcher": "Task",
        "command": "echo test"
      }
    ]
  }
}
```

**New format (CORRECT):**

```json
{
  "hooks": {
    "PostToolUse": [
      {
        "matcher": "Task",
        "hooks": [
          {
            "type": "command",
            "command": "echo test"
          }
        ]
      }
    ]
  }
}
```

**Key differences:**

- `command` is now nested inside `hooks` array
- Each hook needs `"type": "command"` (or `"type": "prompt"`)
- The old format silently breaks ALL settings loading without error!

**Debug tips:**

- `/permissions` shows loaded permissions
- `/hooks` shows loaded hooks
- Debug logs in `~/.claude/debug/` show settings loading
- `Found 0 hook matchers` in logs = hooks broken

### Hook Input (CRITICAL)

**`$TOOL_INPUT` is passed via stdin, NOT as environment variable!**

**Wrong (empty variable):**

```bash
if echo "$TOOL_INPUT" | grep -q "pattern"; then echo "matched"; fi
```

**Correct (read from stdin first):**

```bash
TOOL_INPUT=$(cat); if echo "$TOOL_INPUT" | grep -q "pattern"; then echo "matched"; fi
```

### Task Tool Hook Limitation

**PostToolUse hooks don't work reliably with Task tool.**

The Task tool spawns a subprocess and doesn't pass stdin to PostToolUse hooks. A
CLAUDE.md instruction is more reliable than a hook that only sends fallback
values.

**Workaround:** Use CLAUDE.md instructions instead of hooks for Task-related
notifications (e.g., ntfy notifications after agent completion).

### Settings Scope Behavior

- Hooks are **merged across scopes** (not overridden)
- Precedence: Managed → User → Project → Local → Plugin (all matching hooks run)
- Permissions follow standard scope precedence (local overrides project)
- See: <https://code.claude.com/docs/en/settings#hook-configuration>

---

## Nginx

### Static proxy_pass poisons same-string variable proxy_pass

**Symptom:** Locations using the runtime-resolution pattern
(`set $upstream_node node:5173; proxy_pass http://$upstream_node;` plus a
`resolver` directive) still connect to a stale IP after the upstream container
is recreated — 502 with `connect() failed` to an IP that now belongs to a
different container.

**Cause:** A single STATIC `proxy_pass http://node:5173;` elsewhere in the
config creates an implicit upstream group named `node:5173`, resolved once at
config load. Variable-based `proxy_pass` first checks whether the value matches
a defined upstream name — the same string matches the implicit group, so nginx
routes into the frozen boot-time IP and never consults the resolver. One static
occurrence disables runtime resolution for every location using the same
host:port string.

**Fix:** Use the variable pattern in ALL locations sharing the upstream (no
static occurrence may remain), or reload nginx after recreating the upstream
container. Found 2026-07-26: `development-vite-hmr.conf` had one static
`proxy_pass` in the `/__vite_hmr__` location, breaking Vite HMR and asset
proxying after the node container was recreated.

---

## PHP-FPM

### putenv() persists across requests in the same worker

**Symptom:** A per-request `putenv('X=...')` is still visible via `getenv('X')`
in later requests served by the same FPM worker. A guard like "only set if not
already set" then mistakes the worker's own previous value for an external
override and freezes it until the worker recycles.

**Rule:** To detect EXTERNALLY provided environment values (FPM pool config,
container env), check `$_SERVER` / `$_ENV` — `putenv()` does not modify those.
When self-setting per-request values, overwrite (or clear via `putenv('X')`) on
every request instead of guarding on `getenv()`.

Found 2026-07-26 while diagnosing the DevToolbar branch display (resolver cwd
bug, tracked as zappzarapp-php-devtoolbar#4 — the consumer-side workaround
documented there relies on exactly this distinction).

---

## Docker & Containers (Addendum)

### New root-level config files need explicit compose mounts

**Symptom:** A tool inside a container fails with ENOENT for a config file that
clearly exists on the host (e.g. `tsc -p tsconfig.resources.json` → "Cannot read
file '/app/tsconfig.resources.json'").

**Cause:** Root-level config files are bind-mounted INDIVIDUALLY into the
containers (compose.override.yaml and compose.ci.yaml). A newly created config
file is not covered by any existing mount — the container simply does not see
it.

**Fix:** Add the single-file mount next to its siblings in BOTH compose files
(dev and CI) for every service that needs it, then validate with
`docker compose config -q`. `compose run`-based targets pick the new mount up
immediately; long-running services need a recreate at the next `make up`.

Found 2026-07-27 adding tsconfig.resources.json for the browser-utils
DevDashboard integration.

---

## Frontend Testing (Addendum)

### Import-time element listeners need the DOM before a fresh import

**Symptom:** A happy-dom test simulates a click on a nav link (or a `change` on
a select) and nothing happens, although the same wiring works in the browser.

**Cause:** DevDashboard page modules run `init()` at import time. Listeners
attached to `document` (event delegation, keydown) survive any later `innerHTML`
reset — but listeners bound to concrete elements (`initTabs()` nav links,
`getElementById(...).addEventListener`) bind to whatever exists at import time.
With the usual static test import, that is an empty DOM, so those listeners are
never attached.

**Fix:** Test element-bound wiring in a separate test file that builds the DOM
first and then does `await import('...')` (fresh module graph per test file).
Everything document-delegated can keep the normal static import. See
tests/node/resources/dev-dashboard/page-init.test.ts.

Found 2026-07-27 migrating the remaining DevDashboard templates to Vite modules.

---

### lint:fix globs drift from lint globs silently

**Symptom:** `make lint-node` reports Prettier errors that `make lint-node-fix`
does not fix.

**Cause:** package.json `lint` and `lint:fix` maintain their file globs
separately; `resources/js/**/*.ts` had been added to `lint` only. Anything
covered by check-globs but not fix-globs produces exactly this loop.

**Fix:** Keep both script globs identical (fixed 2026-07-27). When adding a new
source root to one lint script, grep for the sibling scripts.

---

### Node coverage thresholds are a red baseline, not a CI gate

**Observation (2026-07-27):** `make test-coverage-node` exits non-zero on a
clean develop checkout — the global Vitest thresholds (80% incl. branches) are
not met by the existing backend code. CI only enforces the PHP gate
(`make coverage-check-php`); the Node coverage step runs solely on pushes to the
dead `master` ref. `make check` does not include the Node coverage gate either.
Do not chase the global branch threshold when adding well-tested files; treat
fixing the baseline (or rightsizing the thresholds) as its own task.

---

## Last Updated

2026-07-27 (added: import-time listeners in tests, lint:fix glob drift, node
coverage baseline)
