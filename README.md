<!-- zappzarapp-boilerplate-readme -->

# ⚡ zappzarapp

**zappzarapp** · /ˈt͡sapt͡saˈʁap/

> German colloquial for "in a flash" — from Russian цап-царап: grab it and go.

A **developer platform** for PHP and Node.js projects that gets you from zero to
production-ready in minutes — not hours. Unlike a simple boilerplate, it ships
the complete infrastructure, toolchain, and IDE integration around your code:
clone, configure, and go.

## Why zappzarapp?

- **Dual-Language Stack** — PHP and Node.js as equal citizens, not an
  afterthought
- **Zero Configuration** — `make setup` handles everything: SSL certs, secrets,
  dependencies, containers
- **Security & GDPR-Ready** — Docker Secrets, internal TLS, network
  segmentation, encryption helpers, audit logging
- **Production-Ready** — Same stack from development to deployment, including a
  Kubernetes Helm chart
- **Full IDE Support** — Pre-configured for PHPStorm and VS Code (run configs,
  debugging, database)
- **Modular Architecture** — 7 stack presets from static sites to full-stack,
  enable only what you need

## What's Included

| Category        | Scope                                                                  |
| --------------- | ---------------------------------------------------------------------- |
| Docker Services | 16 pre-configured (Nginx, PHP, Node, databases, cache, search, queue…) |
| Make Targets    | 258 across 17 categories (`make help`)                                 |
| Stack Presets   | 7 modes, from full-stack to static                                     |
| Kubernetes      | Helm chart for production deployment                                   |
| Documentation   | 40+ guides in [`.zappzarapp/docs/`](.zappzarapp/docs/)                 |
| IDE Configs     | PHPStorm + VS Code, working out of the box                             |

## Integrated Toolchain

| Category            | PHP               | Node/TypeScript     |
| ------------------- | ----------------- | ------------------- |
| **Static Analysis** | PHPStan (Level 8) | TypeScript (strict) |
| **Code Quality**    | PHPMD, Rector     | ESLint + sonarjs    |
| **Formatting**      | PHP-CS-Fixer      | Prettier            |
| **Testing**         | PHPUnit           | Vitest              |
| **Coverage**        | Xdebug/PCOV       | v8                  |
| **Documentation**   | phpDocumentor     | TypeDoc             |
| **Dead Code**       | —                 | Knip, depcheck      |

**Infrastructure linting:** Hadolint (Docker), ShellCheck (Bash), SQLFluff
(SQL), Markdownlint, YAML validation

**Container testing:** GOSS (serverspec-style container tests), BATS (Makefile
integration tests)

**Git hooks:** CaptainHook — auto-fix on commit, conventional-commit validation,
static analysis and tests on push

**Security scanning:** composer audit, pnpm audit, Trivy (images + SBOM), OWASP
ZAP, Semgrep (CI)

## Ecosystem Packages

The boilerplate ships pre-wired with zappzarapp's standalone packages — each
independently maintained and usable in any project via `composer require` /
`pnpm add`:

| Package                                                                                     | Registry                                                            | Purpose                                                                 |
| ------------------------------------------------------------------------------------------- | ------------------------------------------------------------------- | ----------------------------------------------------------------------- |
| [`zappzarapp/security`](https://github.com/marcstraube/zappzarapp-php-security)             | [Packagist](https://packagist.org/packages/zappzarapp/security)     | CSP, security headers, CSRF, cookies, input sanitization, rate limiting |
| [`zappzarapp/devtoolbar`](https://github.com/marcstraube/zappzarapp-php-devtoolbar)         | [Packagist](https://packagist.org/packages/zappzarapp/devtoolbar)   | In-app developer toolbar: timeline, query analyzer, exception tracking  |
| [`zappzarapp/audit-logger`](https://github.com/marcstraube/zappzarapp-php-audit-logger)     | [Packagist](https://packagist.org/packages/zappzarapp/audit-logger) | GDPR-compliant audit logging with tamper-proof checksums (PHP)          |
| [`@zappzarapp/audit-logger`](https://github.com/marcstraube/zappzarapp-node-audit-logger)   | [npm](https://www.npmjs.com/package/@zappzarapp/audit-logger)       | GDPR-compliant audit logging with tamper-proof checksums (Node)         |
| [`@zappzarapp/browser-utils`](https://github.com/marcstraube/zappzarapp-node-browser-utils) | [npm](https://www.npmjs.com/package/@zappzarapp/browser-utils)      | Type-safe, zero-dependency browser/Node utilities (storage, request, …) |

## IDE Integration

| Feature         | PHPStorm/WebStorm          | VS Code                  |
| --------------- | -------------------------- | ------------------------ |
| **Run Configs** | 45 curated run configs     | 44 curated tasks         |
| **Debugging**   | Xdebug ready (port 9003)   | Xdebug ready (port 9003) |
| **Database**    | Connections pre-configured | SQLTools pre-configured  |
| **Code Style**  | Project settings included  | Settings synced          |
| **Extensions**  | —                          | Recommendations included |

No manual setup required — `make setup` configures database connections for both
IDEs. See [.idea/README.md](.idea/README.md) and
[.vscode/README.md](.vscode/README.md).

## Stack Presets

| Preset      | What runs                                             |
| ----------- | ----------------------------------------------------- |
| `fullstack` | PHP + Node (Vite HMR + API) + database + Redis        |
| `php-only`  | PHP + database + Redis                                |
| `node-only` | Node + database + Redis                               |
| `framework` | Node frontend framework (Next.js, Nuxt, SvelteKit, …) |
| `assets`    | Vite asset pipeline only (PHP renders the pages)      |
| `minimal`   | Nginx serving static files                            |
| `idle`      | Node container idle (PHP-only work without rebuilds)  |

Optional services — Redis, Mercure, Meilisearch, Elasticsearch, Mailpit,
SeaweedFS, RabbitMQ — are pre-configured and one `.env` toggle away.

## Quick Start

```bash
# 1. Initialize local config (auto-detects your host UID/GID -> .env.local)
make init

# 2. (Optional) Adjust ports, enabled services, etc. in .env

# 3. Build images, install dependencies, and start everything
make setup
```

`make setup` already builds the images, installs dependencies, runs migrations
and **starts the containers** — no separate `make up` needed the first time.

The application is available at:

- **HTTP:** <http://localhost:8080>
- **HTTPS:** <https://localhost:8443>
- **Dev Dashboard:** <https://localhost:8443/_dev>

For daily work afterwards, use `make up` / `make down` to start and stop the
environment.

Prerequisites: Docker 20.10+, Docker Compose V2, Make, Git.

## What It Is — and Is Not

**It is** a developer platform: infrastructure, tooling, and scaffolding that
live _around_ your application code.

**It is not** a framework: it adds no runtime dependencies to your application.
Your PHP and Node code stay plain and portable.

### Built for

- Teams building PHP and/or Node.js applications
- Projects requiring GDPR/compliance features
- Developers who want to understand their stack, not just use it
- Production deployments from day one

### Consider alternatives if

- Single-framework projects (Laravel, Symfony, Nest.js have dedicated tools)
- CMS-focused work (Drupal, WordPress, TYPO3 have specialized environments)
- Quick prototypes where Docker knowledge isn't desired

## Project Structure

```text
.
├── .zappzarapp/       # Boilerplate config & docs
├── docker/            # Docker configuration
├── public/            # Web root (Nginx)
├── resources/         # Frontend source (JS, CSS)
├── src/
│   ├── node/          # Node.js backend/frontend
│   └── php/           # PHP backend code
├── storage/           # Runtime data
└── tests/             # PHPUnit and Vitest tests
```

## Documentation

Detailed documentation is available in [`.zappzarapp/docs/`](.zappzarapp/docs/):

### Getting Started

- [Quickstart Guide](.zappzarapp/docs/QUICKSTART.md)
- [Customization](.zappzarapp/docs/getting-started/CUSTOMIZATION.md)
- [Troubleshooting](.zappzarapp/docs/TROUBLESHOOTING.md)
- [Windows Setup](.zappzarapp/docs/setup/WINDOWS.md)

### Development

- [Dev Dashboard](.zappzarapp/docs/development/DEV-DASHBOARD.md)
- [Makefile Reference](.zappzarapp/docs/development/MAKEFILE-REFERENCE.md)
- [Frontend Scaffolding](.zappzarapp/docs/development/FRONTEND-SCAFFOLDING.md)
- [Xdebug Configuration](.zappzarapp/docs/development/XDEBUG.md)
- [Dependencies](.zappzarapp/docs/development/DEPENDENCIES.md)
- [Renovate (Auto-Updates)](.zappzarapp/docs/development/RENOVATE.md)

### Testing

- [PHP Testing](.zappzarapp/docs/testing/TESTING-PHP.md)
- [Node.js Testing](.zappzarapp/docs/testing/TESTING-NODE.md)
- [Shell & Makefile Testing (BATS/GOSS)](.zappzarapp/docs/testing/TESTING-SHELL.md)
- [GOSS Container Tests](tests/goss/README.md)

### Infrastructure

- [Architecture Overview](.zappzarapp/docs/infrastructure/ARCHITECTURE.md)
- [Network Configuration](.zappzarapp/docs/infrastructure/NETWORK.md)
- [Nginx Configuration](.zappzarapp/docs/infrastructure/NGINX.md)
- [Deployment Guide](.zappzarapp/docs/infrastructure/DEPLOYMENT.md)
- [Kubernetes](.zappzarapp/docs/infrastructure/KUBERNETES.md)
- [Optional Services](.zappzarapp/docs/infrastructure/OPTIONAL-SERVICES.md)
- [Performance Tuning](.zappzarapp/docs/infrastructure/PERFORMANCE.md)
- [Error Pages](.zappzarapp/docs/infrastructure/ERROR-PAGES.md)
- [Monitoring](.zappzarapp/docs/infrastructure/MONITORING.md)

### Security

- [SSL Certificates](.zappzarapp/docs/security/SSL-CERTIFICATES.md)
- [Internal TLS](.zappzarapp/docs/security/INTERNAL-TLS.md)
- [Secrets Management](.zappzarapp/docs/security/SECRETS.md)
- [Database Encryption](.zappzarapp/docs/security/ENCRYPTION.md)
- [Audit Logging](.zappzarapp/docs/security/AUDIT-LOGGING.md)
- [Access Log Monitoring](.zappzarapp/docs/security/ACCESS-LOG-MONITORING.md)
- [Security Scanning](.zappzarapp/docs/security/SECURITY-SCANNING.md)
- [Backup & Recovery](.zappzarapp/docs/security/BACKUP.md)
- [Data Retention Policy](.zappzarapp/docs/security/RETENTION-POLICY.md)

### IDE Setup

- [JetBrains (PhpStorm/WebStorm)](.idea/README.md)
- [VS Code](.vscode/README.md)

### Other

- [Contributing](.zappzarapp/docs/CONTRIBUTING.md)
- [Changelog](CHANGELOG.md)

## Make Commands

```bash
make help          # Show all available commands (FILTER=<category> to filter)
make init          # Initialize local config (create .env.local)
make setup         # Full setup: build, install, migrate, start (first time)
make up            # Start containers (daily use)
make down          # Stop containers
make logs          # Show logs (make logs php nginx for specific services)
make test          # Run all tests
make check         # Full quality check (CI simulation)
make open-app      # Open the application in the browser
```

See the [Makefile Reference](.zappzarapp/docs/development/MAKEFILE-REFERENCE.md)
for all 258 targets.

## Git Hooks

This project uses [Captainhook](https://github.com/captainhookphp/captainhook)
for Git hooks. Hooks are installed automatically during `make setup`.

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
