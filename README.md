# Docker WebDev Boilerplate

A flexible Docker boilerplate for PHP and/or Node.js web development with a modern toolchain.

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
- **HTTP:** http://localhost:8080
- **HTTPS:** https://localhost:8443
- **Dev Dashboard:** http://localhost:8080/_dev

## Project Structure

```
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

### Security & Compliance
- [SSL Certificates](documentation/SSL-CERTIFICATES.md)
- [Database Encryption](documentation/ENCRYPTION.md)
- [Audit Logging](documentation/AUDIT-LOGGING.md)
- [Network Architecture](documentation/NETWORK.md)

### Development
- [Dev Dashboard](documentation/DEV-DASHBOARD.md)
- [Xdebug Configuration](documentation/XDEBUG.md)
- [PHP Testing](documentation/TESTING-PHP.md)
- [Node.js Testing](documentation/TESTING-NODE.md)
- [Renovate (Auto-Updates)](documentation/RENOVATE.md)

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

This project uses [Captainhook](https://github.com/captainhookphp/captainhook) for Git hooks.

Hooks are automatically installed during `make setup`. To install manually:

```bash
vendor/bin/captainhook install
```

Configured hooks include:
- **pre-commit:** Code style checks, static analysis
- **commit-msg:** Commit message validation (conventional commits)

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
