# System Architecture

This document provides a comprehensive overview of the zappzarapp boilerplate
architecture, including components, data flows, and design decisions.

## Architecture Diagram

```text
                                    INTERNET
                                        │
                                        ▼
┌──────────────────────────────────────────────────────────────────────────────┐
│                             FRONTEND NETWORK                                 │
│  ┌────────────────────────────────────────────────────────────────────────┐  │
│  │                             NGINX                                      │  │
│  │  • Reverse Proxy & Load Balancer                                       │  │
│  │  • SSL/TLS Termination (ports 8080/8443)                               │  │
│  │  • Static Asset Serving (Brotli compression)                           │  │
│  │  • Custom Error Pages                                                  │  │
│  └───────────────────┬───────────────────────┬────────────────────────────┘  │
└──────────────────────┼───────────────────────┼───────────────────────────────┘
                       │                       │
                       ▼                       ▼
┌──────────────────────────────────────────────────────────────────────────────┐
│                             BACKEND NETWORK                                  │
│  ┌───────────────────────────┐    ┌───────────────────────────────────────┐  │
│  │         PHP-FPM           │    │             NODE.JS                   │  │
│  │  • Unix Socket Connection │    │  • Vite Dev Server (HMR, port 5173)   │  │
│  │  • PHP 8.4                │    │  • Express API (port 3000)            │  │
│  │  • Xdebug Support         │    │  • PM2 Process Manager                │  │
│  │  • DevDashboard           │    │  • DevDashboard (dev only)            │  │
│  └───────────┬───────────────┘    └──────────────┬────────────────────────┘  │
└──────────────┼───────────────────────────────────┼───────────────────────────┘
               │                                   │
               ▼                                   ▼
┌──────────────────────────────────────────────────────────────────────────────┐
│                             DATABASE NETWORK                                 │
│  ┌─────────────────┐  ┌─────────────────┐  ┌──────────────────────────────┐  │
│  │   POSTGRESQL    │  │    MARIADB      │  │           REDIS              │  │
│  │  • pg_cron ext. │  │  (alternative)  │  │  • TLS Encryption            │  │
│  │  • SSL/TLS      │  │  • SSL/TLS      │  │  • Sessions/Cache            │  │
│  │  • Port 5432    │  │  • Port 3306    │  │  • Port 6379                 │  │
│  └─────────────────┘  └─────────────────┘  └──────────────────────────────┘  │
└──────────────────────────────────────────────────────────────────────────────┘
```

## Component Overview

### Core Services

| Service      | Technology        | Purpose                                       | Network(s)        |
| ------------ | ----------------- | --------------------------------------------- | ----------------- |
| **nginx**    | Nginx 1.28 Alpine | Reverse proxy, SSL termination, static assets | frontend, backend |
| **php**      | PHP 8.4-FPM       | Backend application server                    | backend, database |
| **node**     | Node.js 24.12 LTS | Frontend (Vite) + Backend (Express)           | backend, database |
| **postgres** | PostgreSQL 16+    | Primary database (default)                    | database          |
| **mariadb**  | MariaDB 11+       | Alternative database                          | database          |
| **redis**    | Redis + TLS       | Caching, sessions, job queues                 | database          |

### Network Segmentation (3-Tier)

The architecture implements GDPR-compliant network isolation:

1. **Frontend Network**: Public-facing (nginx only)
2. **Backend Network**: Application layer (nginx, php, node)
3. **Database Network**: Data layer (databases + php/node as gateways)

See [NETWORK.md](NETWORK.md) for detailed network security documentation.

## Stack Presets

The boilerplate supports 7 configurable stack presets via `.env`:

