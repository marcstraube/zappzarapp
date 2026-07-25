# Troubleshooting Guide

Common problems and their solutions for the zappzarapp boilerplate.

## Quick Diagnostics

Before diving into specific issues, run these diagnostic commands:

```bash
# Check container status
make status

# Check container health
make check-health

# View logs
make logs

# Check Docker system
docker info
docker compose version
```

## Docker Issues

### Containers Won't Start

#### Symptom

`make up` fails or containers exit immediately.

#### Solutions

1. **Check for existing containers**

   ```bash
   docker ps -a | grep zappzarapp
   make down  # Stop any existing containers
   make up
   ```

2. **Check Docker daemon**

   ```bash
   sudo systemctl status docker
   sudo systemctl restart docker
   ```

3. **Rebuild images**

   ```bash
   make clean
   make build
   make up
   ```

4. **Complete reset** (WARNING: deletes all data)

   ```bash
   make fresh  # Type 'YES' to confirm
   ```

### "Image not found" Error

#### Symptom

```text
Error: Required images not found: php node nginx
```

#### Solution

Build images first:

```bash
make build
make up
```

### Port Already in Use

#### Symptom

```text
Error: Bind for 0.0.0.0:8080 failed: port is already allocated
```

#### Solutions

1. **Find and stop the conflicting process**

   ```bash
   sudo lsof -i :8080
   kill <PID>
   ```

2. **Change the port in `.env`**

   ```bash
   NGINX_PORT=8081
   NGINX_SSL_PORT=8444
   ```

3. **Rebuild and restart**

   ```bash
   make restart
   ```

### Docker Creates Directories Instead of Files

#### Symptom

- `Error: EISDIR: illegal operation on a directory, read` when Node tries to
  read a lockfile
- `cannot load certificate: ... is a directory` from nginx
- Container restart loops

#### Cause

Bind mounts like `./file.txt:/container/file.txt` create the host path as a
**directory** if `./file.txt` doesn't exist when the container starts. Commonly
affected files: `composer.lock` / `pnpm-lock.yaml` (deleted or never created),
`docker/certs/nginx/` and `docker/certs/internal/` (not generated), `secrets/*`
files (not generated).

#### Solutions

1. **Ensure files exist before `docker compose up`**

   ```bash
   # For lockfiles that became directories
   docker run --rm -v ./:/app alpine sh -c "rm -rf /app/composer.lock && touch /app/composer.lock"

   # For certs/secrets: run the make targets
   make ssl-generate
   make secrets
   ```

2. **Prevention**: Run `make setup` on a fresh clone or after deleting generated
   files (it runs all generation steps).

### Symlinks in Bind Mounts Don't Resolve

#### Symptom

When linking local packages for development:

- `composer install` fails with "url supplied for path repository does not
  exist"
- `pnpm install` cannot find the linked package
- `ls -la packages/` inside the container shows broken symlinks

#### Cause

Symlinks inside a bind-mounted directory point to host paths (e.g.
`../../other-repo`) that don't exist inside the container.

#### Solution

Mount the actual package directories directly instead of the symlink parent:

```yaml
# WRONG: symlinks in packages/ don't resolve
volumes:
  - ./packages:/var/www/html/packages:ro

# CORRECT: mount actual directories
volumes:
  - ../my-package:/var/www/html/packages/my-package:ro
```

Host-side symlinks in `packages/` remain useful for IDE resolution and local
tooling — only Docker needs the direct mounts.

### Single-File Bind Mounts Serve Stale Content

#### Symptom

A host file is updated, but tools inside the container still see the old version
(e.g. PHPUnit failing on a testsuite directory that was already removed from the
mounted config).

#### Cause

Files mounted individually (e.g.
`./phpunit.xml.dist:/var/www/html/phpunit.xml.dist:ro`) are bound by inode.
Editors and tools that write via replace-and-rename (most IDEs, `sed -i`) create
a new inode — the container keeps reading the old one.

#### Solution

Recreate the container after editing a single-file-mounted config:

```bash
docker compose up -d --force-recreate <service>
```

A plain `restart` is not reliable.

### Volume Errors After Switching Dev ↔ Prod

#### Symptom

Containers fail with permission errors on named volumes after switching between
development and production environments.

#### Cause

Development and production images run services with different UIDs (dev: 1000,
prod: postgres=70, redis=999). Volumes initialized by one environment are not
readable by the other.

#### Solution

Delete the project volumes when switching environments:

```bash
make down
docker volume rm $(docker volume ls -q | grep zappzarapp)
# Update .env for the target environment
make build && make up
```

### Cannot Write Files in Production Containers

#### Symptom

Copying binaries or writing files at runtime fails in production containers.

#### Cause

Production containers run with a read-only root filesystem.

