# Makefile Reference

Complete reference for all available `make` commands in the zappzarapp
boilerplate.

Run `make help` to see all available commands with descriptions.

## Filtering Help Output

Use `FILTER=` to filter help output by category:

```bash
make help FILTER=?          # List all available categories
make help FILTER=docker     # Docker-related commands
make help FILTER=test       # Testing commands (Goss, PHPUnit, etc.)
make help FILTER=setup      # Setup and installation commands
make help FILTER=security   # Security-related commands
```

The filter is case-insensitive and matches category names. When filtering,
available categories are shown at the end of the output for reference.

## Quick Reference

| Task                  | Command                |
| --------------------- | ---------------------- |
| First-time setup      | `make setup`           |
| Start containers      | `make up`              |
| Stop containers       | `make down`            |
| Run all tests         | `make test`            |
| Run all checks        | `make check`           |
| Test production build | `make test-production` |
| View logs             | `make logs`            |

## Development Mode Defaults

In development mode (`ZAPPZARAPP_ENV=development` or not set), `make up`
applies:

| Default                | Condition                   | Purpose                    |
| ---------------------- | --------------------------- | -------------------------- |
| `NODE_MODE=assets-api` | NODE_MODE not set or "idle" | Enable Vite HMR + Node API |

**Vite HMR** (Hot Module Replacement) provides instant hot-reload for CSS/JS
changes without page refresh. The Express API backend enables the Node.js
DevDashboard for coverage and documentation generation.

**Override options:**

- `NODE_MODE=assets` - Vite HMR only (no Express backend)
- `NODE_MODE=api` - Express backend only (no Vite HMR)
- `ENABLE_NODE=false` - Disable Node completely (no HMR, no API)

## Setup Commands

Commands for initial project setup and configuration.

| Command                         | Description                                                                   |
| ------------------------------- | ----------------------------------------------------------------------------- |
| `make setup`                    | Full setup (directories, secrets, build, install) - entry point               |
| `make customize`                | List shipped templates that still contain customization placeholders          |
| `make init`                     | Create `.env.local` with USER_ID/GROUP_ID (called interactively by setup)     |
| `make composer-install`         | Install/update Composer dependencies via Docker (guaranteed consistency)      |
| `make composer-install-local`   | Install Composer dependencies locally (for IDE code completion)               |
| `make pnpm-install`             | Install Node.js dependencies via Docker (requires ZAPPZARAPP_ENV=development) |
| `make pnpm-install-local`       | Install Node.js dependencies locally (for IDE code completion)                |
| `make hooks-install`            | Install Git hooks using CaptainHook                                           |
| `make ide-config`               | Configure all IDE database connections (PHPStorm + VS Code)                   |
| `make ide-config-full`          | Update all IDE configs with custom ports from `.env.local`                    |
| `make ide-config-phpstorm`      | Configure PHPStorm only (`.idea/dataSources.local.xml`)                       |
| `make ide-config-vscode`        | Configure VS Code only (`.vscode/settings.json`)                              |
| `make ide-config-phpstorm-full` | Update PHPStorm shared config with custom ports                               |
| `make ide-config-vscode-full`   | Update VS Code config with custom ports                                       |
| `make ide-lock`                 | Lock IDE config files from git (auto-run by ide-config)                       |
| `make ide-unlock`               | Unlock IDE config files for committing (zappzarapp contributors)              |

### Setup Workflow

```bash
# New project - single command
make setup          # Prompts for init if needed, then builds everything

# IDE setup (optional)
make composer-install-local  # PHP code completion
make pnpm-install-local      # Node.js code completion
```

On first run, `make setup` will ask whether to run `make init` (auto-detect
USER_ID/GROUP_ID) or continue with defaults from `.env`.

### IDE Database Setup

`make setup` automatically runs `make ide-config`, which configures database
connections for both PHPStorm and VS Code.

**Configured connections:**

- PostgreSQL (Docker) - local development
- MariaDB (Docker) - local development
- PostgreSQL (Remote) - production access
- MariaDB (Remote) - production access

#### PHPStorm

**First connection:**

1. Open **Database** tool window (`View → Tool Windows → Database`)
2. Click the data source → **Test Connection**
3. Get password: `cat secrets/db_password.txt`
4. Paste password, enable **Save password** → stored in system keyring

**Custom ports:** Run `make ide-config-phpstorm-full` if you changed ports in
`.env.local`

**Remote DB (SSH tunnel):** PHPStorm handles SSH tunnels automatically when
configured in `.env.local` (see below).

#### VS Code (SQLTools)

**First connection:**

1. Open **SQLTools** sidebar (database icon)
2. Click a connection → prompted for password
3. Get password: `cat secrets/db_password.txt`

**Custom ports:** Run `make ide-config-vscode-full` if you changed ports in
`.env.local`

**Remote DB (SSH tunnel):** SQLTools doesn't support integrated SSH tunnels.
Start tunnel manually before connecting:

