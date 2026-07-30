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

### Restrictive host `umask 077` crash-loops nginx + pgadmin (baked entrypoints)

- **Symptom** (fresh clone + `make setup` on a host with `umask 077`): `nginx`
  and `pgadmin` restart-loop with
  `/bin/sh: can't open '/…entrypoint…sh': Permission denied`
  (postgres/redis/node stay healthy).
- **Root cause**: `umask 077` makes every git-checked-out file owner-only
  (`700`). Dockerfiles bake the entrypoint via `COPY` (no `--chmod`) + a bare
  `RUN chmod +x`, which adds execute for all classes but NOT read → source `700`
  becomes `711` = execute-only for non-owner. The baked file is owned by the
  build-time image uid (nginx `100`, pgadmin `root`), but the container runs as
  a DIFFERENT uid (nginx remapped to `1000` via `usermod` in the dev stage;
  pgadmin `5050`) → the runtime user hits "other" perms `--x` (no read) → a
  shell cannot open/read the script. Under the assumed `umask 022` sources are
  `755` (world-readable), which masks the uid mismatch — so it only breaks on
  restrictive-umask hosts.
- **Fix**: umask-independent baked scripts — `COPY --chmod=0755 …` (BuildKit) on
  the entrypoint, drop the bare `RUN chmod +x`. Applied to nginx + pgadmin
  Dockerfiles. (mariadb/postgres entrypoints are bind-mounted from the host
  owned by uid 1000 and their containers run as uid 1000 → owner match → NOT
  affected; verified healthy.) Do NOT blanket `chmod -R a+rX` the tree — would
  expose `docker/certs` private keys + docker secrets (keep those `600`).

### `config.platform.php` floor too low → composer install aborts, php_vendor stays empty

- **Symptom**: php restart-loops with
  `ERROR: Composer dependencies not installed!`. NOT a permission issue — the
  `php_vendor` NAMED volume (not the host `vendor/`, which is the separate
  `composer-install-local` IDE copy) is genuinely empty because
  `make composer-install` aborted.
- **Root cause**: `composer.json` `config.platform.php` is `"8.4"`, which
  composer reads as `8.4.0`. Locked deps (symfony 8.1.x → `symfony/process`,
  `string`, `var-dumper`, event-dispatcher via php-cs-fixer …) require
  `php >=8.4.1`. `composer install` validates locked packages against the
  platform override (`8.4.0`), fails all of them (`Error 2`), installs nothing.
  Real runtime PHP is 8.4.23 and would satisfy it — the too-low _pin_ is the
  bug.
- **Fix**: bump `config.platform.php` to `"8.4.1"` (the actual dependency floor)
  so resolution and install agree. `require.php` `^8.4` already allows it.

### `COPY --chmod` is BuildKit-only → breaks `DOCKER_BUILDKIT=0` CI builds

- **Symptom**: `Production Build Test` / `Security Scan` fail at
  `Step 13/63 : COPY --chmod=0644 docker/php/conf.d/amqp.ini …` with exit 1. The
  `Step N/M` + `---> Running in` output = the CLASSIC (non-BuildKit) builder.
- **Root cause**: `COPY --chmod=` is a BuildKit-only directive; the classic
  builder rejects it. Several CI steps force `DOCKER_BUILDKIT=0` (ci.yml build
  jobs, security-scan.yml, zap-scan.yml). The umask-safe fresh-clone fix had
  introduced `COPY --chmod` in php/node/pgadmin — invisible locally (`make`
  builds default to BuildKit) but fatal on the `DOCKER_BUILDKIT=0` paths.
- **Why those jobs pin the classic builder**: the `production`/`test` stages do
  `COPY --from=zappzarapp-node-backend:latest` / `zappzarapp-goss:latest` —
  references to separately-built LOCAL images. BuildKit resolves `--from=<tag>`
  against a registry and can't see local images, so those builds must use the
  classic builder (which reads the local image store). Net: shared base stages
  can't use ANY BuildKit-only syntax.
