# Project Learnings

Aggregated knowledge from all development sessions. Use this as reference to avoid repeating mistakes.

---

## Docker & Containers

### Secrets Handling

- **Docker Compose ignores `mode`, `uid`, `gid` for secrets** - these options only work in Docker Swarm. Use bind mounts with chmod 644 for Compose.
- **`cap_drop: ALL` affects root too** - removes ALL capabilities, including from root. Need `DAC_OVERRIDE` for root to read files owned by others.
- **`no-new-privileges` blocks `su-exec`** - run containers as target user directly via `user: "UID:GID"` instead.
- **Secrets flow in Compose**: Entrypoint (root) copies secrets to `/tmp/secrets/` with mode 0444, app processes read from there.

### Volume Permissions

- **Dev↔Prod volume incompatibility**: Different UIDs (dev: 1000, prod: postgres=70, redis=999). Delete volumes when switching environments.
- **Read-only container filesystems**: Production containers have read-only rootfs. Can't copy binaries at runtime - use build-time installation.

### Build & Targets

- **Multi-stage builds need explicit targets**: `NODE_TARGET`, `NGINX_TARGET`, `PHP_TARGET` must be set based on `NODE_MODE`.
- **Asset source for nginx/php**: Changed from `zappzarapp-node:latest` to `zappzarapp-node-backend:latest` for Vite assets.

### Networking

- **Nginx/PHP in Kubernetes = TCP**: Unix sockets don't work across pods. Use TCP (php:9000) instead.
- **`fsGroup` for StatefulSets**: PostgreSQL needs `fsGroup: 70` in podSecurityContext for volume permissions.

---

## Node.js

### pnpm Workspaces

- **`pnpm prune --prod` breaks workspace symlinks** - use `rm -rf node_modules && pnpm install --prod` instead.
- **EBUSY on pnpm-lock.yaml**: Docker bind-mounts don't support atomic rename. Use `--no-install` flags, run `make pnpm-sync` separately.

### Sync vs Install Targets

- **`*-install` targets**: Use `--frozen-lockfile` for CI/Production reproducibility. Fails if lockfile doesn't match package.json.
- **`*-sync` targets**: No frozen-lockfile, for after package.json changes (scaffolds, branch switches).
- **`sync-lockfiles`**: Convenience target that syncs both Composer and pnpm lockfiles.

### NODE_MODE Architecture

| Mode | Frontend | Backend | Description |
|------|----------|---------|-------------|
| assets | Vite HMR | - | Static assets only |
| api | - | Express | API only |
| assets-api | Vite HMR | Express | Full-stack (Vite + Express) |
| framework | Nuxt/Next | - | SSR framework only |
| framework-api | Nuxt/Next | Express | SSR + API |
| idle | - | - | Container sleeps |

### Port Configuration

- **Nuxt/Nitro production**: `devServer.port` only applies to dev. Use `NITRO_PORT` or `PORT` env var for production.
- **Dual-container architecture**: `node` (frontend, port 3001) + `node-backend` (Express API, port 3000).

---

## PHP

### Database Connections

- **MariaDB requires explicit PDO SSL options**: Use `DatabaseConfig::getPdoSslOptions()` for SSL connections.
- **Health checks need SSL too**: `HealthCheck.php` must use same SSL config as application.

---

## Nginx

### Dynamic Configuration

- **`ENABLE_PHP` affects routing**: Entrypoint sets `INDEX_DIRECTIVE` and `TRY_FILES_FALLBACK` dynamically.
- **Health check snippets**: Write to `/run/nginx/snippets/` (tmpfs) since `/etc/nginx/snippets/` is read-only.
- **Production templates**: Process with `envsubst` at runtime, same as development.

---

## Testing

### GOSS Two-Phase Strategy

| Phase | When | What | How |
|-------|------|------|-----|
| Build-time | `docker build --target test` | Files, configs, commands | GOSS in Dockerfile |
| Runtime | `make goss-test` | HTTP, TLS, connectivity | Shell script |

### Bash Scripting

- **`((var++))` fails with `set -e` when var=0** - use `var=$((var + 1))` instead.

---

## Kubernetes

### Security Context

- **SETUID/SETGID capabilities for su-exec**: Add to securityContext if container uses su-exec.
- **Network policies**: Update when adding new services (node-backend needed separate policies).

### ConfigMaps

- **nginx.conf and php-fpm.conf**: Must be ConfigMaps in K8s (can't use Docker-specific entrypoint templating).

---

## Development Workflow

### Commits

- **Commit after each completed todo**: Prevents mixed changes that need to be split later. Each todo = one focused commit.
- **User review before commit**: After completing a todo, inform user that changes are ready for review. Wait for approval before committing.
- **Thematically grouped commits**: Keep commits focused on one topic (feature, fix, docs) for cleaner history.

### Bash Commands

- **Single commands > chaining**: `cmd1 && cmd2 && cmd3` requires manual confirmation. Single Bash calls are auto-approved.
- **Better error handling**: Single commands allow precise error handling instead of entire chain aborting.

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

## Documentation

### Markdown Tables

- **Emojis cause alignment issues** - use ASCII labels `[CRIT]`/`[MED]`/`[LOW]` instead of emoji.

### CLAUDE.md Location

- Claude Code recognizes both `./CLAUDE.md` and `./.claude/CLAUDE.md`. Latter keeps project root cleaner.

---

## Claude Workflow

### Post-Implementation Verification

**After completing each task, automatically run relevant checks:**

| Change Type | Verification Steps |
|-------------|-------------------|
| Docker/Compose changes | `docker compose config`, restart containers, test health |
| PHP code changes | `make check` (includes PHPStan, PHPMD, CS-Fixer) |
| Node code changes | `make lint-node`, `make test-node` |
| Configuration files | `docker compose config`, relevant service tests |
| Database-related | Restart DB container or `make fresh` if schema changed |

**Workflow:**
1. Implement change
2. Run relevant checks/tests
3. Verify functionality (health endpoints, manual tests)
4. Only then report completion to user

**Important:** Database password or credential changes require fresh initialization:
```bash
make down
docker volume rm <project>-postgres-data
make up
```

---

## Last Updated

2026-01-19 (aggregated from sessions 2026-01-15 to 2026-01-19)