```bash
ssh -N -L 5432:internal-db:5432 user@bastion.example.com
# Then connect to localhost:5432 in VS Code
```

#### Custom Ports (Both IDEs)

If you changed `POSTGRES_PORT` or `MARIADB_PORT` in `.env.local`:

```bash
make ide-config-full    # Updates both PHPStorm and VS Code

# Hide local changes from git:
git update-index --assume-unchanged .idea/dataSources.xml
git update-index --assume-unchanged .vscode/settings.json
```

#### Remote Database Configuration

Configure in `.env.local` (gitignored, your personal credentials):

```bash
# Remote DB connection (coordinate with team for these values)
DB_REMOTE_HOST=internal-db.k8s.cluster
DB_REMOTE_PORT=5432
DB_REMOTE_NAME=production
DB_REMOTE_USER=app_readonly

# SSH tunnel (your personal credentials - PHPStorm only)
DB_REMOTE_SSH_HOST=bastion.example.com
DB_REMOTE_SSH_PORT=22
DB_REMOTE_SSH_USER=your-username
DB_REMOTE_SSH_KEY=~/.ssh/id_ed25519
```

Run `make ide-config` to apply configuration.

### IDE Config Lock (Skip-Worktree)

PhpStorm regenerates `.idea/php.xml` on every `composer install/update`, adding
vendor include paths. This causes unnecessary git noise.

**Automatic behavior:**

`make ide-config` (run during `make setup`) automatically locks `.idea/php.xml`
using git's skip-worktree flag. Local changes are ignored by git.

**For zappzarapp contributors:**

When you need to commit changes to `.idea/php.xml` (e.g., new tool config):

```bash
make ide-unlock        # Unlock for committing
# ... make changes ...
git add .idea/php.xml
git commit -m "chore(ide): update PHPStan config"
make ide-lock          # Re-lock after commit
```

**Check lock status:**

```bash
git ls-files -v .idea/php.xml
# 'S' prefix = skip-worktree (locked)
# 'H' prefix = normal (unlocked)
```

## Docker Commands

Container lifecycle management.

| Command               | Description                                                          |
| --------------------- | -------------------------------------------------------------------- |
| `make up`             | Start enabled containers (based on `.env` ENABLE\_\* flags)          |
| `make down`           | Stop containers                                                      |
| `make restart`        | Restart containers (down + up)                                       |
| `make build`          | Build Docker images                                                  |
| `make build-no-cache` | Build Docker images without cache                                    |
| `make clean`          | Remove containers, networks and dangling images (keeps data volumes) |
| `make fresh`          | Complete clean slate rebuild, removing ALL data volumes (DANGEROUS!) |
| `make rebuild`        | Complete rebuild (clean + build + up)                                |
| `make prune`          | Remove untagged/dangling images related to this project              |
| `make reset`          | Factory reset - remove generated files, keep source code (DANGEROUS) |
| `make reset-full`     | Factory reset INCLUDING source code reset via git (VERY DANGEROUS)   |

### Service-Specific Commands

Many commands accept optional service names to operate on specific containers:

```bash
# Start/stop/restart specific services
make up php nginx          # Start only PHP and Nginx
make down php              # Stop only PHP
make restart php nginx     # Restart PHP and Nginx

# Build specific images
make build php             # Build only PHP image
make build-no-cache php    # Force rebuild PHP without cache

# View logs of specific services
make logs php nginx        # Show combined logs of PHP and Nginx
```

Without arguments, commands operate on all enabled services (based on `.env`).

### Image Freshness Validation

When running `make up`, the system automatically checks if Docker images are
older than configuration files. This prevents issues where code changes aren't
reflected because cached images are being used.

**Checked files:**

- `docker/*/Dockerfile` - Container build definitions
- `docker/*/entrypoint*.sh` - Startup scripts
- `compose.yaml`, `compose.override.yaml` - Service configuration
- `.env` - Environment variables (NODE_MODE, ZAPPZARAPP_ENV, etc.)

**Behavior:**

| Scenario             | What happens                                  |
| -------------------- | --------------------------------------------- |
| Images up-to-date    | Containers start normally                     |
| Images outdated      | Warning + interactive prompt "Rebuild? [y/N]" |
| Non-interactive (CI) | Warning + hint to run `make rebuild`          |

**Flags:**

```bash
make up                    # Default: interactive prompt if outdated
make up FORCE=1            # Auto-rebuild if outdated (no prompt)
make up SKIP_VALIDATION=1  # Skip freshness check entirely (CI/CD)
```

**When to use each:**

- **Default (interactive):** Daily development workflow
- **FORCE=1:** Automated scripts, when you always want fresh images
- **SKIP_VALIDATION=1:** CI/CD pipelines where images are built separately

**Troubleshooting "Vite Dev Server Not Running":**

If the welcome page shows "Vite Dev Server Not Running" after `make up`:

1. Check if `make up` showed a freshness warning (images may be outdated)
2. Run `make rebuild` to ensure fresh images
3. Verify `NODE_MODE=assets-api` or `NODE_MODE=assets` in `.env`
4. Check node container logs: `make logs-node`

### Factory Reset

Two levels of reset are available for returning to a clean state:

**`make reset`** - Removes generated files but keeps your source code:

- This project's Docker containers, images, volumes, networks and the project
  builder's build cache — resources of **other projects on the same host are
  left untouched** (opt back into the old system-wide wipe with
  `PRUNE_SYSTEM=1 make reset`)
- Goss test resources
- `storage/` contents (if not a mountpoint)
- `vendor/`, `node_modules/` (dependencies)
- `composer.lock`, `pnpm-lock.yaml` (lockfiles)
- `.env.local` (local overrides)
- `build/`, `public/build/`, `docs/api/`, `tools/` (generated files)

Keeps: `secrets/`, `docker/certs/`, source code.

**`make reset-full`** - Same as above, PLUS:

- `secrets/` (generated secrets)
- `docker/certs/{ca,nginx,internal}/` (generated certificates)
- Source code reset via
  `git checkout -- src/ tests/ resources/ config/ templates/`
- `README.md`, `CHANGELOG.md`, `AGENTS.md`, `.claude/` docs reset to boilerplate
  state

The system trust store is never touched: if you ran `make ssl-trust-ca`, the
removed CA stays trusted until you run `make ssl-untrust-ca` (works even after
the certificate files are gone).

**Safety features:**

- Mountpoint detection: Directories that are mountpoints (e.g., NFS) are skipped
- Confirmation required: Must type `RESET` (or `RESET-FULL`) to proceed
- Source code preserved: `make reset` never touches `src/`, `tests/`, etc.
- Docker cleanup is project-scoped: only `com.docker.compose.project`-labeled
  resources, project-named images, and the named buildx builder cache are
  removed

**When to use:**

| Scenario                          | Command           |
| --------------------------------- | ----------------- |
| Fresh start, keep my code changes | `make reset`      |
| Complete boilerplate reset        | `make reset-full` |
| Just rebuild containers           | `make fresh`      |

### Container Information

| Command              | Description                                           |
| -------------------- | ----------------------------------------------------- |
| `make status`        | Show running containers status and image disk usage   |
| `make logs`          | Show logs of all containers (or specific services)    |
| `make logs-save`     | Export logs to timestamped directory for team sharing |
| `make logs-nginx`    | Show Nginx logs only                                  |
| `make logs-php`      | Show PHP logs only                                    |
| `make logs-node`     | Show Node.js logs only                                |
| `make logs-redis`    | Show Redis logs only                                  |
| `make logs-postgres` | Show PostgreSQL logs only                             |
| `make logs-mariadb`  | Show MariaDB logs only                                |

### Log Export for Team Debugging

Export container logs with debugging metadata for sharing with team members:

```bash
# Export all running containers (all logs)
make logs-save

# Export specific services only
make logs-save SERVICES=php,node,nginx

# Filter by time range (Docker --since syntax)
make logs-save SINCE=2h              # Last 2 hours
make logs-save SINCE=30m             # Last 30 minutes
make logs-save SINCE="2026-01-20T10:00:00"  # Since specific time

# Combined: specific services with time filter
make logs-save SERVICES=php,nginx SINCE=1h
```

**Output structure:**

```text
logs/2026-01-20-1430/
├── php.log
├── node.log
├── nginx.log
├── postgres.log
└── metadata.txt
```

**metadata.txt includes:**

- Export timestamp and time filter
- Git branch and last commit hash
- Environment settings (ZAPPZARAPP_ENV, DB_TYPE, NODE_MODE)
- Active COMPOSE_PROFILES
- Current container status (`docker compose ps`)

### Container Shells

| Command               | Description                        |
| --------------------- | ---------------------------------- |
| `make shell-nginx`    | Open shell in Nginx container      |
| `make shell-php`      | Open shell in PHP container        |
| `make shell-node`     | Open shell in Node container       |
| `make shell-redis`    | Open shell in Redis container      |
| `make shell-postgres` | Open shell in PostgreSQL container |
| `make shell-mariadb`  | Open shell in MariaDB container    |

### Kubernetes Deployment

Deploy to Kubernetes using Helm. See
[KUBERNETES.md](../infrastructure/KUBERNETES.md) for details.

| Command               | Description                            |
| --------------------- | -------------------------------------- |
| `make k8s-build`      | Build the images the chart will deploy |
| `make k8s-deploy`     | Deploy to Kubernetes using Helm        |
| `make k8s-remove`     | Remove deployment from Kubernetes      |
| `make k8s-status`     | Show pods, services, and Helm status   |
| `make k8s-logs [pod]` | View logs from a specific pod          |

