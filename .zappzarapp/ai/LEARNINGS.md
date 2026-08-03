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

### `docker buildx bake` cross-target file copy: context keys must be TAGLESS

- **Goal**: retire `DOCKER_BUILDKIT=0`. The prod/test image builds are pinned to
  the classic builder because they `COPY --from=zappzarapp-goss:latest` /
  `zappzarapp-node-backend:latest` — references to LOCAL tagged images the
  BuildKit builder can't see. Native fix: build everything in ONE `bake` graph
  and wire cross-image copies via bake **named contexts**
  (`contexts = { <key> = "target:<other-target>" }`), so BuildKit links the
  source target's rootfs directly — no local image store, gha cache works.
- **GOTCHA (cost me the first PoC)**: a bake `contexts` map key that contains a
  COLON — e.g. `"zappzarapp-goss:latest" = "target:goss"` — is PARSED
  (`bake --print` shows it) but SILENTLY NOT APPLIED at solve time; the build
  falls back to pulling `docker.io/library/zappzarapp-goss:latest` from the
  registry → `insufficient_scope: authorization failed`. The CLI
  `--build-context "zappzarapp-goss:latest=docker-image://…"` DOES handle the
  colon; only the HCL/compose `contexts` map fails on colon keys.
- **Fix**: make the `COPY --from=` reference (and thus the context key) TAGLESS.
  Change `COPY --from=zappzarapp-goss:latest` → `COPY --from=goss` and wire
  `contexts = { goss = "target:goss" }`. Proven end-to-end on buildx 0.33 /
  docker 29.4 (docker-container driver): goss v0.4.9 binary copied + executed,
  `EXIT 0`. Isolation that pinned the cause: colon key `gossbin`-vs-`x:latest`
  A/B on a throwaway Dockerfile.
- **Scope note**: all 6 goss `COPY --from` refs live in CI-only `test` stages
  (php/nginx/postgres/redis/node) → safe. The 2 node-backend refs live in
  php/nginx `production` stages → they also feed the USER-FACING
  `docker compose build` prod path, which can't use bake `target:` links.
- **Compose side (the other half)**: the make/compose production path resolves
  the SAME tagless ref via compose `build.additional_contexts`:
  `node-backend-assets=docker-image://zappzarapp-node-backend:latest`. Two key
  facts: (a) a `docker-image://` context pointing at a LOCAL tagged image IS
  resolved from the local store by the default docker-buildkit builder (no
  registry pull) — this is exactly what a bare `COPY --from=<local-image>` could
  NOT do (it tried to pull); the explicit context declaration is the difference.
  So the existing `docker tag …node-backend:latest` dance in the `make`
  production targets now feeds the context — no DOCKER_BUILDKIT=0, no make
  restructuring. (b) `additional_contexts=service:<name>` was rejected: it
  forces the referenced service's PROFILE active for EVERY production compose
  command (even nginx validates it), which would wrongly start node-backend in
  modes that don't need it. Put additional_contexts in compose.production.yaml
  ONLY (never base) — declaring it in base makes dev builds fail with "unknown
  service … as additional context".
- **Two mechanisms, one tagless ref**: CI/bake → `contexts=target:node-backend`;
  make/compose → `additional_contexts=docker-image://…:latest`. Scan matrices
  build only cross-ref-free stages (dev/base/final) so they need NO context —
  just `docker buildx build --load` (—load exports to the daemon for
  Trivy/Dockle).

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
- **RESOLVED — `--chmod` reintroduced (branch `chore/dockerfile-copy-chmod`)**:
  the strategic fix landed — the `docker buildx bake` refactor retired every
  `DOCKER_BUILDKIT=0` path, so BuildKit-only syntax is safe everywhere again.
  All 24 `COPY x` + `RUN chmod` pairs across the 11 Dockerfiles were folded back
  to `COPY --chmod=…` (incl. 6 goss `COPY --from=goss --chmod=0755`, which also
  stops the `RUN chmod +x` copy-up that duplicated the ~10 MB goss binary per
  test stage). pgadmin's `USER root … RUN chmod … USER pgadmin` dance is GONE —
  the whole point of the sub-lesson above: `--chmod`/`--chown` apply atomically
  at copy time regardless of the active USER. Deliberately NOT converted (kept
  as `RUN`): recursive app-tree hardening (`chmod -R 555/770 /var/www/html…`,
  `find … chmod 660`) and the setuid-strip `find … chmod -s` — none are 1:1 COPY
  pairs. php conf.d stayed a per-line `--chmod=0644` list (NOT a `*.ini` glob):
  `development.ini`/`xdebug.ini` are dev-only and must not be baked in. Verified
  end-to-end: `stat` on the built postgres image reports `755` regardless of
  host umask. The gotcha above still stands as a rule — never add BuildKit-only
  syntax to a classic-builder path; it just no longer applies here because none
  remain.

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
- Third instance found 2026-08-02: `ssl-trust-ca`'s help delegation shared its
  recipe line with the sudo install branches — `make -n ssl-trust-ca` on a
  detected OS actually installed the CA into the trust store. Same fix as
  k8s-build (2026-08-02): a literal `make` for the delegation keeps `-n` inert.
  When ADDING a `$(MAKE)` call to an existing recipe line, check what else that
  logical line executes under `-n`.

### pnpm 10 → 11 migration is NOT a drop-in — three breaking changes (verified against pnpm docs)

