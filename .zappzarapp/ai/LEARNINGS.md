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

## Docker & Containers (Addendum 2)

### Single-file bind mounts serve a stale inode after an atomic-replace edit

**Symptom:** You edit a bind-mounted root config file on the host (e.g.
`vitest.config.ts`), but a long-running container keeps reading the OLD content
— `grep` inside the container shows different line numbers/values than the host
file.

**Cause:** Editors (and the Edit tool) write a temp file and `rename()` it over
the target, which changes the file's inode. Docker binds a SINGLE file by its
inode at container start; when the host inode is replaced, the container's mount
still points at the now-orphaned old inode. (Directory bind mounts are immune —
they resolve names live.)

**Fix:** Restart the affected long-running container
(`docker compose restart <svc>`) to re-bind the current inode. Ephemeral
`compose run --rm` containers are immune — they re-resolve the mount on each
start. Found 2026-07-27: a `vitest.config.ts` threshold change was invisible to
the running `node` container until restart.

---

## Frontend Testing (Addendum 2)

### The @vitest/ui html reporter crashes coverage runs (ENOENT cp)

**Symptom:** `make test-coverage-node` fails with an unhandled
`ENOENT: lstat '/app/build/coverage/node'` and a non-zero exit, even when every
test passes and thresholds are met. The stack points at
`HTMLReporter.onFinishedReportCoverage` → `node:fs/cp`.

**Cause:** The test `reporters: ['verbose', 'html']` include the `@vitest/ui`
HTML reporter, whose `onFinishedReportCoverage` `cp`s the coverage report dir
into its UI bundle. During a coverage run it races/mismatches on that path and
throws. A CI coverage gate does not need the interactive UI report at all.

**Fix:** Scope coverage runs to a non-UI reporter — `test:coverage` is
`vitest run --coverage --reporter=verbose`. Regular `make test-node` keeps the
html reporter (it never triggers the coverage-copy path). Found 2026-07-27 while
making the Node coverage gate green.

---

### Mocking a `new`-ed class (net.Socket) in Vitest needs a real function

**Symptom:** `vi.mocked(net.Socket).mockImplementationOnce(() => {...})` makes
`new net.Socket()` throw `... is not a constructor`, so the code-under-test
catches it and every probe reports failure.

**Cause:** Vitest/tinyspy constructs mocks via `Reflect.construct`, which
requires the implementation to be constructable. An **arrow function is not
constructable**, so it throws.

**Fix:** Use a named `function` expression as the implementation
(`mockImplementationOnce(function mockSocket() { ...; return socket; })`) — or a
`class`. Found 2026-07-27 while adding the RabbitMQ TCP probe to the Node
HealthCheckService.

---

### The node-backend service passes no optional-service ENV (health checks dormant)

**Observation:** `compose.yaml`'s `node-backend` service only receives DB/Redis
env — none of the `ENABLE_MEILISEARCH/ELASTICSEARCH/RABBITMQ/SEAWEEDFS` flags or
their URLs that `HealthCheckService` reads. So every optional-service check in
the Node health endpoint (including the pre-existing Meilisearch one) defaults
to `disabled` and never actually runs, even when those containers are up. The
PHP `php` service _does_ get all the flags.

**Also:** Elasticsearch runs internal **HTTPS with xpack.security enabled**
(`xpack.security.http.ssl.enabled: true`), so an unauthenticated
`/_cluster/health` GET returns 401 — the PHP check even uses `http://` against
it and would fail outright. Any real ES health probe needs TLS **and** an API
key/basic auth. Meilisearch `/health` and the SeaweedFS master `/cluster/status`
are auth-free. Noted 2026-07-27; wiring the node-backend env (and ES auth) left
as an explicit decision, not silently changed.

---

## CI / GitHub Actions

### Path-based job gating (dorny/paths-filter)

- A `changes` job runs `dorny/paths-filter@v3` and exposes per-area boolean
  outputs (`php`/`node`/`infra`/`ci`); every other job `needs: [changes]` and
  gates via `if: needs.changes.outputs.<area> == 'true'`. Filter values are the
  strings `'true'`/`'false'` — compare against `'true'`, not a bare truthiness.
- The filter mirrors `docker/hooks/change-detector.sh` intent. `ci` (any change
  under `.github/`) is an override that forces every downstream job to run.
- `dorny/paths-filter` needs `actions/checkout` before it (to diff against the
  base/before-SHA) — works for both `push` and `pull_request`.