`make k8s-build` renders the chart and builds exactly the `zappzarapp-*` images
for the enabled services (including optional ones behind Compose profiles). The
multi-target services (nginx, php, node, node-backend) are built from their
production targets and retagged to `:latest` — the chart mounts no application
source, so the development-target images (which expect Compose bind mounts)
would not run there. Which production targets are picked also derives from the
chart: enabling the node frontend (`node.enabled`) selects the framework targets
(node/proxy-nginx/framework-php); `.env` `NODE_MODE` is not consulted, so a
Chart/`.env` mismatch cannot produce wrong images. `make k8s-deploy` refuses to
deploy in development if a required image is missing locally — build it first
with `make k8s-build`.

**Examples:**

```bash
# Build the enabled services' images (into minikube: eval $(minikube docker-env) first)
make k8s-build

# Deploy with default values
make k8s-deploy

# Deploy with production values (either set ZAPPZARAPP_ENV=production in .env or
# pass it inline -- a caller-supplied ZAPPZARAPP_ENV wins over the env files)
ZAPPZARAPP_ENV=production make k8s-deploy

# View logs
make k8s-logs zappzarapp-php-xxxxx

# Remove deployment
make k8s-remove
```

### Production Testing

Test production builds with different service configurations. These targets
start containers in production mode and run health checks to validate
functionality.

| Command                        | Description                                                           |
| ------------------------------ | --------------------------------------------------------------------- |
| `make test-production`         | Test with ZAPPZARAPP_ENV-configured services (smart, respects `.env`) |
| `make test-production-minimal` | Test with minimal services (nginx + app + db only)                    |
| `make test-production-full`    | Test with ALL services (comprehensive, ignores `.env`)                |

**test-production (Recommended):**

Tests production build with services activated based on `.env` configuration:

- Always: nginx
- Conditional: php, node, database, redis, etc. (based on `ENABLE_*` flags)
- Health checks: nginx HTTP, database connectivity, redis connectivity
- Use case: CI/CD default, tests realistic production config (~1min)

**test-production-minimal (Fast):**

Tests only core services:

- nginx + php/node + database
- No optional services (redis, elasticsearch, etc.)
- Health checks: nginx HTTP, database connectivity
- Use case: Quick validation, fast feedback for PRs (~30s)

**test-production-full (Comprehensive):**

Tests ALL available services regardless of ENABLE\_\* settings:

- All core services (nginx, php, node, node-backend)
- All data services (postgres, mariadb, redis)
- All optional services (elasticsearch, meilisearch, mercure, rabbitmq,
  seaweedfs)
- Extended health checks with longer timeouts
- Use case: Pre-release validation, nightly builds (~3min)

**Examples:**

```bash
# Smart test (respects .env)
make test-production

# Quick test for PR
make test-production-minimal

# Comprehensive test before release
make test-production-full
```

**Requirements:**

- `ZAPPZARAPP_ENV=production` must be set in `.env` or passed to make directly
- Production images must be built first (`ZAPPZARAPP_ENV=production make build`)
- Services must be stopped before running tests

See [DEPLOYMENT.md](../infrastructure/DEPLOYMENT.md) for detailed documentation.

### Package Managers

| Command                   | Description                                            |
| ------------------------- | ------------------------------------------------------ |
| `make composer CMD="..."` | Execute Composer command in running container          |
| `make composer-update`    | Update Composer dependencies (updates `composer.lock`) |
| `make pnpm CMD="..."`     | Execute pnpm command in running container              |
| `make pnpm-update`        | Update Node.js dependencies (updates `pnpm-lock.yaml`) |

**Examples:**

```bash
make composer CMD="require vendor/package"
make pnpm CMD="add vue"
```

## Node.js Development

Commands for frontend and Node.js backend development.

| Command                    | Description                                                    |
| -------------------------- | -------------------------------------------------------------- |
| `make node-dev`            | Start Vite dev server with HMR (Hot Module Replacement)        |
| `make node-dev-full`       | Start full-stack development (Vite + Express backend with PM2) |
| `make node-dev-vite`       | Start only Vite dev server with PM2                            |
| `make node-dev-backend`    | Start only Node.js backend with PM2                            |
| `make node-frontend-dev`   | Start Node frontend framework dev server (Next.js, Nuxt, etc.) |
| `make node-frontend-build` | Build Node frontend framework                                  |
| `make node-frontend-start` | Start Node frontend framework production server                |
| `make node-server-dev`     | Start Node.js backend in development watch mode (tsx watch)    |
| `make node-server-build`   | Build Node.js backend (TypeScript -> JavaScript)               |
| `make node-build`          | Execute the frontend build inside the Node container           |
| `make node-up`             | Start Node service (static target)                             |
| `make node-api-up`         | Start Node.js Backend API Server (api target)                  |
| `make node-framework-up`   | Start Node.js Frontend Server (framework target)               |

### PM2 Process Manager

| Command                 | Description             |
| ----------------------- | ----------------------- |
| `make node-pm2-status`  | Show PM2 process status |
| `make node-pm2-logs`    | Show PM2 logs           |
| `make node-pm2-restart` | Restart PM2 processes   |
| `make node-pm2-stop`    | Stop PM2 processes      |

