# ⚡ zappzarapp

**zappzarapp** · /ˈt͡sapt͡saˈʁap/

> German colloquial for "in a flash" — from Russian цап-царап: grab it and go.

A professional web development stack that gets you coding in minutes, not hours.
No setup hassle, no reinventing the wheel - just clone, configure, and go.

## Why zappzarapp?

- **Dual-Language Stack** — PHP and Node.js as equal citizens, not an
  afterthought
- **Zero Configuration** — `make setup` handles everything: SSL certs, secrets,
  dependencies, containers
- **GDPR-Ready** — 3-tier network segmentation, encryption helpers, audit
  logging infrastructure
- **Production-Ready** — Same stack from development to deployment, no rebuild
  required
- **Full IDE Support** — Pre-configured for PHPStorm and VS Code (run configs,
  debugging, database)
- **Modular Architecture** — 7 stack presets from static sites to full-stack,
  enable only what you need
- **Optional Services** — Redis, Mercure, Meilisearch, Elasticsearch, Mailpit,
  SeaweedFS, RabbitMQ — all pre-configured, one toggle away

### Built for

- Teams building PHP and/or Node.js applications
- Projects requiring GDPR/compliance features
- Developers who want to understand their stack, not just use it
- Production deployments from day one

### Consider alternatives if

- Single-framework projects (Laravel, Symfony, Nest.js have dedicated tools)
- CMS-focused work (Drupal, WordPress, TYPO3 have specialized environments)
- Quick prototypes where Docker knowledge isn't desired

## Features

- **PHP 8.4** with PHP-FPM and comprehensive extension support
- **Node.js 24** with Vite, TypeScript, and HMR
- **Nginx** as reverse proxy with SSL/TLS support
- **Databases:** PostgreSQL and MariaDB with encryption at rest
- **Redis** for caching and sessions
- **GDPR Compliance:** Audit logging, encryption services, network segmentation
- **Development Tools:** Xdebug, PHPStan, PHPUnit, Vitest, ESLint
- **IDE Support:** Pre-configured for PhpStorm and VS Code
- **Git Hooks:** Automated quality checks via Captainhook
- **CI/CD Ready:** GitHub Actions, GitLab CI, Renovate

## Prerequisites

- Docker 20.10+
- Docker Compose V2
- Make
- Git

## Quick Start

```bash
# 1. Initialize project (creates .env from example)
make init

# 2. Configure environment
#    Edit .env and adjust settings (ports, user IDs, etc.)

# 3. Setup project (builds images, installs dependencies, creates directories)
make setup

# 4. Start containers
make up
```

The application is available at:

- **HTTP:** <http://localhost:8080>
- **HTTPS:** <https://localhost:8443>
- **Dev Dashboard:** <http://localhost:8080/\_dev>

## Project Structure

```text
.
├── src/
│   ├── php/           # PHP backend code
│   └── node/          # Node.js backend code
├── resources/         # Frontend source (JS, CSS)
├── public/            # Web root (Nginx)
├── docker/            # Docker configuration
├── tests/             # PHPUnit and Vitest tests
├── documentation/     # Technical documentation
└── storage/           # Runtime data
```

## Documentation

Detailed documentation is available in [`documentation/`](documentation/):

### Getting Started

- [Quickstart Guide](documentation/QUICKSTART.md)
- [Customization](documentation/getting-started/CUSTOMIZATION.md)
- [Troubleshooting](documentation/TROUBLESHOOTING.md)
- [Windows Setup](documentation/setup/WINDOWS.md)

### Development

- [Dev Dashboard](documentation/development/DEV-DASHBOARD.md)
- [Makefile Reference](documentation/development/MAKEFILE-REFERENCE.md)
- [Frontend Scaffolding](documentation/development/FRONTEND-SCAFFOLDING.md)
- [Xdebug Configuration](documentation/development/XDEBUG.md)
- [Dependencies](documentation/development/DEPENDENCIES.md)
- [Renovate (Auto-Updates)](documentation/development/RENOVATE.md)

### Testing

- [PHP Testing](documentation/testing/TESTING-PHP.md)
- [Node.js Testing](documentation/testing/TESTING-NODE.md)
- [GOSS Container Tests](tests/goss/README.md)

### Infrastructure

- [Architecture Overview](documentation/infrastructure/ARCHITECTURE.md)
- [Network Configuration](documentation/infrastructure/NETWORK.md)
- [Nginx Configuration](documentation/infrastructure/NGINX.md)
- [Deployment Guide](documentation/infrastructure/DEPLOYMENT.md)
- [Kubernetes](documentation/infrastructure/KUBERNETES.md)
- [Optional Services](documentation/infrastructure/OPTIONAL-SERVICES.md)
- [Performance Tuning](documentation/infrastructure/PERFORMANCE.md)
- [Error Pages](documentation/infrastructure/ERROR-PAGES.md)
- [Monitoring](documentation/infrastructure/MONITORING.md)

### Security

- [SSL Certificates](documentation/security/SSL-CERTIFICATES.md)
- [Internal TLS](documentation/security/INTERNAL-TLS.md)
- [Secrets Management](documentation/security/SECRETS.md)
- [Database Encryption](documentation/security/ENCRYPTION.md)
- [Audit Logging](documentation/security/AUDIT-LOGGING.md)
- [Access Log Monitoring](documentation/security/ACCESS-LOG-MONITORING.md)
- [Security Scanning](documentation/security/SECURITY-SCANNING.md)
- [Backup & Recovery](documentation/security/BACKUP.md)
- [Data Retention Policy](documentation/security/RETENTION-POLICY.md)

### IDE Setup

- [JetBrains (PhpStorm/WebStorm)](.idea/README.md)
- [VS Code](.vscode/README.md)

### Components

- [Node.js Frontend](src/node/frontend/README.md)

### Other

- [Contributing](documentation/CONTRIBUTING.md)
- [Changelog](documentation/CHANGELOG.md)

## Make Commands

```bash
make help          # Show all available commands
make init          # Initialize project (create .env)
make setup         # Full project setup
make up            # Start containers
make down          # Stop containers
make build         # Build Docker images
make restart       # Restart containers
make logs          # Show logs
make shell-php     # Shell in PHP container
make shell-node    # Shell in Node container
make test          # Run all tests
make analyse       # Static analysis (PHPStan)
make check         # Full quality check
```

## Git Hooks

This project uses [Captainhook](https://github.com/captainhookphp/captainhook)
for Git hooks.

Hooks are automatically installed during `make setup`. To install manually:

```bash
vendor/bin/captainhook install
```

Configured hooks include:

- **pre-commit:** Auto-fix code style (PHP-CS-Fixer, Prettier, ESLint,
  Markdownlint)
- **commit-msg:** Commit message validation (conventional commits)
- **pre-push:** Static analysis (PHPStan, TypeScript) and tests (PHPUnit,
  Vitest)

## Development Modes

### PHP Backend (Default)

```bash
ENV=development make up
```

### Node.js Development (HMR)

```bash
make node-dev      # Starts Vite dev server on port 5173
```

### Production Build

```bash
ENV=production make build
make up
```

## License

MIT License - see [LICENSE](LICENSE) for details.