- **Fix (tactical)**: replace `COPY --chmod=MODE src dst` with `COPY src dst` +
  `RUN chmod MODE dst` (absolute octal mode — umask-safe like `--chmod`, but
  works under BOTH builders). 27 COPYs across php/node/pgadmin/nginx.
- **Fix (strategic, see todo.md)**: make cross-image sharing BuildKit-native via
  `docker buildx bake` (goss/node-backend/php/nginx as targets in one graph, as
  the GitLab side already does) → retire every `DOCKER_BUILDKIT=0`, regain gha
  layer cache for prod builds, and `--chmod` could return.
- **Lesson**: any `COPY --chmod`/`RUN --mount`/other BuildKit-only directive in
  a Dockerfile that ALSO gets built with `DOCKER_BUILDKIT=0` will fail. Grep
  both before adding BuildKit syntax: `grep -rn 'DOCKER_BUILDKIT=0' .github/` vs
  the Dockerfiles built there. Not caught locally because `make` uses BuildKit.
- **Grep gotcha (cost a second CI round)**: `--chmod` can appear AFTER other
  flags — `COPY --chown=nginx:nginx --chmod=0755 …`. A first sweep grepped
  `COPY --chmod` (anchored) and missed nginx's line, so php/node went green but
  nginx (Production Build Test + BATS Integration + Dockle/Image Scan) stayed
  red. Always grep `--chmod` position-independently:
  `grep -rnE '^\s*(COPY|ADD)\b.*--chmod' docker/`.
- **`RUN chmod` runs as the active USER (cost a third CI round)**: unlike
  `COPY --chmod` (applied atomically by the builder regardless of USER), a
  post-COPY `RUN chmod` executes as the image's current USER. `dpage/pgadmin4`
  defaults to `USER pgadmin` (uid 5050), so `RUN chmod 0755 …` on a root-owned
  COPYed file failed with exit 1 (`target pgadmin: failed to solve`). Fix: run
  the chmod under an explicit `USER root` block (pgadmin already had one for a
  `chown` — fold the chmod in), then switch back. php/node/nginx base images
  default to root, so only pgadmin was affected. When converting `COPY --chmod`
  → `RUN chmod`, check the base image's default USER. pgadmin builds ONLY in the
  BATS integration preset (not the scan/prod-build jobs), so it surfaced last.

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

### `make -n` is NOT side-effect-free with recursive make

