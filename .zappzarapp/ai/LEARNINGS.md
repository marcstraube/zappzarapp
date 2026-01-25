# Project Learnings

Aggregated knowledge from all development sessions. Use this as reference to
avoid repeating mistakes.

---

## Docker & Containers

### Bind Mount Directory Bug

**Problem:** Docker creates directories instead of files when bind mount target
doesn't exist on host.

**Symptoms:**

- `Error: EISDIR: illegal operation on a directory, read` when Node tries to
  read a lockfile
- `cannot load certificate: is a directory` from nginx
- Container restart loops

**Affected files (boilerplate):**

- `composer.lock` / `pnpm-lock.yaml` (if deleted or never created)
- `docker/certs/nginx/` and `docker/certs/internal/` (if not generated)
- `secrets/*` files (if not generated)

**Fix:** Ensure files exist before `docker compose up`:

```bash
# For lockfiles (if directories)
docker run --rm -v ./:/app alpine sh -c "rm -rf /app/composer.lock && touch /app/composer.lock"

# For certs/secrets: run make targets
make ssl-generate
make secrets
# Or: make setup (runs all)
```

**Root cause in compose.yaml:** Bind mounts like
`./file.txt:/container/file.txt` create `/container/file.txt` as directory if
`./file.txt` doesn't exist.

**Prevention:** Run `make setup` on fresh clone or after deleting generated
files.

### Secrets Handling

- **Docker Compose ignores `mode`, `uid`, `gid` for secrets** - these options
  only work in Docker Swarm. Use bind mounts with chmod 644 for Compose.
- **`cap_drop: ALL` affects root too** - removes ALL capabilities, including
  from root. Need `DAC_OVERRIDE` for root to read files owned by others.
- **`no-new-privileges` blocks `su-exec`** - run containers as target user
  directly via `user: "UID:GID"` instead.
- **Secrets flow in Compose**: Entrypoint (root) copies secrets to
  `/tmp/secrets/` with mode 0444, app processes read from there.

### Elasticsearch Security

- **Secret-Dateiberechtigungen müssen 400 oder 600 sein**: Elasticsearch
  verlangt striktere Berechtigungen als andere Services. Während Redis/RabbitMQ
  mit 644 funktionieren, lehnt Elasticsearch dies ab:

  ```text
  ERROR: File ... must have file permissions 400 or 600, but actually has: 644
  ```

### Volume Permissions

- **Dev↔Prod volume incompatibility**: Different UIDs (dev: 1000, prod:
  postgres=70, redis=999). Delete volumes when switching environments.
- **Read-only container filesystems**: Production containers have read-only
  rootfs. Can't copy binaries at runtime - use build-time installation.

### Docker Socket Access Without Root

**Problem:** Running containers with `--user root` changes file ownership to
root:root on bind-mounted volumes.

**Solution:** Use host user UID/GID with Docker group access:

```bash
docker run --rm \
    -v /var/run/docker.sock:/var/run/docker.sock \
    -v "$(pwd):$(pwd)" -w "$(pwd)" \
    --user "$(id -u):$(id -g)" \
    --group-add "$(stat -c %g /var/run/docker.sock)" \
    my-image command
```

**Why it works:**

- `--user "$(id -u):$(id -g)"` - runs as host user, files keep correct ownership
- `--group-add "$(stat -c %g /var/run/docker.sock)"` - adds docker group for
  socket access

**Note:** The `stat -c %g` syntax is Linux-specific. On macOS, use `stat -f %g`.

### Build & Targets

- **Multi-stage builds need explicit targets**: `NODE_TARGET`, `NGINX_TARGET`,
  `PHP_TARGET` must be set based on `NODE_MODE`.
- **Asset source for nginx/php**: Changed from `zappzarapp-node:latest` to
  `zappzarapp-node-backend:latest` for Vite assets.
- **nginx production build requires node-backend first**: The nginx Dockerfile
  `production` stage has `COPY --from=zappzarapp-node-backend:latest`. This
  image must exist before nginx is built. In development mode, this is skipped
  (different target). For CI/production builds:

  ```bash
  # Build and tag node-backend first
  docker compose --profile node-backend build node-backend
  docker tag <project>-node-backend:api zappzarapp-node-backend:latest
  # Then build nginx with DOCKER_BUILDKIT=0 (sees local images)
  DOCKER_BUILDKIT=0 docker compose build nginx
  ```

### Networking

- **Nginx/PHP in Kubernetes = TCP**: Unix sockets don't work across pods. Use
  TCP (php:9000) instead.