### Frontend Scaffolding

| Command                        | Description                                      |
| ------------------------------ | ------------------------------------------------ |
| `make node-frontend-clean`     | Remove existing frontend (confirms if not empty) |
| `make node-frontend-nuxt`      | Scaffold Nuxt 3 frontend                         |
| `make node-frontend-next`      | Scaffold Next.js frontend                        |
| `make node-frontend-remix`     | Scaffold React Router (formerly Remix v2)        |
| `make node-frontend-sveltekit` | Scaffold SvelteKit frontend                      |

See [Frontend Scaffolding](FRONTEND-SCAFFOLDING.md) for details.

### Development URLs

| Service     | URL                     |
| ----------- | ----------------------- |
| Vite HMR    | <http://localhost:5173> |
| Node API    | <http://localhost:3000> |
| Nginx Proxy | <http://localhost:8080> |

### Browser Shortcuts

Open frequently used pages directly from the CLI. The opener is detected per OS
(`xdg-open` on Linux, `open` on macOS, `start` on Windows).

| Command               | Description                                            |
| --------------------- | ------------------------------------------------------ |
| `make open-app`       | Open the application (`https://localhost:8443`)        |
| `make open-dashboard` | Open the Dev Dashboard (`/_dev/`)                      |
| `make open-docs`      | Open generated API docs (run `make docs` first)        |
| `make open-coverage`  | Open coverage reports (run `make test-coverage` first) |

## Database & Cache

Database management and CLI access.

### PostgreSQL

| Command                 | Description                       |
| ----------------------- | --------------------------------- |
| `make postgres-cli`     | Open PostgreSQL CLI (psql)        |
| `make postgres-dump`    | Create database backup (dump.sql) |
| `make postgres-restore` | Restore database from dump.sql    |

### MariaDB

| Command                | Description                               |
| ---------------------- | ----------------------------------------- |
| `make mariadb-cli`     | Open MariaDB CLI                          |
| `make mariadb-dump`    | Create MariaDB database backup (dump.sql) |
| `make mariadb-restore` | Restore MariaDB database from dump.sql    |

### Redis

| Command              | Description                         |
| -------------------- | ----------------------------------- |
| `make redis-cli`     | Open Redis CLI                      |
| `make redis-flush`   | Flush all Redis data (DANGEROUS!)   |
| `make redis-monitor` | Monitor Redis commands in real-time |

## Backup & Migrations

Data backup and database migration commands.

| Command                             | Description                                              |
| ----------------------------------- | -------------------------------------------------------- |
| `make backup-all`                   | Backup all enabled services                              |
| `make backup-db`                    | Create encrypted database backup (GDPR-compliant)        |
| `make backup-db-list`               | List all database backups                                |
| `make backup-db-restore`            | Restore database from backup (interactive)               |
| `make backup-seaweedfs`             | Create encrypted SeaweedFS backup                        |
| `make backup-seaweedfs-list`        | List all SeaweedFS backups                               |
| `make backup-seaweedfs-restore`     | Restore SeaweedFS from backup                            |
| `make backup-rabbitmq`              | Export RabbitMQ definitions                              |
| `make backup-rabbitmq-list`         | List all RabbitMQ backups                                |
| `make backup-rabbitmq-restore`      | Import RabbitMQ definitions                              |
| `make backup-elasticsearch`         | Create Elasticsearch snapshot                            |
| `make backup-elasticsearch-list`    | List all Elasticsearch snapshots                         |
| `make backup-elasticsearch-restore` | Restore Elasticsearch from snapshot                      |
| `make db-migrations`                | Run database migrations (encryption helpers, audit logs) |
| `make db-cleanup`                   | Run retention policy cleanup (delete old logs)           |

See [BACKUP.md](../security/BACKUP.md) for detailed backup documentation.

## Quality Assurance

Code quality, testing, and validation commands.

### Combined Checks

| Command                 | Description                                                                                                                                                                                                                                    |
| ----------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `make check`            | Fast pre-check — static checks + tests WITHOUT coverage (cs-check, analyse-php, phpmd, rector-check, prettier-check, analyse-node, lint-node, test, deps-validate, compose-validate, validate-env, lint-md, lint-sql, lint-docker, lint-shell) |
| `make ci`               | Faithful CI gate simulation — the static-check set plus `test-coverage-php` + `coverage-check-php` (PHP coverage strictness: `failOnRisky`/`beStrictAboutCoverageMetadata`) + `test-coverage-node` + `audit`. Run before pushing.              |
| `make audit`            | Audit dependencies for known vulnerabilities (`composer audit` + `pnpm audit`, mirrors the CI Dependency Audit job)                                                                                                                            |
| `make test`             | Run all tests (PHP + Node.js)                                                                                                                                                                                                                  |
| `make test-coverage`    | Generate coverage reports for PHP and Node.js                                                                                                                                                                                                  |
| `make deps-validate`    | Validate dependency lockfiles (composer.lock, pnpm-lock.yaml)                                                                                                                                                                                  |
| `make compose-validate` | Validate Docker Compose configuration files                                                                                                                                                                                                    |