- **Skip cascades through `needs`**: a skipped needed job skips its dependents
  automatically (their `if` doesn't use `always()`). So gating `bats-quick` on
  `infra||ci` also gates the expensive `bats-integration` (`needs: bats-quick`).
  We still repeat the path condition on `bats-integration` for explicitness and
  to AND it with the existing event/branch condition.
- `markdownlint` lives inside `node-quality`, so `**/*.md` is in the `node`
  filter — a pure docs change still gets linted (slight over-trigger, but keeps
  coverage). Same reason `resources/**` is under `node`.
- `ci-summary` (`if: always()`) also `needs: [changes]`: if the `changes` job
  itself fails, all downstream jobs skip, and without this the summary would go
  green on a broken filter. `contains(needs.*.result, 'failure')` ignores
  `skipped`, so gated-out jobs don't fail the summary.
- **Branch-protection caveat:** a job skipped via job-level `if:` counts as
  _passing_ for required status checks — path-gated jobs won't block merges when
  legitimately skipped. Verify required-check names still resolve if enabled.

### Pin the latest action major — verify, don't assume

- `dorny/paths-filter` latest is **v4** (v4.0.2), not the habitual v3 — v4 only
  bumps the Node runtime, API identical. Verify via
  `curl -fsSL api.github.com/repos/<owner>/<repo>/releases/latest`. All other
  actions in `.github/workflows/` are already on current majors (checkout@v7,
  setup-buildx-action@v4, upload-artifact@v7, codecov-action@v7,
  github-script@v9, codeql-action@v4).

### GitLab CI mirrors the same gating (native rules:changes)

- `.gitlab-ci.yml` ships for boilerplate users who use GitLab — keep it in sync
  with `.github/workflows/ci.yml`. GitLab needs no detector job: each job gets
  `rules: - changes: *<paths>` where `*<paths>` is a YAML anchor holding a FLAT
  list of globs. You cannot splice two sequence anchors into one `changes:` list
  (nested arrays are rejected), so each `*-paths` anchor embeds the CI-config
  paths (`.gitlab-ci.yml`, `.gitlab/**/*`) directly — that's the `ci` override.
- GitLab directory globs need the `/*` suffix: `docker/**/*`, not `docker/**`.
- `only:` and `rules:` are mutually exclusive per job — the
  `only: [master, develop, merge_requests]` jobs (coverage, bats:integration,
  build:production) were fully converted to `rules` with an `if:` branch/MR
  guard AND `changes:`.
- Concurrency equivalent: `workflow.auto_cancel.on_new_commit: interruptible`
  (GitLab 15.3+) + `default: interruptible: true`; `build:production` overrides
  `interruptible: false` so the deployable-artifact validation always finishes
  (the GitLab analogue of the GitHub master-exemption).
- `workflow.rules` added the canonical "skip branch pipeline when an MR is open"
  guard to avoid duplicate MR+branch pipelines.
- Pre-existing image pins `docker:24-{cli,dind}` / `python:3.12-alpine` are
  dated but left unchanged (out of scope; GitLab CI is not actively run here).
  Bump if GitLab CI is ever reactivated.
- **Drift caught by the first live GitLab run:** `.docker-setup` before_script
  (and build:production) called `make ssl-selfsigned`, a target that no longer
  exists — it was renamed to `ssl-internal` (what GitHub CI uses). Every
  docker-setup job failed at before_script. Undetected because GitLab CI had
  never run. Lesson: when porting/editing a never-run CI config, grep every
  `make <target>` / `pnpm run <script>` reference against the Makefile/package
  scripts before pushing, rather than discovering drift one failed job at a
  time.

### concurrency: exempt master from cancellation

- `concurrency.cancel-in-progress: ${{ github.ref != 'refs/heads/master' }}` —
  cancels superseded PR/develop runs (saves minutes) but lets master runs finish
  since they validate the deployable artifact.

### change-detector.sh `compose` pattern misses `compose.yaml` (latent, unfixed)

- `docker/hooks/change-detector.sh` `compose` type matches `docker-compose.*`
  only; the repo uses `compose.yaml`/`compose.*.yaml`, so the pre-push hook does
  NOT detect compose changes. The CI `infra` filter uses `compose*.yaml` and is
  correct. Flagged during the CI refactor 2026-07-28; hook fix left out of
  scope.

### docs-php had the same cross-UID staleness bug as docs-node

- Same root cause fixed for `docs-node-backend`/`-frontend` in `40a97d7`:
  phpDocumentor runs as `www-data` (UID 82) and cannot overwrite output files
  owned by a different UID (host root in CI). Unlike docs-node, `docs-php` had
  no freshness check, so it _silently shipped stale docs_. Fix: empty
  `docs/api/php` as root via the alpine helper BEFORE generating, `touch` a
  stamp, then assert `docs/api/php/index.html -nt stamp` after `composer docs`.
  Verified 2026-07-28.

---

## Last Updated

2026-07-28 (added: CI path-based job gating + skip-cascade semantics,
concurrency master-exempt, change-detector compose-pattern gap, docs-php
cross-UID staleness fix, paths-filter v4 version-check, GitLab CI mirror via
native rules:changes + auto_cancel)

2026-07-27 (added: import-time listeners in tests, lint:fix glob drift, node
coverage baseline, single-file bind-mount stale inode, @vitest/ui coverage
ENOENT, vitest constructor-mock needs real function, node-backend optional-svc
env dormant + ES internal HTTPS/auth)
