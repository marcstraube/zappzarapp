# Quickstart Guide

Get zappzarapp running in under 5 minutes.

## Prerequisites

- **Docker** 24.0+ with Docker Compose v2
- **Make** (GNU Make)
- **Git**

### Verify Installation

```bash
docker --version    # Docker version 24.0+
docker compose version  # Docker Compose v2.x
make --version      # GNU Make
```

### Optional: Local Composer / pnpm

The application itself needs neither on your host — `vendor/` and `node_modules`
live in Docker volumes. Two things still benefit from a local install:

- **Composer** — recommended. Beyond IDE indexing, the Git hooks run through
  `vendor/bin/captainhook` on the host, so pre-commit/pre-push need local PHP
  dependencies. `make setup` warns when local Composer is missing; see
  [CONTRIBUTING](CONTRIBUTING.md) for `make composer-install-local`.
- **pnpm** — optional. Only improves IDE indexing of Node dependencies; the
  hooks (lint-staged) run inside Docker and do not need pnpm on the host.

## Quick Setup

### 1. Create Your Project

#### Option A: Fork on GitHub (recommended)

1. Fork `marcstraube/zappzarapp` on GitHub
2. Clone your fork:

```bash
git clone git@github.com:YOUR-USERNAME/my-project.git
cd my-project
make setup
```

#### Option B: Direct clone

```bash
git clone https://github.com/marcstraube/zappzarapp.git my-project
cd my-project
git remote set-url origin git@github.com:YOUR-USERNAME/my-project.git
make setup
```

On first run, `make setup` will ask whether to auto-detect your USER_ID/GROUP_ID
(recommended) or continue with defaults.

> **Note:** After `make setup`, your project files (README, CHANGELOG, etc.) are
> replaced with project templates. The `zappzarapp` remote for updates is added
> automatically when you run `make boilerplate-sync`.

This command:

- Creates project directories
- Generates SSL certificates (self-signed)
- Creates Docker secrets
- Builds all Docker images
- Installs dependencies (Composer + pnpm)
- Starts containers

### 2. Verify Installation

```bash
make check-health
```

Open in browser:

- **HTTP**: <http://localhost:8080>
- **HTTPS**: <https://localhost:8443> (accept self-signed cert)

## First Steps After Setup

### Customize the Templates

`make setup` replaces the README and agent-context files with project templates
that still contain placeholders. See what is left to fill in:

```bash
make customize
```

Replace each `zappzarapp:customize` marker with your content and re-run until it
reports all templates are customized. See
[CUSTOMIZATION.md](getting-started/CUSTOMIZATION.md) for the full list.

### Start Development

```bash
# Start all containers (if not running)
make up

# Start Vite dev server with HMR
make node-dev

# Or start full-stack development (Vite + Express)
make node-dev-full
```

> **Note:** In development mode, Node starts with `NODE_MODE=assets` by default.
> This enables **Vite HMR** (Hot Module Replacement) for instant CSS/JS
> hot-reload. Override with `NODE_MODE=assets-api` for full-stack Node.js
> development.

### Access URLs

| Service       | URL                          | Description           |
| ------------- | ---------------------------- | --------------------- |
| Nginx (HTTP)  | <http://localhost:8080>      | Main entry point      |
| Nginx (HTTPS) | <https://localhost:8443>     | SSL entry point       |
| Vite HMR      | <http://localhost:5173>      | Frontend dev server   |
| Node API      | <http://localhost:3000>      | Express backend       |
| Dev Dashboard | <http://localhost:8080/_dev> | PHP development tools |

### Run Tests

```bash
# Run all tests (PHP + Node)
make test

# PHP tests only
make test-php

# Node.js tests only
make test-node

# With coverage reports
make test-coverage
```

### Check Code Quality

```bash
# Run all quality checks
make check

# Individual checks
make analyse     # PHPStan
make cs-check    # PHP-CS-Fixer (dry-run)
make phpmd       # PHPMD
```

## Common Commands

| Command           | Description                  |
| ----------------- | ---------------------------- |
| `make up`         | Start all containers         |
| `make down`       | Stop all containers          |
| `make restart`    | Restart containers           |
| `make build`      | Build Docker images          |
| `make logs`       | Show all logs                |
| `make status`     | Show container status        |
| `make shell-php`  | Open shell in PHP container  |
| `make shell-node` | Open shell in Node container |

**Tip:** Many commands accept service names: `make restart php nginx`,
`make logs php`, `make build php`.

See [MAKEFILE-REFERENCE.md](development/MAKEFILE-REFERENCE.md) for all available
commands.

## Stack Configuration

### Choose Your Stack

Edit `.env` to enable/disable services:

```bash
# Full-Stack (default)
ENABLE_PHP=true
ENABLE_NODE=true
ENABLE_DATABASE=true
ENABLE_REDIS=true

# Pure PHP Stack
ENABLE_PHP=true
ENABLE_NODE=false
ENABLE_DATABASE=true
ENABLE_REDIS=true

# Pure Node.js Stack
ENABLE_PHP=false
ENABLE_NODE=true
ENABLE_DATABASE=true
ENABLE_REDIS=true

# Static/JAMstack (Nginx only)
ENABLE_PHP=false
ENABLE_NODE=false
ENABLE_DATABASE=false
ENABLE_REDIS=false
```

After changing, rebuild:

```bash
make build && make up
```

### Database Selection

Choose PostgreSQL (default) or MariaDB:

```bash
# PostgreSQL (default)
DB_TYPE=postgres
DB_HOST=postgres
DB_PORT=5432

# MariaDB
DB_TYPE=mariadb
DB_HOST=mariadb
DB_PORT=3306
```

## IDE Setup

### For PHP Development

Install dependencies locally for IDE code completion:

```bash
make composer-install-local
```

Configure your IDE to use the local `vendor/` directory.

### For Node.js Development

```bash
make pnpm-install-local
```

### PHPStorm / VSCode

Pre-configured run configurations are available:

- **PHPStorm**: `.idea/runConfigurations/`
- **VSCode**: `.vscode/tasks.json`

## Project Structure

```text
my-project/
├── .zappzarapp/       # Boilerplate config & docs
├── docker/            # Docker configuration
├── public/            # Web root
├── resources/         # Frontend assets (JS, CSS, images)
├── src/
│   ├── node/          # Node.js backend/frontend
│   └── php/           # PHP application code
├── storage/           # Runtime data (uploads, cache, logs)
└── tests/             # Test suites
```

## Troubleshooting

### Permission Issues

```bash
# Reset storage permissions
chmod 770 storage -R
```

### Container Won't Start

```bash
# Check logs
make logs

# Rebuild from scratch
make fresh
```

### Port Already in Use

Edit `.env.local` to override ports:

```bash
NGINX_PORT=8081        # Default: 8080
NGINX_SSL_PORT=8444    # Default: 8443
VITE_PORT=5174         # Default: 5173
```

See [TROUBLESHOOTING.md](TROUBLESHOOTING.md) for more solutions.

## Updating from zappzarapp

When zappzarapp releases new features or fixes, sync the infrastructure:

```bash
# Preview changes (dry-run)
make boilerplate-diff

# Apply infrastructure updates
make boilerplate-sync
```

> **Automatic remote:** The `zappzarapp` remote is added automatically on first
> run. No manual setup required.

**Auto-synced (infrastructure):**

- `.zappzarapp/` — Boilerplate docs, standards, templates
- `docker/` — Docker configurations
- `.github/` — CI/CD workflows
- Config files — eslint, phpstan, vite, etc.

**Preserved (your project):**

- `README.md`, `CHANGELOG.md` — Your project docs
- `.claude/CLAUDE.md` — Your Claude configuration
- `src/`, `tests/` — Your code
- `.ai/` — Your project knowledge

**Manual review (shown after sync):**

- `Makefile` — May have project customizations
- `.gitignore` — May have project-specific ignores
- `composer.json`, `package.json` — May have project dependencies

## Next Steps

1. **Read the Architecture**: [ARCHITECTURE.md](infrastructure/ARCHITECTURE.md)
2. **Explore Makefile**:
   [MAKEFILE-REFERENCE.md](development/MAKEFILE-REFERENCE.md)
3. **Set up Debugging**: [XDEBUG.md](development/XDEBUG.md)
4. **Configure SSL**: [SSL-CERTIFICATES.md](security/SSL-CERTIFICATES.md)
5. **Learn Testing**: [TESTING-PHP.md](testing/TESTING-PHP.md) |
   [TESTING-NODE.md](testing/TESTING-NODE.md)

## Quick Reference Card

```bash
# === DAILY WORKFLOW ===
make up              # Start containers
make node-dev        # Start frontend dev
make down            # Stop containers

# === DEVELOPMENT ===
make shell-php       # PHP shell
make shell-node      # Node shell
make logs            # View logs

# === QUALITY ===
make check           # All checks
make test            # All tests
make cs-fix          # Fix code style

# === DATABASE ===
make postgres-cli    # PostgreSQL shell
make mariadb-cli     # MariaDB shell
make redis-cli       # Redis shell

# === MAINTENANCE ===
make composer-update  # Update PHP deps
make pnpm-update      # Update Node deps
make backup-db        # Backup database
make backup-all       # Backup all services

# === BOILERPLATE ===
make boilerplate-diff # Preview upstream changes
make boilerplate-sync # Sync infrastructure
```