### PHP Quality Tools

| Command              | Description                                             |
| -------------------- | ------------------------------------------------------- |
| `make analyse`       | Run static analysis (PHP + Node)                        |
| `make analyse-php`   | Run PHPStan static analysis                             |
| `make phpmd`         | Run PHPMD (PHP Mess Detector) for code quality analysis |
| `make cs-check`      | Check coding style (dry-run)                            |
| `make cs-fix`        | Fix coding style automatically                          |
| `make cs-fix-all`    | Fix coding style aggressively on all files              |
| `make rector-check`  | Run Rector for automated refactoring analysis (dry-run) |
| `make rector-fix`    | Apply Rector refactorings automatically                 |
| `make outdated`      | Check for outdated PHP + Node.js dependencies (both)    |
| `make outdated-php`  | Check for outdated Composer packages                    |
| `make outdated-node` | Check for outdated pnpm packages (workspace-wide)       |

### Node.js Quality Tools

| Command               | Description                                      |
| --------------------- | ------------------------------------------------ |
| `make analyse-node`   | Run TypeScript type checking (static analysis)   |
| `make lint-node`      | Run ESLint on TypeScript/JavaScript files        |
| `make lint-node-fix`  | Fix ESLint issues automatically                  |
| `make prettier-check` | Check formatting with Prettier (TS/JS, Markdown) |
| `make prettier-fix`   | Fix formatting with Prettier (TS/JS, Markdown)   |

### PHP Testing

| Command                   | Description                                                                                                            |
| ------------------------- | ---------------------------------------------------------------------------------------------------------------------- |
| `make test-php`           | Run PHPUnit tests                                                                                                      |
| `make test-php-debug`     | Run PHPUnit tests with Xdebug enabled                                                                                  |
| `make test-coverage-php`  | Generate PHPUnit coverage report (HTML)                                                                                |
| `make coverage-check-php` | Enforce minimum coverage (`PHP_COVERAGE_MIN` in `.env`, ratchet — raise as coverage climbs, never lower; CI runs this) |

### Node.js Testing

| Command                   | Description                            |
| ------------------------- | -------------------------------------- |
| `make test-node`          | Run Vitest tests                       |
| `make test-node-watch`    | Run Vitest in watch mode               |
| `make test-coverage-node` | Generate Vitest coverage report (HTML) |

### GOSS Container Testing

GOSS provides automated testing for Docker containers, validating
configurations, services, packages, and runtime behavior.

#### Core GOSS Commands

| Command                | Description                                                   |
| ---------------------- | ------------------------------------------------------------- |
| `make goss-build`      | Build GOSS testing tool image (required for build-time tests) |
| `make goss-test`       | Run runtime integration tests for all running containers      |
| `make goss-test-build` | Run GOSS build-time tests for all images                      |
| `make goss-test-all`   | Run both build-time and runtime tests                         |
| `make goss-cleanup`    | Remove all Goss test containers, networks, and volumes        |

#### Service-Specific Tests

Run runtime tests for specific containers:

| Command                        | Description                            |
| ------------------------------ | -------------------------------------- |
| `make goss-test-nginx`         | Test nginx container (runtime)         |
| `make goss-test-php`           | Test PHP container (runtime)           |
| `make goss-test-node-backend`  | Test node-backend container (runtime)  |
| `make goss-test-node-frontend` | Test node-frontend container (runtime) |
| `make goss-test-postgres`      | Test PostgreSQL container (runtime)    |
| `make goss-test-mariadb`       | Test MariaDB container (runtime)       |
| `make goss-test-redis`         | Test Redis container (runtime)         |
| `make goss-test-mercure`       | Test Mercure container (runtime)       |
| `make goss-test-meilisearch`   | Test Meilisearch container (runtime)   |
| `make goss-test-elasticsearch` | Test Elasticsearch container (runtime) |
| `make goss-test-mailpit`       | Test Mailpit container (runtime)       |
| `make goss-test-seaweedfs`     | Test SeaweedFS container (runtime)     |
| `make goss-test-rabbitmq`      | Test RabbitMQ container (runtime)      |

#### Preset Testing

Test complete stack configurations:

| Command                      | Description                                     |
| ---------------------------- | ----------------------------------------------- |
| `make goss-test-preset`      | Test a preset (PRESET=dev-fullstack, VERBOSE=1) |
| `make goss-test-matrix`      | Run ALL preset tests (dev + prod)               |
| `make goss-test-matrix-dev`  | Run development preset tests only               |
| `make goss-test-matrix-prod` | Run production preset tests only (CI/CD)        |

**Development Presets:**