| Preset                   | PHP | Node | Database | Redis | Use Case                       |
| ------------------------ | --- | ---- | -------- | ----- | ------------------------------ |
| **Full-Stack** (default) | Yes | Yes  | Yes      | Yes   | Complete web application       |
| **Pure PHP**             | Yes | No   | Yes      | Yes   | Traditional PHP applications   |
| **Pure Node.js**         | No  | Yes  | Yes      | Yes   | JavaScript/TypeScript backends |
| **Static/JAMstack**      | No  | No   | No       | No    | Pre-built static sites         |
| **Minimal Node.js**      | No  | Yes  | No       | No    | Simple Node.js APIs            |
| **Minimal PHP**          | Yes | No   | No       | No    | Simple PHP scripts             |
| **Custom**               | Mix | Mix  | Mix      | Mix   | Custom configuration           |

**Development Defaults:** `make up` ensures Node starts with `assets-api` mode
to enable both Vite HMR (Hot Module Replacement) and the Express API backend.

Configuration in `.env`:

```bash
ENABLE_PHP=true
ENABLE_NODE=true
ENABLE_DATABASE=true
ENABLE_REDIS=true
```

## Data Flows

### HTTP Request Flow

```text
Client Request
      │
      ▼
┌─────────────┐     ┌─────────────┐     ┌─────────────┐
│    Nginx    │────▶│  PHP/Node   │────▶│  Database   │
│  (SSL/TLS)  │     │ (App Logic) │     │ (Postgres)  │
└─────────────┘     └─────────────┘     └─────────────┘
      │                   │                   │
      │                   ▼                   │
      │            ┌─────────────┐            │
      │            │    Redis    │◀───────────┘
      │            │  (Cache)    │
      │            └─────────────┘
      ▼
Client Response
```

### PHP-FPM Communication

Nginx communicates with PHP-FPM via Unix socket for optimal performance:

```text
nginx → /var/run/php-fpm/php-fpm.sock → php-fpm
```

### Node.js Communication

Nginx proxies to Node.js via HTTP:

```text
nginx → http://node:3000 → Express API
nginx → http://node:5173 → Vite Dev Server (development)
```

## Docker Architecture

### Multi-Stage Builds

Each service uses optimized multi-stage Dockerfiles:

```text
                    ┌─────────────────┐
                    │   Base Stage    │
                    │ (dependencies)  │
                    └────────┬────────┘
                             │
              ┌──────────────┴──────────────┐
              ▼                             ▼
     ┌─────────────────┐           ┌─────────────────┐
     │   Development   │           │   Production    │
     │  (dev tools,    │           │  (optimized,    │
     │   hot reload)   │           │   read-only)    │
     └─────────────────┘           └─────────────────┘
```

### Build Order (Production)

```text
1. node (builds assets) ──▶ 2. php (copies assets) ──▶ 3. nginx (serves assets)
                                                              │
4. postgres/mariadb ────────────────────────────────────────▶ │
5. redis ───────────────────────────────────────────────────▶ │
                                                        (parallel)
```

### Volume Architecture

| Volume           | Purpose                 | Mount Point                |
| ---------------- | ----------------------- | -------------------------- |
| `php-fpm-socket` | PHP-FPM Unix socket     | `/var/run/php-fpm`         |
| `redis-data`     | Redis persistence       | `/data`                    |
| `postgres-data`  | PostgreSQL data         | `/var/lib/postgresql/data` |
| `mariadb-data`   | MariaDB data            | `/var/lib/mysql`           |
| `node_modules`   | Node.js dependencies    | `/app/node_modules`        |
| `php_vendor`     | PHP vendor dependencies | `/app/vendor`              |

## Security Architecture

### Docker Secrets

Sensitive data is managed via Docker Secrets:

```text
./secrets/
├── db_password.txt           # Database password
├── db_root_password.txt      # Database root password
├── encryption_key.txt        # Application encryption key
└── backup_encryption_key.txt # Backup encryption key
```

Mounted in containers at `/run/secrets/<secret_name>`.

### SSL/TLS

- **Development**: Self-signed certificates (auto-generated)
- **Production**: Let's Encrypt certificates with auto-renewal
- **Internal**: TLS for Redis, PostgreSQL, and MariaDB connections

### Production Hardening

Production mode (`ENV=production`) enables:

- Read-only root filesystems
- Resource limits (CPU, memory)
- Security headers
- Minimal logging
- No development tools