#### Solution

Install binaries and static files at build time in the Dockerfile instead of
copying them at runtime.

## Permission Issues

### Linux: Permission Denied Errors

#### Symptom

```text
Permission denied: /app/vendor
Permission denied: /app/node_modules
```

#### Solutions

1. **Set correct user IDs in `.env`**

   ```bash
   # Find your IDs
   id -u  # e.g., 1000
   id -g  # e.g., 1000

   # Update .env
   USER_ID=1000
   GROUP_ID=1000
   ```

2. **Fix storage permissions**

   ```bash
   chmod 770 storage -R
   ```

3. **Fix vendor/node_modules permissions**

   ```bash
   # PHP container
   docker compose exec --user root php chown -R www-data:www-data /app/vendor

   # Node container
   docker compose exec --user root node chown -R node:node /app/node_modules
   ```

### Windows: Line Ending Issues

#### Symptom

Scripts fail with `\r` errors or "bad interpreter".

#### Solution

Configure Git to use LF line endings:

```bash
git config --global core.autocrlf input
git rm --cached -r .
git reset --hard
```

See [WINDOWS.md](setup/WINDOWS.md) for detailed Windows setup.

### Elasticsearch Rejects Secret File Permissions

#### Symptom

```text
ERROR: File ... must have file permissions 400 or 600, but actually has: 644
```

#### Cause

Elasticsearch enforces stricter permissions on secret files than other services.
While Redis/RabbitMQ work with 644, Elasticsearch rejects it and requires 400
or 600.

#### Solution

```bash
chmod 600 secrets/<elasticsearch-secret-file>
```

Restart the Elasticsearch container afterwards.

## PHP Issues

### 502 Bad Gateway

#### Symptom

Nginx returns 502 when accessing PHP pages.

#### Solutions

1. **Check PHP-FPM is running**

   ```bash
   docker compose exec php ps aux | grep php-fpm
   ```

2. **Check PHP-FPM socket**

   ```bash
   docker compose exec php ls -la /var/run/php-fpm/
   ```

3. **Check PHP container health**

   ```bash
   docker inspect zappzarapp-php --format='{{.State.Health.Status}}'
   ```

4. **View PHP logs**

   ```bash
   make logs-php
   ```

5. **Restart PHP container**

   ```bash
   docker compose restart php
   ```

### Composer Install Fails

#### Symptom

`make composer-install` fails with memory or permission errors.

#### Solutions

1. **Increase PHP memory**

   ```bash
   docker compose exec php php -d memory_limit=-1 /usr/bin/composer install
   ```

2. **Clear Composer cache**

   ```bash
   docker compose exec php composer clear-cache
   ```

3. **Run without scripts**

   ```bash
   docker compose exec php composer install --no-scripts
   ```

### Xdebug Not Connecting

#### Symptom

Breakpoints not hitting, debugger not connecting.

#### Solutions

1. **Enable Xdebug in `.env`**

   ```bash
   XDEBUG_MODE=develop,debug
   ```

2. **Restart PHP container**

   ```bash
   docker compose restart php
   ```

3. **Check Xdebug configuration**

   ```bash
   docker compose exec php php -i | grep xdebug
   ```

See [XDEBUG.md](development/XDEBUG.md) for detailed configuration.

## Node.js Issues

### Vite Dev Server Not Starting

#### Symptom

`make node-dev` fails or HMR not working.

#### Solutions

1. **Check Node container is running**

   ```bash
   docker compose ps node
   ```

2. **Check Node logs**

   ```bash
   make logs-node
   ```

3. **Reinstall dependencies**

   ```bash
   make pnpm-install
   ```

4. **Start manually**

   ```bash
   docker compose exec node pnpm run dev
   ```

### pnpm Install Fails

#### Symptom

`make pnpm-install` fails with permission or lock errors.

#### Solutions

1. **Fix permissions**

   ```bash
   docker compose exec --user root node chown -R node:node /app/node_modules
   ```

2. **Clear pnpm cache**

   ```bash
   docker compose exec node pnpm store prune
   ```

3. **Remove lock file and reinstall**

   ```bash
   rm pnpm-lock.yaml
   docker compose exec node pnpm install
   ```

### `pnpm prune --prod` Breaks Workspace Symlinks

#### Symptom

Workspace packages are missing or their symlinks are broken after pruning dev
dependencies.

#### Solution

Don't use `pnpm prune --prod` in workspaces. Reinstall instead:

```bash
rm -rf node_modules && pnpm install --prod
```

### EBUSY Error on pnpm-lock.yaml

#### Symptom

```text
EBUSY: resource busy or locked, rename ... pnpm-lock.yaml
```

#### Cause

Docker bind mounts don't support the atomic rename pnpm uses to write the
lockfile.

