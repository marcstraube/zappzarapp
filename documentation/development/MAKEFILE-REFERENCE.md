# Makefile Reference

Complete reference for all available `make` commands in the zappzarapp
boilerplate.

Run `make help` to see all available commands with descriptions.

## Quick Reference

| Task             | Command      |
| ---------------- | ------------ |
| First-time setup | `make setup` |
| Start containers | `make up`    |
| Stop containers  | `make down`  |
| Run all tests    | `make test`  |
| Run all checks   | `make check` |
| View logs        | `make logs`  |

## Setup Commands

Commands for initial project setup and configuration.

| Command                         | Description                                                               |
| ------------------------------- | ------------------------------------------------------------------------- |
| `make setup`                    | Full setup (directories, secrets, build, install) - entry point           |
| `make init`                     | Create `.env.local` with USER_ID/GROUP_ID (called interactively by setup) |
| `make composer-install`         | Install/update Composer dependencies via Docker (guaranteed consistency)  |
| `make composer-install-local`   | Install Composer dependencies locally (for IDE code completion)           |
| `make pnpm-install`             | Install Node.js dependencies via Docker (requires ENV=development)        |
| `make pnpm-install-local`       | Install Node.js dependencies locally (for IDE code completion)            |
| `make hooks-install`            | Install Git hooks using CaptainHook                                       |
| `make ide-config`               | Configure all IDE database connections (PHPStorm + VS Code)               |
| `make ide-config-full`          | Update all IDE configs with custom ports from `.env.local`                |
| `make ide-config-phpstorm`      | Configure PHPStorm only (`.idea/dataSources.local.xml`)                   |
| `make ide-config-vscode`        | Configure VS Code only (`.vscode/settings.json`)                          |
| `make ide-config-phpstorm-full` | Update PHPStorm shared config with custom ports                           |
| `make ide-config-vscode-full`   | Update VS Code config with custom ports                                   |

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
- `.env` - Environment variables (NODE_MODE, ENV, etc.)

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

### Container Information

| Command              | Description                                         |
| -------------------- | --------------------------------------------------- |
| `make status`        | Show running containers status and image disk usage |
| `make logs`          | Show logs of all containers (or specific services)  |
| `make logs-nginx`    | Show Nginx logs only                                |
| `make logs-php`      | Show PHP logs only                                  |
| `make logs-node`     | Show Node.js logs only                              |
| `make logs-redis`    | Show Redis logs only                                |
| `make logs-postgres` | Show PostgreSQL logs only                           |
| `make logs-mariadb`  | Show MariaDB logs only                              |

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

| Command               | Description                          |
| --------------------- | ------------------------------------ |
| `make k8s-deploy`     | Deploy to Kubernetes using Helm      |
| `make k8s-remove`     | Remove deployment from Kubernetes    |
| `make k8s-status`     | Show pods, services, and Helm status |
| `make k8s-logs [pod]` | View logs from a specific pod        |

**Examples:**

```bash
# Deploy with default values
make k8s-deploy

# Deploy with production values
ENV=production make k8s-deploy

# View logs
make k8s-logs zappzarapp-php-xxxxx

# Remove deployment
make k8s-remove
```

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

| Command                   | Description                                      |
| ------------------------- | ------------------------------------------------ |
| `make frontend-clean`     | Remove existing frontend (confirms if not empty) |
| `make frontend-nuxt`      | Scaffold Nuxt 3 frontend                         |
| `make frontend-next`      | Scaffold Next.js frontend                        |
| `make frontend-remix`     | Scaffold React Router (formerly Remix v2)        |
| `make frontend-sveltekit` | Scaffold SvelteKit frontend                      |

See [Frontend Scaffolding](FRONTEND-SCAFFOLDING.md) for details.

### Development URLs

| Service     | URL                     |
| ----------- | ----------------------- |
| Vite HMR    | <http://localhost:5173> |
| Node API    | <http://localhost:3000> |
| Nginx Proxy | <http://localhost:8080> |

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

| Command              | Description                                                                                                             |
| -------------------- | ----------------------------------------------------------------------------------------------------------------------- |
| `make check`         | Run ALL checks (cs-check, analyse, phpmd, rector-check, prettier-check, type-check, lint-node, test, validate, lint-md) |
| `make test`          | Run all tests (PHP + Node.js)                                                                                           |
| `make test-coverage` | Generate coverage reports for PHP and Node.js                                                                           |
| `make validate`      | Validate composer.json/lock and package.json/lock files                                                                 |