- **Version bump**: `packageManager` in `package.json` (10.34.0 → 11.18.0),
  `ARG PNPM_VERSION` in `docker/node/Dockerfile`, and `engines.pnpm` (→
  `>=11.0.0`). `make pnpm-upgrade` bumps the first two automatically (to
  `npm view pnpm version`), but NOT `engines`. Corepack auto-fetches the pinned
  version from `packageManager`, so the running container uses pnpm 11 the
  moment the field changes — no image rebuild needed for local validation.
- **(1) The `pnpm` field in `package.json` is no longer read.** `overrides`,
  `auditConfig` (and everything else under `pnpm.*`) must move to top-level keys
  in `pnpm-workspace.yaml`. Verified: after the move the lockfile still carries
  the override floors and `pnpm audit` still reports "1 ignored". `overrides`
  may only live at the workspace root.
- **(2) `onlyBuiltDependencies` was REMOVED in v11 → replaced by `allowBuilds`**
  (a map `package -> boolean`, not a list). Setting the old
  `onlyBuiltDependencies` list does nothing; pnpm 11 hard-fails install with
  `ERR_PNPM_IGNORED_BUILDS` and auto-appends
  `allowBuilds: { pkg: "set this to true or false" }` placeholders to
  `pnpm-workspace.yaml`. The fix is
  `allowBuilds: { '@parcel/watcher': true, esbuild: true }` (this project's only
  two build-script deps — read them off the install error). Docs also removed
  `onlyBuiltDependenciesFile`, `neverBuiltDependencies`,
  `ignoredBuiltDependencies`, `ignoreDepScripts`. Codemod:
  `pnpx codemod run pnpm-v10-to-v11`.
- **(3) `verifyDepsBeforeRun` defaults to `install` in v11** → before every
  `pnpm run`/`pnpm exec`, pnpm auto-verifies deps and (on mismatch) tries to
  purge + reinstall `node_modules`. In a non-TTY container that aborts with
  `ERR_PNPM_ABORTED_REMOVE_MODULES_DIR_NO_TTY` (breaks `make analyse-node` /
  `lint-node` / `test-node`). Fix: `verifyDepsBeforeRun: false` — installs are
  managed explicitly via `make pnpm-install` / `pnpm-sync`, and `node_modules`
  is a Docker named volume. **GOTCHA (confirmed by docs + trial): this setting
  is NOT read from `.npmrc` — `pnpm config get verify-deps-before-run` returns
  `undefined`. It MUST live in `pnpm-workspace.yaml` (camelCase).**
- **Dockerfile must COPY `pnpm-workspace.yaml` before `pnpm install`** (pnpm 11
  Pitfall 2) — else the build sees no `allowBuilds` and fails. The main build
  stage already does (`COPY package.json pnpm-workspace.yaml ./`); the framework
  stage installs the user's frontend standalone (default frontend has zero deps,
  so no build scripts → safe).
- **`make pnpm CMD=...` had to become workspace-aware.** It copies the manifest
  to `/tmp` and runs there (bind-mount atomic-rename EBUSY workaround). Pre-
  migration that carried `overrides` (they were in `package.json`, which IS
  copied); post-migration the config is in `pnpm-workspace.yaml`, so the target
  now also copies `pnpm-workspace.yaml` + `.npmrc` + creates the workspace
  package dirs (`src/node/backend`, `src/node/frontend` — else pnpm errors on
  the missing workspace projects). Do NOT copy `pnpm-workspace.yaml` back: it is
  mounted `:ro` in dev compose, and `make pnpm` should never rewrite it.
- **Trivy tie-in**: the 6 residual "pnpm-only" Trivy CVEs (kept un-filtered in
  `.trivy/ignore-policy.rego` as the migration reminder) should clear once the
  node image rebuilds with pnpm 11.18.0 — re-scan on the next develop push to
  confirm before claiming them gone.
- **Method note**: the pnpm-11 config API was *researched against
  pnpm.io/settings
  - the v11.0 release notes*, not reverse-engineered from error messages alone —
    the error-driven guesses (`onlyBuiltDependencies`, `.npmrc` verify-deps)
    were BOTH the wrong pnpm-10 API and only the doc confirmed the v11
    replacements.

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

### `context: fork` skills inherit the live conversation — don't self-test after editing

Live-testing a `context: fork` skill in the same session that just edited its
SKILL.md contaminates the fork: it inherits the conversation about _building_
the skill and may reinterpret its job as "implement this spec" instead of
"execute these checks". Observed 2026-08-02 with `/sync-check` (haiku fork):
right after a SKILL.md extension it wrote a Python implementation + tests +
README into the skill directory instead of running the documented checks — the
files also proved `allowed-tools` (no Write/Edit listed) did not stop file
creation in the forked context. Test fork-skills in a fresh session, and treat
their `allowed-tools` as advisory, not a sandbox.

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

### Auditing the Node.js 20 action deprecation

- GitHub runners force node20 actions onto node24 with a deprecation warning
  (removal pending). To audit which actions are affected, check each ref's
  `runs.using` in its `action.yml`:
  `gh api repos/<owner>/<repo>/contents/action.yml?ref=<tag> --jq .content | base64 -d | grep using`.
- Bumped `docker/build-push-action@v6 → @v7` (v7's only workflow-relevant
  breaking change is the removed `DOCKER_BUILD_NO_SUMMARY` /
  `DOCKER_BUILD_EXPORT_RETENTION_DAYS` envs — unused here) and
  `zaproxy/action-baseline@v0.14.0 → @v0.15.0` (node24 + deps only).