#### Solutions

1. Use `--no-install` flags for commands that would rewrite the lockfile
2. Use the make targets (`make pnpm-sync`, `make pnpm-update`) — they resolve
   the lockfile in a temp location inside the container and copy it back,
   avoiding the rename

### HMR (Hot Module Replacement) Not Working

#### Symptom

Changes not reflecting in browser without manual refresh.

#### Solutions

1. **Check Vite is running**

   ```bash
   docker compose exec node pnpm run dev
   ```

2. **Check browser console** for WebSocket errors

3. **Verify Vite config** allows external connections:

   ```javascript
   // vite.config.js
   server: {
     host: '0.0.0.0',
     hmr: {
       host: 'localhost'
     }
   }
   ```

### "Ignored build scripts" Warning

#### Symptom

When running `pnpm install` locally, you see:

```text
Ignored build scripts: @parcel/watcher@2.5.4, esbuild@0.27.2
```

#### Explanation

This is **not an error** - it's pnpm's security feature blocking native binary
compilation by default. Packages like `@parcel/watcher` and `esbuild` use native
code for performance but have JavaScript/WASM fallbacks.

#### Impact

- **IDE integration**: Works normally - TypeScript types are pure JavaScript
- **Build/dev**: Use `make` targets (run in container with pre-approved builds)
- **Performance**: Slight slowdown for file watching if using local pnpm

#### Solutions

1. **Recommended**: Use container-based commands (`make pnpm-install`, etc.)

2. **If you need local pnpm**: Approve builds explicitly:

   ```bash
   pnpm approve-builds
   ```

## Database Issues

### Cannot Connect to Database

#### Symptom

Application can't connect to PostgreSQL/MariaDB.

#### Solutions

1. **Check database is running**

   ```bash
   docker compose ps postgres  # or mariadb
   ```

2. **Check database health**

   ```bash
   docker inspect zappzarapp-postgres --format='{{.State.Health.Status}}'
   ```

3. **Check database logs**

   ```bash
   make logs-postgres  # or logs-mariadb
   ```

4. **Verify connection settings in `.env`**

   ```bash
   DB_TYPE=postgres
   DB_HOST=postgres  # Container name
   DB_PORT=5432
   DB_NAME=app
   DB_USER=app
   ```

5. **Test connection manually**

   ```bash
   make postgres-cli  # or mariadb-cli
   ```

### Database Password Not Working

#### Symptom

Authentication failed errors.

#### Solutions

1. **Check secrets are generated**

   ```bash
   ls -la secrets/
   cat secrets/db_password.txt
   ```

2. **Regenerate secrets**

   ```bash
   make secrets
   ```

3. **Restart database container**

   ```bash
   docker compose restart postgres  # or mariadb
   ```

4. **Complete database reset** (WARNING: deletes data)

   ```bash
   docker volume rm zappzarapp-postgres-data
   make up
   ```

### Redis Connection Failed

#### Symptom

Cannot connect to Redis, TLS errors.

#### Solutions

1. **Check Redis is running**

   ```bash
   docker compose ps redis
   ```

2. **Check Redis health**

   ```bash
   docker inspect zappzarapp-redis --format='{{.State.Health.Status}}'
   ```

3. **Verify TLS certificates exist**

   ```bash
   ls -la docker/certs/
   ```

4. **Test connection**

   ```bash
   make redis-cli
   ```

## SSL/TLS Issues

### Certificate Errors in Browser

#### Symptom

Browser shows "Your connection is not private".

#### Solutions

1. **For development** - Accept the self-signed certificate
   - Click "Advanced" → "Proceed to localhost"

2. **Regenerate certificates**

   ```bash
   make ssl-selfsigned
   make restart
   ```

3. **For production** - Use Let's Encrypt

   ```bash
   make ssl-letsencrypt
   ```

### SSL Certificate Expired

#### Symptom

Browser shows certificate expiration error.

#### Solutions

1. **Check certificate info**

   ```bash
   make ssl-info
   ```

2. **Renew Let's Encrypt**

   ```bash
   make ssl-renew
   ```

3. **Regenerate self-signed**

   ```bash
   make ssl-selfsigned
   make restart
   ```

## Build Issues

### Build Takes Too Long

#### Solutions

1. **Use BuildKit cache**

   ```bash
   DOCKER_BUILDKIT=1 make build
   ```

2. **Build specific service only**

   ```bash
   docker compose build php  # or node, nginx, etc.
   ```

### Nginx Production Build Fails: node-backend Image Missing

#### Symptom

Building the nginx `production` stage fails because
`zappzarapp-node-backend:latest` doesn't exist.

#### Cause

The nginx Dockerfile `production` stage contains
`COPY --from=zappzarapp-node-backend:latest`, so that image must exist before
nginx is built. Development mode uses a different target and skips this.