| Command                                 | Description                                |
| --------------------------------------- | ------------------------------------------ |
| `make goss-test-dev-fullstack`          | Full-Stack (PHP + Node + Postgres + Redis) |
| `make goss-test-dev-php-only`           | PHP-Only (PHP + Postgres + Redis)          |
| `make goss-test-dev-node-only`          | Node-Only (Node + Postgres + Redis)        |
| `make goss-test-dev-minimal`            | Minimal (Nginx only)                       |
| `make goss-test-dev-fullstack-mariadb`  | Full-Stack with MariaDB                    |
| `make goss-test-dev-fullstack-optional` | Full-Stack with all optional services      |
| `make goss-test-dev-framework`          | Framework mode (Nuxt/Next + Express)       |
| `make goss-test-dev-assets`             | Assets-only (Vite HMR, no Express)         |
| `make goss-test-dev-idle`               | Idle mode (Node container idle)            |

**Production Presets:**

| Command                                  | Description                                |
| ---------------------------------------- | ------------------------------------------ |
| `make goss-test-prod-fullstack`          | Full-Stack (PHP + Node + Postgres + Redis) |
| `make goss-test-prod-php-only`           | PHP-Only (PHP + Postgres + Redis)          |
| `make goss-test-prod-node-only`          | Node-Only (Node + Postgres + Redis)        |
| `make goss-test-prod-minimal`            | Minimal (Nginx only)                       |
| `make goss-test-prod-fullstack-mariadb`  | Full-Stack with MariaDB                    |
| `make goss-test-prod-fullstack-optional` | Full-Stack with all optional services      |

**Examples:**

```bash
# Run all tests (build + runtime)
make goss-test-all

# Test specific service
make goss-test-redis

# Test a specific preset
make goss-test-preset PRESET=dev-fullstack

# Run full test matrix (CI/CD)
make goss-test-matrix VERBOSE=1
```

See `tests/goss/README.md` for detailed GOSS testing documentation.

### Linting

| Command             | Description                                         |
| ------------------- | --------------------------------------------------- |
| `make lint-config`  | Validate YAML configuration files                   |
| `make lint-md`      | Check Markdown files for style issues               |
| `make lint-md-fix`  | Fix Markdown style issues automatically             |
| `make lint-shell`   | Lint shell scripts with ShellCheck                  |
| `make lint-sql`     | Check SQL files for style issues (Postgres/MariaDB) |
| `make lint-sql-fix` | Fix SQL style issues automatically                  |
| `make lint-docker`  | Lint Dockerfiles with hadolint                      |
| `make validate-env` | Validate `.env` configuration for production        |

### Coverage Reports

After running coverage commands, reports are available at:

- PHP: `build/coverage/php/index.html`
- Node.js: `build/coverage/node/index.html`

## Security

Security scanning and secrets management.

### Secrets Management

| Command                         | Description                                                  |
| ------------------------------- | ------------------------------------------------------------ |
| `make secrets`                  | Generate missing Docker Secrets (idempotent)                 |
| `make secrets-rotate-passwords` | Rotate database passwords only (safe, keeps encryption keys) |
| `make secrets-rotate`           | Rotate ALL secrets (DANGER: breaks existing backups!)        |
| `make check-cors`               | Show current CORS configuration and security check           |

### Security Scanning

| Command                        | Description                                                  |
| ------------------------------ | ------------------------------------------------------------ |
| `make security-scan`           | Scan Docker images for vulnerabilities                       |
| `make security-config`         | Check Dockerfiles for misconfigurations                      |
| `make security-deps`           | Scan Composer dependencies for known vulnerabilities         |
| `make security-sbom`           | Generate a Software Bill of Materials (SBOM) using Trivy     |
| `make security-audit-node`     | Scan Node.js dependencies for known vulnerabilities          |
| `make falco-run`               | Start Falco for Runtime Security Monitoring                  |
| `make security-zap`            | Run OWASP ZAP DAST scan (respects .env, full lifecycle)      |
| `make security-zap-full`       | Run comprehensive ZAP scan (all services, ignores .env)      |
| `make security-zap-start`      | Start services for ZAP scan (respects .env ENABLE\_\* flags) |
| `make security-zap-full-start` | Start ALL services for comprehensive ZAP scan (ignores .env) |
| `make security-zap-scan`       | Run ZAP scan (requires running services)                     |
| `make security-zap-stop`       | Stop services after ZAP scan                                 |

#### ZAP Scan Modes

**`security-zap` (Standard):**

- Respects `.env` ENABLE\_\* configuration
- Tests only enabled services
- Use for: Project development, custom stack testing

**`security-zap-full` (Comprehensive):**

- Forces ALL services (ignores `.env`)
- Tests maximum attack surface
- Use for: Boilerplate releases, platform validation

**Manual workflow:**

```bash
# Start services
make security-zap-start          # Standard (.env config)
make security-zap-full-start     # Comprehensive (all services)

# Run scan (can repeat without restart)
make security-zap-scan

# Stop services
make security-zap-stop
```