- **`advanced-security/dismiss-alerts@v2` (v2.0.3) is still node20 with no
  node24 release** — nothing to bump; it carries the deprecation warning until
  upstream updates. Don't try to "fix" it. (checkout@v7, github-script@v9,
  upload-artifact@v7, codeql-action@v4, setup-buildx@v4, paths-filter@v4,
  azure/setup-*@v5 are already node24.)

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

### PHP major bumps: check what the engine absorbed and what PECL abandoned

- **PHP 8.5 builds OPcache into the engine** — `docker-php-ext-install opcache`
  then fails with the cryptic `cp: can't stat 'modules/*'` (configure runs,
  nothing compiles) and aborts the WHOLE chained extension layer; the failure
  surfaced after the last successful extension in the log (intl), not naming the
  guilty one. Isolate by looping `docker-php-ext-install <ext>` per extension in
  a throwaway container. Fix pattern (version-robust, keeps 8.4 buildable):
  conditional install via
  `php -r "exit(extension_loaded('Zend OPcache') ? 0 : 1);"` (pipe-free —
  hadolint DL4006) plus generating the `zend_extension=opcache` load ini at
  image build time only where the `.so` exists.
- **A red extension build can shadow a second one:** gmagick 2.0.6RC1 does not
  compile against PHP 8.5 either (upstream dormant since 2021, no release
  coming). Working fix lives in the open upstream PR's source branch
  (`remicollet/gmagick@issue-pointers`, vitoc/gmagick#59) — built from a
  commit-SHA pin instead of `pecl install`. GraphicsMagick kept over imagick
  deliberately (smaller attack surface) although imagick 3.8.1 builds fine on
  8.5; libvips is the designated opt-in successor (post-1.0).
- Also swept: `intl.error_level` is deprecated since 8.5 (startup warning) —
  dropped; `intl.use_exceptions` is the modern path.

### Long pre-push hooks get killed by the push's SSH connection timeout

- `git push` opens the SSH connection to GitHub BEFORE running the pre-push hook
  and keeps it idle while the hook runs. With a cold buildx cache the
  goss-test-build exceeds the server's idle window, the connection drops, and
  git kills the hook mid-build — captainhook reports the misleading
  `failed to execute: ./docker/hooks/pre-push-quality.sh` with the output cut
  mid-stream (looks like a hook crash, is a timeout).
- Remedy: run `./docker/hooks/pre-push-quality.sh` DIRECTLY first (no SSH window
  pressure) to warm the cache and see real results; the subsequent push then
  re-runs it fast, well inside the window. Symptom fingerprint: the same push
  "fails" at a random point mid-build on each attempt.
- Corollary: `docker builder prune` right before a Docker-touching push trades
  disk space for exactly this failure mode — prune images, keep the builder
  cache when a push is imminent.

### DB-major image bumps invalidate local dev volumes (postgres 17 → 18)

- After Renovate bumped postgres to 18.x, the local `zappzarapp-postgres-data`
  volume (initialized by 17) put the container into a restart loop
  (`database files are incompatible with server`) — and the goss RUNTIME tests
  in the pre-push hook then block every Docker-touching push with seemingly
  unrelated `pg_isready` failures. Check `docker compose ps` and the postgres
  logs first when goss suddenly fails on postgres.
- Dev remedy: drop the volume (`docker volume rm zappzarapp-postgres-data`) and
  let the new major initialize fresh; data worth keeping must be dumped with the
  OLD major's image BEFORE the bump. Boilerplate users will hit this on every PG
  major — candidate for TROUBLESHOOTING.md at the next docs pass.

### Shipped scheduled workflows: a job-level `if:` cannot suppress the run entry

- A `schedule:` trigger ALWAYS creates a workflow run; a job-level
  `if: vars.X != ''` only skips the job, so unconfigured repos still collect
  "skipped" entries in the Actions list (observed 2026-08-02: board sync at
  `*/30` = ~48 noise runs/day on this very repo). There is no workflow-level
  `if:` — GitHub offers no way to conditionally schedule.
- Boilerplate shipping defaults derived from this: (1) rare schedules (weekly
  Renovate) keep the cron + opt-in gate — one skipped run/week is fine and the
  gate turns a red failure into a skip; (2) frequent schedules should not ship
  enabled at all — the board sync became on-demand (`/tasks --sync` →
  `workflow_dispatch`) with a commented-out daily cron for teams that want
  background sync. Also weigh what the job DOES: a scheduled job that closes
  issues is too invasive for an enabled-by-default ship.
- `gh workflow disable <file>` fully silences a scheduled workflow without
  deleting it (counterpart: `gh workflow enable`) — the right hint for users who
  keep an opt-in workflow they never plan to configure.

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

### Semgrep SAST triage: inline `nosemgrep` beats a central ignore list (and placement is strict)

- **Context**: 27 open "Semgrep OSS" code-scanning alerts before v1.0 (the
  non-container remainder after the Trivy/Dockle triage). Breakdown: 18 nginx
  (`dynamic-proxy-host` 9 + `missing-internal` 9, both firing on the SAME 9
  `proxy_pass` lines), 3 `last-user-is-root`, 3 `var-in-href`, 1
  `cors-misconfiguration`, 1 `phpinfo-use`, 1 `gcm-no-tag-length`.
- **Verdict**: 26 were false-positives / intentional, 1 was a real (cheap) fix.
  - **nginx** — both rules are systematic FPs for a reverse proxy:
    `proxy_pass https://$upstream_*` uses a variable that is a **hardcoded**
    `set $upstream_* service:port` on the line above (the variable only defers
    DNS resolution to runtime — a documented nginx idiom), never user input →
    `dynamic-proxy-host` is moot; and the proxied locations ARE the app's
    intentionally public routes, so `missing-internal` is wrong by design.
  - **`last-user-is-root`** — node Dockerfile hits are the CI-only GOSS
    `test-api`/`test-framework` stages (never deployed; runtime stages end
    `USER node`); seaweedfs is the documented root→su-exec drop in the
    entrypoint (same intentional pattern as Dockle CIS-DI-0001).
  - **`var-in-href` / `phpinfo-use`** — dev-only DevDashboard, server-controlled
    values.
  - **`cors-misconfiguration`** — origin is reflected only after passing the
    allowlist check; the wildcard is an explicit `CORS_ORIGINS=*` opt-in.
  - **`gcm-no-tag-length` (the real one)** — FIXED not suppressed: pass
    `{ authTagLength: TAG_LENGTH }` to `createCipheriv`/`createDecipheriv`. The
    decrypt path already validated the tag length, so this is defence-in-depth,
    but it clears the rule at the crypto-API level (the "secure by default"
    call).
- **Method (mirrors the Trivy endgame but simpler)**: suppress **inline at the
  source** with `nosemgrep`, NOT a central list. Rationale: self-documenting,
  survives re-scans without churn (unlike per-alert GitHub dismissal, which
  re-opens on every SARIF re-fingerprint), and puts the reason next to the code.
  Every suppression states a reason; rule IDs may be shortened to the last
  segment (`nosemgrep: var-in-href -- ...` works, verified).
- **GOTCHA — placement is one line, exactly**: `nosemgrep` is honored only on
  the **same line as the finding** or the **single line immediately above** it.
  A two-line comment where the `nosemgrep` keyword sits on the FIRST line and a
  continuation on the second pushes the keyword TWO lines up → NOT honored (the
  cors alert survived the first pass exactly this way). Fix: keyword on the line
  directly above the flagged line; put any extra prose ABOVE the keyword line.
- **VERIFY LOCALLY — Semgrep matches CI (unlike Trivy)**:
  `pip install semgrep==1.172.0` (the CI-pinned version) in a venv, run the
  exact CI config
  (`--config p/security-audit --config p/secrets --config p/php --config p/typescript`)
  → reproduced all 27 findings byte-for-byte, and confirmed 0 after suppression.
  Registry rules download fresh + version-pinned, so no stale-DB divergence like
  the local Trivy DB had. Always re-scan to prove placement before pushing.
- Doc: the triage table lives in
  `.zappzarapp/docs/security/SECURITY-SCANNING.md` ("Semgrep (SAST)
  Suppressions").
- **CORRECTION (verified against GitHub): `nosemgrep` does NOT close the alert
  on its own.** `--json` omits suppressed findings (looks like 0), but `--sarif`
  (what CI uploads) INCLUDES them with `suppressions: [{state: accepted}]`.
  GitHub code scanning does NOT auto-dismiss from SARIF in-source suppressions,
  so the alerts stay **open**. The `advanced-security/dismiss-alerts@v2` action
  must run after `upload-sarif` (reads the sarif-id + sarif-file, dismisses the
  suppressed ones as "won't fix"). Lesson: verify against the actual GitHub
  alert state (`gh api .../code-scanning/alerts?state=open`), never just the
  local `--json`.

### "Semgrep OSS is reporting errors" = parse-warning notifications, not a real failure

- GitHub's tool-status error is triggered by **warning-level
  `toolExecutionNotifications`** in the SARIF, not only by
  `executionSuccessful:false`. Semgrep emits one per file its parser cannot
  read.
- Here: 36 warnings — 35 from `kubernetes/templates/*.yaml` (Helm templates
  `{{ .Values.* }}` are not valid standalone YAML) + 1 from a stray invalid-YAML
  file. Fix = `.semgrepignore` for `kubernetes/templates/` (inherent to Helm,
  not a workaround). `executionSuccessful` was `true` the whole time.
- **Diagnosis gotcha**: the SARIF downloaded via `code-scanning/analyses/{id}`
  is GitHub's REPROCESSED copy — it strips `invocations`. To see the real
  notifications, inspect the RAW semgrep output (`semgrep ... --sarif` locally
  with the CI-pinned version). Don't exclude committed source to silence
  warnings: `.claude/hooks/*.sh` stayed in SAST scope; only the unparseable
  files were excluded.

### hadolint `:latest` drift → DL3025 now fires on HEALTHCHECK CMD

- `make lint-docker` used `hadolint/hadolint` (implicit `:latest`). A newer
  hadolint applies **DL3025** ("JSON notation for CMD/ENTRYPOINT") to
  `HEALTHCHECK CMD` too, where shell form is required (env vars, pipes,
  `|| exit 1`) → `.hadolint.yaml`'s `failure-threshold: warning` failed the job.
- Fix: pin `hadolint/hadolint:v2.15.0` + ignore `DL3025` (the real container
  ENTRYPOINT/CMD already use exec form). Same `:latest`-linter-drift pattern as
  the other pinned tools — pin mutable linter images.

### pnpm audit matches advisories by GHSA, not CVE

- `auditConfig.ignoreCves: [CVE-2025-5891]` did NOT suppress the pm2 ReDoS,
  because pnpm reports/matches it by its GHSA (`GHSA-x5gf-qvw8-r2rm`) — the two
  IDs are the SAME advisory. Use `ignoreGhsas` for pnpm-audit exceptions.
- Resolved properly instead: bumped pm2 `^6 → ^7.0.3` (fixes the ReDoS at the
  root, on Node 24 which satisfies pm2 7's `>=18`; v7's breaking changes are
  internal dependency internalization) → both ignore entries removed, the
  KNOWN-VULNERABILITIES pm2 entry deleted. pm2 is dev-only (prod runs `node`
  directly), validated pm2 7 + ecosystem.config.cjs still load.

### `make lint-config` yamllint glob did not recurse — plus a config was missing

- `cytopia/yamllint ./**/*.yaml` only matched the TOP level (`sh` has no
  globstar, so `**` == `*`), so nested YAML (kubernetes, docker/*, .github/…)
  was never linted. Fix: pass `.` (recurse) + pin `cytopia/yamllint:1`.
- Recursing surfaced 232 `line-length` hits (long CI/compose lines are fine) and
  needed a `.yamllint`: `extends: relaxed`, `line-length: disable`, `ignore:`
  Helm templates + `pnpm-lock.yaml` + build dirs. yamllint auto-discovers
  `.yamllint` from cwd.

### A non-consumed `.yaml` doc file drifts and misleads — delete it, document at the code

- `.claude/hooks/change-watch.yaml` was a "reference" mirroring the hard-coded
  `DOC_EXTRACTORS` array in `change-watch.sh`. Nothing read it, it was invalid
  YAML, and it had ALREADY drifted (documented an old skills pattern the script
  no longer uses). A drifted reference is worse than none.
- Fix: delete it; the one bit not already in the script (the `use_filename`
  value meanings) moved into a comment next to the array. Documentation belongs
  WITH the code it describes, in one place, so it cannot drift. Distinguish this
  (a fixable format/design smell) from a legitimate exclusion like the Helm
  templates. See [[feedback_flag_suboptimal_dont_bandaid]].

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

## Kubernetes / Helm Chart

### A template `{{- else }}` after volumeClaimTemplates emits a DUPLICATE pod `volumes:` key

- **The bug (5 of 6 StatefulSets affected, latent until persistence is
  disabled)**: the templates rendered the data-emptyDir fallback as an
  `{{- else }}` branch of the `volumeClaimTemplates` conditional, indented at
  pod-spec level. With persistence off, postgres/mariadb/meilisearch/rabbitmq
  rendered a SECOND `volumes:` key in the pod spec — invalid YAML under strict
  parsing; under last-wins parsing the pod silently loses its tmp/run/secret
  volumes while volumeMounts still reference them → API server rejects the pod.
  seaweedfs "worked" only because its branch emitted bare list items that
  happened to continue the pod volumes list; elasticsearch only because it had
  no other pod volumes.
- **Fix pattern**: put the fallback INSIDE the pod volumes list as
  `{{- if not .persistence.enabled }} - name: data / emptyDir {{- end }}` and
  reduce volumeClaimTemplates to a plain `{{- if }}`.
- **Detection gotcha**: `helm lint`/`helm template` never catch this (lint uses
  default values = persistence on; template does not parse its own output), and
  PyYAML's safe_load silently takes last-wins. Verify with a strict
  duplicate-key loader over the FULL render matrix (all services on × all
  persistence off).

### `drop: [ALL]` breaks entrypoints that su-exec, even as root

- A container that starts as root but drops privileges via `su-exec`/`gosu`
  needs CAP_SETUID/CAP_SETGID — root does NOT bypass dropped capabilities. The
  chart's hardcoded seaweedfs securityContext (`drop: [ALL]`, no adds) made the
  service unstartable in k8s whenever enabled (entrypoint does
  `exec su-exec 1000:1000 weed …`). redis/postgres/mariadb had the right caps in
  values all along — the hardcoded optional-service blocks just never got them.
  Values-driven securityContext per service (now uniform) makes this reviewable
  in ONE file instead of six templates.
- Only elasticsearch can run `runAsNonRoot: true` — its Dockerfile pins
  `USER 1000:0`. All other optional-service images start as root (verified via
  `docker buildx imagetools inspect <ref> --format '{{json .Image}}'` — fetches
  remote Config.User without pulling layers).

### Production renders now fail on a resolved `latest` tag

- `zappzarapp.imageTag` helper `fail`s when `global.env == "production"` and the
  resolved tag is `latest`: with `imagePullPolicy: Always` that would deploy
  whatever the registry currently holds. Dev (`imagePullPolicy: Never`, local
  images) is unaffected; the `make k8s-deploy` preflight guard stays dev-only,
  this guard is its production counterpart. `make k8s-build`'s empty-SERVICES
  error branch re-runs `helm template` visibly so the real helm error (e.g. this
  guard) is not swallowed by the `2>/dev/null` derive.

### Chart ↔ Compose image-tag contract: multi-target services never produced `:latest`

- The chart references every image as `zappzarapp-<svc>:latest`, but Compose
  tags the four multi-target services by BUILD TARGET
  (`zappzarapp-nginx:${NGINX_TARGET}`, php/node/node-backend likewise, pinned to
  `:development` by the override) — only the single-target services (postgres,
  redis, optional) get Compose's implicit `:latest`. So `make k8s-build` built
  images the chart could never reference and the k8s-deploy preflight flagged
  nginx/php/node as missing right after building them.
- The DEVELOPMENT-target images would be the wrong content anyway: only
  `production-base` COPYs the app source into the image; the development targets
  expect Compose bind mounts, and the chart mounts config/secrets/tmp but NO
  source. A dev-tagged image in k8s = a pod without code.
- **Fix**: `make k8s-build` splits chart-derived services — single-target ones
  still delegate to `make build`, the multi-target four build via the
  compose.production.yaml overlay (same NODE_MODE→target map as `make build`'s
  production branch) and are retagged `<target>` → `:latest`. node-backend
  builds first: the php/nginx production stages copy its baked assets via the
  `node-backend-assets` docker-image context, so
  `zappzarapp-node-backend:latest` must exist before those builds start.

### Compose v5 (Alpine ≥ 3.24) requires the buildx PLUGIN — silent legacy fallback (2026-08-02)

- Alpine 3.24 ships `docker-cli-compose` **5.x** (3.23 had 2.40.x). Compose v2
  had BuildKit built in; **v5 requires the separate `docker-cli-buildx` plugin
  and silently falls back to the LEGACY builder without it** (warning: "Docker
  Compose requires buildx plugin to be installed", then
  `Sending build context to Docker daemon` spam). The legacy builder cannot
  process our BuildKit-only Dockerfiles (`COPY --chmod`, `# syntax=`) →
  `make build` fails.
- Hit when the alpine 3.24 Renovate bump landed in `docker/bats/Dockerfile`: CI
  "BATS Integration Tests" red on `make build creates Docker images`, while
  everything host-side stayed green (host has buildx). The bats image had NEVER
  contained buildx — Compose 2.x just didn't need it.
- **Fix**: `docker-cli-buildx` in the bats image apk list +
  `docker buildx version` in the test-stage self-check so a missing plugin fails
  the image build instead of the downstream integration job.
- **Generalization**: any container that drives `docker compose build` via the
  host socket needs the buildx plugin alongside `docker-cli-compose` once its
  base ships Compose ≥ 5.

### Renovate cannot update FROM tags composed from multiple ARGs (2026-08-02)

- Renovate's dockerfile manager RESOLVES ARG-composed FROM lines fine during
  extraction (`FROM redis:${REDIS_VERSION}-alpine${ALPINE_VERSION}` is detected
  as `redis:8.6-alpine3.23` with a correct update available), but the WRITE-BACK
  fails when the tag is assembled from more than one ARG — or from an ARG plus a
  literal suffix (`rabbitmq:${RABBITMQ_VERSION}-management-alpine`). Symptom:
  permanent "Error updating branch: update failure" in the run log, the branch
  lands under "Errored" on the dependency dashboard forever; debug log shows
  `expectedValue` vs `foundValue` mismatch ("Value is not updated").
- The failure is silent about its cause: nothing flags the Dockerfile pattern —
  found only by reading the debug log of the workflow run.
- **Fix/convention**: pin the FULL image tag in ONE ARG
  (`ARG REDIS_VERSION=8.6-alpine3.23` + `FROM redis:${REDIS_VERSION}`). Renovate
  treats `-alpine3.23`/`-fpm-alpine3.23`/`-management-alpine` as a compatibility
  suffix and preserves it on updates. Single-ARG full-tag FROMs (goss, mailpit,
  seaweedfs, …) always worked — only composed ones break.
- Applied to redis/php/node/postgres/mariadb/rabbitmq (branch
  `fix/renovate-dockerfile-version-args`); the separate per-service
  `ARG ALPINE_VERSION` is gone from those six ("consistent Alpine across
  services" was already fiction — bats had drifted to 3.24). bats/nginx keep
  `ALPINE_VERSION` because there it IS the full tag of `FROM alpine:…`.

### `ENV=production make <target>` is CLOBBERED by `.env` sourcing (RESOLVED 2026-08-02)

- Every Makefile recipe sources `.env` (which sets `ENV=development`) AFTER the
  caller's environment, so `ENV=production make k8s-build` (and `make build`, as
  KUBERNETES.md suggests) silently runs the development path. The supported
  switch is editing `ENV` in `.env` — but docs advertise the env-var form and
  nothing warns. Found when a prod-guard verification unexpectedly kicked off a
  full dev image build. Needs a Makefile-wide precedence decision (capture
  caller ENV before sourcing, or fix the docs) — see todo.md.
- **RESOLVED (branch `fix/make-env-clobbering`):** caller ENV now wins. All ~60
  inline `. ./.env` sites were consolidated onto `LOAD_ENV` (which also fixed
  those sites ignoring `.env.production`/`.env.local`), and `LOAD_ENV`
  re-applies a make-level `CALLER_ENV` after each sourcing step. Guard rails:
  only `development`/`production` are honored — a command-line `ENV=prod` is a
  hard `$(error)` (typo protection), an unsupported value inherited from the
  environment is warned about and ignored (POSIX shells use `$ENV` for a
  startup-file path; it must not flip the build mode). Precedence: caller ENV >
  `.env.local` > `.env.production` > `.env`. BATS coverage in `make-env.bats`
  ("Caller ENV Precedence" section) via `make validate-env` output.

### Renovate custom plain-text datasource: three defaults silently kill the watcher (2026-08-03)

- Context: PECL-release watcher for gmagick (`customDatasources` on
  `https://pecl.php.net/rest/r/gmagick/latest.txt`, format `plain`, regex
  customManager on a watch-only `ARG GMAGICK_PECL_BASELINE` in
  `docker/php/Dockerfile`). Three independent defaults each would have made it a
  dead letter — all three only surfaced in `make renovate` dry-runs:
- **`composer` versioning rejects PECL's separator-less RC form**: `2.0.6RC1` →
  "unsupported/unversioned value", dep skipped entirely. Fix: a `regex:`
  versioning with a `prerelease` group, which orders `2.0.6RC1 < 2.0.6 < 2.0.7`
  correctly.
- **`ignoreUnstable` (default true) filters new RCs**: from a stable-looking
  current value, a future `2.0.7RC1` would be silently dropped — but a new RC is
  the most likely shape of the next release from a dormant upstream. Set
  `ignoreUnstable: false` in the package rule.
- **`minimumReleaseAge` + plain datasource = pending forever**: plain-text
  datasources carry no `releaseTimestamp`, and the default
  `minimumReleaseAgeBehaviour=timestamp-required` marks timestamp-less releases
  as pending — the update never leaves the pending state, so no PR is ever
  created (log line: "Marking 1 release(s) as pending"). Set
  `minimumReleaseAge: "0 days"` in the package rule.
- **Verification pattern**: falsification test — lower the baseline ARG to an
  older version, dry-run must now propose the real latest (proves the live
  fetch + versioning + comparison chain), then restore. `updates: []` alone
  proves nothing.

### GitLab CI: jobs do NOT share a Docker daemon — build-then-scan across jobs is a mirage (2026-08-03)

- Every job gets its own `docker:dind` service; images built in a `build` stage
  job are GONE in the scan jobs (the trivy jobs did not even have a dind service
  — `trivy image <name>` had no daemon at all). The standalone security-scan
  file's build:images → scan:*-image design could never work.
- **Fix pattern** (mirrors the GitHub matrix): one `parallel: matrix:` job per
  image that builds exactly what it scans inside its own dind (cross-ref-free
  `--target`, repo-root context) and installs the pinned trivy release binary. A
  single `TRIVY_VERSION` variable feeds both the job image
  (`aquasec/trivy:${TRIVY_VERSION}` — gitlabci docker manager skips it as
  contains-variable) and the binary download → one regex customManager on
  github-releases is the SSOT.
- **ENTRYPOINT gotcha**: images that ship their tool as ENTRYPOINT
  (aquasec/trivy, zricethezav/gitleaks) get the GitLab job script passed to that
  entrypoint → `unknown command "sh" for "trivy"`. Needs
  `image: {name: …, entrypoint: [""]}`.
- **`make secrets` empty-file gotcha**: the generators pipe
  `openssl | tr | head > file`; the pipeline exit code is head's, so a missing
  openssl "generated" EMPTY secret files with a green message (seen in the
  alpine docker:29-cli job before openssl was added to the apk list). Guard
  added: `secrets` now fails fast when openssl is absent.
- **needs on rules-filtered jobs**: `needs: [build:images]` where build:images
  is excluded by rules (e.g. include-usage on a push pipeline) makes pipeline
  creation fail — heavy jobs carry their own rules instead of needs-chains.
- **Round 2 of the same catalogue (first run with working jobs)**:
  - Stale `TRIVY_IGNOREFILE: .trivyignore.yaml` variable — the file was retired
    for `.trivy/ignore-policy.rego`, and trivy HARD-FAILS when an explicitly
    named ignore file is absent (the default `.trivyignore` is skipped
    silently). Killed every trivy invocation in one stroke.
  - `make security-zap-scan`'s service check ran a BARE `docker compose ps` (no
    `$(LOAD_ENV)`): with the pipeline's own `COMPOSE_PROJECT_NAME` in the
    environment it queried the WRONG project ("No services running" while all
    containers were up under the .env project name). Any recipe line that talks
    to compose must go through `$(LOAD_ENV)`; check via `ps -q --status running`
    instead of grepping STATUS text.
  - `make ssl-internal` on alpine without the `acl` package only WARNS about
    missing setfacl — then the unprivileged production containers (redis, php,
    rabbitmq) cannot read the 600-mode TLS keys and crash at startup, surfacing
    as instantly-"unhealthy" optional dependencies in compose up. CI images need
    `acl` alongside openssl.
- **Round 3 — the php production container could NEVER start**: the production
  entrypoint wrote the timezone INI into `/usr/local/etc/php/conf.d/`, but
  production runs `read_only: true` and compose always sets `TZ` (`${TZ:-UTC}`)
  → the write fails, `set -e` kills the container instantly, compose reports an
  "unhealthy" optional dependency. Repro:
  `docker run --rm --read-only --tmpfs /tmp -u 82:82 -e TZ=UTC zappzarapp-php:production true`.
  Fix: write the INI to /tmp (tmpfs) and append it via
  `PHP_INI_SCAN_DIR=":/tmp/php-conf.d"` (leading colon keeps the compiled-in
  conf.d). Related CI/make traps fixed alongside:
  - `exec | tr || echo unknown` swallows the exec failure (pipe exit code is
    tr's) → probe yields "" instead of "unknown"; and a `read -p` prompt in a
    non-interactive CI shell dies cryptically → tty-guard with a hard abort.
  - scan:dependencies chicken-and-egg: the dev entrypoints refuse to start while
    dependencies are missing — which is what the job installs via exec.
    `NODE_MODE=idle` + `PHP_SKIP_DEPENDENCY_CHECK=1` are the designed CI escape
    hatches.
  - security-report summary: `${scan^}` is a bashism (alpine /bin/sh: "bad
    substitution"), and `paste | bc` counted on a bc that alpine does not ship —
    the `|| echo 0` fallback would have reported every image as "Clean".
    Replaced with a jq-only sum.

### GitLab CI: unquoted `key: value` colon inside a script line = config rejected (2026-08-03)

- `- echo "Repository: $CI_PROJECT_PATH"` in a `script:` list is parsed by YAML
  as a single-entry MAPPING (`{'echo "Repository': '$CI_PROJECT_PATH"'}`), not a
  string. GitLab then refuses to create the pipeline: "jobs:...:script config
  should be a string or a nested array of strings" — pipeline record exists with
  `status=failed`, zero jobs, `yaml_errors=null`; the actual message is only in
  GraphQL `pipeline.errorMessages`.
- Bit us in `.gitlab/security-scan.gitlab-ci.yml` (`scan:zap`): the file is not
  included in the main pipeline, so it was never validated until its first
  standalone run — a lint gap for any standalone CI file. Quick local check:
  parse the YAML and assert every `script`/`before_script`/`after_script` item
  is a string.

---

## Last Updated

2026-08-03 (added: Renovate plain-datasource watcher — composer versioning
rejects `2.0.6RC1`, ignoreUnstable filters new RCs, timestamp-less releases pend
forever under minimumReleaseAge; falsification-test pattern for watchers; GitLab
standalone CI file rejected at creation over an unquoted colon in an echo line,
error only visible via GraphQL errorMessages; standalone security-scan never-ran
rot — jobs don't share a Docker daemon so build-then-scan across jobs can't work
→ self-building parallel:matrix rework, ENTRYPOINT images need entrypoint:[""],
`make secrets` silently wrote empty files when openssl was missing → fail-fast
guard; round 2: stale TRIVY_IGNOREFILE hard-fails every trivy call, zap-scan's
bare `docker compose ps` queried the wrong project without LOAD_ENV, missing
acl/setfacl crashes unprivileged prod containers on 600-mode TLS keys; round 3:
php production container could NEVER start — timezone-INI write vs read_only
rootfs → PHP_INI_SCAN_DIR/tmpfs fix; exec|tr pipe swallows probe failure, read
-p dies non-interactively, NODE_MODE=idle + PHP_SKIP_DEPENDENCY_CHECK=1 for the
dependency-audit chicken-and-egg, ${scan^}/bc report bashisms)

2026-08-02 session 2 (added: k8s chart hardening — duplicate pod `volumes:` key
from `{{- else }}` after volumeClaimTemplates; redis Deployment→StatefulSet;
su-exec needs SETUID/SETGID despite drop:[ALL]; values-driven securityContext
for optional services; production render fails on resolved `latest` tag;
chart↔Compose tag contract — k8s-build now builds the multi-target services from
production targets and retags to `:latest`; `ENV=production make …` clobbered by
`.env` sourcing)

2026-08-02 (added: k8s SSOT — Helm optional services (mariadb/redis/mercure/
meilisearch/elasticsearch/seaweedfs/rabbitmq/mailpit) switched from raw upstream
images to the hardened `zappzarapp-*` built images, so the upstream version
lives ONLY in `docker/<svc>/Dockerfile` ARG and never drifts in values.yaml
again; Compose already built all of these — k8s was the outlier pulling stock
images = a security regression vs Compose. LATENT BUG surfaced+fixed: the
db/optional templates built the image string INLINE
(`{{ .Values.X.image.repository }}:...`) instead of via the
`zappzarapp.imageRepository` helper, so with global.imageRegistry set they
rendered WITHOUT the registry prefix (postgres/redis had this latent too) →
converted all 9 to the helper. Renovate: helm-values still extracts them but a
`matchPackageNames:["zappzarapp-*"] enabled:false` rule stops lookups of the
non-published internal images; the old Dockerfile↔k8s grouping rule's raison
d'être is gone. Optional services sit behind Compose profiles → plain
`make build` skips them; automated via new `make k8s-build` (derives the enabled
set from `helm template` — image lines only, else the `zappzarapp-`
Helm-fullname prefix on secrets/configmaps/PVCs pollutes the match — then
delegates to `make build <svcs>`) + a `make k8s-deploy` preflight guard that
fail-fasts in development when a required `zappzarapp-*:tag` image is absent
from the local daemon (instead of a late ImagePullBackOff). Guard skips in
production — registry is authoritative there. BATS: k8s targets had ZERO
coverage — added dry-run tests (k8s-build/deploy/remove/status) + help-presence.
GOTCHA that forced a Makefile tweak: GNU make EXECUTES recipe lines containing
the recursive-make variable even under `make -n` (recursive- make special case),
so `make -n k8s-build` actually ran `helm` → in the helm-less BATS image it hit
the guard's `exit 1` and the dry-run test failed. Fix: call the delegated build
with a LITERAL `make` (not the variable) so the whole recipe line is
printed-not-executed under `-n` → inert & CI-safe. Corollary: never put the
recursive-make variable in a recipe line — not even in a trailing comment — if
you want `make -n` to stay inert; make scans the raw line for it before the
shell sees the comment. goss N/A here — no image/ Dockerfile change.)

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