#### Solution

For CI/production builds, build and tag node-backend first:

```bash
# Build and tag node-backend first
docker compose --profile node-backend build node-backend
docker tag <project>-node-backend:api zappzarapp-node-backend:latest
# Then build nginx with DOCKER_BUILDKIT=0 (sees local images)
DOCKER_BUILDKIT=0 docker compose build nginx
```

### Out of Disk Space

#### Symptom

Build fails with "no space left on device".

#### Solutions

1. **Clean Docker system**

   ```bash
   docker system prune -a  # WARNING: removes all unused images
   docker volume prune     # WARNING: removes unused volumes
   ```

2. **Remove project-specific resources**

   ```bash
   make clean
   ```

## Network Issues

### Container Cannot Reach Another Container

#### Symptom

PHP can't connect to database, Nginx can't reach Node.

#### Solutions

1. **Check network configuration**

   ```bash
   docker network ls | grep zappzarapp
   docker network inspect zappzarapp-backend
   ```

2. **Verify container is in correct network**

   ```bash
   docker inspect zappzarapp-php --format='{{json .NetworkSettings.Networks}}' | jq
   ```

3. **Restart networking**

   ```bash
   make down
   docker network prune
   make up
   ```

See [NETWORK.md](infrastructure/NETWORK.md) for network architecture details.

## Test Issues

### PHPUnit Tests Failing

#### Solutions

1. **Run with verbose output**

   ```bash
   docker compose exec php vendor/bin/phpunit -v
   ```

2. **Check test database**

   ```bash
   docker compose exec php php -r "echo getenv('DATABASE_URL');"
   ```

### PHPUnit Fails With "No tests executed!"

#### Symptom

A PHPUnit run using `--filter`, `--group`, or `--testsuite` exits with a
non-zero code and "No tests executed!" although nothing is broken.

#### Cause

Since PHPUnit 12.5.18
([#6276](https://github.com/sebastianbergmann/phpunit/issues/6276)), an explicit
selection that matches zero tests is treated as a failure. Scripts that treat an
empty match as success break.

Because lockfiles are not tracked in this repo, CI resolves fresh dependencies
and picks up such behavior changes before local environments do — the same drift
pattern as `latest`-tagged linter images (see the sqlfluff case in
[sql.md](../standards/sql.md)).

#### Solution

Make sure explicit selections match at least one test, or explicitly tolerate
the empty-selection exit code in scripts.

### Vitest Tests Failing

#### Solutions

1. **Run with verbose output**

   ```bash
   docker compose exec node pnpm test -- --reporter=verbose
   ```

2. **Update snapshots**

   ```bash
   docker compose exec node pnpm test -- -u
   ```

## Git Hook Issues

### Staged Files Recorded as Deleted After Pre-Commit

#### Symptom

After a failed pre-commit run, files staged as modifications are suddenly
recorded as **deleted** in the index (and end up deleted in the next commit),
while the files still exist on disk. Typically affects `.idea/` and `.vscode/`
files.

#### Cause

The pre-commit hook runs lint-staged inside the dev-tools container with
`--no-stash`. Files staged on the host but not mounted into the container don't
exist from the container's point of view. When lint-staged applies task
modifications, its index sync records those invisible files as deletions — the
"might result in data loss" warning is literal.

#### Detection

Watch the commit output for unexpected `delete mode` lines, and check
`git show --stat` after committing. Affected files reappear as untracked (`??`).

#### Recovery

The files are still on disk:

```bash
git add <files>
git commit --amend --no-verify
```

`--no-verify` avoids the same hook clobbering the index again, and skips
lint-staged's empty-commit guard, which also fires when the only staged files
are container-invisible.

#### Prevention

The lint-staged markdown glob filters `.idea/`/`.vscode/` via a function entry
in `lint-staged.config.js`. Commits touching only IDE-directory markdown still
need `--no-verify` (empty-commit guard edge case).

## Getting More Help

### Collect Debug Information

```bash
# System info
docker version
docker compose version
make --version

# Container status
make status

# Recent logs
docker compose logs --tail=100

# Environment
cat .env | grep -v PASSWORD
```

### Log Locations

| Log            | Command              |
| -------------- | -------------------- |
| All containers | `make logs`          |
| Nginx          | `make logs-nginx`    |
| PHP            | `make logs-php`      |
| Node           | `make logs-node`     |
| PostgreSQL     | `make logs-postgres` |
| MariaDB        | `make logs-mariadb`  |
| Redis          | `make logs-redis`    |

### Reset Everything

When all else fails, complete reset:

```bash
make fresh  # Type 'YES' to confirm
```

This removes ALL containers, images, and volumes, then rebuilds from scratch.
