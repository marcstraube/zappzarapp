# Quickstart Guide

Get the docker-webdev boilerplate running in under 5 minutes.

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

## Quick Setup

### 1. Clone and Initialize

```bash
git clone <repository-url> my-project
cd my-project
make init
```

This creates `.env` from `.env.example`.

### 2. Configure Environment

Edit `.env` and set your user/group IDs (prevents permission issues):

```bash
# Find your IDs
id -u  # User ID
id -g  # Group ID

# Edit .env
USER_ID=1000    # Your user ID
GROUP_ID=1000   # Your group ID
```

### 3. Run Setup

```bash
make setup
```

This command:

- Creates project directories
- Generates SSL certificates (self-signed)
- Creates Docker secrets
- Builds all Docker images
- Starts containers
- Installs dependencies (Composer + pnpm)

### 4. Verify Installation

```bash
make check-health
```

Open in browser:

- **HTTP**: <http://localhost:8080>
- **HTTPS**: <https://localhost:8443> (accept self-signed cert)

## First Steps After Setup

### Start Development

```bash
# Start all containers (if not running)
make up

# Start Vite dev server with HMR
make node-dev

# Or start full-stack development (Vite + Express)
make node-dev-full
```

### Access URLs

| Service       | URL                          | Description           |
| ------------- | ---------------------------- | --------------------- |
| Nginx (HTTP)  | <http://localhost:8080>      | Main entry point      |
| Nginx (HTTPS) | <https://localhost:8443>     | SSL entry point       |
| Vite HMR      | <http://localhost:5173>      | Frontend dev server   |
| Node API      | <http://localhost:3000>      | Express backend       |
| Dev Dashboard | <http://localhost:8080/dev/> | PHP development tools |

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
| `make logs`       | Show all logs                |
| `make status`     | Show container status        |
| `make shell-php`  | Open shell in PHP container  |
| `make shell-node` | Open shell in Node container |

See [MAKEFILE-REFERENCE.md](MAKEFILE-REFERENCE.md) for all available commands.

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
├── src/php/           # PHP application code
├── src/node/          # Node.js application code
├── resources/         # Frontend assets (JS, CSS, images)
├── public/            # Web root
├── tests/             # Test suites
├── docker/            # Docker configuration
├── documentation/     # Documentation
└── storage/           # Runtime data (uploads, cache, logs)
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

Edit `.env` to change ports:

```bash
NGINX_PORT=8081        # Default: 8080
NGINX_SSL_PORT=8444    # Default: 8443
VITE_PORT=5174         # Default: 5173
```

See [TROUBLESHOOTING.md](TROUBLESHOOTING.md) for more solutions.

## Next Steps

1. **Read the Architecture**: [ARCHITECTURE.md](ARCHITECTURE.md)
2. **Explore Makefile**: [MAKEFILE-REFERENCE.md](MAKEFILE-REFERENCE.md)
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
make composer-update # Update PHP deps
make pnpm-update     # Update Node deps
make backup          # Backup database
```