- **`fsGroup` for StatefulSets**: PostgreSQL needs `fsGroup: 70` in
  podSecurityContext for volume permissions.

---

## Makefile

### Shell Compatibility

- **Use `SHELL := bash` not `/bin/bash`**: PATH-based lookup works on Linux,
  macOS, WSL, and Git Bash.
- **Brace expansion `{a,b}` requires Bash**: POSIX sh doesn't support it. Our
  Makefile uses Bash explicitly.
- **Windows support**: Requires WSL or Git Bash. Native cmd.exe/PowerShell not
  supported.

---

## Node.js

### pnpm Workspaces

- **`pnpm prune --prod` breaks workspace symlinks** - use
  `rm -rf node_modules && pnpm install --prod` instead.
- **EBUSY on pnpm-lock.yaml**: Docker bind-mounts don't support atomic rename.
  Use `--no-install` flags, run `make pnpm-sync` separately.

### pnpm Updates

- **pnpm version is pinned in `package.json`** via
  `"packageManager": "pnpm@x.x.x"`. Corepack enforces this version.
- **To update pnpm**: Use `make pnpm-upgrade` (fetches latest version via
  container).
- **Why container?**: Local Node/pnpm versions may differ. Container is the
  source of truth.
- **Container rebuild doesn't help**: Even with `--no-cache --pull`, the
  `packageManager` field takes precedence.

### pnpm Build Scripts Security

- **"Ignored build scripts" warning is normal**: pnpm blocks native binary
  compilation by default for security (prevents malicious postinstall scripts).
- **Affected packages**: `@parcel/watcher`, `esbuild` - use native binaries for
  performance but fall back to WASM/JS alternatives.
- **IDE integration unaffected**: TypeScript types are pure JS, no native code
  needed. Autocomplete, type checking work without native binaries.
- **No action required**: Use `make` targets (run in container) instead of local
  `pnpm` commands - containers have pre-approved builds.
- **To approve locally** (if needed): Run `pnpm approve-builds` to explicitly
  allow build scripts for specific packages.

### ESLint Configuration

- **Type-aware rules need `project` in parserOptions**: Rules like
  `no-unnecessary-condition` require `project: './tsconfig.json'`.
- **`@typescript-eslint/no-unnecessary-condition`**: Catches redundant typeof
  checks, always-true/false conditions. May have false positives with closures
  (use eslint-disable comment with explanation).
- **`@typescript-eslint/unbound-method`**: Disable for test files -
  `vi.mocked()` returns unbound methods by design.
- **Test file exceptions**: Create separate ESLint config block for
  `tests/**/*.ts` with relaxed rules.

### IDE vs. Linter Parity

**Problem:** PHPStorm/WebStorm show warnings that ESLint doesn't catch.

**Solution:** Use `eslint-plugin-sonarjs` for IDE parity:

```javascript
// eslint.config.js
import sonarjsPlugin from 'eslint-plugin-sonarjs';

// In plugins:
sonarjs: sonarjsPlugin,

// In rules:
'sonarjs/prefer-immediate-return': 'warn',
```

**Mapping:**

| IDE Warning                | ESLint Rule                          |
| -------------------------- | ------------------------------------ |
| "Redundant local variable" | `sonarjs/prefer-immediate-return`    |
| "Can be simplified"        | Various `@typescript-eslint/*` rules |

**Workflow:** Always check IDE diagnostics via `mcp__ide__getDiagnostics` after
changes - linter checks alone may miss IDE-specific inspections.

### Composer Updates

- **Composer is baked into PHP image** at build time via `FROM composer:2`.
- **To update Composer**: Run `make build-php` - pulls latest `composer:2`
  image.
- **No runtime pinning**: Unlike pnpm/corepack, Composer version is fixed at
  build time.
- **No `composer-upgrade` target needed**: Container rebuild handles it
  automatically.

### Sync vs Install Targets

- **`*-install` targets**: Use `--frozen-lockfile` for CI/Production
  reproducibility. Fails if lockfile doesn't match package.json.
- **`*-sync` targets**: No frozen-lockfile, for after package.json changes
  (scaffolds, branch switches).
- **`sync-lockfiles`**: Convenience target that syncs both Composer and pnpm
  lockfiles.

### Port Configuration

- **Nuxt/Nitro production**: `devServer.port` only applies to dev. Use
  `NITRO_PORT` or `PORT` env var for production.
- **Dual-container architecture**: `node` (frontend, port 3001) + `node-backend`
  (Express API, port 3000).

---

## PHP

### PHP 8.4 Features

- **`readonly class`**: PHP 8.2+ allows marking entire class as `readonly` — all
  properties become automatically readonly, no need for `readonly` keyword on
  each property.