## Configuration Files

### Primary Configuration

| File                      | Purpose                                |
| ------------------------- | -------------------------------------- |
| `compose.yaml`            | Base Docker Compose configuration      |
| `compose.override.yaml`   | Development overrides (watch, volumes) |
| `compose.production.yaml` | Production hardening                   |
| `.env`                    | Environment configuration              |
| `Makefile`                | Build and deployment automation        |

### Service Configuration

| File                     | Service | Purpose               |
| ------------------------ | ------- | --------------------- |
| `phpstan.neon`           | PHP     | Static analysis rules |
| `phpmd.xml.dist`         | PHP     | Mess detector rules   |
| `.php-cs-fixer.dist.php` | PHP     | Code style rules      |
| `phpunit.xml.dist`       | PHP     | Test configuration    |
| `vite.config.js`         | Node    | Frontend build        |
| `vitest.config.ts`       | Node    | Test configuration    |
| `eslint.config.js`       | Node    | Linting rules         |
| `ecosystem.config.cjs`   | Node    | PM2 process manager   |

## Directory Structure

```text
zappzarapp/
├── .zappzarapp/              # Boilerplate config & docs
├── backups/                  # Encrypted backups
│   ├── db/                   # Database backups
│   ├── elasticsearch/        # Elasticsearch backups
│   ├── rabbitmq/             # RabbitMQ backups
│   └── seaweedfs/            # SeaweedFS backups
├── build/                    # Build output
├── config/                   # Application config
├── docker/                   # Docker configuration
│   ├── certs/                # SSL certificates
│   ├── mariadb/              # MariaDB config
│   ├── nginx/                # Nginx config
│   ├── node/                 # Node config
│   ├── php/                  # PHP config
│   ├── postgres/             # PostgreSQL config
│   ├── redis/                # Redis config
│   └── scripts/              # Utility scripts
├── migrations/               # Database migrations
├── public/                   # Web root
│   └── build/                # Compiled assets
├── resources/                # Frontend resources
│   ├── css/                  # Stylesheets
│   ├── images/               # Images
│   └── js/                   # JavaScript/TypeScript
├── secrets/                  # Docker secrets
├── src/                      # Application source code
│   ├── node/                 # Node.js application
│   │   ├── backend/          # Express API
│   │   │   ├── App/          # Production application
│   │   │   └── DevDashboard/ # Development dashboard (dev only)
│   │   └── frontend/         # SSR Frontend (optional)
│   └── php/                  # PHP application
│       ├── App/              # Main application
│       └── DevDashboard/     # Development dashboard
├── storage/                  # Runtime storage
└── tests/                    # Test suites
    ├── node/                 # Vitest tests
    └── php/                  # PHPUnit tests
```

## 12-Factor App Compliance

This architecture follows all 12 factors:

| Factor               | Implementation                          |
| -------------------- | --------------------------------------- |
| I. Codebase          | Single Git repository                   |
| II. Dependencies     | `composer.json`, `package.json`         |
| III. Config          | Environment variables (`.env`)          |
| IV. Backing Services | PostgreSQL, Redis as attached resources |
| V. Build/Release/Run | Multi-stage Dockerfiles                 |
| VI. Processes        | Stateless (sessions in Redis)           |
| VII. Port Binding    | Self-contained services                 |
| VIII. Concurrency    | Docker Compose, PM2                     |
| IX. Disposability    | Health checks, graceful shutdown        |
| X. Dev/Prod Parity   | Same stack in all environments          |
| XI. Logs             | stdout/stderr, JSON format              |
| XII. Admin Processes | Make commands                           |

## Related Documentation

- [Network Security](NETWORK.md) - Detailed network architecture
- [SSL Certificates](../security/SSL-CERTIFICATES.md) - Certificate management
- [Secrets Management](../security/SECRETS.md) - Docker Secrets
- [Backup & Restore](../security/BACKUP.md) - Encrypted backups
- [Testing](../testing/TESTING-PHP.md) - Test infrastructure