- GNU make executes any recipe line containing the string `$(MAKE)` even under
  `-n`/`--dry-run` (the recursive-make exception, so sub-makes recurse and
  report). If a destructive block shares ONE continued recipe line (`\`-joined)
  with a `$(MAKE)` call, `-n` runs the whole line for real. The `setup` target's
  boilerplate file-swap block (`mv`/`cp`/`rm` of README/CLAUDE.md/AGENTS.md/
  CHANGELOG.md) is one recipe line that also calls `$(MAKE) --silent ide-unlock`
  — so `make -n setup BOILERPLATE=1` actually performed the swaps. Do NOT use
  `make -n` to "preview" a recipe containing `$(MAKE)`; read the recipe, or test
  in a throwaway worktree. (2026-07-29)

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

### CI image reuse: use a layer cache, not a per-SHA image push

- Every CI job rebuilt the app image from scratch (recompiling PHP extensions /
  the Node toolchain) — the dominant per-job cost. Three tiers were measured on
  the GitLab mirror (feature-branch run, same job set):
  - **Job consolidation + `needs:` DAG** (10 quality jobs → 2, all parallel):
    6.5 min wall / 24 compute.
  - **SHA-tagged whole-image push+pull** (a `build:*` job pushes
    `$CI_REGISTRY_IMAGE/<svc>:$SHA`, downstream pull + `up`): a REGRESSION — 7.7
    min wall, compute a wash. On dind every job runs in parallel, so the
    "redundant" compiles already overlap; a serial build job REMOVES that
    parallelism, and a per-SHA tag never serves as cache for the next pipeline.
  - **BuildKit registry LAYER cache** (`docker buildx bake` on a
    docker-container builder, `cache-from/to type=registry` with a STABLE ref
    `$CI_REGISTRY_IMAGE/cache:<svc>`, `--load`): warm 3.8 min wall / 16 compute
    (−42% / −34% vs consolidation), cold (Dockerfile/deps change) 8.8 min.
    Parallel, and persists across pipelines even on ephemeral shared runners.
- Takeaways: (1) reuse via a _layer cache_, not a whole-image artifact; (2)
  stable cache ref, not per-commit; (3) `cache-to ...,ignore-error=true` so an
  instance without a registry degrades to uncached instead of failing; (4) do
  NOT set the docker-container builder as default, or compose's implicit builds
  of postgres/redis won't `--load` into the dind daemon; (5) exact analogue of
  GitHub Actions `type=gha` (a `build-images` job warms it, downstream
  `cache-from`). Works on free gitlab.com (dind + free-tier registry). Verified
  2026-07-28.

---

## Documentation Audit (pre-v1.0 sweep)

### Doc-vs-code drift found across `.zappzarapp/docs/` (2026-07-29)

- **Dead `make` targets outlive their rename in docs.** `ssl-selfsigned` /
  `ssl-generate` were renamed to `ssl-internal` long ago (CI caught it once, see
  CI section) but the string still lingered in TROUBLESHOOTING, MAKEFILE-
  REFERENCE, NODE-SSL, INTERNAL-TLS, a dev-dashboard template, and even a
  shipped `docker/nginx/conf.d/ssl-development.conf.template` comment.
  `make db-cli` never existed at all (real: `postgres-cli` / `mariadb-cli`).
  Lesson: grep every `make <target>` reference in docs against the live Makefile
  — a rename fixes code + CI but rarely the prose.
- **DB migrations do NOT live at `/docker-entrypoint-initdb.d/<file>.sql` at
  runtime.** Only `init-db.sh` is baked to that path; the project isn't mounted
  into postgres/mariadb, so
  `docker compose exec … -f /docker-entrypoint-initdb.d/ 001_audit_logs.sql`
  fails. Migrations apply ONLY via `make db-migrations` (loops
  `migrations/<engine>/*.sql` through stdin). Several security docs also had the
  file numbers off by one (`001_audit_logs`→`002`, `002_retention`→`003` —
  there's a `000_encryption_helpers` + `001_users` ahead of them).
- **`AuditLogger` is `Zappzarapp\AuditLogger\…` (extracted package), not
  `App\Infrastructure\Audit\…`.** Only the `HasAuditLogging` trait is local;
  `AuditLogger` + `AuditLoggerInterface` come from the package. Doc examples had
  the interface under the wrong namespace.
- **`.env.production` is committed and NOT gitignored** (a template like
  `.env`). CORS.md both told users to `cp .env.production.example` (no such
  file) and claimed the file is gitignored. Secrets belong in Docker secrets,
  not this file.
- **Pinned version numbers in ARCHITECTURE.md rot silently** — Node 24.12→24.13,
  PostgreSQL 16+→17, MariaDB 11+→12 all lagged the Dockerfiles. Prefer
  major-only floors (`17+`) over exact minors in prose.
- **`make setup` already runs `up`** — its completion banner still said "Next
  steps: make up" (same redundancy the README had). Reworded to open-app /
  down·up daily use.

---

## Security / Dependency Scanning

### Container-CVE triage: ignore-unfixed was already on → the flood was fixable, not noise

- **Context**: ~1174 open Trivy code-scanning alerts before v1.0. The working
  assumption (todo.md) was "mostly no-fix OS noise, `--ignore-unfixed` will
  crush it." **Wrong premise**: `ignore-unfixed: true` was already set in every
  Trivy scan step. So all 1174 were _fixable_ HIGH/CRITICAL CVEs, not noise.
- **Where they came from**: dominated by stale upstream image tags pinned back
  in Dec/Jan (elasticsearch 244, mercure 180, seaweedfs 173, mariadb 172,
  mailpit 154, meilisearch 72) plus self-built Alpine images that **never ran
  `apk upgrade`** (node 107, postgres 92, php 17). nginx/redis/rabbitmq were 0
  because they install few/no extra apk packages.
- **Real fixes (permanent, got 1174 → 6)**: (1) `apk upgrade --no-cache` in the
  base stage of every self-built Alpine image (php/node/postgres/nginx/redis) +
  the Alpine-based mercure — ignore-unfixed only reports fixable CVEs, so the
  fix is by definition in the repo the upgrade pulls. (2) **`apt-get upgrade` in
  mariadb + elasticsearch** — BOTH are Debian/Ubuntu-based (not "unpatchable
  upstream monoliths"!); apt cleared the OS-package bulk (mariadb 70→0). ES
  ships as uid 1000:0 → `USER root` for the upgrade then restore `USER 1000:0`
  (else root-regression + Semgrep last-user-is-root). (3) Bump every image tag
  to newest patch/minor in major. (4) setuid/setgid strip
  (`find / -xdev -type f \( -perm -4000 -o -perm -2000 \) -exec chmod -s {} \;`)
  for Dockle CIS-DI-0008.
- **The irreducible residual is upstream-only**: Go `stdlib`/modules compiled
  into bundled `gosu`/Caddy binaries (postgres/mariadb/mercure/seaweedfs), Java
  JARs bundled in the ES distribution (netty, jackson-databind, ...), and
  npm/undici/ tar/glob-libs bundled in the Node image. None fixable by us.
- **ENDGAME — three approaches, only the last works. This is the key lesson:**
  1. **`.trivyignore` CVE-ID list — FAILS.** A list built from a LOCAL scan does
     not match CI: the local Trivy DB was stale vs CI's (0 CVE-ID overlap on
     mariadb). And `TRIVY_IGNOREFILE` **env is ignored by
     aquasecurity/trivy-action** — you must use its `trivyignores:` input. Even
     wired, CVE IDs drift as the DB updates (same image, different IDs hours
     apart).
  2. **Per-alert GitHub dismissal — CHURNS.** Dismissals stick for an UNCHANGED
     image, but every image rebuild re-fingerprints Trivy's SARIF → GitHub
     creates NEW open alerts for the same CVEs (dismissed 80, then 54 new
     appeared after Dockerfile edits).
  3. **Package-level Trivy `--ignore-policy` (Rego) — WORKS.** The vulnerable
     PACKAGES are stable even as CVE IDs churn. `.trivy/ignore-policy.rego`
     lists the upstream-only package names (Go stdlib+modules, `io.netty:*`,
     jackson-databind, undici, tar, brace-expansion, minimatch, picomatch);
     `default ignore = false` + `ignore { ignore_packages[input.PkgName] }` +
     `startswith(input.PkgName, "io.netty:")`. Wired via `ignore-policy:`
     (GitHub) / `--ignore-policy` (GitLab). Alerts are never created → no
     treadmill. `pnpm` EXCLUDED (fixable via pnpm 11 → kept visible). VEX
     (`--vex`) is the standards-track upgrade path.
- **Measure locally, but trust CI for counts**: local Trivy
  (`aquasec/trivy:latest`) uses a different DB snapshot than the CI trivy-action
  → different/more CVEs. Local is fine for "does the fix reduce it" but the
  authoritative count is CI. Also: Trivy vendor-severity (its HIGH,CRITICAL
  filter) ≠ GitHub CVSS bucketing — a distro-HIGH CVE with CVSS 5 shows as
  "MEDIUM"; same finding, don't chase it.
- **Dockle**: `.dockleignore` for rule-level accepts — CIS-DI-0001 (intentional
  runtime root-drop via su-exec/gosu), DKL-DI-0004 (upstream redis-layer FP),
  CIS-DI-0010 (build-ENV FP — this project passes secrets via Docker secret
  FILES, never ENV, so every flagged key is a non-secret base-image var). Real
  fixes: setuid strip (CIS-DI-0008), baked postgres `pg_isready` HEALTHCHECK
  (CIS-DI-0006).

### pnpm 11 is NOT a drop-in: it stops reading `pnpm.*` fields from package.json

- **Blocker found by build-testing a pnpm 10→11 bump**: pnpm 11 emits
  `[WARN] The "pnpm" field in package.json is no longer read by pnpm. The following keys were ignored: "pnpm.auditConfig", "pnpm.overrides".`
  This project drives its security override floors (KNOWN-VULNERABILITIES.md)
  **and** the brace-expansion audit exception (`GHSA-mh99-v99m-4gvg`) through
  exactly those fields → a blind bump silently disables both.
- **Second breaking change (found after fixing the first)**: with the config
  moved, `pnpm install` resolves fine but pnpm 11 then **hard-fails**
  (`ERR_PNPM_IGNORED_BUILDS`, non-zero exit) on dependencies whose install/build
  scripts are not explicitly approved — here `esbuild` + `@parcel/watcher`. pnpm
  10 only warned. Needs an `onlyBuiltDependencies` allow-list in
  pnpm-workspace.yaml; in a quick test the allow-list did NOT take on the first
  attempt, so this needs proper debugging + validation, not a one-liner.
- **Consequence / effort verdict**: pnpm 11 is a real migration with ≥2
  behavioural breaks, not a version bump — decoupled into its own PR (validate
  node build + both CIs on the mirror). The 6 residual pnpm HIGH/CRIT CVEs
  (fixed only in 11.x) stay baselined; kept PNPM_VERSION at 10.34.0 (newest
  10.x). For the migration: (1) move `pnpm.overrides` + `pnpm.auditConfig` from
  package.json to `pnpm-workspace.yaml` top-level keys, (2) add
  `onlyBuiltDependencies: [esbuild, '@parcel/watcher']` and verify it actually
  suppresses ERR_PNPM_IGNORED_BUILDS, (3) green both CIs.
- **Also**: `--frozen-lockfile` fails locally with
  `ERR_PNPM_LOCKFILE_CONFIG_MISMATCH` when the lockfile was made by another pnpm
  line — harmless in CI (no committed lockfile → non-frozen install), but it
  will bite anyone testing a pnpm-major bump against a stale local lockfile.

---

## Last Updated

2026-07-30 (added: container-CVE triage — ignore-unfixed already on so the 1174
were fixable; apk upgrade + tag bumps got −94%; upstream-only residual baselined
via .trivyignore.yaml + TRIVY_IGNOREFILE; pnpm 11 drops pnpm.* package.json
fields so it needs a config migration, not a bump)

2026-07-29 (added: pre-v1.0 documentation audit sweep — dead `make` targets in
prose, migrations only via `make db-migrations`, AuditLogger package namespace,
`.env.production` committed-not-gitignored, ARCHITECTURE version rot, redundant
setup banner)

2026-07-28 (added: CI path-based job gating + skip-cascade semantics,
concurrency master-exempt, change-detector compose-pattern gap, docs-php
cross-UID staleness fix, paths-filter v4 version-check, GitLab CI mirror via
native rules:changes + auto_cancel, CI image reuse via BuildKit registry/gha
layer cache — not per-SHA image push)

2026-07-27 (added: import-time listeners in tests, lint:fix glob drift, node
coverage baseline, single-file bind-mount stale inode, @vitest/ui coverage
ENOENT, vitest constructor-mock needs real function, node-backend optional-svc
env dormant + ES internal HTTPS/auth)