- **Asymmetric visibility**: PHP 8.4 introduces `public private(set)` for
  properties that are publicly readable but only privately writable. Requires
  `@noinspection PhpPublicPropertyModifierCanBeOmittedInspection` on class level
  because PhpStorm suggests removing `public` (which would change the API).
- **`resource` is not a native PHP type**: For functions returning
  `resource|false` (like `fsockopen`), use `mixed` return type and add
  `@noinspection PhpMixedReturnTypeCanBeReducedInspection` to suppress "type can
  be narrowed" warnings.

### Database Connections

- **MariaDB requires explicit PDO SSL options**: Use
  `DatabaseConfig::getPdoSslOptions()` for SSL connections.
- **Health checks need SSL too**: `HealthCheck.php` must use same SSL config as
  application.

### PHP-DI Container

- **Autowiring resolves interface dependencies automatically**: When binding
  `InterfaceA => ClassA`, other classes with `InterfaceA` constructor parameters
  are resolved automatically. No need for explicit `constructorParameter()`.
- **Interface bindings ARE required**: Autowiring can't guess which
  implementation to use for an interface. Always register interface→class
  mappings in `config/container.php`.

---

## Nginx

### Dynamic Configuration

- **`ENABLE_PHP` affects routing**: Entrypoint sets `INDEX_DIRECTIVE` and
  `TRY_FILES_FALLBACK` dynamically.
- **Health check snippets**: Write to `/run/nginx/snippets/` (tmpfs) since
  `/etc/nginx/snippets/` is read-only.
- **Production templates**: Process with `envsubst` at runtime, same as
  development.

---

## Testing

### GOSS Two-Phase Strategy

| Phase      | When                         | What                     | How                |
| ---------- | ---------------------------- | ------------------------ | ------------------ |
| Build-time | `docker build --target test` | Files, configs, commands | GOSS in Dockerfile |
| Runtime    | `make goss-test`             | HTTP, TLS, connectivity  | Shell script       |

### Bash Scripting

- **`((var++))` fails with `set -e` when var=0** - use `var=$((var + 1))`
  instead.

### Unit Test Best Practices

- **No network I/O in unit tests**: Mock all external connections. Tests that
  try real connections (e.g., `redis://nonexistent:9999`) generate warnings and
  are actually integration tests.
- **Test data at method start**: Define test variables at the beginning of test
  methods for readability, even if only used in closures. PHPStorm's "variable
  only used in closure" warning can be disabled for test scopes.
- **RandomException in PHP 8.2+**: `random_bytes()` can throw
  `\Random\RandomException`. In tests, either catch it or add `@throws` PHPDoc.

---

## Kubernetes

### Security Context

- **SETUID/SETGID capabilities for su-exec**: Add to securityContext if
  container uses su-exec.
- **Network policies**: Update when adding new services (node-backend needed
  separate policies).

### ConfigMaps

- **nginx.conf and php-fpm.conf**: Must be ConfigMaps in K8s (can't use
  Docker-specific entrypoint templating).

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

### Framework Scaffolding

1. Stop containers (`make down`)
2. Run scaffold (`make frontend-nuxt`)
3. Sync lockfile (`make pnpm-sync`)
4. Start containers (`make up`)

### Environment Switching

```bash
# Dev → Prod or Prod → Dev
make down
docker volume rm $(docker volume ls -q | grep zappzarapp)
# Update .env
make build && make up
```

---

## IDE Configuration (PHPStorm)

### Inspection Profiles

- **Project-wide inspections in `.idea/inspectionProfiles/`**: These are shared
  via git, ensuring all developers have the same warnings.
- **Scopes for targeted rules**: Create `.idea/scopes/Tests.xml` to define test
  file patterns, then reference in inspection profile to disable rules for
  tests.
- **"Variable only used in closure"**: Disable for test scope - test data
  variables at method start improve readability even if only used in closures.

### Scope Configuration

```xml
<!-- .idea/scopes/Tests.xml -->
<component name="DependencyValidationManager">
  <scope name="Tests" pattern="file:tests//*" />
</component>
```

```xml
<!-- .idea/inspectionProfiles/Project_Default.xml -->
<inspection_tool class="PhpVariableUsedOnlyInClosureInspection" enabled="true">
  <scope name="Tests" level="INFORMATION" enabled="false" />
</inspection_tool>
```

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

## Last Updated

2026-01-24 (cleanup: removed duplicated docs - pm2 CVE to KNOWN-VULNERABILITIES,
NODE_MODE to .env, markdown tables to standards/markdown.md)