### PHP Quality Tools

| Command             | Description                                             |
| ------------------- | ------------------------------------------------------- |
| `make analyse`      | Run PHPStan static analysis                             |
| `make phpmd`        | Run PHPMD (PHP Mess Detector) for code quality analysis |
| `make cs-check`     | Check coding style (dry-run)                            |
| `make cs-fix`       | Fix coding style automatically                          |
| `make cs-fix-all`   | Fix coding style aggressively on all files              |
| `make rector-check` | Run Rector for automated refactoring analysis (dry-run) |
| `make rector-fix`   | Apply Rector refactorings automatically                 |
| `make outdated`     | Check for outdated Composer dependencies                |

### Node.js Quality Tools

| Command               | Description                                      |
| --------------------- | ------------------------------------------------ |
| `make type-check`     | Run TypeScript type checking (static analysis)   |
| `make lint-node`      | Run ESLint on TypeScript/JavaScript files        |
| `make lint-node-fix`  | Fix ESLint issues automatically                  |
| `make prettier-check` | Check formatting with Prettier (TS/JS, Markdown) |
| `make prettier-fix`   | Fix formatting with Prettier (TS/JS, Markdown)   |

### PHP Testing

| Command                  | Description                             |
| ------------------------ | --------------------------------------- |
| `make test-php`          | Run PHPUnit tests                       |
| `make test-php-debug`    | Run PHPUnit tests with Xdebug enabled   |
| `make test-coverage-php` | Generate PHPUnit coverage report (HTML) |

### Node.js Testing

| Command                   | Description                            |
| ------------------------- | -------------------------------------- |
| `make test-node`          | Run Vitest tests                       |
| `make test-node-watch`    | Run Vitest in watch mode               |
| `make test-coverage-node` | Generate Vitest coverage report (HTML) |

### Linting

| Command            | Description                             |
| ------------------ | --------------------------------------- |
| `make lint-config` | Validate YAML configuration files       |
| `make lint-md`     | Check Markdown files for style issues   |
| `make lint-md-fix` | Fix Markdown style issues automatically |

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

### Security Scanning

| Command                    | Description                                              |
| -------------------------- | -------------------------------------------------------- |
| `make security-scan`       | Scan Docker images for vulnerabilities                   |
| `make security-config`     | Check Dockerfiles for misconfigurations                  |
| `make security-deps`       | Scan Composer dependencies for known vulnerabilities     |
| `make security-sbom`       | Generate a Software Bill of Materials (SBOM) using Trivy |
| `make security-audit-node` | Scan Node.js dependencies for known vulnerabilities      |
| `make falco-run`           | Start Falco for Runtime Security Monitoring              |

See [SECURITY-SCANNING.md](../security/SECURITY-SCANNING.md) for detailed
security documentation.

## SSL/TLS

SSL certificate management.

| Command                    | Description                                                    |
| -------------------------- | -------------------------------------------------------------- |
| `make ssl-selfsigned`      | Generate self-signed SSL certificate for development           |
| `make ssl-letsencrypt`     | Setup Let's Encrypt SSL certificate (production)               |
| `make ssl-renew`           | Renew Let's Encrypt certificate and reload all SSL services    |
| `make ssl-reload-services` | Reload all SSL-dependent services after certificate renewal    |
| `make ssl-info`            | Show SSL certificate information                               |
| `make ssl-prod-enable`     | Enable SSL/TLS for production (generates config from template) |
| `make ssl-clean`           | Remove all SSL certificates (DANGEROUS!)                       |

### SSL Setup Workflow

```bash
# Development (self-signed)
make ssl-selfsigned
make restart

# Production (Let's Encrypt)
make ssl-letsencrypt  # Follow prompts for domain/email
make ssl-prod-enable
ENV=production make build && make up
```

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

| Command             | Description                                  |
| ------------------- | -------------------------------------------- |
| `make check-health` | Check application health by container status |
| `make renovate`     | Run Renovate dependency scanner              |

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
ENV=production make build
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
make check  # Runs: cs-check, analyse, phpmd, rector-check, test, validate
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

### Performance Issues

```bash
make status     # Check resource usage
make logs       # Check for errors
make prune      # Clean up unused images
```