See [SECURITY-SCANNING.md](../security/SECURITY-SCANNING.md) for detailed
security documentation.

## SSL/TLS

SSL certificate management.

| Command                    | Description                                                    |
| -------------------------- | -------------------------------------------------------------- |
| `make ssl-internal`        | Generate CA + all certificates (default for development)       |
| `make ssl-trust-ca`        | Trust internal CA in system (auto-detects OS, requires sudo)   |
| `make ssl-trust-ca-help`   | Show manual instructions to trust CA for all OSes              |
| `make ssl-untrust-ca`      | Remove internal CA from system trust store (requires sudo)     |
| `make ssl-untrust-ca-help` | Show manual instructions to remove CA for all OSes             |
| `make ssl-letsencrypt`     | Setup Let's Encrypt SSL certificate (production)               |
| `make ssl-renew`           | Renew Let's Encrypt certificate and reload all SSL services    |
| `make ssl-reload-services` | Reload all SSL-dependent services after certificate renewal    |
| `make ssl-info`            | Show SSL certificate information                               |
| `make ssl-prod-enable`     | Enable SSL/TLS for production (generates config from template) |
| `make ssl-clean`           | Remove all SSL certificates (DANGEROUS!)                       |

### SSL Setup Workflow

```bash
# Development (self-signed with CA)
make ssl-internal      # Generate CA + certificates
make ssl-trust-ca      # Trust CA in system (avoids browser warnings)
make restart

# Production (Let's Encrypt)
make ssl-letsencrypt  # Follow prompts for domain/email
make ssl-prod-enable
ZAPPZARAPP_ENV=production make build && make up
```

### Supported OS for ssl-trust-ca

| OS            | Method                         |
| ------------- | ------------------------------ |
| macOS         | System Keychain                |
| Arch/Manjaro  | `trust anchor`                 |
| Debian/Ubuntu | `update-ca-certificates`       |
| RHEL/Fedora   | `update-ca-trust`              |
| openSUSE      | `update-ca-certificates`       |
| Windows       | Manual (see ssl-trust-ca-help) |

See [SSL-CERTIFICATES.md](../security/SSL-CERTIFICATES.md) for detailed
documentation.

## Documentation

API documentation generation.

| Command                   | Description                                                     |
| ------------------------- | --------------------------------------------------------------- |
| `make docs`               | Generate all API documentation (PHP + Node)                     |
| `make docs-php`           | Generate PHP API documentation using phpDocumentor              |
| `make docs-node`          | Generate all Node/TypeScript documentation (Backend + Frontend) |
| `make docs-node-backend`  | Generate Node.js Backend API documentation                      |
| `make docs-node-frontend` | Generate Node.js Frontend documentation (if code exists)        |
| `make docs-clean`         | Remove generated documentation                                  |

Documentation output:

- PHP: `docs/api/php/`
- Node Backend: `docs/api/node-backend/`
- Node Frontend: `docs/api/node-frontend/`

## Workflow Commands

Common workflow combinations.

| Command             | Description                                                                         |
| ------------------- | ----------------------------------------------------------------------------------- |
| `make check-health` | Check application health by container status                                        |
| `make renovate`     | Dry-run Renovate locally (no PRs; validates `renovate.json`, lists pending updates) |

## Command Patterns

### Passing Arguments

Some commands accept arguments via environment variables:

```bash
# Composer with arguments
make composer CMD="require vendor/package"
make composer CMD="update --dry-run"

# pnpm with arguments
make pnpm CMD="add vue"
make pnpm CMD="run build"

# Backup with custom retention
make backup-db RETENTION=14
```

### Chaining Commands

Commands can be chained with `&&`:

```bash
make build && make up
make cs-fix && make check
make down && make fresh
```

### Environment Overrides

Override environment variables inline:

```bash
ZAPPZARAPP_ENV=production make build
XDEBUG_MODE=debug make up
NODE_TARGET=app-server make up
```

## Tips

### Daily Development Workflow

```bash
# Morning: Start environment
make up
make node-dev  # or node-dev-full

# During development
make test      # Run tests
make cs-fix    # Fix code style
make logs      # Check logs if issues

# End of day
make down
```

### Before Committing

```bash
make check  # Runs: cs-check, analyse, phpmd, rector-check, test, deps-validate, compose-validate
```

### After Pulling Changes

```bash
make composer-install  # Update PHP dependencies
make pnpm-install      # Update Node dependencies
make restart           # Restart containers
```

### Debugging

```bash
XDEBUG_MODE=develop,debug make restart  # Enable Xdebug
make test-php-debug                      # Run tests with debugger
```

### Testing Production Build

```bash
# Before release: Test production configuration
ZAPPZARAPP_ENV=production make build
make test-production

# Quick smoke test
make test-production-minimal

# Comprehensive pre-release validation
make test-production-full
```

### Performance Issues

```bash
make status     # Check resource usage
make logs       # Check for errors
make prune      # Clean up unused images
```
