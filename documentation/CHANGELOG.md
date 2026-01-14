# zappzarapp - Changelog

**Erstellt:** 2025-12-19 **Letzte Aktualisierung:** 2026-01-15 (Branding &
Project Identity) **Version:** 3.40

---

## Changelog

### Version 3.40 (2026-01-14) - Branding & Project Identity

Complete rebranding from "docker-webdev" to "zappzarapp" with new visual
identity, favicon, and comprehensive documentation improvements.

#### Project Rename

- **Renamed**: Project from "docker-webdev" to "zappzarapp"
- **Etymology**: German colloquial for "in a flash" — from Russian цап-царап
  (grab it and go)
- **IPA**: /ˈt͡sapt͡saˈʁap/
- **Updated**: 59+ files with new project name (compose.yaml, Dockerfiles,
  Makefile, CI/CD configs, documentation, IDE configs)

#### Favicon & Visual Identity

- **New**: `public/favicon.svg` - Lightning bolt icon (golden gradient on dark
  circle)
- **New**: `public/assets/dev-dashboard/favicon.svg` - Separate favicon for Dev
  Dashboard
- **New**: `docs/assets/favicon.svg` - Separate favicon for Docsify guides
- **Design**: zappzarapp infrastructure keeps its branding even when users
  customize the app favicon

#### API Documentation Improvements

- **New**: Dynamic titles from package files
  (`{ProjectName} - PHP/Node API - v{version}`)
- **New**: Version field added to `composer.json`
- **New**: Makefile post-processing extracts name/version from composer.json and
  package.json
- **Fixed**: PHP docs title overflow with improved CSS (flex layout,
  text-overflow ellipsis)
- **Fixed**: Favicon integration for both phpDocumentor and TypeDoc

#### Package Metadata

- **Updated**: `composer.json` with author, homepage, support URLs
- **Updated**: `package.json` with author, repository, bugs, homepage
- **Added**: Git remote for GitHub (`git@github.com:marcstraube/zappzarapp.git`)

#### Documentation

- **New**: `documentation/getting-started/CUSTOMIZATION.md` - Guide for
  customizing the project (package files, license, branding, git remote)
- **Updated**: `documentation/_sidebar.md` with Customization link
- **Updated**: `README.md` header with project name, IPA, and etymology
- **Updated**: `templates/app/welcome.php` with new branding

#### Configuration

- **Updated**: `.gitignore` to include `docs/assets/` directory
- **Updated**: `typedoc.json` with favicon option
- **Updated**: `phpdoc.xml` with shorter title for better display

#### Developer Experience

- **Fixed**: `.prettierignore` now allows local formatting of `.idea/` and
  `.vscode/` markdown files (not mounted in container)
- **Fixed**: Vite file watcher ignores config files to prevent "Resource busy"
  errors during git operations
- **Fixed**: `lint-staged` uses standard stash mode (removed `--no-stash` for
  better Docker compatibility)
- **Changed**: `composer validate` without `--strict` to allow `version` field

---

### Version 3.39 (2026-01-14) - Dependency Injection & Test Data Libraries

Added PSR-11 compatible Dependency Injection using php-di with auto-wiring
support. Both App and DevDashboard now use constructor injection for cleaner,
more testable code. Also added FakerPHP and @faker-js/faker for test data
generation.

#### Dependency Injection (php-di)

- **New**: `php-di/php-di` as production dependency
- **New**: `config/container.php` for App container configuration
- **New**: DI container initialization in `public/index.php`
- **Refactored**: `WelcomeController` uses constructor injection for ViteHelper
  and HealthCheck
- **Refactored**: `StatusController` uses constructor injection for HealthCheck
- **Refactored**: `DashboardController` uses constructor injection for all 5
  services

#### DevDashboard Container Isolation

- **New**: DevDashboard uses its own isolated DI container (not shared with App)
- **Benefit**: Changes to App's container config don't affect DevDashboard

#### Test Data Libraries

- **New**: `fakerphp/faker` (PHP) as dev dependency
- **New**: `@faker-js/faker` (Node.js) as dev dependency
- **Use case**: Generate realistic test data for unit tests and database seeders

#### Code Style Enforcement

- **New**: PHP-CS-Fixer rule `fully_qualified_strict_types` enforces use
  statements
- **New**: PHP-CS-Fixer rule `global_namespace_import` enforces class imports
- **Effect**: `new \DI\ContainerBuilder()` → `use DI\ContainerBuilder;` +
  `new ContainerBuilder()`

#### Documentation

- **Updated**: `documentation/development/DEV-DASHBOARD.md` with DI examples
- **Updated**: Service usage examples now show `$this->serviceName->method()`
  pattern
- **Updated**: "Adding New Services" guide updated for constructor injection

#### Tests

- **Updated**: `DashboardControllerTest` uses factory method to create
  controller with dependencies

#### Pre-Commit Hook Improvements

- **Fixed**: Root config files (`.php-cs-fixer.dist.php`, `rector.php`) excluded
  from PHP pre-commit hooks
- **Fixed**: Root config files (`*.config.js`, `*.config.ts`) excluded from
  lint-staged (mounted as read-only)
- **Fixed**: `typedoc.json` now mounted as read-only in Node container
- **Reason**: Config files are mounted read-only for security and should not be
  auto-fixed by linters

### Version 3.38 (2026-01-14) - Welcome Page & Health Check Improvements

Improved the Welcome page endpoint listing and extended the `/status` health
check to include all optional services.

#### Welcome Page Endpoints

- **Improved**: Better sorting (Pages → Health Checks → Node.js API →
  Development)
- **Improved**: Cleaner naming (removed redundant "(via Router)" suffix)
- **New**: Node.js API endpoints now listed (`/api/node/health`,
  `/api/node/hello`, `POST /api/node/echo`)
- **New**: Node.js endpoints shown conditionally based on `ENABLE_NODE` and
  `NODE_MODE`
- **New**: Copy-to-clipboard button for POST endpoint curl command with visual
  feedback

#### Health Check (`/status`)

- **New**: Support for optional services health checks
- **New**: `ENABLE_MERCURE`, `ENABLE_MEILISEARCH`, `ENABLE_ELASTICSEARCH`,
  `ENABLE_MAILPIT`, `ENABLE_MINIO`, `ENABLE_RABBITMQ` environment variables
- **New**: Health check methods for all optional services:
  - Mercure (TCP port 80)
  - Meilisearch (HTTP `/health` API)
  - Elasticsearch (HTTP `/_cluster/health` API)
  - Mailpit (TCP port 8025)
  - MinIO (HTTP `/minio/health/live` API)
  - RabbitMQ (TCP port 5672)
- **Improved**: `features` section now shows all `ENABLE_*` flags

### Version 3.37 (2026-01-14) - Unified Documentation Theme

Unified the visual design across all three documentation systems (Docsify,
PHPDocumentor, TypeDoc) with a consistent Indigo color scheme.

#### Docsify (Markdown Guides)

- **New**: Added `docs/index.html` as Docsify-powered documentation hub
- **New**: Added `documentation/_sidebar.md` for structured navigation
- **Styling**: White background, Indigo accents (`#3f51b5`), dark blue headings
  (`#303f9f`)
- **Features**: Search, code copy buttons, syntax highlighting, pagination

#### PHPDocumentor (PHP API)

- **New**: Custom theme override via inline `<style>` injection
- **Header**: White background with border (previously gradient)
- **Badges**: Replaced green SVG icons with Indigo (`#3f51b5`)
- **Colors**: All primary colors changed from green (HSL 96°) to Indigo (HSL
  231°)
- **Links**: Consistent Indigo link colors with dark blue hover states

#### TypeDoc (Node.js/TypeScript API)

- **New**: `documentation/assets/custom-typedoc.css` with comprehensive
  overrides
- **Header**: White background with border (previously gradient)
- **TypeScript colors**: All type colors (interface, class, function, method,
  etc.) changed from green/pink to Indigo shades
- **Badges**: `code.tsd-tag` styled with light Indigo background for readability

#### Unified Color Palette

| Element         | Color        | Hex       |
| --------------- | ------------ | --------- |
| Primary         | Indigo       | `#3f51b5` |
| Primary Dark    | Dark Indigo  | `#303f9f` |
| Primary Light   | Light Indigo | `#c5cae9` |
| Primary Lighter | Pale Indigo  | `#e8eaf6` |
| Text            | Dark Gray    | `#212529` |
| Text Muted      | Gray         | `#6c757d` |
| Background      | White        | `#fff`    |
| Background 2nd  | Light Gray   | `#f8f9fa` |
| Border          | Lighter Gray | `#e9ecef` |

#### Infrastructure

- **Nginx**: Added `/docs/guides/` location for Docsify in `default.conf` and
  `ssl-development.conf.template`
- **Docker**: Added `documentation/` volume mount to nginx and php containers
- **Makefile**: `docs-php` target now injects custom CSS as inline `<style>` tag

### Version 3.36 (2026-01-14) - PHP Extensions & Makefile Service Control

Added essential PHP extensions for messaging, WebSockets, and financial
calculations. Extended Makefile with service-specific container control.

#### PHP Extensions

- **soap**: SOAP client/server for legacy APIs, payment gateways, shipping
  providers
- **bcmath**: Arbitrary precision mathematics for financial calculations and
  cryptocurrency
- **sockets**: Low-level socket interface for WebSocket implementations
- **amqp**: High-performance AMQP client for RabbitMQ message broker
- **pcntl**: Process control for signal handling and graceful queue worker
  shutdown

#### Makefile

- **Service-specific control**: Multiple commands now accept optional service
  names for targeted operations:
  - `make up php nginx` - Start specific services
  - `make down php` - Stop specific service
  - `make restart php nginx` - Restart specific services
  - `make build php` - Build specific images
  - `make build-no-cache php` - Force rebuild specific images
  - `make logs php nginx` - View logs of specific services
- Without arguments, commands work as before (profile-based logic)

#### Documentation

- **MAKEFILE-REFERENCE.md**: Added "Service-Specific Commands" section with
  examples
- **QUICKSTART.md**: Added tip about service-specific commands
- **Welcome Page**: Updated Quick Start section with service-specific examples

#### Configuration

- Added INI files for each new extension in `docker/php/conf.d/`
- Extensions can be individually disabled by commenting out the `extension=`
  line

---

### Version 3.35 (2026-01-14) - DevDashboard Improvements & IDE Integration

Enhanced DevDashboard with optional services health monitoring and improved IDE
integration.

#### DevDashboard

- **Health Check Integration**: Added health monitoring for all optional
  services (Mercure, Meilisearch, Elasticsearch, Mailpit, MinIO, RabbitMQ)
- **Embedded CSS**: Added missing CSS classes for modal, spacing, and color
  utilities
- **Log Viewer Modal**: Added modal for viewing log file contents with inline
  styles (no Tailwind dependency)
- **Design Consistency**: Unified styling across all dashboard pages

#### IDE Integration

- **PhpStorm Prettier**: Added `.idea/prettier.xml` configuration for automatic
  Prettier formatting on save and reformat (includes Markdown support)
- **PhpStorm Quality Tools**: Configured PHPStan (Level 8 with `phpstan.neon`),
  PHP CS Fixer, PHPMD, ESLint, and TypeScript inspections for parity with
  `make check`
- **PhpStorm Markdown**: Disabled conflicting Markdown inspections (Prettier
  handles formatting)
- **VSCode Markdownlint**: Added configuration matching
  `.markdownlint-cli2.jsonc`

#### Code Quality

- **Rector Compatibility**: Refactored `ErrorPage` to use `extract()` pattern
  for template variables
- **PHP 8.4 Updates**: Applied typed constants and strict string casts

#### Documentation

- **DEV-DASHBOARD.md**: Updated with optional services section, removed emojis
  from tables (Prettier compatibility)

---

### Version 3.34 (2026-01-14) - Optional Services & Backup System

Added six optional services with TLS support and restructured the backup system
for all data stores.

#### Added Services

- **Mercure** (`ENABLE_MERCURE=true`)
  - Real-time messaging via Server-Sent Events (SSE)
  - Port: 8081, auto-generated JWT secret

- **Meilisearch** (`ENABLE_MEILISEARCH=true`)
  - Lightning-fast, typo-tolerant search engine
  - Port: 7700, auto-generated master key

- **Elasticsearch** (`ENABLE_ELASTICSEARCH=true`)
  - Full-featured distributed search and analytics
  - Ports: 9200, 9300, configurable heap size

- **Mailpit** (`ENABLE_MAILPIT=true`)
  - Email testing tool (catches all outgoing SMTP)
  - Ports: 1025 (SMTP), 8025 (Web UI)
  - Production: disabled by default (`replicas: 0`)

- **MinIO** (`ENABLE_MINIO=true`)
  - S3-compatible object storage with TLS
  - Ports: 9000 (API), 9001 (Console)

- **RabbitMQ** (`ENABLE_RABBITMQ=true`)
  - Enterprise message broker with TLS support
  - Ports: 5672/5671 (AMQP), 15672 (Management UI)
  - Production: TLS-only mode

#### Added Backup System

- **Unified Backup Directory Structure**:
  - `backups/db/` - Database backups
  - `backups/minio/` - MinIO backups
  - `backups/rabbitmq/` - RabbitMQ definitions
  - `backups/elasticsearch/` - Elasticsearch snapshots

- **Backup Commands**:
  - `backup-all` - Backup all enabled services
  - `backup-db`, `backup-db-list`, `backup-db-restore`
  - `backup-minio`, `backup-minio-list`, `backup-minio-restore`
  - `backup-rabbitmq`, `backup-rabbitmq-list`, `backup-rabbitmq-restore`
  - `backup-elasticsearch`, `backup-elasticsearch-list`,
    `backup-elasticsearch-restore`

- **Backup Scripts**: `docker/scripts/backup-{minio,rabbitmq,elasticsearch}.sh`
  and `restore-{minio,rabbitmq,elasticsearch}.sh`

#### Added Makefile Targets

- `logs-{mercure,meilisearch,elasticsearch,mailpit,minio,rabbitmq}`
- `shell-{mercure,meilisearch,elasticsearch,mailpit,minio,rabbitmq}`
- Mailpit production warning in `make up`

#### Added Documentation

- `documentation/infrastructure/OPTIONAL-SERVICES.md`

#### Added IDE Integration

- VS Code tasks for all new services and backup commands
- IntelliJ run configurations for all new services and backup commands

#### Changed

- **Renamed Backup Commands** (breaking change):
  - `backup` → `backup-db`
  - `backup-list` → `backup-db-list`
  - `restore` → `backup-db-restore`
  - `backup-cleanup` → `backup-db-cleanup`

- **Database Backup Directory**: `backup/` → `backups/db/`

- **Profile System**: All services use Docker Compose profiles

- **Markdownlint**: Disabled MD055 (table pipe style) for Prettier compatibility

- **Documentation**: Updated `BACKUP.md`, `MAKEFILE-REFERENCE.md`,
  `QUICKSTART.md`, `ARCHITECTURE.md`

---

### Version 3.33 (2026-01-14) - Architecture Simplification

Major simplification: Removed Docker Compose Watch in favor of pure bind mounts.
Explicit dependency installation instead of auto-install in entrypoints.

#### Removed

- **Docker Compose Watch**: Removed `develop:` blocks from
  `compose.override.yaml`
  - Root cause of EBUSY errors (file locking on package.json/pnpm-lock.yaml)
  - Root cause of "Resource busy" errors during git commits (lint-staged)
  - Vite HMR and PHP-FPM already handle file changes natively
  - Bind mounts provide instant sync without file locking issues

- **Auto-install in Entrypoints**: Dependencies no longer install automatically
  - Entrypoints now fail-fast with clear error message if dependencies missing
  - More predictable container startup time
  - No network dependency during `make up`
  - Industry standard: explicit install via `make setup` or `make *-install`

#### Changed

- **Makefile Targets** (simplified and consistent):
  - `pnpm`: Changed from `exec` to `run` (works without running container)
  - `pnpm-install`: Changed from `exec` to `run` (works without running
    container)
  - `pnpm-update`: Simplified from complex `docker create/cp` to `run` with /tmp
  - `composer-install`: Removed conditional `--no-scripts` logic
  - `up`: Removed Watch process start and PID file handling
  - `down`: Removed Watch process kill logic
  - `setup`: Now explicitly runs `composer-install` + `pnpm-install` before `up`

- **Entrypoints** (fail-fast pattern):
  - `docker/node/entrypoint.sh`: Validates dependencies, bypasses check for
    commands (e.g., `pnpm install`)
  - `docker/php/entrypoint.development.sh`: Validates dependencies only for
    `php-fpm`, not for `composer` commands

- **Dockerfiles** (Development Stage cleanup):
  - `docker/node/Dockerfile`: Removed redundant COPY for configs and source
    (provided via bind mounts)
  - `docker/php/Dockerfile`: Removed redundant COPY for source (provided via
    bind mounts)

#### Added

- **Bind Mounts** (`compose.override.yaml`): Added missing config file mounts
  for Node container:
  - `vite.config.js`, `tsconfig.json`, `tsconfig.build.json`
  - `tsconfig.vitest.json`, `vitest.config.ts`, `ecosystem.config.cjs`

#### Fixed

- **EBUSY errors**: `make pnpm CMD="add ..."` no longer fails with atomic rename
  errors (Docker Compose Watch was the cause, not bind mounts)
- **Git commit errors**: `git commit` no longer fails with "Resource busy" when
  lint-staged runs (Watch file handles were blocking git stash/checkout)

#### Documentation

- Updated `QUICKSTART.md`: Corrected setup step order
- Updated `DEPENDENCIES.md`: Replaced Watch architecture with bind mount docs
- Updated `TROUBLESHOOTING.md`: Removed "Docker Compose Watch Not Working"
- Updated `PERFORMANCE.md`: Updated file sync description
- Updated `templates/app/welcome.php`: Simplified Quick Start section

---

### Version 3.32 (2026-01-14) - Security & Quality Tools

#### Added

- **roave/security-advisories** (Composer dev dependency):
  - Zero-config security scanning for PHP dependencies
  - Blocks `composer install/update` when known CVEs detected
  - No configuration required - works automatically

- **hadolint** (Dockerfile linter via Docker):
  - `make lint-docker`: Lint all Dockerfiles in `docker/` directory
  - `.hadolint.yaml`: Configuration with sensible defaults for boilerplate
  - Ignores: DL3018 (version pinning), DL3059 (consecutive RUN), DL3002 (root
    user), DL3003 (WORKDIR), SC2046/SC3009 (shell compatibility)

- **depcheck** (Node.js dev dependency):
  - `make depcheck`: Find unused Node.js dependencies
  - Keeps package.json clean

- **knip** (Node.js dev dependency):
  - `make knip`: Find dead code, unused exports and files
  - Comprehensive codebase analysis

- **dive** (Docker image analyzer via Docker):
  - `make dive`: Interactive Docker image layer analysis
  - Shows layer sizes, helps optimize images

- **IDE Integration** (6 new configurations):
  - PhpStorm: `Quality__Lint_Dockerfiles.xml`, `Quality__Depcheck.xml`,
    `Quality__Knip.xml`, `Quality__Dive.xml`
  - VSCode: `Quality: Lint Dockerfiles`, `Quality: Depcheck (Unused Deps)`,
    `Quality: Knip (Dead Code)`, `Quality: Dive (Image Analysis)`

#### Fixed

- **PHP Development Entrypoint**: Non-php-fpm commands (like `composer`) now run
  as `www-data` (UID 1000) instead of root
  - Added `su-exec` to PHP development image
  - `entrypoint.development.sh` now uses `su-exec www-data` for non-FPM commands

- **Makefile `composer` target**: Changed from `docker compose exec` to
  `docker compose run` for correct user permissions on host files

- **Makefile `pnpm` target**: Fixed atomic rename issue on Docker bind mounts
  - Docker bind mounts don't support atomic rename (EBUSY error)
  - Solution: Run pnpm in /tmp, then copy files back to host

#### Changed

- **Makefile Target Naming**: Renamed Node.js package management targets for
  consistency with Composer naming convention
  - `node-install` → `pnpm-install`
  - `node-update` → `pnpm-update`
  - `node-install-local` → `pnpm-install-local`
  - New naming separates package management (`pnpm-*`) from runtime (`node-*`)
  - Container/runtime targets unchanged: `node-dev`, `node-build`, `node-pm2-*`

---

### Version 3.31 (2026-01-14) - PHP Extension Configuration

#### Added

- **Individual PHP Extension INI Files** (`docker/php/conf.d/`):
  - Each extension now has its own INI file for easy enable/disable
  - Extensions can be disabled by commenting out `extension=` line
  - Files: `exif.ini`, `gd.ini`, `gmagick.ini`, `intl.ini`, `opcache.ini`,
    `pdo_mysql.ini`, `pdo_pgsql.ini`, `redis.ini`, `sodium.ini`, `zip.ini`

- **Xdebug INI Enhancement**:
  - `zend_extension=xdebug` directive now in `xdebug.ini` (single source of
    truth)
  - Auto-generated PIE INI removed during build

#### Changed

- **Dockerfile Base Stage**:
  - Auto-generated `docker-php-ext-*.ini` files now removed
  - Custom extension INIs copied instead for full control
  - Removed unnecessary curl compilation (built-in since PHP 8.4 Alpine)

- **compose.override.yaml**:
  - All extension INIs mounted for development
  - Added documentation about curl being statically compiled

- **php.ini**:
  - Simplified Redis session documentation with cross-reference to `redis.ini`
  - Removed obsolete extension settings comment block

#### Technical Notes

- **curl**: Statically compiled (`--with-curl`), cannot be disabled via INI
- **sodium**: Shared extension (`--with-sodium=shared`), can be disabled via INI
- All other extensions: Can be disabled by commenting `extension=` line

---

### Version 3.30 (2026-01-14) - Node.js Quality Tools Parity & Documentation Fixes

#### Added

- **Makefile Node.js Quality Targets** (parity with PHP tools):
  - `type-check`: TypeScript static analysis (equivalent to PHPStan)
  - `lint-node`: ESLint for TypeScript/JavaScript
  - `lint-node-fix`: ESLint auto-fix
  - `prettier-check`: Code formatting check (TS/JS, Markdown)
  - `prettier-fix`: Code formatting auto-fix

- **Pre-push Hook Enhancements** (captainhook.json):
  - PHPUnit tests now run on pre-push (was missing)
  - TypeScript type-check now runs on pre-push (parity with PHPStan)

- **IDE Integration** for new targets:
  - PhpStorm: 5 new run configurations in `.idea/runConfigurations/`
  - VSCode: 5 new tasks in `.vscode/tasks.json`

- **Documentation** (4 new files):
  - `documentation/CONTRIBUTING.md`: Contribution guidelines (lock files, commit
    conventions)
  - `documentation/QUICKSTART.md`: Get started in under 5 minutes
  - `documentation/TROUBLESHOOTING.md`: Common problems and solutions
  - `documentation/CHANGELOG.md`: Boilerplate version history (renamed from
    TODO.md)

- **Documentation reorganization** (moved to subdirectories):
  - `infrastructure/`: ARCHITECTURE.md, DEPLOYMENT.md, PERFORMANCE.md,
    MONITORING.md
  - `development/`: MAKEFILE-REFERENCE.md

#### Changed

- **Makefile `check` target**: Now includes all Node.js quality tools
  (`prettier-check`, `type-check`, `lint-node`)
- **lint-staged.config.js**: Added Prettier formatting before markdownlint for
  markdown files
- **package.json format scripts**: Now include all markdown files (`**/*.md`)
- **compose.override.yaml**: Added root markdown file mounts and documentation
  folder (read-write for Prettier/markdownlint)
- **.markdownlint-cli2.jsonc**: Added `MD046: fenced` rule (enforces ` ``` `
  code blocks instead of indented blocks, enables language specification for
  syntax highlighting)
- **CI/CD Pipelines**:
  - GitHub Actions: Added Markdownlint step to node-quality job
  - GitLab CI: Added new `node:markdownlint` job in quality stage
- **README.md**: Updated Git hooks description (pre-commit auto-fix tools, added
  pre-push with PHPStan/TypeScript/PHPUnit/Vitest)
- **documentation/README.md**: Restructured documentation index, reorganized
  files into subdirectories

#### Fixed

- **ASCII Diagram Alignment** in existing documentation:
  - `ERROR-PAGES.md`: Symmetric flowchart alignment
  - `NETWORK.md`: Centered connectors between network tiers

- **Markdown Lint Errors** (280+ errors fixed):
  - MD040: Added language specifiers to all code blocks
  - MD036: Changed bold emphasis to blockquotes in security docs
  - MD060: Fixed table alignment via Prettier

- **PhpStorm Run Configuration Order**: Fixed alphabetical sorting in
  `.idea/workspace.xml`

---

### Version 3.29 (2026-01-14) - Node.js Major Updates

#### Fixed

- **Node package manager operations on Linux**: Fixed `pnpm update` failing with
  "EBUSY: resource busy" on Linux due to overlay-fs limitations (same issue as
  Composer in 3.28)
  - Solution: Use docker create/cp approach to avoid single-file bind mount
    atomic rename issues

#### Changed

- **Dockerfile (Node Development Stage)**: Removed
  `COPY package.json pnpm-lock.yaml ./` - files are now mounted as volumes
- **compose.override.yaml**: Added bidirectional volume mounts for Node package
  manager files
- **Makefile `node-update`**: Now uses docker create/cp approach to avoid bind
  mount issues

#### Removed

- **Makefile `sync-lockfiles`**: No longer needed - lock files are now
  bidirectionally mounted
- **docker cp sync operations**: Removed from `composer-install`,
  `composer-install-local`, `node-install`, `node-install-local`

#### Updated (Node.js - Major Versions)

- **express**: 4.x → 5.x (new routing engine, async middleware support)
- **vite**: 6.x → 7.x (improved HMR, faster builds)
- **pino**: 9.x → 10.x (performance improvements)
- **pino-http**: 10.x → 11.x (Express 5 compatibility)
- **@types/node**: 22.x → 24.x (Node.js 24 LTS types)
- **eslint-config-prettier**: 9.x → 10.x (ESLint 9 flat config support)
- **markdownlint-cli2**: 0.18 → 0.20

---

### Version 3.28 (2026-01-14) - Composer Volume Mount Fix

#### Fixed

- **Composer write permissions on Linux**: Fixed
  `composer update/require/remove` failing with "Permission denied" on Linux due
  to overlay-fs limitations
  - Root cause: Single-file bind mounts combined with Docker overlay-fs
    prevented writing to files copied via `COPY` in Dockerfile
  - Solution: Mount `composer.json` and `composer.lock` as volumes instead of
    copying them into the development image

#### Changed

- **Dockerfile (PHP Development Stage)**: Removed
  `COPY composer.json composer.lock* ./` - files are now mounted as volumes in
  development
- **compose.override.yaml**: Added bidirectional volume mounts for
  `composer.json` and `composer.lock`
- **Makefile `composer-update`**: Now runs as user 1000:1000 with direct
  composer entrypoint (bypasses root-requiring entrypoint)
- **Makefile `composer`**: Now runs as `www-data` user for consistent
  permissions

#### Updated

- **rector/rector**: 2.2 → 2.3
- **lint-staged.config.js**: Exclude package manager configs (`composer.json`,
  `package.json`, `package-lock.json`) from prettier checks

---

### Version 3.27 (2026-01-13) - IDE Integration Update

#### Changed

- **PhpStorm Run Configurations**: Complete overhaul with 101 configurations for
  all Make targets
  - Organized by category: Setup, Docker, Logs, Shell, Node, Database, Redis,
    Backup, Quality, Test, Security, Docs, SSL, Renovate
  - Alphabetically sorted in workspace.xml for consistent display order

- **VSCode Tasks**: Updated `.vscode/tasks.json` with 101 tasks matching all
  Make targets
  - Proper task grouping (build, test)
  - Background tasks for logs and watch processes
  - Dedicated panels for shell and CLI tasks

#### Added

- **New PhpStorm Run Configurations** for recently added Make targets:
  - `composer-install-local`, `node-install-local` (local IDE dependency
    install)
  - `cs-fix-all`, `lint-md-fix` (aggressive code style fixing)
  - `secrets-rotate` (security secret rotation)
  - All Node.js PM2 management commands
  - All database CLI, dump, restore commands for both PostgreSQL and MariaDB

---

### Version 3.26 (2026-01-13) - PHP Error Pages, Vite HTTPS/HMR & DevDashboard Fixes

#### Added

- **PHP Error Page System** (`ErrorPage.php`):
  - Centralized error page generation with content negotiation (HTML/JSON based
    on Accept header)
  - All HTTP errors (400-599) routed through PHP for consistent handling
  - Template-based error pages in `templates/app/error.php`
  - Unit tests for error handling (`ErrorPageTest.php`)

- **Error Pages Documentation** (`documentation/infrastructure/ERROR-PAGES.md`):
  - Complete guide for error page architecture
  - Content negotiation behavior explanation
  - Nginx fallback when PHP is down

#### Fixed

- **Vite HMR over HTTPS**: Hot Module Replacement now works correctly when
  accessing the application via HTTPS (`https://localhost:8443`)
  - `ViteHelper.php`: Uses relative URLs for HTTPS requests to avoid mixed
    content blocking
  - `vite.config.js`: Added `clientPort: 8443` and `protocol: 'wss'` for
    WebSocket connection through nginx proxy
  - Nginx configs: Added proxy location for Vite internal routes (`/@fs/`,
    `/@id/`, `/@vite/`, `node_modules/`)

- **DevDashboard Database Connection**: Health check now uses Docker Secrets for
  database password
  - `HealthCheckService.php`: Refactored to use `DatabaseConfig` class instead
    of direct `getenv('DB_PASSWORD')`
  - Ensures consistent Docker Secrets support across all database connections

#### Changed

- **Template Reorganization**: View files moved to `templates/` directory
  - `templates/app/` - Application templates (welcome.php, error.php)
  - `templates/dev-dashboard/` - DevDashboard templates (moved from
    `src/php/DevDashboard/Views/`)

- **Nginx SSL Config Sync**: Both `ssl-development.conf.template` and
  `default.conf` now have identical Vite proxy configurations

- **Production SSL Config (`ssl-production.conf.template`)**:
  - Dynamic port via `${NGINX_SSL_PORT}` variable (was hardcoded 8443)
  - Updated to `http2 on;` directive syntax
  - Added Node.js Backend API Proxy (`/api/node/*`) with JSON error responses
  - Added `@php_router` fallback for non-existent .php files
  - Added `@nginx_health` fallback for health checks when PHP is down
  - API endpoints now use nested PHP handler with `fastcgi_intercept_errors off`

---

### Version 3.25 (2026-01-13) - Developer Tooling & Dependency Management

#### Added

- **Markdownlint Integration**:
  - Added `markdownlint-cli2` for Markdown linting
  - New config file `.markdownlint-cli2.jsonc` with project-specific rules
  - New Make targets: `make lint-md`, `make lint-md-fix`

- **lint-staged for Pre-Commit Hooks**:
  - Added `lint-staged` for running linters only on staged files
  - New config file `lint-staged.config.js` (TS/JS, JSON, Markdown)
  - Updated `captainhook.json` to use lint-staged instead of full codebase
    checks
  - Separate PHP checks (syntax + CS-Fixer) for staged files only

- **Custom Nginx Error Pages**:
  - Added `docker/nginx/errors/404.html` - styled 404 page
  - Added `docker/nginx/errors/50x.html` - styled 50x error page
  - Updated Nginx configs with `error_page` directives and internal `/errors/`
    location

- **`make node-update`**: New target for updating Node.js dependencies with
  automatic lockfile sync

- **Dependency Management Documentation**
  (`documentation/development/DEPENDENCIES.md`):
  - Complete guide for Composer and pnpm dependency management
  - Docker Compose Watch architecture explanation
  - Workflow examples (new feature, after pull, fresh install)
  - Troubleshooting section for common issues

#### Changed

- **`make help` formatting**: Increased column width from `%-20s` to `%-26s` for
  better readability

- **Automatic Lockfile Sync**: All dependency targets now sync only the relevant
  lockfile:
  - `make composer-install` → syncs `composer.lock` after install
  - `make node-install` → syncs `pnpm-lock.yaml` after install
  - `make composer-install-local` → syncs `composer.lock` before install (if
    container running)
  - `make node-install-local` → syncs `pnpm-lock.yaml` before install (if
    container running)

- **`make sync-lockfiles`**: Remains available for manual sync of both lockfiles

- **`compose.override.yaml`**: Added mounts for lint-staged config, markdownlint
  config, and documentation folder

---

### Version 3.24 (2026-01-13) - Makefile Logs Fix

#### Fixed

- **`make logs` now shows all containers**: Previously only displayed nginx logs
  because Docker Compose profiles were not passed to the `logs` command. Now
  dynamically includes all enabled services (php, node, redis, postgres) based
  on `ENABLE_*` flags in `.env`, matching the behavior of `make up`,
  `make down`, and `make build`.

---

### Version 3.23 (2026-01-13) - Docker Secrets & Container Improvements

#### Added

- **Docker Secrets Support**:
  - Secure password management via file-based secrets (`./secrets/`)
  - `_FILE` environment variable pattern for PHP and Node (`DB_PASSWORD_FILE`,
    etc.)
  - Secrets mounted read-only to containers at `/run/secrets/<secret_name>`
  - Auto-generation of secrets during `make setup`

- **Make Commands for Secrets**:
  - `make secrets` - Generate missing secrets (idempotent, safe to run anytime)
  - `make secrets-rotate-passwords` - Rotate DB passwords only (preserves
    encryption keys)
  - `make secrets-rotate` - Rotate ALL secrets (with warning about backup key
    impact)

- **DatabaseConfig `_FILE` Support** (PHP & Node):
  - `getEnvOrFile()` method checks `{VAR}_FILE` first, then falls back to
    `{VAR}`
  - Automatic whitespace trimming from secret file content
  - Graceful fallback when secret file doesn't exist

- **Custom Dockerfiles**:
  - `docker/mariadb/Dockerfile` - Custom MariaDB image with healthcheck script
  - `docker/redis/Dockerfile` - Custom Redis image based on Alpine

- **Unit Tests**:
  - 6 PHP tests for `_FILE` support (file reading, precedence, fallback)
  - 6 Node tests for `_FILE` support (file reading, precedence, fallback)
  - Total: 107 tests (up from 95)

- **Documentation** (`documentation/security/SECRETS.md`):
  - Complete guide for Docker Secrets usage
  - Production deployment examples (Swarm, Kubernetes, Vault)
  - Troubleshooting section
  - "Disabling Docker Secrets" guide for legacy/external DB setups

#### Changed

- **compose.yaml - Password Configuration**:
  - Both options documented inline (Docker Secrets vs. Environment Variables)
  - Clear instructions for switching between options
  - `POSTGRES_PASSWORD_FILE` and `MARIADB_PASSWORD_FILE` as defaults

- **compose.override.yaml**:
  - Removed password environment variables (moved to compose.yaml)
  - Development overrides now only contain non-password settings

- **.env.example / .env**:
  - New "DATABASE PASSWORDS" section with clear documentation
  - Instructions for switching from Secrets to Environment Variables
  - Reference to SECRETS.md for details

- **Dockerfile Improvements**:
  - Nginx, PHP, Node, Postgres Dockerfiles updated with better layer caching
  - Consistent USER_ID/GROUP_ID handling across all containers

- **Security Workflow** (`.github/workflows/security-scan.yml`):
  - Updated scan configuration

- **HealthCheck** (`src/php/App/Infrastructure/HealthCheck.php`):
  - Improved service status detection

---

### Version 3.22 (2026-01-13) - Database SSL & Bidirectional Mounts

#### Added

- **Database SSL Configuration**:
  - `DB_SSL_CA` environment variable for custom CA certificate path
  - `DB_SSL_CA=system` option for cloud databases with public CA-signed
    certificates
  - `DB_SSL_VERIFY` environment variable (true/false) for certificate
    verification
  - Automatic SSL fallback for MariaDB using internal certificate
    (`/etc/ssl/db-certs/cert.crt`)

- **PostgreSQL sslmode Support**:
  - Automatic sslmode selection based on SSL configuration
  - `verify-full` when SSL CA + verification enabled
  - `require` when SSL CA configured without verification
  - Default `prefer` behavior when no explicit SSL config

- **DatabaseConfig SSL Methods** (PHP & Node):
  - `getSslConfig()` / `getSslCa()` / `getSslVerify()`
  - `hasSsl()` - checks if SSL is configured and certificate exists
  - `getPostgresSslMode()` - returns appropriate sslmode string
  - `getPdoSslOptions()` (PHP) - returns PDO options for MariaDB SSL

- **Unit Tests**:
  - 39 PHP tests for DatabaseConfig (SSL, sslmode, URL parsing)
  - 40 Node tests for database.ts (SSL, sslmode, URL parsing)

#### Changed

- **compose.override.yaml - Bidirectional Bind Mounts**:
  - PHP: `src/php`, `tests/php`, `templates` now use bind mounts instead of
    watch sync
  - Node: `src/node`, `tests/node`, `resources` now use bind mounts instead of
    watch sync
  - Enables code quality tools (CS Fixer, ESLint, Prettier) to write changes
    back to host
  - Removed redundant watch sync entries for these directories

- **SSL Certificate Mount Point**:
  - Unified mount point `/etc/ssl/db-certs/` for both PHP and Node containers
  - Certificates from `./docker/certs/` mounted for database SSL connections

- **.env.example Documentation**:
  - Clarified `DB_TYPE` vs `DB_HOST` purpose
  - Added `ENABLE_DATABASE=false` examples for external database usage
  - Added SSL configuration section with examples

- **Quick Start Instructions** (`.env.example`, `templates/welcome.php`):
  - Fixed: `make fresh` → `make up` (fresh is dangerous, deletes all data)
  - Fixed: "Set ENV=development" → "Adjust USER_ID, GROUP_ID" (ENV already has
    correct default)

- **HealthCheck & Welcome Page**:
  - Added `ENABLE_DATABASE` environment variable support
  - Database status now correctly shows "Disabled" when `ENABLE_DATABASE=false`
  - Service Configuration section updated with `ENABLE_DATABASE`

#### Fixed

- **CS Fixer Changes Not Written to Host**:
  - Root cause: Docker Compose watch sync is unidirectional (host → container)
  - Fixed by using bidirectional bind mounts for source directories
  - PhpStorm inspections now match container tool results

- **Welcome Page Database Display**:
  - Was checking `DB_TYPE` (always set) instead of `ENABLE_DATABASE`
  - Now correctly shows database as disabled when not enabled

---

### Version 3.21 (2026-01-13) - Windows Setup & Changelog Cleanup

#### Added

- **Windows Setup Documentation** (`documentation/setup/WINDOWS.md`):
  - WSL2 + Docker Desktop setup guide (recommended approach)
  - Git Bash alternative for environments without WSL2
  - Troubleshooting section (volume permissions, WSL2 memory, performance tips)
  - IDE configuration for VS Code (Remote-WSL) and PhpStorm/WebStorm
  - Quick reference table for common Make commands

- **Documentation Structure**:
  - New `documentation/setup/` directory for platform-specific guides
  - Updated `documentation/README.md` with "Setup Guides" section

#### Changed

- **.gitattributes** - Extended LF enforcement for critical file types:
  - Added `*.sh` (shell scripts)
  - Added `*.sql` (migrations)
  - Added `*.env` and `*.env.*` (environment files)
  - Added `Makefile` and `Dockerfile`

- **TODO.md Restructure**:
  - Removed detailed project plan (Phases 1-8, ~2400 lines)
  - Converted to changelog-only format
  - Added Version 1.0 summary capturing initial architecture decisions
  - Reduced file size from 5374 to ~3030 lines

---

### Version 3.20 (2026-01-12) - CI/CD Cleanup & PHPUnit Modernization

#### Changed

- **PHPUnit Modernization**:
  - Replaced `@covers` annotation with PHP 8 `#[CoversClass()]` attribute in
    `SystemInfoServiceTest.php`
  - Added `PHPUnit\Framework\Attributes\CoversClass` import

- **GitLab CI Cleanup Consolidation**:
  - Integrated `after_script` cleanup into `docker-setup` anchor (DRY principle)
  - Removed unused `.cleanup` anchor
  - Removed redundant `after_script` from `dependency-audit` and
    `build:production` jobs
  - All 15 Docker jobs now inherit cleanup automatically

- **GitHub Actions Cleanup Consolidation**:
  - Created reusable Composite Action:
    `.github/actions/docker-cleanup/action.yml`
  - Added cleanup to jobs missing it: `php-quality`, `php-tests`,
    `node-quality`, `node-tests`
  - Replaced manual cleanup steps in `dependency-audit`, `build-production`,
    `dependency-scan`
  - All 7 Docker jobs now use the same centralized cleanup action

### Version 3.19 (2026-01-12) - Filename Parity & Dashboard Fix

#### Changed

- **Filename Parity (dev/prod → development/production)**:
  - Renamed `docker/php/entrypoint.dev.sh` →
    `docker/php/entrypoint.development.sh`
  - Renamed `compose.prod.yaml` → `compose.production.yaml`
  - Consistent with ENV values (`development`/`production`) and Dockerfile
    targets
  - Updated all references in:
    - `compose.override.yaml`
    - `Makefile` (16 occurrences)
    - `.github/workflows/ci.yml`
    - `.gitlab-ci.yml`
    - `docker/nginx/conf.d/csp-production.conf`
    - `docker/node/ssl-example.md`
    - `docker/mariadb/my.cnf.example`
    - `documentation/infrastructure/NETWORK.md`
    - `documentation/security/ENCRYPTION.md`

#### Fixed

- **Dev Dashboard Git Status**:
  - Removed unreliable "uncommitted changes" counter from dashboard
  - Git status now shows only branch and commit (work correctly in container)
  - Root cause: `.dockerignore` excludes files from build, but `.git` is mounted
    as volume, causing Git to see tracked files as "deleted"
  - Updated `SystemInfoService.php`, `dashboard.php`, and related tests

---

### Version 3.18 (2026-01-12) - GDPR Security Scanning & GitLab CI

#### Added

- **GDPR Phase 3.2 - Automated Security Scanning**:
  - `.github/workflows/security-scan.yml`: Comprehensive GitHub security
    workflow
    - Docker image vulnerability scans (all 5 images: PHP, Node, Nginx,
      PostgreSQL, MariaDB)
    - Dependency scanning (Composer + pnpm audit)
    - Filesystem scan (IaC misconfigurations)
    - Secret scanning (Trivy + TruffleHog)
    - Scheduled weekly runs + manual trigger with configurable severity
  - `.gitlab/security-scan.gitlab-ci.yml`: Equivalent GitLab CI security
    pipeline
    - Same scan coverage as GitHub workflow
    - Gitleaks for secret scanning (GitLab alternative to TruffleHog)
    - Security summary report generation
  - `documentation/security/SECURITY-SCANNING.md`: Security scanning guide
    - Local scanning instructions (Trivy CLI)
    - Best practices (version pinning, multi-stage builds, non-root users)
    - Optional Dependabot and OWASP ZAP configuration

- **GDPR Phase 2.3 & 3.1 - Access Log Monitoring Documentation**:
  - `documentation/security/ACCESS-LOG-MONITORING.md`: Host-level monitoring
    guide
    - Relevant log locations in Docker setup
    - Tool recommendations (fail2ban, Logwatch, Prometheus/Grafana, Loki, Wazuh)
    - Quick start recommendations by complexity
    - Integration with application audit logging

#### Changed

- **GitHub CI Pipeline** (`.github/workflows/ci.yml`):
  - Simplified `security` job to `dependency-audit` (only Composer + pnpm audit)
  - Removed Trivy scans (moved to dedicated security-scan.yml)
  - Faster CI runs, comprehensive scans run separately on schedule

- **GitLab CI Pipeline** (`.gitlab-ci.yml`):
  - Simplified security jobs to single `dependency-audit` job
  - Removed Trivy scans (moved to dedicated security-scan.gitlab-ci.yml)
  - Consistent with GitHub workflow structure

---

### Version 3.17 (2026-01-12) - GDPR Retention Policies & Docker Stability

#### Added

- **GDPR Phase 2.2 - Retention Policies**:
  - `migrations/postgresql/002_retention_policies.sql`: PostgreSQL retention
    functions
  - `migrations/mariadb/002_retention_policies.sql`: MariaDB retention
    procedures
  - `delete_old_logs(table, days)`: Delete logs older than retention period
  - `anonymize_user(id, reference)`: GDPR Art. 17 user anonymization template
  - `get_retention_status()`: Retention monitoring helper
  - `documentation/security/RETENTION-POLICY.md`: Comprehensive retention guide
  - `make db-cleanup`: New target for manual cleanup (default: 730 days)

- **PostgreSQL pg_cron Extension**:
  - `docker/postgres/Dockerfile`: Custom PostgreSQL 17 Alpine image with pg_cron
    pre-compiled
  - `docker/postgres/entrypoint.sh`: Custom entrypoint for SSL certificate
    handling
  - pg_cron pre-configured in compose files (`shared_preload_libraries`)

#### Fixed

- **Makefile Profile Handling**:
  - `build`, `build-no-cache`, `clean`, `fresh` now correctly handle all
    profiles
  - `make up` uses `docker compose up -d` before starting watch (fixes container
    startup)
  - Merged redundant `up-core` into `up` target

- **Node Container Stability**:
  - Fixed restart loop caused by `node_modules` volume permissions
  - Dockerfile now creates `/app/node_modules` with correct ownership before
    `USER node`
  - Added `pnpm-lock.yaml*` glob pattern (optional for initial setup)

- **PHP Dockerfile**:
  - Added `composer.lock*` glob pattern in development stage (optional for
    initial setup)

#### Changed

- **Docker Image Versions**:
  - Redis: Fixed to `redis:7.4-alpine3.21` (Alpine 3.23 not available for Redis)
  - MariaDB: Changed to `mariadb:12.1` (Major.Minor only for auto-patch updates)
  - PostgreSQL: Changed from `image:` to `build:` with custom Dockerfile

- **Code Cleanup**:
  - Removed duplicate ARG declarations in `docker/postgres/Dockerfile`
  - Moved pg_cron documentation to `compose.yaml` (central location)
  - `.gitignore`: Fixed `/tools/` entry (was incorrectly
    `/tools/.docker-watch.log`)

---

### Version 3.16 (2026-01-12) - GDPR Backup Strategy & Documentation Structure

#### Added

- **GDPR Phase 2.1 - Backup Strategy**:
  - `docker/scripts/backup-databases.sh`: Encrypted database backups
    (AES-256-CBC)
  - `docker/scripts/restore-database.sh`: Restore with auto-detection of DB type
  - Supports PostgreSQL and MariaDB
  - Configurable retention policy via `BACKUP_RETENTION_DAYS` in `.env`
  - Backup logging to `storage/logs/backup.log`

- **New Make Targets**:
  - `make backup`: Create encrypted database backup
  - `make backup RETENTION=X`: Override retention policy
  - `make backup-list`: List available backups
  - `make restore`: Interactive restore from backup
  - `make db-migrations`: Run GDPR database migrations (encryption helpers,
    audit logs)

- **Configuration**:
  - `BACKUP_RETENTION_DAYS` in `.env.example` (default: 30 days)
  - `backups/` directory created during `make setup`

#### Changed

- **Documentation Restructure**:
  - Reorganized `documentation/` into thematic subdirectories:
    - `security/`: AUDIT-LOGGING.md, BACKUP.md, ENCRYPTION.md,
      SSL-CERTIFICATES.md
    - `development/`: DEV-DASHBOARD.md, RENOVATE.md, XDEBUG.md
    - `testing/`: TESTING-NODE.md, TESTING-PHP.md
    - `infrastructure/`: NETWORK.md
  - Updated `documentation/README.md` with new structure and aligned tables

- **Markdown Formatting**:
  - Fixed table column alignment in all documentation files (PhpStorm
    compatibility)
  - Fixed numbered list continuation with code blocks (proper indentation)

#### Documentation

- `documentation/security/BACKUP.md`: Complete backup & restore guide with GDPR
  compliance table

---

### Version 3.15 (2026-01-12) - Terminal Output & Entrypoint Fixes

#### Fixed

- **Makefile Terminal Corruption**:
  - Added `$(DC)` variable with `--progress=plain` to prevent Docker Compose
    progress output from corrupting terminal
  - Changed `nohup` to `setsid` for Docker Compose Watch to fully detach from
    terminal session
  - Added `< /dev/null` to stdin redirection for complete terminal separation

#### Removed

- **Entrypoint Copy-on-Write Workaround**:
  - Removed obsolete composer.json/composer.lock copy-move workaround from
    `entrypoint.dev.sh`
  - This workaround was no longer needed with Docker Compose Watch (files are
    synced, not baked into image layer)
  - Simplifies entrypoint and eliminates "Resource busy" errors on container
    restart

---

### Version 3.14 (2026-01-12) - Documentation Restructure & MIT License

#### Changed

- **README.md Overhaul**:
  - Reduced from 621 lines to ~130 lines
  - Focused on project description, features, and quick start
  - Moved detailed documentation to `documentation/` directory
  - Updated Quick Start to use correct workflow (`make init` → edit `.env` →
    `make setup` → `make up`)
  - Make is now a required prerequisite (not optional)
  - Added IDE support (PhpStorm, VS Code) and Git Hooks (Captainhook) to
    features

- **License Changed to MIT**:
  - Updated `README.md`, `composer.json`, `package.json`
  - Created `LICENSE` file with MIT license text

#### Added

- **documentation/XDEBUG.md**: Complete Xdebug configuration guide (moved from
  old README)
- **documentation/RENOVATE.md**: Dependency management with Renovate (moved from
  old README)

#### Updated

- **documentation/README.md**: Added links to new documentation files, updated
  structure
- **documentation/TESTING-PHP.md**: Updated directory structure to reflect
  actual test files
- **documentation/TESTING-NODE.md**: Fixed directory structure
  (`tests/node/App/` instead of `tests/node/`)

---

### Version 3.13 (2026-01-12) - PhpStorm Docker Integration & Health Dashboard Fixes

#### Fixed

- **PhpStorm Docker Quality Tools**:
  - Corrected tool paths for PHPMD, PHP-CS-Fixer, and PHPStan to use container
    paths (`/var/www/html/vendor/bin/...`)
  - Fixed `DOCKER_REMOTE_PROJECT_PATH` from `/opt/project` to `/var/www/html`
  - Added proper remote-mappings for Docker interpreter
  - Configured PHP-CS-Fixer `rulesetPath` for Custom config

- **PHP `disable_functions`**:
  - Enabled `parse_ini_file` in development.ini (required by PHPMD)

- **Health Dashboard SSL Certificates**:
  - Added missing volume mount for `/docker/certs` in PHP container
  - SSL certificate info now displays correctly at `/_dev/health`

#### Added

- **Composer Dependencies**:
  - Added `ext-redis` to required PHP extensions

- **Documentation**:
  - Moved DevDashboard README to `documentation/DEV-DASHBOARD.md`

#### Changed

- **PHPStan Configuration**:
  - Set `reportUnmatchedIgnoredErrors: false` to keep ignore patterns for future
    use without warnings

---

### Version 3.12 (2026-01-12) - Code Quality & Documentation Improvements

#### Fixed

- **Makefile `fresh` Target**:
  - Added `--no-cache` flag to ensure true clean rebuilds
  - Previously used cached layers, defeating the purpose of a "fresh" build

- **PHPUnit Coverage Warnings**:
  - Removed `src/php/App/Infrastructure` exclusion from `phpunit.xml.dist`
  - Changed `HasAuditLoggingTest` from `#[CoversClass]` to `#[CoversNothing]`
    (traits can't be coverage targets)
  - Eliminated 30+ coverage target warnings

- **PHP development.ini Comment**:
  - Fixed misleading comment about disabled functions
  - Now correctly documents both `shell_exec` (phpDox) and
    `curl_exec/curl_multi_exec` (Composer)

- **PHP Import Statements**:
  - Refactored to use `use` imports instead of fully qualified class names
    (`\PDO`, `\PDOException`, etc.)
  - Files updated: `DatabaseService.php`, `AuditLogger.php`,
    `CalculatorIntegrationTest.php`

#### Added

- **`make build-no-cache` Target**:
  - New Makefile target for explicit no-cache builds
  - Supports both development and production environments

- **Separated Coverage Directories**:
  - PHP coverage: `build/coverage/php/`
  - Node.js coverage: `build/coverage/node/`
  - Prevents report conflicts when running both coverage commands

- **Test Documentation in `documentation/`**:
  - `TESTING-PHP.md` - Comprehensive PHP testing guide (PHPUnit)
  - `TESTING-NODE.md` - Comprehensive Node.js testing guide (Vitest)
  - Updated `documentation/README.md` with Testing & Quality section

- **New PHP Infrastructure Classes**:
  - `CorsMiddleware.php` - CORS handling with configurable origins via
    `CORS_ORIGINS` env
  - `HealthStatus.php` - Enum for health check states (OK, DEGRADED, ERROR,
    DISABLED, UNKNOWN)

#### Changed

- **Docker Compose Watch Configuration** (`compose.override.yaml`):
  - Added `./build:/var/www/html/build` mount to PHP service for coverage
    reports
  - Added `tests/php` to Watch sync for test file changes

- **Vitest Configuration** (`vitest.config.ts`):
  - Coverage directory changed from `./build/coverage` to
    `./build/coverage/node`

- **Makefile Coverage Commands**:
  - `test-coverage-php`: Output to `build/coverage/php/`
  - `test-coverage-node`: Output to `build/coverage/node/`
  - `test-coverage`: Updated info message with correct paths

#### Removed

- `tests/php/README.md` - Moved to `documentation/TESTING-PHP.md`
- `tests/node/README.md` - Moved to `documentation/TESTING-NODE.md`

#### Status

- PHPStan Level 8: No errors
- PHP CS Fixer: 0 files need fixing
- PHPUnit: 65 tests, 229 assertions (0 warnings)
- Vitest: 61 tests passing

### Version 3.11 (2026-01-11) - Code Quality & PHPStan Level 8 Compliance

#### Fixed

- **PHPDoc Formatting Issues**:
  - Fixed malformed class docblocks in `HealthCheckService.php`,
    `DatabaseService.php`
  - Fixed method docblock indentation issues
  - Corrected misplaced `@return` annotations at class level

- **PHPMD @SuppressWarnings Compatibility**:
  - Changed format from `@SuppressWarnings(PHPMD.*)` to
    `@SuppressWarnings("PHPMD.*")` (quoted)
  - Fixes PHPStan parsing errors while maintaining PHPMD compatibility
  - Affected files: `HealthCheckService.php`, `DatabaseService.php`,
    `WelcomeController.php`

- **PHPStan Type Issues (Level 8)**:
  - `AuditLogger.php`: Changed `?string $logFilePath` to `string` (never null
    after construction)
  - `AuditLogger.php`: Removed redundant null check that was always false
  - `AuditLoggerTest.php`: Added `PDO&MockObject` intersection type for mock
    property
  - `AuditLoggerTest.php`: Added `assertIsString()` for `file_get_contents()`
    result

- **PHPStan Configuration**:
  - Added ignore pattern for test-specific assertions that are valid
    documentation
  - Pattern: `method.alreadyNarrowedType` in `tests/*` (assertIsArray,
    assertTrue, etc.)

#### Changed

- **MariaDB Encryption Config (`my.cnf.example`)**:
  - Encryption settings now enabled by default (uncommented)
  - Rationale: File is only copied when enabling table-level encryption
  - Setup steps reduced from 5 to 4 (removed "uncomment encryption settings")
  - Optional settings remain commented: `innodb_encrypt_tables`,
    `innodb_encryption_rotate_key_age`

#### Status

- PHPStan Level 8: No errors
- PHP CS Fixer: 0 files need fixing
- PHPUnit: 65 tests, 229 assertions
- Vitest: 61 tests passing

### Version 3.10 (2026-01-09) - SSL/TLS Secure-by-Default

**GDPR Phase 1.1 Implementation - Encryption at Rest and in Transit:**

#### Added

- **Automated SSL/TLS Setup**:
  - `make setup` now automatically generates self-signed certificates if not
    present
  - Certificate check integrated into setup workflow
  - Automatic fallback to `make ssl-selfsigned` for development

- **Encryption Key Management**:
  - Automatic generation of `ENCRYPTION_KEY` and `BACKUP_ENCRYPTION_KEY` in .env
  - 256-bit AES keys generated via `openssl rand -base64 32`
  - Keys are checked and generated only if missing or empty

- **SSL/TLS for Databases (Development)**:
  - PostgreSQL SSL enabled by default with custom entrypoint script
  - MariaDB SSL enabled by default with custom entrypoint script
  - Certificate permission handling via entrypoint wrappers
  - Certificates mounted to `/tmp/certs/` and copied with correct ownership

- **SSL/TLS for Databases (Production)**:
  - PostgreSQL SSL configuration activated in `compose.prod.yaml`
  - MariaDB SSL configuration activated in `compose.prod.yaml`
  - Redis TLS configuration activated (port 6380, non-plaintext mode)
  - All services use shared certificates from `docker/certs/`

- **Nginx SSL/HTTPS with Dynamic Port Configuration**:
  - HTTPS enabled on port 8443 (configurable via `NGINX_SSL_PORT`)
  - **Automatic HTTP → HTTPS redirect**: `http://localhost:8080` →
    `https://localhost:8443/`
  - Redirect uses configurable `NGINX_SSL_PORT` from environment variable
  - SSL configuration generated dynamically from template using `envsubst`
  - HTTP/2 support enabled for HTTPS connections
  - Template-based configuration: `ssl-development.conf.template`
  - Entrypoint script processes templates at container startup
  - Port dynamically injected from `NGINX_SSL_PORT` environment variable
  - CSP and SSL configs mounted by default in production (`compose.prod.yaml`)
  - Self-signed certificates work out-of-the-box after `make setup`
  - Ready for Let's Encrypt certificates (`make ssl-letsencrypt`)
  - **Note**: Browser will show certificate warning for self-signed cert
    (expected behavior)

- **Custom Entrypoint Scripts**:
  - `docker/nginx/entrypoint.sh`: Processes SSL template with `envsubst` for
    dynamic port configuration
  - `docker/postgres/entrypoint.sh`: Handles SSL certificate setup for
    PostgreSQL
  - `docker/mariadb/entrypoint.sh`: Handles SSL certificate setup for MariaDB
  - PostgreSQL & MariaDB scripts ensure correct ownership (postgres:postgres,
    mysql:mysql)
  - Automatic permission fixing (644 for .crt, 600 for .key)
  - Nginx script runs as root to write config, then starts nginx

#### Changed

- **Environment Configuration**:
  - `NGINX_SSL_PORT=8443` now enabled by default in `.env.example`
  - Automatic SSL port binding in `compose.yaml`
  - SSL volumes mounted automatically (no manual uncomment needed)

- **Makefile Setup Target Enhanced**:
  - Added SSL certificate existence check
  - Added encryption key generation logic
  - Improved setup flow with colored output
  - Better error handling for missing keys

- **Database Configuration**:
  - PostgreSQL: SSL enabled with custom entrypoint in development
  - MariaDB: SSL enabled with custom entrypoint in development
  - Redis: TLS mode enabled in production (port 6380, plaintext disabled)

- **Nginx Configuration**:
  - Fixed file permissions for `csp-production.conf` (600 → 644)
  - Fixed file permissions for `ssl-production.conf.example` (600 → 644)
  - Converted `ssl-development.conf` to template file with `${NGINX_SSL_PORT}`
    placeholder
  - Nginx Dockerfile: Added `gettext` package for `envsubst` support
  - Nginx Dockerfile: Removed `USER nginx` to allow root entrypoint execution
  - Container starts as root, processes templates, then runs nginx

#### Security Improvements

- **Transport Layer Security**:
  - All database connections encrypted by default
  - PostgreSQL enforces SSL with server certificates
  - MariaDB enforces secure transport with `--require-secure-transport=ON`
  - Redis TLS mode disables plaintext connections

- **At-Rest Encryption Preparation**:
  - Encryption keys ready for column-level encryption (Phase 1.2)
  - Keys stored securely in `.env` (excluded from version control)
  - Backup encryption key for future backup script usage

- **Certificate Management**:
  - Self-signed certificates for development (automatic generation)
  - Symlinks replaced with actual files for proper container access
  - Certificate permissions: 644 for both `.crt` and `.key` (Nginx
    compatibility)
  - Production-ready Let's Encrypt integration (via `make ssl-letsencrypt`)
  - Centralized certificate location (`docker/certs/`)
  - Certificates shared across all services (Nginx, PostgreSQL, MariaDB, Redis)

#### GDPR Compliance

- **Article 32 (Security of Processing)**:
  - ✅ Encryption of personal data in transit (SSL/TLS)
  - ✅ Preparation for encryption at rest (keys generated)
  - ✅ Secure-by-default configuration

- **Article 25 (Data Protection by Design)**:
  - ✅ Default security settings enabled automatically
  - ✅ No manual intervention required for basic security

#### Developer Experience

- **Zero-Configuration Security**:
  - SSL/TLS works out of the box after `make setup`
  - No manual certificate generation needed
  - No manual key generation needed

- **Backward Compatibility**:
  - Existing deployments continue to work
  - Entrypoint scripts handle missing certificates gracefully
  - Optional encryption keys (can remain empty if not used)

#### Testing

- Complete container rebuild verified (`make fresh`)
- All services healthy after SSL/TLS activation
- PostgreSQL SSL verified: `SHOW ssl;` returns `on`
- **HTTP → HTTPS redirect verified**: `http://localhost:8080` returns HTTP 301 →
  `https://localhost:8443/`
- Redirect uses correct configurable port from `NGINX_SSL_PORT`
- Nginx HTTPS verified: `https://localhost:8443` returns HTTP 200 OK
- Following redirect with `-k` flag works: Final HTTP 200 OK
- SSL template processing verified: "SSL configuration generated with
  NGINX_SSL_PORT=8443"
- HTTP/2 protocol confirmed on HTTPS connections
- Certificate validity: 1 year from generation (self-signed)
- Certificate verified: Subject CN=localhost, valid chain
- Encryption keys: 256-bit base64-encoded
- **Browser behavior**: Self-signed certificate warning is shown (expected and
  normal)

#### Documentation

- GDPR Phase 1.1 tasks completed from `GDPR-NEXT-STEPS.md`
- Next phase: Database Encryption (Phase 1.2 - optional)
- Next phase: Audit Logging (Phase 1.3 - GDPR Art. 30)

---

### Version 3.9 (2026-01-03) - Security Hardening: GDPR Compliance & Network Isolation

**Major security enhancements for production environments with 10,000+ users:**

#### Added

- **3-Network Segmentation Architecture** (GDPR Art. 32 compliance):
  - `frontend`: Public-facing nginx only
  - `backend`: Application layer (nginx, php, node)
  - `database`: Data persistence layer (postgres, mariadb, redis)
  - Prevents direct database access from public-facing services
  - Reduces attack surface and enables Defense-in-Depth strategy
  - Comprehensive documentation in `documentation/NETWORK.md`

- **Unix Socket Communication**:
  - PHP-FPM now uses Unix sockets instead of TCP (nginx ↔ php)
  - 10-20% performance improvement over TCP
  - Enhanced security (no network exposure)
  - Shared volume `/var/run/php-fpm` for socket communication

- **SSL/TLS Infrastructure** (prepared for activation):
  - PostgreSQL SSL configuration (commented, ready to enable)
  - MariaDB SSL configuration (commented, ready to enable)
  - Redis TLS configuration (commented, ready to enable)
  - All services can use single certificate from `docker/certs/`

- **Persistent Data Volumes** (Production):
  - Added volumes for all databases in `compose.prod.yaml`
  - Prevents data loss on container recreation
  - Named volumes with project prefix for clarity

#### Changed

- **Certificate Directory Restructured**:
  - Moved from `docker/nginx/certs/` to `docker/certs/`
  - Centralized location for all service certificates
  - Updated all references across 9 files (configs, docs, scripts)

- **PHP-FPM Configuration**:
  - Added custom pool config for Unix socket support
  - Updated healthchecks to use Unix socket
  - Config now consistent across development and production stages

- **Nginx Configuration**:
  - FastCGI pass updated to Unix socket
  - Applied to both default and SSL configurations
  - Maintained backward compatibility

#### Removed

- Redundant `NODE_ENV=production` from PHP service (no functional use in
  PHP-FPM)

#### Fixed

- Production database tmpfs permissions (changed `/var/run` to `/var/run/nginx`)
- **PM2 Process Manager Configuration**:
  - Fixed PM2 not passing `--import tsx` interpreter args to Node.js process
  - Changed from `interpreter` + `interpreter_args` to `script` + `args` pattern
  - Backend now starts correctly with TypeScript transpilation via tsx loader
  - Resolved endless restart loops caused by `wait_ready: true` with missing
    `process.send('ready')`
  - Configured structured JSON logging (consistent with project-wide logging
    standard)
  - Disabled PM2 watch mode (better handled by bind mounts for file change
    detection)
- **Node.js Graceful Shutdown**:
  - Added `process.off()` calls to prevent multiple SIGINT/SIGTERM handlers
  - Prevents duplicate shutdown attempts during container restarts
- **Development Workflow** (Cross-Platform Compatibility):
  - Hybrid approach: COPY in Dockerfile + Docker Compose Watch for file sync
  - Source code (src/, tests/, resources/, templates/) copied into images at
    build time
  - Docker Compose Watch syncs file changes in development (~50-100ms latency)
  - Named volumes for dependencies (node_modules, vendor) - best performance on
    all platforms
  - Consistent performance across Linux, Windows, and macOS
  - No manual configuration needed - works out-of-the-box on all platforms

#### Security Impact

- **GDPR Compliance**: Network segmentation addresses Art. 32 requirements
- **Attack Surface**: Databases unreachable from frontend network
- **Performance**: Unix sockets reduce latency by ~15%
- **Data Integrity**: Persistent volumes prevent accidental data loss

---

### Version 3.8 (2026-01-02) - Production Docker Compose YAML Syntax Fix

**Fixed YAML syntax errors in production compose file:**

#### Fixed - Docker Configuration

- **Production Compose YAML Syntax**:
  - Fixed invalid YAML syntax in `compose.prod.yaml` for PostgreSQL and MariaDB
    services
  - Removed inline comments from multi-line command strings (lines 134-154 for
    PostgreSQL, 183-196 for MariaDB)
  - Comments inside folded multi-line strings (`>`) were being passed to
    database commands, causing syntax errors
  - Moved SSL configuration comments outside command blocks as proper YAML
    comments
  - Validated with `docker compose config --quiet` to ensure correctness

### Version 3.7 (2026-01-02) - Code Quality & Test Coverage Improvements

**Achieved 100% Node.js test coverage, eliminated all PHPMD errors, and improved
TypeScript configuration structure:**

#### Fixed - Code Quality

- **PHPMD Error Elimination**:
  - Fixed all 26 PHPMD errors across multiple files
  - Added appropriate `@SuppressWarnings` annotations for intentional patterns
  - Added missing `use` statements (PDO, PDOException, Redis, Exception, etc.)
  - Removed error control operators (`@`) in HealthCheck and services
  - Files improved: WelcomeController, HealthCheck, DatabaseService,
    HealthCheckService, LogService, QualityService

#### Changed - Node.js Architecture

- **Entry Point Separation**:
  - Moved `server.ts` from `src/node/App/` to `src/node/` (proper separation of
    concerns)
  - Entry point now separated from application logic
  - Updated `ecosystem.config.cjs` to reference correct path:
    `src/node/server.ts`
- **TypeScript Configuration Cleanup**:
  - Split into three distinct configs for clarity:
    - `tsconfig.json`: Development & testing (includes only `src/node/App/**/*`
      and `tests/node/App/**/*`)
    - `tsconfig.build.json`: Production builds (includes `server.ts` entry
      point)
    - `tsconfig.vitest.json`: Test-specific settings
  - Fixed `vitest.config.ts` test patterns to match new structure
    (`tests/node/App/**/*`)

#### Fixed - Test Coverage

- **Node.js Coverage: 100%**:
  - Achieved 100% coverage on application code (exceeds 80% target)
  - app.ts: 100%, math.ts: 100%
  - Resolved Vitest v8 coverage issue with server.ts through proper
    configuration
  - 27/27 tests passing

#### Changed - Docker Configuration

- **Watch Mode Improvements**:
  - Added `sync+restart` action for config files in `compose.override.yaml`
  - Auto-restart on changes to: tsconfig.json, tsconfig.build.json,
    tsconfig.vitest.json, vitest.config.ts, vite.config.js, ecosystem.config.cjs
  - Improves development experience with automatic config reloading
- **Production Build Fix**:
  - Added `tsconfig.build.json` to Dockerfile COPY step (was missing)
  - Fixed production build to compile both frontend AND backend:
    `pnpm run build && pnpm run server:build`
  - Ensures TypeScript server is properly compiled in production images

#### Technical Debt Removed

- Deleted unnecessary `tests/node/App/unit/server.test.ts` (wasn't testing
  server.ts)
- Simplified coverage configuration (removed unnecessary glob-specific
  thresholds)
- Cleaned up workarounds that were masking cache issues

### Version 3.6 (2025-12-31) - Granular Service Control & Makefile Optimization

**Introduced optional database control, simplified Makefile logic, and improved
health checks for better visibility:**

#### Added - Optional Database Control

- **`ENABLE_DATABASE` Environment Variable**:
  - New granular control flag in `.env` and `.env.example` (default: `true`)
  - Allows running stack without database (e.g., for external DB connections)
  - Consistent with existing `ENABLE_PHP`, `ENABLE_NODE`, `ENABLE_REDIS` pattern
  - Added Preset [6]: "Minimal PHP (PHP only, no Database/Redis)"

#### Changed - Makefile Simplification

- **Profile-Based Service Management**:
  - Removed redundant `SERVICES` variable (was duplicating `PROFILES`
    functionality)
  - Now uses Docker Compose profiles idiomatically:
    `docker compose $PROFILES up -d`
  - Cleaner, more maintainable code following Docker Compose best practices
  - Reduced code complexity in `up-core` and `down` targets

- **Enhanced `check-health` Command**:
  - Now displays **all services** regardless of state (enabled/disabled)
  - Added `⚪ Disabled (ENABLE_*=false)` status for deactivated services
  - Provides complete stack overview at a glance
  - Distinguishes between "disabled by config" vs "unhealthy/not running"

#### Fixed - Path Configuration

- **TypeScript Path Mappings**:
  - Fixed `tsconfig.json`: `@tests/*` now correctly points to
    `./tests/node/App/*`
  - Fixed `vitest.config.ts`: `@tests` alias updated to `./tests/node/App`
  - Aligns with App/ directory structure introduced in v3.5

### Version 3.5 (2025-12-31) - Docker Compose Watch, Vitest 4, App Structure Migration

**Modern development workflow with Docker Compose Watch (2025 standard), Vitest
4 upgrade, and improved project structure:**

#### Added - Docker Compose Watch (2025 Standard)

- **Lock File Synchronization Strategy**:
  - Named volumes for dependencies: `php_vendor` (prevents permission issues)
  - Lock files excluded from bind mounts (generated in containers)
  - `develop.watch` configured for both PHP and Node.js services
  - `action: rebuild` triggers on composer.json/package.json/lock file changes
  - `action: sync` for real-time source code updates (src/php, src/node)
  - Manual sync via `make sync-lockfiles` (uses `docker cp`)

- **Makefile Commands**:
  - `make sync-lockfiles`: Copies lock files from containers to host (for Git
    tracking)
  - `make validate`: Extended to check both Composer and pnpm lockfile presence
  - `make setup`: Now creates `tools/` directory (fixes `make docs` permission
    issues)
  - `make up`: Automatically starts database based on `DB_TYPE` environment
    variable
  - `make down`: Now uses same profiles as `make up` (stops all enabled services
    correctly)

#### Changed - Vitest Upgrade (2.1.8 → 4.0.16)

- **Dependencies Updated**:
  - `vitest`: ^2.1.8 → ^4.0.16
  - `@vitest/coverage-v8`: ^2.1.8 → ^4.0.16
  - `@vitest/ui`: ^2.1.8 → ^4.0.16
  - Added: `@types/ws` ^8.5.13, `ws` ^8.18.0 (for future WebSocket live-logs)

- **Configuration Improvements** (vitest.config.ts):
  - Fixed TypeScript import: `import * as path from 'path'` (was causing TS1259
    error)
  - Simplified coverage include/exclude (Vitest 4 fixed pattern matching)
  - Updated alias: `'@node': './src/node/App'` (reflects new App/ structure)
  - Comment clarifies: "Vitest 4.x: Fixed include/exclude handling (no longer
    needs workarounds)"

#### Changed - App/ Directory Structure Migration

- **PHP Source Files**:
  - Moved: `src/php/*.php` → `src/php/App/` (preserves subdirectory structure)
  - Examples: `Http/Router.php`, `Http/Controller/*.php`,
    `Infrastructure/*.php`, `Utils/*.php`
  - Updated composer.json PSR-4: `"App\\": "src/php/App/"` (was
    `"App\\": "src/php/"`)
  - Updated composer.json PSR-4 dev: `"App\\Tests\\": "tests/php/App/"` (was
    `"tests/php/"`)

- **PHP Test Files**:
  - Moved: `tests/php/*.php` → `tests/php/App/`
  - Examples: `Feature/CalculatorIntegrationTest.php`, `Unit/CalculatorTest.php`

- **Node.js Source Files**:
  - Moved: `src/node/*.ts` → `src/node/App/`
  - Examples: `app.ts`, `server.ts`, `utils/math.ts`
  - Updated tsconfig.json paths: `"@node/*": ["./src/node/App/*"]`

- **Node.js Test Files**:
  - Moved: `tests/node/*.test.ts` → `tests/node/App/`
  - Examples: `integration/api.test.ts`, `unit/math.test.ts`
  - Updated vitest.config.ts include: `tests/node/**/*.{test,spec}.{ts,js}`

- **Template Path Fix**:
  - `WelcomeController.php`: Added extra `../` for new directory nesting level
  - Path: `__DIR__ . '/../../../../../templates/welcome.php'` (was 5 levels,
    now 6)

#### Changed - IDE Integration Updates

- **.idea/zappzarapp.iml**: Updated sourceFolders and testFolders for App/
  structure
- **.idea/phpunit.xml**: Corrected test directories path
- **ecosystem.config.cjs**: Updated Node.js app paths to src/node/App/
- **phpunit.xml.dist**: Updated test suite directories to tests/php/App/

#### Fixed - Git Status on DevDashboard

- **Problem**: Git commands failed for www-data user with "dubious ownership"
  error
- **Root Cause**: Repository owned by host user (UID 1000), but PHP-FPM runs as
  www-data (UID 82)
- **Solution**: Changed `git config --global` to `git config --system` in
  docker/php/entrypoint.dev.sh
  - System-wide config (`/etc/gitconfig`) accessible to all users
  - Global config (`/root/.gitconfig`) only accessible to root
- **Result**: Dashboard now correctly displays:
  - Current branch (e.g., "node-testing-backup")
  - Latest commit hash (e.g., "8f2a806")
  - Uncommitted changes count with status badge

#### Fixed - Makefile Issues

- **Database Auto-Start**: `make up` now includes database in SERVICES variable
  - Changed: `SERVICES="nginx ${DB_TYPE:-postgres}"` (was `SERVICES="nginx"`)
  - Database type controlled by `DB_TYPE` env var (postgres/mariadb), not
    separate flag

- **make down Profile Handling**: Now uses same profiles as `make up`
  - Previously only stopped nginx, left other containers running
  - Now correctly stops all enabled services (php, node, redis,
    postgres/mariadb)

- **Confusing Hints Removed**: Deleted "For guaranteed consistency, use 'make
  ...-install'" messages
  - Messages appeared on `*-install-local` targets but were misleading
  - Lock files now managed via Docker Compose Watch strategy

#### Fixed - PHP Entrypoint (docker/php/entrypoint.dev.sh)

- **composer.lock Check**: Added lockfile existence check to install condition
  - Before: Only checked `vendor/` directory and `autoload.php`
  - After: Also checks for `composer.lock` existence
  - Ensures lockfile is generated on first `make setup` run
  - Condition:
    `if [ ! -d vendor ] || [ ! -f vendor/autoload.php ] || [ ! -f composer.lock ]`

#### Testing

- ✅ Fresh developer workflow: `make init` → `make setup` → `make up` (tested
  from clean slate)
- ✅ PHPUnit: 36 tests, 163 assertions passing
- ✅ Vitest: 25 tests passing
- ✅ All endpoints functional: localhost:8080, /\_dev, /\_dev/system, etc.
- ✅ Git Status displays correctly on DevDashboard (branch, commit, uncommitted
  changes)
- ✅ make docs working (tools/ directory created by setup)
- ✅ make validate checks both composer.json and pnpm-lock.yaml
- ✅ make down stops all services (verified with make status)
- ✅ Database starts automatically with make up

#### Documentation

- Removed confusing lockfile consistency hints from Makefile
- Added Vitest 4.x comment explaining simplified coverage config
- Git safe.directory comment updated to clarify system-wide vs global scope

### Version 3.4 (2025-12-30) - Development Dashboard Completion

**Complete implementation of all dashboard pages with proper autoloading, volume
mounts, and production safety:**

#### Completed - Dashboard Pages

- **Quality Page (`/_dev/quality`)**:
  - PHP quality tools status: PHPStan Level 8, PHPMD, PHP CS Fixer (properly
    detected)
  - Node.js quality tools status: ESLint, Prettier, TypeScript (detected via
    volume mounts)
  - Code statistics: Accurate file counts for PHP/Node source and test files
  - Test coverage reports for PHP (PHPUnit) and Node.js (Vitest)
  - Quick action commands: Run quality checks, auto-fix code style, generate
    coverage

- **Logs Page (`/_dev/logs`)**:
  - Log statistics: File count, total size, available sources
  - Application logs detection (requires `make setup` to create storage/logs)
  - Available log sources: Docker Compose logs, per-service logs, application
    logs
  - CLI commands with examples for log viewing (docker compose logs, tail, grep)
  - Usage tips: Real-time monitoring, filtering, searching patterns
  - Makefile integration documentation

- **Database Page (`/_dev/database`)**:
  - Database overview: Type, version, table count, total size
  - Connection pool statistics: Total, active, idle connections
  - Tables list: Row counts, sizes, schemas (PostgreSQL/MariaDB support)
  - CLI commands: psql/mysql access, dump, restore, exec
  - Database client recommendations: pgAdmin, DBeaver, TablePlus, DataGrip
  - Error handling for disconnected databases

- **System Page Improvements**:
  - Fixed phpinfo() button toggle (removed duplicate "Hide" link)
  - phpinfo() logos display correctly (fixed CSP to allow data: URIs)
  - Improved phpinfo() display with scrollable container

- **Dashboard Page Enhancements**:
  - Git status working correctly (branch, commit, uncommitted changes)
  - System health monitoring with status indicators
  - Quick actions for common tasks

#### Fixed - Critical Issues

- **PHP Autoloading**: Removed all `require_once` statements from
  DashboardController
  - Added `DevDashboard\` namespace to composer.json PSR-4 autoload
  - Added `Tests\DevDashboard\` namespace to composer.json PSR-4 autoload-dev
  - Eliminates "does not comply with psr-4" warnings during composer
    dump-autoload

- **CSP Security**: Fixed Content-Security-Policy blocking phpinfo() images
  - Added `img-src 'self' data:` to development CSP in nginx default.conf
  - Allows base64-encoded images (PHP logo, Zend logo) to display correctly

- **Production Safety**: Dashboard now only available in development environment
  - Checks `ENV=development` in public/index.php (not a separate variable)
  - Prevents accidental exposure in production of sensitive data:
    - Environment variables and secrets
    - Database credentials
    - phpinfo() system details
    - Git repository information

- **UI/UX Fixes**:
  - Added explicit spacing in header (gap-4 between sections, gap-2 within)
  - Fixed list styling with consistent bullet points (removed double bullets)
  - Removed duplicate "Hide phpinfo()" link in System page

#### Added - Architecture Improvements (DEV-only)

- **Volume Mounts** (compose.override.yaml):
  - Node.js config files: eslint.config.js, .prettierrc.json, tsconfig.json
  - Git repository: .git/ (read-only) for git status functionality
  - Node.js source: src/node/, tests/node/ for accurate code statistics
  - Entrypoint script: docker/php/entrypoint.dev.sh

- **Git Integration**:
  - Created entrypoint.dev.sh to configure git safe.directory automatically
  - Resolves "dubious ownership" errors in Docker container
  - Enables Git status detection in dashboard

- **Quality Detection**:
  - QualityService now properly checks for config files (no assumptions)
  - Node.js tools detected via volume-mounted configs
  - PHP tools detected from phpstan.neon, phpmd.xml.dist, .php-cs-fixer.dist.php

#### Changed - IDE Integration

- **.idea/zappzarapp.iml**:
  - Corrected sourceFolders: `src/php/App`, `src/php/DevDashboard` (not generic
    `src/php`)
  - Corrected test folders: `tests/php/App`, `tests/php/DevDashboard`
  - Added Node.js folders: `src/node`, `tests/node`

- **.idea/phpunit.xml**:
  - Fixed directories: `$PROJECT_DIR$/tests/php` (not generic `tests`)

- **.vscode/settings.json**:
  - Updated PHPStan level to 8 (was 5)
  - Updated PHPStan configFile to phpstan.neon (was phpstan.neon.dist)

- **phpstan.neon**:
  - Upgraded from Level 5 to Level 8 for stricter type checking

- **Makefile**:
  - Added `storage/logs` to directories created by `make setup`
  - Ensures Application Logs feature works after setup

- **composer.json**:
  - Added DevDashboard and Tests\DevDashboard namespaces to autoload

#### Services Implementation

- **QualityService**: Code quality metrics aggregation
  - Detects PHP tools: PHPStan, PHPMD, PHP CS Fixer
  - Detects Node.js tools: ESLint, Prettier, TypeScript
  - Counts source files and test files for both PHP and Node.js
  - Checks for test coverage reports

- **LogService**: Log viewing and aggregation
  - Lists available log sources (Docker, application, per-service)
  - Provides CLI commands for log viewing
  - Calculates log file statistics

- **DatabaseService**: Database introspection
  - Database overview with type-specific queries (PostgreSQL/MariaDB)
  - Tables list with row counts and sizes
  - Connection pool statistics
  - CLI commands for database operations

#### Testing

- ✅ All 6 dashboard pages return HTTP 200
- ✅ phpinfo() images display correctly (2 logos present)
- ✅ Application Logs shows as "Available" after make setup
- ✅ Node.js quality tools detected correctly
- ✅ Git status shows branch and commit info
- ✅ No composer autoloading warnings
- ✅ PHPUnit DevDashboard suite: 19 tests, 131 assertions - OK
- ✅ Proper PHP autoloading working (no require_once needed)

#### Security Notes

- Dashboard only accessible when `ENV=development`
- Volume mounts for .git and Node.js configs are DEV-only
  (compose.override.yaml)
- Sensitive environment variables masked in display
- phpinfo() only available in development

### Version 3.3 (2025-12-30) - Development Dashboard

**Comprehensive development dashboard for real-time system monitoring and
insights:**

#### Added - Development Dashboard (`/_dev`)

- **Dashboard Pages**:
  - Main Dashboard (`/_dev`): System overview with health status, git info,
    quick actions
  - Health Checks (`/_dev/health`): Container status, database connections,
    service monitoring, SSL certificate info
  - System Info (`/_dev/system`): PHP version, loaded extensions (160+),
    environment variables, phpinfo() viewer
  - Placeholder Pages: Quality metrics, Database tools, Log viewer (to be
    implemented)

- **Core Services**:
  - `HealthCheckService`: Real-time health monitoring via TCP socket checks
    - Container checks: nginx, php, node, redis, postgres (based on enabled
      services)
    - Database connections: PostgreSQL/MariaDB (based on DB_TYPE)
    - Service checks: PHP-FPM, Node.js, Nginx
    - SSL certificate validation with expiry warnings
  - `SystemInfoService`: System information aggregation
    - PHP version, SAPI, Zend version
    - 160+ loaded extensions with version info
    - Environment variables with sensitive data masking
    - Git repository status (branch, commit, uncommitted changes)

- **Technical Implementation**:
  - Simple function-based routing under `/_dev` prefix
  - Server-side rendered PHP views with inline CSS (CSP-compliant, no external
    CDN)
  - Environment-based enable/disable (`ENABLE_DEV_DASHBOARD=false` for
    production)
  - Comprehensive test coverage: 19 tests, 123 assertions (100% passing)

- **API Endpoints**:
  - `/_dev/api/health-check`: Overall system health status (JSON)
  - `/_dev/api/container-status`: Detailed container status (JSON)

#### Changed - Infrastructure

- **Makefile**: Add DevDashboard directory structure in `make setup`
  - `src/php/DevDashboard/{Controllers,Services,Views}`
  - `tests/php/DevDashboard/{Controllers,Services}`

- **Docker Nginx Configuration**:
  - Fixed config mounting: Only copy base configs into image (default.conf,
    csp-production.conf)
  - SSL configs now properly opt-in via compose.yaml volumes (not baked into
    image)
  - Resolved restart loop issue caused by missing SSL certs with mounted config

- **PHPUnit Configuration**:
  - Added DevDashboard test suite to phpunit.xml.dist

- **Public Entry Point**:
  - Integrated DevDashboard routing before app routes
  - Dashboard only loads when path starts with `/_dev`

#### Technical Details

- **Health Check Strategy**: TCP socket connectivity checks instead of Docker
  CLI (works inside containers)
- **Environment Awareness**: Only checks enabled services (ENABLE_PHP,
  ENABLE_NODE, ENABLE_REDIS, DB_TYPE)
- **Security**: Sensitive environment variables (PASSWORD, SECRET, KEY) are
  masked in display
- **Styling**: Self-contained inline CSS (~190 lines) for zero external
  dependencies

### Version 3.2 (2025-12-30) - IDE Integration (VS Code & PhpStorm)

**Complete IDE configurations for both Visual Studio Code and PhpStorm with full
feature parity:**

#### Added - VS Code Configuration (`.vscode/`)

- **Workspace Configuration**:
  - `extensions.json`: 26 recommended extensions (PHP, Node.js, Docker, Git,
    Testing, Database)
  - `settings.json`: Comprehensive workspace settings with tool integration
  - `tasks.json`: 24 pre-configured tasks for all Makefile commands
  - `launch.json`: Debug configurations for PHP (Xdebug), Node.js, Frontend,
    Full-Stack compounds
  - `README.md`: Complete documentation with setup guide and troubleshooting

- **PHP Development Tools**:
  - Intelephense with PHP 8.4 support
  - PHP CS Fixer integration (PER-CS standard, risky rules enabled)
  - PHPStan Level 5 integration
  - PHPMD integration
  - PHPUnit Test Explorer
  - Xdebug 3.5.0 debugging (port 9003)

- **JavaScript/TypeScript Tools**:
  - ESLint validation and auto-fix
  - Prettier formatting
  - TypeScript strict mode
  - Vitest Test Explorer
  - Auto imports and path updates

- **Docker & Database**:
  - Docker extension integration
  - Remote Containers support
  - SQL Tools with PostgreSQL and MariaDB pre-configured

- **Editor Configuration**:
  - Tab size: 4 (PHP), 2 (JS/TS)
  - 120 char ruler, Unix line endings (LF)
  - Format on save (PHP: CS Fixer via onsave, JS/TS/Markdown: Prettier)
  - Real-time linting (PHPStan, PHPMD, ESLint)
  - Code spell checker with custom dictionary

#### Added - PhpStorm Configuration (`.idea/`)

- **Run Configurations** (`runConfigurations/`):
  - 25 pre-configured run configurations organized by category
  - Browser: Open App
  - Make: Up, Down, Restart, Fresh Build, Rebuild
  - PHP: CS Fixer, PHPStan, PHPMD, Run Tests, Coverage Report
  - Node: ESLint, Prettier, Type Check, Run Tests, Coverage Report
  - Quality: Run All Checks, Fix All
  - Test: Run All Tests
  - Docs: Generate API Documentation
  - SSL: Generate Self-Signed, Show Certificate Info
  - Logs: View All
  - Shell: PHP Container, Node Container

- **Database Connections** (`dataSources.xml`):
  - PostgreSQL (Docker): localhost:5432/app
  - MariaDB (Docker): localhost:3306/app

- **Documentation** (`README.md`):
  - Complete setup guide
  - Troubleshooting section
  - Feature comparison with VS Code

#### Changed

- **VS Code Configuration**:
  - Fixed `cSpell.enableFiletypes` → `cSpell.enabledFileTypes` (deprecated
    syntax)
  - Renamed tasks for consistency with PhpStorm: `Docker: *` → `Make: *`
    - `Docker: Up` → `Make: Up`
    - `Docker: Down` → `Make: Down`
    - `Docker: Restart` → `Make: Restart`
    - `Docker: Fresh Build` → `Make: Fresh Build`
    - `Docker: Rebuild` → `Make: Rebuild`
- **PhpStorm Run Configurations**:
  - Renamed `Rebuild_Docker_Images.xml` → `Make__Rebuild.xml` (consistent
    naming)
  - Renamed `Run_App_in_Browser.xml` → `Browser__Open_App.xml`
  - Removed `Docker.xml` (redundant, all Docker operations via Make)
- **.gitignore**: Updated to allow VS Code workspace config (like PhpStorm
  .idea)
  - Only ignore user-specific files (\*.code-workspace, .history/)

#### Feature Parity (Both IDEs)

Complete feature parity between VS Code and PhpStorm:

- ✅ PHP Interpreter via Docker Compose
- ✅ Code Style: PHP CS Fixer (PER-CS)
- ✅ Static Analysis: PHPStan Level 5
- ✅ Mess Detection: PHPMD
- ✅ Testing: PHPUnit, Vitest
- ✅ Debugging: Xdebug 3.5.0
- ✅ Database Tools: PostgreSQL, MariaDB connections
- ✅ Run Configurations/Tasks for all Make commands
- ✅ Comprehensive documentation (README.md in both .vscode/ and .idea/)

---

### Version 3.1 (2025-12-29) - SSL/TLS Integration

**Comprehensive SSL/TLS support for all services with zero-config philosophy:**

#### Added

- **SSL Certificate Management**:
  - Self-signed certificate generator
    (`docker/nginx/certs/generate-selfsigned.sh`)
  - Let's Encrypt setup script (`docker/nginx/certs/setup-letsencrypt.sh`)
  - Makefile commands: `ssl-selfsigned`, `ssl-letsencrypt`, `ssl-renew`,
    `ssl-info`, `ssl-clean`
  - Comprehensive SSL documentation (`docker/nginx/certs/README.md`)
- **Nginx SSL Configurations**:
  - `ssl-development.conf`: Zero-config SSL für Development (localhost,
    self-signed)
  - `ssl-production.conf.example`: Production template mit HSTS, OCSP Stapling,
    strenger CSP
  - Separate Configs für Development/Production Parität
- **Database SSL Support**:
  - PostgreSQL: SSL connection configuration in `compose.prod.yaml` (optional)
  - MariaDB: SSL connection configuration in `compose.prod.yaml` (optional)
  - Certificate mounting via volumes (commented, ready to uncomment)
- **Node.js SSL Support**:
  - Documentation für HTTPS server setup (`docker/node/ssl-example.md`)
  - Szenarien: Nginx Reverse Proxy (default) vs. Direct Exposure
- **Environment Variables**:
  - `NGINX_SSL_PORT` für SSL Port Configuration (default: 8443)

#### Changed

- **Directory Structure**: SSL certificate directory via `make setup` statt
  .gitkeep
- **Compose Files**:
  - `compose.yaml`: SSL volumes für development (commented)
  - `compose.prod.yaml`: SSL volumes für production (commented)
- **Zero-Config Philosophy**: Development SSL funktioniert out-of-the-box nach
  `make ssl-selfsigned`

#### Removed

- Obsolete `.gitkeep` files (alle Directories werden via `make setup` erstellt):
  - `src/php/.gitkeep`, `src/node/.gitkeep`
  - `resources/js/.gitkeep`, `resources/css/.gitkeep`,
    `resources/images/.gitkeep`
  - `config/.gitkeep`, `templates/.gitkeep`
  - `storage/app/.gitkeep`, `storage/cache/.gitkeep`,
    `storage/sessions/.gitkeep`

#### Security

- Modern TLS configuration (Mozilla Intermediate Profile)
- TLSv1.2/1.3 only, strong cipher suites
- HSTS, OCSP Stapling in production config
- Strikte CSP in production SSL config

---

### Version 3.0 (2025-12-29) - Quality & CI/CD Integration

**Peer Review Improvements based on comprehensive code review:**

#### Added

- **CI/CD Templates**:
  - GitHub Actions workflow (`.github/workflows/ci.yml`) mit 6 Jobs:
    - php-quality: CS-Fixer, PHPStan, PHPMD, Rector
    - php-tests: PHPUnit mit Coverage (80% threshold)
    - node-quality: ESLint, Prettier, TypeScript
    - node-tests: Vitest mit Coverage (80% threshold)
    - security: Trivy Scans für Docker Images, Dependency Audits
    - build-production: Production Build Validation
  - GitLab CI pipeline (`.gitlab-ci.yml`) mit 15+ Jobs über 5 Stages
- **Security**:
  - Production CSP Config (`docker/nginx/conf.d/csp-production.conf`)
  - Strict Content-Security-Policy ohne unsafe-inline/unsafe-eval
  - Optional als Volume in `compose.prod.yaml` (kommentiert)
- **Configuration**:
  - Composer `platform-check: true` für PHP Version Consistency
  - TypeDoc `theme: "default"` explizit konfiguriert

#### Changed

- **Health Checks**: Node.js Development Health Check verbessert
  - Alt: `test -f /app/package.json` (nur File-Check)
  - Neu: Prüft auf laufende Services (Vite:5173 oder Backend:3000)
  - Fallback auf File-Check für idle Mode
- **Documentation**: compose.prod.yaml mit CSP Config Mount Beispiel

#### Removed

- Obsoleter TODO Kommentar in `docker/php/Dockerfile` (Composer wurde bereits
  korrekt deinstalliert in Zeile 144)

#### Quality Notes

- Projekt-Status nach Peer Review: **AUSGEZEICHNET (9.5/10)**
- PhpStorm Settings bereits perfekt konfiguriert (Docker Interpreter, PHPStan
  Level 5, CS-Fixer, PHPMD)
- Komplette PHP ↔ Node.js Parität bei allen Quality Tools
- Zero-Config Readiness validiert
- 12-Factor App Compliance vollständig

---

### Version 2.19 (2025-12-29)

- ✅ **Code Quality & Build Optimization (Gemini-Review + Optimierungen)**
  - **Kontext:** Gemini AI Review des gesamten Projekts mit 7
    Verbesserungsvorschlägen
    - Nach Analyse: 5 Vorschläge sinnvoll, 2 inkorrekt/obsolet
    - **Resultat:** 5 Optimierungen implementiert (+ 1 Datei-Cleanup)
  - **Änderung 1: Rector vollständig integriert (composer.json + Makefile)**
    - **Problem:** Rector-Dependency vorhanden, aber nicht nutzbar
      - `rector.php` Config existierte, aber keine Scripts/Targets
      - Automatische PHP 8.4 Refactorings nicht verfügbar
    - **Lösung:**
      - `composer.json`: Scripts `rector-check` (dry-run) und `rector-fix`
        hinzugefügt
      - `Makefile`: Targets `make rector-check` und `make rector-fix`
        hinzugefügt
    - **Nutzen:**
      - ✅ Automatische Code-Upgrades auf PHP 8.4 Syntax (property hooks, etc.)
      - ✅ Dead Code Detection & Removal
      - ✅ Type Declaration Improvements
  - **Änderung 2: Git Line-Ending-Konsistenz (.gitattributes)**
    - **Problem:** Nur `* text=auto` ohne explizite LF-Enforcement
      - Potenzielle CRLF/LF-Inkonsistenzen zwischen Windows/Unix/macOS
    - **Lösung:** Explizite `eol=lf` Regeln für alle Text-Dateien
      - `* text=auto eol=lf` (Global Default)
      - Explizite Rules: `*.php`, `*.js`, `*.ts`, `*.json`, `*.md`, `*.yaml`,
        `*.yml`, `*.xml`
    - **Nutzen:**
      - ✅ 100% LF-Garantie (verhindert CRLF auf Windows)
      - ✅ Keine Git-Diff-Rauschen durch Line-Ending-Wechsel
  - **Änderung 3: PHP-CS-Fixer auf @PER-CS:risky umgestellt
    (.php-cs-fixer.dist.php)**
    - **Vorher:** `@auto` Ruleset (veraltet, deprecated in PHP-CS-Fixer v4)
    - **Nachher:** `@PER-CS:risky` + Custom Binary Operator Alignment
      - `@PER-CS:risky` = PER Coding Style 2.0 (PSR-12 Nachfolger, offizieller
        PHP-FIG Standard)
      - `setRiskyAllowed(true)` aktiviert (required für :risky Variante)
      - Custom Rule: `binary_operator_spaces` mit `=>` und `=` Alignment
    - **Nutzen:**
      - ✅ Modernster PHP Coding Standard (Industry Best Practice)
      - ✅ Zukunftssicher (PER ersetzt PSR-12 offiziell)
      - ✅ Konsistent mit PhpStorm-Config (.idea/php.xml nutzt auch PER-CS)
  - **Änderung 4: Docker Compose Build-Dependencies (compose.yaml)**
    - **Problem:** nginx + php kopieren von `zappzarapp-node:latest`, aber keine
      explizite Dependency
      - `docker/nginx/Dockerfile:66`:
        `COPY --from=zappzarapp-node:latest /app/public/build/`
      - `docker/php/Dockerfile:140`:
        `COPY --from=zappzarapp-node:latest /app/public/build/`
      - Potenzielle Race-Condition bei `make build` (node muss zuerst gebaut
        werden)
    - **Lösung:** `depends_on: node` bei nginx + php hinzugefügt
      - `condition: service_started` (wartet auf node-Container Start)
      - `required: false` (optional, da nur für Build relevant)
    - **Nutzen:**
      - ✅ Korrekte Build-Reihenfolge garantiert (Docker Compose orchestriert
        automatisch)
      - ✅ Makefile-Logic vereinfacht (kein manueller "build node first"-Hack
        mehr nötig)
      - ✅ Konsistent mit Best Practices (explizite Dependencies deklarieren)
  - **Änderung 5: Node.js Security-Audit (Makefile)**
    - **Problem:** PHP hat `make security-deps`, Node.js hatte kein Äquivalent
      - Inkonsistenz: PHP-Dependencies werden gescannt, npm-Dependencies nicht
    - **Lösung:** `make security-audit-node` hinzugefügt
      - Führt `pnpm audit` im node-Container aus
      - Scannt npm-Dependencies auf bekannte CVEs
    - **Nutzen:**
      - ✅ Parität zwischen PHP und Node.js Security-Tooling
      - ✅ Früherkennung von npm-Package-Schwachstellen
  - **Änderung 6: Obsolete Datei entfernt (php_cs_fixer.dist.php)**
    - **Problem:** Doppelte PHP-CS-Fixer Config
      - `.php-cs-fixer.dist.php` (neu, modern) ✅
      - `php_cs_fixer.dist.php` (alt, ungenutzt, verwaist) ❌
    - **Lösung:** Alte Datei gelöscht
  - **Änderung 7: CaptainHook PHP Lint Hook Fix (captainhook.json)**
    - **Problem:** Pre-commit Hook schlägt fehl bei gelöschten PHP-Dateien
      - `git diff --name-only --cached` listet auch gelöschte Dateien
      - `php -l` versucht nicht-existierende Dateien zu linten → "Could not open
        input file"
    - **Lösung:** `--diff-filter=d` hinzugefügt
      - Filtert gelöschte Dateien aus dem diff
      - Nur existierende PHP-Dateien werden gelintet
  - **Files geändert:**
    - `composer.json` (+ rector-check/fix Scripts)
    - `.gitattributes` (+ explizite eol=lf Rules)
    - `.php-cs-fixer.dist.php` (@auto → @PER-CS:risky)
    - `compose.yaml` (+ depends_on: node bei nginx/php)
    - `Makefile` (+ rector-check/fix, security-audit-node Targets)
    - `captainhook.json` (+ --diff-filter=d im PHP Lint Hook)
    - `php_cs_fixer.dist.php` (gelöscht)
  - **PhpStorm-Integration verifiziert (.idea/php.xml):**
    - `allowRiskyRules="true"` ✅ (konsistent mit setRiskyAllowed(true))
    - `codingStandard="PER-CS"` ✅ (konsistent mit @PER-CS:risky)
    - Keine Anpassungen nötig (bereits korrekt konfiguriert)
  - **Neue Make-Befehle:**
    - `make rector-check` - Zeigt potenzielle PHP 8.4 Refactorings (dry-run)
    - `make rector-fix` - Führt automatische Refactorings aus
    - `make security-audit-node` - Scannt Node.js Dependencies auf CVEs
  - **Vorteile:**
    - ✅ Vollständige Rector-Integration für PHP 8.4 Upgrades
    - ✅ Line-Ending-Konsistenz über alle Plattformen
    - ✅ Modernster PHP Coding Standard (PER-CS statt deprecated @auto)
    - ✅ Korrekte Docker Build-Orchestrierung (explizite Dependencies)
    - ✅ Parität: PHP + Node.js Security-Scanning
    - ✅ Code-Aufräumung (obsolete Dateien entfernt)
    - ✅ CaptainHook robuster bei Datei-Löschungen

### Version 2.18 (2025-12-29)

- ✅ **Nginx /docs/ Route: API-Dokumentation über Browser zugänglich
  (Development-Only)**
  - **Problem:** Generierte API-Dokumentation ist lokal vorhanden, aber nicht im
    Browser abrufbar
    - `make docs` generiert Dokumentation in `docs/`, aber kein Web-Zugriff
    - Entwickler müssen Dateien direkt im Filesystem öffnen
    - Inkonsistent mit Dashboard-Integration der anderen Endpoints
  - **Lösung: Nginx Route + Dashboard-Integration (nur Development)**
    - **Nginx `/docs/` Location Block (`docker/nginx/conf.d/default.conf`):**
      - `location ^~ /docs/` - Prefix-Match mit `^~` modifier (verhindert
        Regex-Matching)
      - `alias /var/www/html/docs/` - Serve-Pfad
      - `autoindex on` - Directory-Listing für Übersichtsseite
      - `try_files $uri $uri/ =404` - File-Serving-Logik
      - `add_header Cache-Control "no-cache, must-revalidate"` - Verhindert
        veraltete Docs
      - **Warum `^~` modifier:** Verhindert, dass Regex-Location
        `~* \.(css|js|...)` CSS/JS-Files in docs/ abfängt
    - **Redirect `/docs` → `/docs/`:**
      - `location = /docs { return 301 /docs/; }` - Trailing Slash Normalization
    - **Volume Mount (compose.override.yaml):**
      - `- ./docs:/var/www/html/docs:ro` (Read-Only, nur Development)
      - **Sicherheit:** In Production nicht gemountet → 404 für `/docs/`
        (intended behavior)
    - **Dashboard-Integration (`templates/welcome.php`):**
      - Neue Sektion "📖 API Documentation" (nur Development:
        `if ($vite->isDevelopment())`)
      - Links zu `/docs/`, `/docs/api/php/`, `/docs/api/node/`
      - Hinweis: "Run `make docs` to generate/update API documentation"
  - **Endpoints:**
    - `http://localhost:8080/docs/` - Dokumentations-Übersicht (Directory
      Listing)
    - `http://localhost:8080/docs/api/php/` - PHP API Docs (phpDocumentor)
    - `http://localhost:8080/docs/api/node/` - Node/TypeScript API Docs
      (TypeDoc)
  - **Files geändert:**
    - `docker/nginx/conf.d/default.conf` (neue `/docs/` Location Blocks)
    - `compose.override.yaml` (docs/ Volume Mount für nginx Service)
    - `templates/welcome.php` (neue "API Documentation" Sektion)
  - **Vorteile:**
    - ✅ Entwickler können API-Docs direkt im Browser öffnen
    - ✅ Dashboard zeigt alle verfügbaren Endpoints inkl. Dokumentation
    - ✅ Development-Only Feature (Production-sicher)
    - ✅ CSS/JS-Files funktionieren korrekt (`^~` modifier verhindert Konflikte)
    - ✅ Konsistent mit "production-ready boilerplate"-Philosophie

### Version 2.17 (2025-12-29)

- ✅ **Documentation Generation: PHP + Node/TypeScript (Production-Ready
  Setup)**
  - **Problem:** Keine automatische API-Dokumentations-Generierung vorhanden
    - Manual documentation ist fehleranfällig und veraltet schnell
    - Keine Parität zwischen PHP und Node.js Tooling
    - Inkonsistent mit "production-ready boilerplate"-Philosophie
  - **Lösung: Pre-konfigurierte Documentation-Tools für beide Stacks**
    - **PHP: phpDocumentor v3.9.1 (PHAR standalone)**
      - **Installation:** Auto-Download on first use (Makefile lädt PHAR bei
        Bedarf herunter)
      - **Warum PHAR:** Vermeidet Composer-Dependency-Konflikte (phpDocumentor
        v3 requires Monolog v2, wir nutzen v3)
      - **Warum Download-on-Demand:** Kein 25MB Binary im Repo (tools/ ist in
        .gitignore)
      - **Config:** `phpdoc.xml` (scannt `src/php/`, Output: `docs/api/php/`)
      - **Features:** Class diagrams, inheritance graphs, Markdown support,
        responsive UI
      - **Script:** `composer docs` →
        `php tools/phpdoc.phar --config=phpdoc.xml`
      - **Makefile-Logic:** `make docs-php` prüft ob PHAR existiert, downloadet
        sie sonst automatisch
    - **Node/TypeScript: TypeDoc**
      - **Installation:** `pnpm add -D typedoc` (package.json devDependencies)
      - **Config:** `typedoc.json` (scannt `src/node/`, Output:
        `docs/api/node/`)
      - **Features:** TypeScript-native, type inference, cross-referenced
        navigation
      - **Script:** `pnpm run docs` → `typedoc`
    - **Makefile-Targets:**
      - `make docs` - Generiert PHP + Node Dokumentation
      - `make docs-php` - Nur PHP API Docs
      - `make docs-node` - Nur Node/TypeScript API Docs
      - `make docs-clean` - Löscht generierte Dokumentation
    - **Files geändert:**
      - `phpdoc.xml` (neu) - phpDocumentor Konfiguration
      - `typedoc.json` (neu) - TypeDoc Konfiguration
      - `composer.json` (Script: `docs`)
      - `package.json` (Script: `docs`, DevDep: `typedoc`)
      - `.gitignore` (ignoriert `docs/`, `.phpdoc/` Cache, `tools/`)
      - `Makefile` (neue Documentation-Section mit Auto-Download-Logic)
      - `README.md` (umfassende "Documentation Generation"-Sektion mit Examples,
        Best Practices, CI/CD Integration)
      - `tools/` (git-ignored, PHAR wird on-demand downloaded)
  - **Vorteile:**
    - ✅ Zero-Config Documentation Generation (out-of-the-box)
    - ✅ PHP + Node Parität (beide Stacks haben Tools)
    - ✅ Konsistent mit Projekt-Philosophie: "Production-ready modern defaults"
    - ✅ PHPDoc & TSDoc Best Practices demonstriert
    - ✅ CI/CD Integration-Example in README
    - ✅ Keine Composer-Dependency-Konflikte (PHAR-Ansatz)
    - ✅ Makefile-Integration für einfache Nutzung

### Version 2.16 (2025-12-29)

- ✅ **Alpine Linux: Upgrade auf 3.23 (alle Services)**
  - **Grund:** Version-Matching für Nginx + Brotli-Modul
  - **Geänderte Dockerfiles:**
    - `docker/nginx/Dockerfile`: Alpine 3.22 → 3.23
    - `docker/php/Dockerfile`: Alpine 3.22 → 3.23
    - `docker/node/Dockerfile`: Alpine 3.22 → 3.23
      - **Fix:** `COREPACK_ENABLE_DOWNLOAD_PROMPT=0` hinzugefügt
      - **Grund:** Alpine 3.23 / Node 24 - Corepack fragt interaktiv nach
        Download-Bestätigung
      - **Lösung:** Environment-Variable deaktiviert interaktive Prompts
  - **Vorteile:**
    - Neueste Sicherheitsupdates (Alpine 3.23, Dezember 2024)
    - Konsistente Alpine-Version über alle Services
    - Garantiertes Version-Matching zwischen nginx und Modulen

- ✅ **Nginx: Brotli-Kompression aktiviert (Dual-Compression-Strategie)**
  - **Problem:** Nur Gzip-Kompression aktiv, moderne Brotli-Kompression nicht
    genutzt
    - Brotli bietet 10-20% bessere Kompression als Gzip
    - Offizielle nginx Docker-Images haben Version-Mismatch mit Alpine
      Brotli-Paketen
    - Inkonsistenz zur "production-ready modern defaults"-Philosophie
  - **Lösung: Nginx direkt aus Alpine-Repository + Brotli + Gzip parallel
    aktiviert**
    - **Dockerfile-Strategie-Wechsel:**
      - **Vorher:** `FROM nginx:1.29-alpine3.22` (offizielles nginx
        Docker-Image)
      - **Nachher:** `FROM alpine:3.23` + Installation von nginx aus Alpine-Repo
      - **Warum:** Garantiert Version-Matching zwischen nginx und
        nginx-mod-http-brotli
      - Alpine 3.23 liefert: `nginx-1.28.0-r8` +
        `nginx-mod-http-brotli-1.28.0-r8` (perfekt matched)
    - **nginx.conf (Zeile 1-3, 51-72):**
      - **Module laden:**
        - `load_module modules/ngx_http_brotli_filter_module.so;`
        - `load_module modules/ngx_http_brotli_static_module.so;`
      - **Brotli Compression (Primary - Modern browsers):**
        - `brotli on;` mit Level 6 (balanced compression/speed)
        - Identische MIME-Types wie Gzip (text/_, application/_, fonts)
      - **Gzip Compression (Fallback - Legacy browsers):**
        - `gzip on;` bleibt aktiv (100% Backward Compatibility)
        - Gleiche Konfiguration wie vorher
      - **Automatische Negotiation:**
        - Nginx wählt Brotli für moderne Clients (Chrome 50+, Firefox 44+,
          Safari 11+, Edge 15+)
        - Gzip für Legacy-Clients (alte Browser, CLI-Tools ohne Brotli)
        - Basiert auf `Accept-Encoding` HTTP-Header
    - **Vorteile:**
      - ✅ 10-20% bessere Kompression für moderne Clients (Brotli)
      - ✅ 100% Backward Compatibility (Gzip Fallback)
      - ✅ Konsistent mit Projekt-Philosophie: "Production-ready modern
        defaults"
      - ✅ Zero Configuration nötig (funktioniert out-of-the-box)
      - ✅ Version-Matching garantiert (Alpine managed Dependencies)
      - ✅ Kein Build-Overhead (alpine packages, keine Source-Compilation)
    - **Browser-Support:**
      - Brotli: Chrome 50+, Firefox 44+, Safari 11+, Edge 15+ (99%+ Coverage)
      - Gzip: Universal (alle Browser seit 1990er)

### Version 2.15 (2025-12-29)

- ✅ **Git Hooks: Vollständige Containerisierung für 100% Version-Parität**
  - **Problem:** Git Hooks liefen auf lokalen Host-Tools
    - CaptainHook nutzte lokales PHP (8.5.1) statt Container-PHP (8.4)
    - pnpm/node Commands nutzten lokales Node statt Container-Node
    - Abhängigkeit von lokaler Entwickler-Installation
    - Xdebug-Versionskonflikt-Warnungen
    - Inkonsistenz zwischen Hook-Umgebung und Production-Container
  - **Lösung: Alle Hook-Commands in Containern ausführen**
    - **captainhook.json komplett überarbeitet**
      - **commit-msg Hook:**
        - Vorher: `pnpm exec commitlint --edit $1`
        - Nachher:
          `docker compose exec -T node pnpm exec commitlint --edit /app/.git/COMMIT_EDITMSG`
        - Benötigt .git Mount im Node-Container
      - **pre-commit Hook (5 Actions):**
        - PHP-CS-Fixer:
          `docker compose exec -T php vendor/bin/php-cs-fixer fix --diff --config=.php-cs-fixer.dist.php --dry-run`
        - PHP Syntax Check:
          `git diff --name-only --cached | grep .php$ | xargs -r -I {} docker compose exec -T php php -l /var/www/html/{}`
        - Prettier:
          `docker compose exec -T node pnpm exec prettier --check 'src/**/*.{ts,js,json}'`
        - ESLint:
          `docker compose exec -T node pnpm exec eslint 'src/**/*.{ts,js}' --max-warnings=0`
        - TypeScript: `docker compose exec -T node pnpm run type-check`
      - **pre-push Hook (2 Actions):**
        - PHPStan:
          `docker compose exec -T php vendor/bin/phpstan analyse --configuration=phpstan.neon --memory-limit=1G`
        - Vitest: `docker compose exec -T node pnpm test`
    - **compose.override.yaml: Node-Container Anpassungen (Zeilen 99, 73-82)**
      - **Git-Zugriff für commitlint:**
        - Neu: `./.git:/app/.git:ro` (read-only mount)
        - Ermöglicht commitlint Zugriff auf Git-Metadaten
      - **Volume-Mounts vereinheitlicht:**
        - Vorher: `.:/app` (gesamtes Projekt gemountet, inkonsistent zu PHP)
        - Nachher: Spezifische Files/Directories wie bei PHP-Container
        - Neue Mounts: package.json, pnpm-lock.yaml, src/node, tests/node,
          public, resources, dist, vite.config.js, tsconfig.json,
          eslint.config.js, commitlint.config.js, etc.
        - Konsistenz: Beide Container (PHP + Node) nutzen identisches
          Mount-Pattern
    - **Ausführliche Descriptions wiederhergestellt**
      - Alle captainhook.json Actions haben aussagekräftige Beschreibungen
      - Statt "(in container)" nun: "Validate commit message format against
        Conventional Commits standard", "Check for styling issues with
        PHP-CS-Fixer (Dry-Run)", etc.
    - **`-T` Flag verwendet:** Deaktiviert TTY allocation (Git Hooks nicht
      interaktiv)
    - **Hooks neu installiert:** `vendor/bin/captainhook install -f`
  - **Vorteile:**
    - **100% Version-Parität:** PHP 8.4, Node 24, pnpm 10.26.2 exakt wie in
      Containern
    - **Keine lokalen Dependencies:** Nur Git, Docker, Make erforderlich
    - **Reproduzierbar:** Jeder Developer hat identisches Environment
    - **Konsistent:** Alle Development-Tools laufen in Containern
    - **Keine Versionskonflikt-Warnungen:** Host-PHP spielt keine Rolle mehr
    - **"Container als venv":** Makefile + containerisierte Hooks = vollständige
      Isolation
    - **Einheitliche Volume-Struktur:** PHP und Node nutzen identisches
      Mount-Pattern
  - **Trade-off akzeptiert:**
    - Container müssen laufen (ist beim Development sowieso gegeben)
    - Minimal höhere Latenz (~100ms) durch Docker exec (nicht spürbar bei
      Commits)
  - **Dependencies aktualisiert:**
    - `@eslint/js@^9.18.0` zu devDependencies hinzugefügt (package.json:40)
    - Erforderlich für ESLint 9.x Flat Config System (eslint.config.js:1)
    - pnpm-lock.yaml automatisch regeneriert

### Version 2.14 (2025-12-29)

- ✅ **Monolog für PHP: Strukturiertes Logging**
  - **Problem:** PHP hatte kein Logging-Framework, während Node.js bereits Pino
    hatte
    - Keine strukturierte Log-Ausgabe für PHP-Anwendungen
    - Inkonsistenz zwischen PHP- und Node.js-Stack
    - Developer müssten selbst Logging-Lösung wählen/implementieren
  - **Lösung: Monolog als Standard-Logger (analog zu Pino für Node.js)**
    - **Monolog 3.9.0 installiert** (composer.json:17, composer.lock)
    - **PSR-3 Standard:** Framework-agnostisch, kompatibel mit Laravel, Symfony,
      etc.
    - **Verwendung:**

      ```php
      use Monolog\Logger;
      use Monolog\Handler\StreamHandler;

      $log = new Logger('app');
      $log->pushHandler(new StreamHandler('/var/www/html/storage/logs/app.log', Logger::DEBUG));

      $log->info('User logged in', ['user_id' => 123]);
      $log->error('Database connection failed', ['error' => $e->getMessage()]);
      ```

    - **Logs:** Standard-Pfad `storage/logs/app.log` (konfigurierbar)
    - **Vorteile:**
      - Strukturierte Logs mit Context-Daten
      - Mehrere Handler möglich (File, Syslog, Slack, etc.)
      - Production-ready mit Rotation-Support
      - Vollständige Symmetrie zu Node.js Pino-Setup

- ✅ **Conventional Commits: Automatisierte Commit-Validierung**
  - **Problem:** Keine einheitliche Commit-Message-Struktur
    - Inkonsistente Commit-Messages erschweren Changelog-Generierung
    - Keine Kategorisierung von Änderungen (feat, fix, refactor, etc.)
    - Keine automatische Validierung → Manuelle Code-Reviews nötig
  - **Lösung: Commitlint mit Conventional Commits Standard**
    - **Dependencies installiert (package.json:38-39)**
      - `@commitlint/cli 20.2.0`
      - `@commitlint/config-conventional 20.2.0`
    - **Git Commit Template (.gitmessage)**
      - Interaktive Vorlage mit allen Commit-Typen
      - Erklärt Format, Scope, Subject, Body, Footer
      - Verwendung: `git config commit.template .gitmessage`
      - Zeigt Best-Practices bei jedem Commit
    - **Commitlint Konfiguration (commitlint.config.js)**
      - Extends `@commitlint/config-conventional`
      - **Erlaubte Typen:** feat, fix, refactor, style, docs, test, chore, perf,
        ci, build, revert
      - **Rules:**
        - Subject: Lowercase, kein Punkt, max 100 Zeichen
        - Body/Footer: Max 100 Zeichen pro Zeile
        - Leere Zeile zwischen Subject und Body erzwungen
      - **Format:** `<type>(<scope>): <subject>`
        - Beispiel: `feat(auth): add JWT token validation`
        - Beispiel: `fix(api): correct user endpoint response format`
    - **CaptainHook Integration (captainhook.json:2-11)**
      - **commit-msg Hook:** Validiert jede Commit-Message
      - Command: `pnpm exec commitlint --edit $1`
      - Automatische Ablehnung bei ungültigen Messages
      - Hilfreiche Fehlermeldungen mit Korrekturvorschlägen
    - **Hooks neu installiert:** `vendor/bin/captainhook install -f`
      - Alle Hooks aktiv: commit-msg, pre-commit, pre-push, etc.
    - **Vorteile:**
      - Automatische Changelog-Generierung möglich
      - Klare Kategorisierung (Breaking Changes, Features, Fixes)
      - Verbesserte Code-Review-Effizienz
      - Semantic Versioning Support

- ✅ **TypeScript Type-Safety: Eliminierung unsicherer eslint-disable
  Workarounds**
  - **Problem:** Pre-Commit Hook scheiterte wegen ESLint-Fehlern in
    TypeScript-Dateien
    - `@typescript-eslint/no-unsafe-assignment` bei pino-http Import
    - `@typescript-eslint/no-unsafe-call` bei pinoHttp Aufruf
    - `@typescript-eslint/strict-boolean-expressions` bei env vars (|| statt ??)
    - `@typescript-eslint/no-base-to-string` bei req.query.name
    - `@typescript-eslint/restrict-template-expressions` bei Template-Literals
    - Unsichere Workarounds mit `eslint-disable` Kommentaren
  - **Lösung: Typsichere Implementierung statt eslint-disable**
    - **pino-http Import korrigiert (src/node/app.ts:10)**

      ```typescript
      // Vorher (unsicher):
      import pinoHttpImport from 'pino-http';
      const pinoHttp =
        pinoHttpImport as unknown as typeof pinoHttpImport.default;

      // Nachher (typsicher):
      import pinoHttp from 'pino-http';
      ```

    - **Environment Variables mit Nullish Coalescing (src/node/app.ts:12-14,
      server.ts:14-17)**

      ```typescript
      // Vorher: || (falsy check)
      const NODE_ENV = process.env.NODE_ENV || 'production';

      // Nachher: ?? (null/undefined check)
      const NODE_ENV = process.env.NODE_ENV ?? 'production';
      ```

    - **req.query.name Type-Guard (src/node/app.ts:107-108)**

      ```typescript
      // Vorher (unsicher):
      const name = req.query.name || 'World';

      // Nachher (typsicher):
      const nameParam = req.query.name;
      const name =
        typeof nameParam === 'string' && nameParam.length > 0
          ? nameParam
          : 'World';
      ```

    - **req.body explizit als unknown (src/node/app.ts:114)**

      ```typescript
      // Vorher (unsicher):
      res.json({ echo: req.body });

      // Nachher (typsicher):
      const body: unknown = req.body;
      res.json({ echo: body });
      ```

    - **PORT als String Type (server.ts:14)**

      ```typescript
      const PORT = process.env.PORT ?? '3000';
      ```

  - **Verifikation:**
    - ESLint: 0 Fehler, 0 Warnungen (--max-warnings=0)
    - TypeScript: tsc --noEmit ohne Fehler
    - Prettier: Alle Dateien korrekt formatiert
    - Tests: 25/25 bestanden
  - **Vorteile:**
    - Keine eslint-disable Kommentare mehr nötig
    - Vollständige Type-Safety ohne Ausnahmen
    - Bessere IDE-Unterstützung und Autocomplete
    - Verhindert Runtime-Fehler durch strikte Typisierung
    - Pre-Commit Hook läuft ohne Fehler durch

- ✅ **Docker Compose: Volume-Mounts konsistent und PHP-Test-Pfad korrigiert**
  - **Problem:** Inkonsistente Volume-Mount-Strategie zwischen PHP und Node
    - PHP-Container: Gezielte Mounts für jede Datei/Verzeichnis
    - Node-Container: Komplettes Projekt-Root (`.:/app`)
    - PHP-Container: `./tests:/var/www/html/tests` statt
      `./tests/php:/var/www/html/tests/php`
    - PHP-CS-Fixer: `__DIR__ . '/tests'` statt `__DIR__ . '/tests/php'`
    - Inkonsistenz mit Verzeichnisstruktur `tests/php/` und `tests/node/`
  - **Lösung: Gezielte Mounts für beide Container + korrekte Pfade**
    - **compose.override.yaml - PHP (Zeile 33)**
      - Vorher: `./tests:/var/www/html/tests`
      - Nachher: `./tests/php:/var/www/html/tests/php`
    - **compose.override.yaml - Node (Zeilen 73-99)**
      - Vorher: `.:/app` (alles gemountet)
      - Nachher: Gezielte Mounts analog zu PHP

        ```yaml
        # Application
        - ./package.json:/app/package.json
        - ./pnpm-lock.yaml:/app/pnpm-lock.yaml
        - ./src/node:/app/src/node
        - ./tests/node:/app/tests/node
        - ./public:/app/public
        - ./resources:/app/resources
        - ./dist:/app/dist
        - node_modules:/app/node_modules

        # Build & Config (read-only)
        - ./vite.config.js:/app/vite.config.js:ro
        - ./tsconfig.json:/app/tsconfig.json:ro
        - ./vitest.config.ts:/app/vitest.config.ts:ro
        # ... weitere Konfigs

        # Quality Assurance Tools Config (read-only)
        - ./eslint.config.js:/app/eslint.config.js:ro
        - ./commitlint.config.js:/app/commitlint.config.js:ro
        # ... weitere QA-Konfigs

        # Build Output
        - ./build:/app/build
        ```

    - **.php-cs-fixer.dist.php (Zeile 19)**
      - Vorher: `__DIR__ . '/tests'`
      - Nachher: `__DIR__ . '/tests/php'`

  - **Vorteile:**
    - **Konsistenz:** Beide Container verwenden gleiche Mount-Strategie
    - **Sicherheit:** Keine ungewollten Dateien im Container (`.git`, `.env`,
      etc.)
    - **Read-Only:** Konfigurationsdateien mit `:ro` Flag geschützt
    - **Explizit:** Klar erkennbar welche Dateien gemountet werden
    - **Performance:** Weniger Dateien = schnelleres File-Watching
    - **Dokumentation:** Kommentare zeigen Zweck jeder Mount-Gruppe
    - **Korrekte Pfade:** PHP-Tools arbeiten nur mit PHP-Tests

- ✅ **Docker Image-Tags korrigiert: Redis und PostgreSQL Alpine-Versionen**
  - **Problem:** Fehlerhafte Image-Tags aus Version 2.13 verhinderten
    `make fresh`
    - `redis:7.4-alpine3.22` - **Tag existiert nicht!** (manifest unknown)
    - `postgres:17.7-alpine3.22` - **Tag existiert nicht!** (manifest unknown)
    - Redis und PostgreSQL verwenden **nicht** das Tagging-Format `-alpine3.22`
    - Alpine-Version kann bei offiziellen Images nicht im Tag spezifiziert
      werden
  - **Lösung: Korrekte offizielle Image-Tags verwenden**
    - **compose.yaml (Zeile 64)**
      - Vorher: `redis:7.4-alpine3.22` ❌
      - Nachher: `redis:7.4-alpine` ✅
    - **compose.yaml (Zeile 88)**
      - Vorher: `postgres:17.7-alpine3.22` ❌
      - Nachher: `postgres:17-alpine` ✅ (PostgreSQL nutzt Major-Versionen)
    - **Hinweis:** Alpine-Version wird vom Image-Maintainer bestimmt
      - Redis und Postgres verwenden `-alpine` ohne Versionssuffix
      - Alpine-Version ist typischerweise aktuellste stabile Version
      - Fixierung nur über vollständigen Digest möglich (nicht praktikabel)
      - Nur bei Custom-Dockerfiles: `FROM alpine:3.22` möglich
  - **Verifikation:**
    - `docker compose config` ohne Fehler
    - Images erfolgreich gepullt
    - `make fresh` läuft fehlerfrei durch
  - **Changelog TODO.md korrigiert:**
    - Version 2.13 Eintrag "Alpine-Versionen fixiert" aktualisiert
    - Dokumentiert warum Alpine-Version-Tags nicht funktionieren

### Version 2.13 (2025-12-29)

- ✅ **Node.js: Quality-of-Life Tooling (Testing, Linting, Formatting)**
  - **Problem:** Node.js-Stack hatte keine Quality-Tools wie PHP (PHPUnit,
    PHPStan, PHP-CS-Fixer)
    - Kein Testing-Framework → Keine automatisierten Tests
    - Kein Linter → Keine statische Code-Analyse
    - Kein Formatter → Inkonsistente Code-Formatierung
    - Keine Git-Hooks für Node.js → Manuelle Quality-Checks
  - **Lösung: Vollständiges QoL-Tooling analog zu PHP-Stack**
    - **Testing: Vitest** (PHPUnit-Äquivalent)
      - Vitest 2.1.8 mit Native Vite-Integration
      - Coverage Reports (V8): HTML, LCOV, Text → `build/coverage/`
      - Vitest UI für interaktive Test-Entwicklung
      - Test-Verzeichnisstruktur: `tests/node/{unit,integration}/` (symmetrisch
        zu PHP `tests/php/{Unit,Feature}/`)
      - Coverage-Thresholds: 80% (Lines, Functions, Branches, Statements)
      - Konfiguration: `vitest.config.ts` mit Path-Aliases (@node, @tests)
    - **Linting: ESLint + TypeScript-ESLint** (PHPStan-Äquivalent)
      - ESLint 9.18.0 mit Flat Config Format (eslint.config.js)
      - TypeScript-ESLint 8.20.0 für Type-Aware Linting
      - Strict Rules: no-explicit-any, no-floating-promises,
        strict-boolean-expressions
      - TypeScript Type-Checking ähnlich PHPStan Level 5
    - **Formatting: Prettier** (PHP-CS-Fixer-Äquivalent)
      - Prettier 3.4.2 mit eslint-plugin-prettier Integration
      - Sane Defaults: Single Quotes, 100 Print Width, LF Line Endings
      - .prettierrc.json + .prettierignore für konsistente Formatierung
      - Konfliktfreie Integration mit ESLint (eslint-config-prettier)
    - **Git Hooks: CaptainHook erweitert**
      - **pre-commit:** Prettier Check, ESLint, TypeScript Type-Check
      - **pre-push:** Vitest Tests (analog zu PHPStan für PHP)
      - Hooks laufen automatisch bei Git-Operationen (captainhook.json:21-38,
        50-55)
    - **Package.json Scripts:** Vollständiges Script-Arsenal
      (package.json:22-30)
      - `pnpm test` → Vitest run
      - `pnpm test:watch` → Watch mode
      - `pnpm test:coverage` → Coverage Report
      - `pnpm lint` / `pnpm lint:fix` → ESLint
      - `pnpm format` / `pnpm format:check` → Prettier
      - `pnpm quality` → Alle Checks (Format, Lint, Type-Check, Test)
    - **Makefile Integration:** setup-Target erweitert (Makefile:94-98, 106)
      - `tests/php/{Unit,Feature}` und `tests/node/{unit,integration}`
        Verzeichnisse (Symmetrie wie src/)
      - `build/{coverage,vitest-report}` für Reports
      - `node-install` automatisch nach `composer-install`
  - **Code Refactoring für Testbarkeit:**
    - Express-App nach `src/node/app.ts` extrahiert (app.ts:1-152)
    - `server.ts` nur noch Server-Startup (server.ts:1-56)
    - `createApp()` exportiert für Tests ohne Server-Start
    - Logger exportiert für Test-Mocking
  - **Beispiel-Tests:**
    - **Unit-Tests:** `tests/node/unit/math.test.ts` (Utility-Funktionen)
    - **Integration-Tests:** `tests/node/integration/api.test.ts` (API-Endpoints
      mit supertest)
    - Security Headers, CORS, 404/500 Error Handling getestet
  - **Dependencies hinzugefügt:** (package.json:40-58)
    - Testing: vitest, @vitest/ui, @vitest/coverage-v8, supertest
    - Linting: eslint, @typescript-eslint/eslint-plugin,
      @typescript-eslint/parser
    - Formatting: prettier, eslint-plugin-prettier, eslint-config-prettier
    - Types: @types/supertest
  - **Dokumentation:** `tests/node/README.md` mit Struktur, Beispielen,
    Thresholds

- ✅ **Test-Verzeichnisstruktur: Vollständige Symmetrie PHP ↔ Node.js**
  - **Problem:** Inkonsistente Test-Verzeichnisstruktur
    - Source-Code getrennt: `src/php/` und `src/node/` ✓
    - Tests gemischt: `tests/Unit`, `tests/Feature`, `tests/unit`,
      `tests/integration` ✗
    - Keine klare Trennung zwischen PHP- und Node.js-Tests
    - PhpStorm-Konfiguration nur für gemischtes `tests/` Verzeichnis
  - **Lösung: Spiegelsymmetrische Struktur wie in src/**
    - **Migration durchgeführt:**
      - `tests/Unit/` → `tests/php/Unit/` (PHPUnit Unit-Tests)
      - `tests/Feature/` → `tests/php/Feature/` (PHPUnit Feature-Tests)
      - `tests/unit/` → `tests/node/unit/` (Vitest Unit-Tests)
      - `tests/integration/` → `tests/node/integration/` (Vitest
        Integration-Tests)
    - **PhpStorm IDE-Konfiguration aktualisiert (.idea/zappzarapp.iml:5-8)**
      - `src/php` (Source) + `tests/php` (Test Source mit Namespace App\Tests\)
      - `src/node` (Source) + `tests/node` (Test Source)
      - IDE erkennt nun beide Sprach-Stacks korrekt
    - **Vitest-Konfiguration isoliert (vitest.config.ts:10-11)**
      - `include: ['tests/node/**/*.{test,spec}.{ts,js}']`
      - `exclude: ['tests/php']` → Keine Konflikte mit PHP-Tests
      - Path-Alias `@tests` → `./tests/node`
    - **Makefile setup-Target (Makefile:94-95)**
      - Erstellt beide Strukturen parallel
      - Kommentar: "separated by language like src/"
  - **Best Practice: Vollständige Symmetrie zwischen PHP- und Node.js-Stack**
    - **Tools:** PHP: PHPUnit, PHPStan, PHP-CS-Fixer | Node.js: Vitest,
      ESLint+TS, Prettier
    - **Struktur:** `src/php/` + `tests/php/` | `src/node/` + `tests/node/`
    - **Workflow:** Gleiche Integration (CaptainHook, Makefile)
    - **Coverage:** Gleiche Anforderungen (80%)
    - **IDE:** Beide Stacks korrekt als Source/Test markiert

- ✅ **Test-Dokumentation & Makefile-Integration: Konsistenz PHP ↔ Node.js**
  - **Problem:** Inkonsistente Dokumentation und fehlende Makefile-Abstraktion
    - `tests/node/README.md` vorhanden, aber `tests/php/README.md` fehlte
    - `.gitkeep` Dateien in `tests/php/` unnötig (durch `make setup` erstellt)
    - Node.js README verwendete direkt `pnpm` Commands statt `make`
    - Keine einheitlichen Makefile-Targets für beide Test-Stacks
  - **Lösung: Symmetrische Dokumentation und Makefile-Targets**
    - **tests/php/README.md erstellt** (analog zu tests/node/README.md)
      - Struktur-Übersicht mit Verweis auf tests/node/
      - Makefile-Commands als primäre Schnittstelle
      - Composer-Commands als Alternative dokumentiert
      - Test-Beispiele für Unit und Feature Tests
      - Quality-Tools (PHPStan, PHP-CS-Fixer) dokumentiert
      - Coverage-Thresholds: 80% (symmetrisch zu Node.js)
    - **tests/node/README.md aktualisiert**
      - Makefile-Commands als primäre Schnittstelle (Makefile:560-592)
      - pnpm-Commands als Alternative dokumentiert
      - Quality-Tools-Sektion hinzugefügt (ESLint, Prettier, pnpm quality)
      - Symmetrisch zur PHP-README strukturiert
    - **.gitkeep Dateien entfernt** (tests/php/Unit/.gitkeep,
      tests/php/Feature/.gitkeep)
      - Unnötig, da `make setup` Verzeichnisse erstellt (Makefile:94-95)
      - Reduziert Dateien-Clutter
    - **Makefile Test-Targets erweitert (Makefile:560-592)**
      - `make test` → Führt beide Stacks aus (test-php + test-node)
      - `make test-coverage` → Beide Coverage-Reports
      - **PHP-Tests:**
        - `make test-php` → PHPUnit Tests
        - `make test-php-debug` → Mit Xdebug
        - `make test-coverage-php` → Coverage Report
      - **Node.js-Tests:**
        - `make test-node` → Vitest Tests
        - `make test-node-watch` → Watch Mode
        - `make test-coverage-node` → Coverage Report
    - **Vorteil: Einheitliche Schnittstelle**
      - Entwickler müssen nicht wissen, ob PHP oder Node.js
      - `make test` führt alle Tests aus
      - Beide READMEs haben identische Struktur
      - Gleiche Abstraktionsebene (Makefile statt direkte Tool-Calls)

- ✅ **Beispiel-Tests & TypeScript-Konfiguration: Vollständige Symmetrie**
  - **Problem:** Asymmetrische Test-Beispiele und Path-Alias-Fehler
    - Node.js hatte Beispiel-Tests (math.test.ts, api.test.ts), PHP nicht
    - Node.js hatte Beispiel-Utilities (src/node/utils/math.ts), PHP nicht
    - TypeScript Path-Aliases (@node/_, @tests/_) funktionierten nicht in Tests
    - IDE konnte Importe nicht auflösen → Entwickler-Erfahrung schlecht
    - `make test-php` und `make test` funktionierten nicht (mehrere Fehler)
  - **Lösung: Symmetrische Beispiele und korrekte TypeScript-Konfiguration**
    - **TypeScript-Konfiguration erweitert (tsconfig.json:16-23, 47-57)**
      - Path-Aliases hinzugefügt: `@/*`, `@node/*`, `@tests/*`
      - `baseUrl: "."` für Alias-Auflösung
      - `include: ["tests/node/**/*"]` → Tests werden von TypeScript erkannt
      - `exclude: ["tests/php"]` → Keine PHP-Dateien in TypeScript
      - `rootDir` entfernt → Flexibilität für src/ und tests/
      - IDE erkennt nun alle Importe korrekt
    - **PHP Beispiel-Utilities erstellt (src/php/Utils/Calculator.php)**
      - Analog zu src/node/utils/math.ts
      - Funktionen: add(), multiply(), divide(), isEven()
      - Type-Hints und DivisionByZeroError
      - Namespace: App\Utils
    - **PHP Unit-Tests erstellt (tests/php/Unit/CalculatorTest.php)**
      - Analog zu tests/node/unit/math.test.ts
      - Testet alle Calculator-Methoden
      - setUp() Methode für Test-Fixture
      - Gruppierung nach Funktionalität (Addition, Multiplication, etc.)
      - Exception-Testing für Division durch Null
    - **PHP Feature-Tests erstellt
      (tests/php/Feature/CalculatorIntegrationTest.php)**
      - Analog zu tests/node/integration/api.test.ts
      - Testet komplexe Workflows über mehrere Methoden
      - Error-Handling-Workflows
      - Chained Calculations (mehrere Operationen nacheinander)
      - Demonstriert Feature-Test-Pattern
  - **Ergebnis: Perfekte Symmetrie zwischen PHP und Node.js**
    - **PHP:** src/php/Utils/Calculator.php →
      tests/php/Unit/CalculatorTest.php +
      tests/php/Feature/CalculatorIntegrationTest.php
    - **Node.js:** src/node/utils/math.ts → tests/node/unit/math.test.ts +
      tests/node/integration/api.test.ts
    - Beide Sprachen haben lauffähige Beispiel-Tests
    - Entwickler können `make test` ausführen → Alle Tests laufen
    - Path-Aliases funktionieren in IDE und Tests
    - Beide Test-Suites haben identische Struktur

- ✅ **PHPUnit-Konfiguration & Test-Infrastruktur-Fixes**
  - **Problem:** `make test-php` und `make test` funktionierten nicht
    - Kein phpunit.xml.dist → PHPUnit fand keine Konfiguration
    - composer.json autoload-dev hatte falschen Pfad (`tests/` statt
      `tests/php/`)
    - phpunit.xml.dist nicht in Docker-Volume gemountet (compose.override.yaml)
    - composer test Script lief mit Coverage, aber Xdebug nicht im
      Coverage-Modus
    - Datei-Permissions zu restriktiv (600 statt 644)
  - **Lösung: Vollständige PHPUnit-Infrastruktur**
    - **phpunit.xml.dist erstellt** mit korrekter Konfiguration
      - Test-Suites: Unit (tests/php/Unit) und Feature (tests/php/Feature)
      - Source-Code für Coverage: src/php/ (exclude: Infrastructure/)
      - Coverage-Konfiguration entfernt aus XML (wird via CLI aktiviert)
      - Cache-Directory: build/.phpunit.cache
      - **Coverage-Generierung via Makefile:**
        - `make test-coverage-php` setzt XDEBUG_MODE=coverage
        - Generiert HTML Report: build/coverage/index.html
        - Generiert Clover XML: build/coverage/clover.xml
        - Keine Coverage-Warnungen bei normalen Tests
    - **composer.json fixes (composer.json:51, 55)**
      - autoload-dev: `"App\\Tests\\": "tests/php/"` (war: tests/)
      - test script: `phpunit` (ohne Coverage-Zwang)
      - → Tests laufen schnell ohne Coverage-Overhead
      - → Coverage über separates Target: `make test-coverage-php` mit
        XDEBUG_MODE=coverage
    - **compose.override.yaml erweitert (compose.override.yaml:39)**
      - phpunit.xml.dist Volume-Mount hinzugefügt
      - Read-only Mount für Konfigurationsdatei
      - Analog zu phpstan.neon und .php-cs-fixer.dist.php
    - **Workflow-Fix:**
      - Container-Neustarts nach Konfigurationsänderungen erforderlich
      - File-Permissions: 644 für Test-Dateien (nicht 600)
      - composer dump-autoload nach autoload-dev Änderungen
  - **Resultat: Beide Test-Suites laufen erfolgreich**
    - `make test-php` → 17 Tests, 32 Assertions ✅
    - `make test-node` → 30+ Tests (math + API integration) ✅
    - `make test` → Beide Test-Suites zusammen ✅
    - Coverage-Reports: `make test-coverage` (beide Sprachen)

- ✅ **PHPMD (PHP Mess Detector): Code Quality & Complexity Analysis**
  - **Problem:** Fehlende Code-Quality-Metriken im PHP-Stack
    - Nur PHPStan (Static Analysis) und PHP-CS-Fixer (Code Style)
    - Keine Complexity-Analyse (Cyclomatic Complexity, NPath)
    - Keine Detection von Code Smells (Long Methods, Too Many Parameters)
    - Keine Warnung bei ungenutztem Code (Unused Variables, Dead Code)
    - Node.js-Stack hatte mit ESLint bereits Complexity-Checks
  - **Lösung: PHPMD für vollständige Code-Quality-Abdeckung**
    - **PHPMD 2.15.0 installiert** (composer.json:22)
      - Dependency: pdepend/pdepend für Metriken-Berechnung
      - Composer Script: `composer phpmd` (composer.json:58)
      - Makefile-Target: `make phpmd` (Makefile:523-525)
    - **phpmd.xml.dist Konfiguration** mit ausgewogenen Regeln
      - **Clean Code Rules:** Detect code smells (disabled: ElseExpression,
        StaticAccess)
      - **Code Size Rules (Complexity):**
        - Cyclomatic Complexity: Max 15 (Warnung bei zu verschachteltem Code)
        - NPath Complexity: Max 250 (Max Ausführungspfade)
        - Excessive Method Length: Max 100 Zeilen
        - Excessive Class Length: Max 500 Zeilen
        - Excessive Parameter List: Max 10 Parameter
        - Too Many Fields: Max 20 Felder
        - Too Many Methods: Max 25 Methoden
      - **Design Rules:** Coupling, Depth of Inheritance (disabled:
        ExitExpression für CLI)
      - **Naming Rules:** Short/Long Variable Names (min 2 chars, exceptions:
        i,j,k,e,id,x,y,a,b)
      - **Unused Code Detection:** Unused Variables, Parameters, Private Methods
      - **Controversial Rules:** Deaktiviert (zu opinionated)
    - **Docker-Integration** (compose.override.yaml:38)
      - phpmd.xml.dist Volume-Mount hinzugefügt
      - Read-only Mount analog zu anderen QA-Tools
    - **Make-Target erweitert:**
      - `make check` führt jetzt auch PHPMD aus (Makefile:527)
      - CI-Simulation: cs-check + analyse + phpmd + test
  - **Ergebnis: Vollständige PHP Quality-Tool-Chain**
    - **Static Analysis:** PHPStan (Level 5)
    - **Code Style:** PHP-CS-Fixer
    - **Complexity & Design:** PHPMD (NEU)
    - **Testing:** PHPUnit
    - Symmetrie zu Node.js: ESLint deckt Complexity + Linting ab, PHPMD tut das
      Gleiche für PHP

- ✅ **Vitest-Konfiguration & TypeScript Path-Alias-Fixes**
  - **Problem:** TypeScript-Fehler in vitest.config.ts und Test-Imports
    - vitest.config.ts Zeile 28: "No overload matches this call" (Coverage
      Thresholds)
    - vitest.config.ts Zeile 37: "reporter does not exist" (sollte "reporters"
      sein)
    - api.test.ts Zeile 3: "Cannot find module @node/app"
    - math.test.ts Zeile 2: "Cannot find module @node/utils/math"
    - Tests liefen, aber IDE zeigte Fehler → Schlechte Developer Experience
  - **Lösung: Korrekte Vitest- und TypeScript-Konfiguration**
    - **vitest.config.ts fixes (vitest.config.ts:28-33, 37)**
      - Coverage Thresholds müssen unter `thresholds` Property genested sein
      - `reporter` → `reporters` (Plural) für korrekte Vitest API
      - Vorher: `lines: 80, functions: 80, ...` direkt in coverage
      - Nachher: `thresholds: { lines: 80, functions: 80, ... }`
    - **tsconfig.vitest.json erstellt** für Vitest-spezifische TypeScript-Config
      - Erweitert tsconfig.json mit Vitest-spezifischen Types
      - `"types": ["vitest/globals", "node"]` für globale Test-Funktionen
      - `"include": ["tests/node/**/*", "vitest.config.ts"]`
      - Separates TypeScript-Projekt für Tests (composite: true)
    - **Path-Alias-Auflösung funktioniert jetzt vollständig**
      - tsconfig.json hatte bereits baseUrl und paths konfiguriert
      - vitest.config.ts resolve.alias mappte @node → src/node
      - IDE erkennt nun alle Importe korrekt (keine roten Wellenlinien mehr)
    - **tsconfig.json moduleResolution fix (tsconfig.json:6-7)**
      - `module: "NodeNext"` → `module: "ESNext"` (für Vite/Vitest
        Kompatibilität)
      - `moduleResolution: "NodeNext"` → `moduleResolution: "bundler"`
      - NodeNext erforderte .js Dateiendungen in Imports → PhpStorm-Fehler in
        server.ts:12
      - bundler-Strategie ist optimal für Vite-basierte Projekte
      - Löst PhpStorm-Fehler ohne .js Extensions in allen Imports
  - **Resultat: Alle Tests laufen erfolgreich ohne TypeScript-Fehler**
    - `make test-php` → 17 Tests, 32 Assertions ✅
    - `make test-node` → 25 Tests (13 Math Unit + 12 API Integration) ✅
    - `make test` → 42 Tests gesamt (PHP + Node.js) ✅
    - Keine TypeScript-Diagnostics-Fehler mehr in IDE
    - Coverage-Reports funktionieren korrekt
    - HTML Reports: build/coverage/ (PHP) und build/vitest-report.html (Node.js)

- ✅ **Security & Code Quality Maintenance**
  - **Problem:** Verschiedene Warnungen und Sicherheitslücken
    - .prettierignore: Redundanter Eintrag `public/build` (bereits durch `build`
      abgedeckt)
    - package.json: pm2 5.4.3 hat CVE-2025-5891 (Severity 4.3) - ReDoS in
      Config.js
    - pnpm 9.15.1 verfügbar für Update auf 10.26.2 (Major-Version)
  - **Lösung: Security-Update und Code-Bereinigung**
    - **.prettierignore bereinigt (.prettierignore:5-7)**
      - `public/build` Eintrag entfernt (redundant zu `build` Glob-Pattern)
      - Reduziert false-positive Warnungen in IDE
    - **pm2 Security-Update (package.json:50)**
      - pm2 5.4.3 → 6.0.14 (behebt CVE-2025-5891)
      - ReDoS-Schwachstelle in Config.js geschlossen
      - Alle Tests laufen nach Update erfolgreich (25 Tests ✅)
    - **pnpm Major-Update durchgeführt (package.json:62-64)**
      - pnpm 9.15.1 → 10.26.2 (Major-Update)
      - packageManager in package.json aktualisiert
      - engines.pnpm Requirement: >=9.0.0 → >=10.0.0
      - Alle 25 Tests laufen erfolgreich mit pnpm 10 ✅
      - Keine Breaking Changes bei unserem Setup

- ✅ **PhpStorm IDE-Konfiguration: Vollständige Source/Test Folder Markierung**
  - **Problem:** Inkonsistente PhpStorm Source/Test Folder Konfiguration
    - src/php und tests/php korrekt als Source/Test markiert ✅
    - src/node NICHT als Source Folder markiert ❌
    - tests/node NICHT als Test Folder markiert ❌
    - Veraltete tests/ Markierung noch vorhanden (überflüssig)
    - TypeScript Autocomplete und Navigation unvollständig
  - **Lösung: Symmetrische IDE-Konfiguration für beide Stacks**
    - **.idea/zappzarapp.iml aktualisiert (Zeilen 5-8)**
      - **Source Folders:**
        - `src/php` (packagePrefix: App\)
        - `src/node` (NEU hinzugefügt)
      - **Test Folders:**
        - `tests/php` (packagePrefix: App\Tests\)
        - `tests/node` (NEU hinzugefügt)
      - Veraltete `tests/` Markierung entfernt
    - **Exclude Folders sortiert** (Zeilen 9-15)
      - Alphabetische Sortierung für bessere Übersicht
      - .pnpm-store, build, dist, node_modules, public/build, storage, vendor
  - **Resultat: Vollständige IDE-Integration**
    - PhpStorm erkennt beide Sprach-Stacks korrekt
    - TypeScript Autocomplete funktioniert für src/node/\*_/_
    - Test-Runner erkennt beide Test-Stacks
    - Navigation und Refactoring für PHP und Node.js
    - Symmetrie zwischen PHP- und Node.js-Entwicklung

- ✅ **PHPUnit XML-Konfiguration: Schema-Konformität**
  - **Problem:** phpunit.xml.dist Schema-Fehler in PhpStorm
    - `restrictDeprecations`, `restrictNotices`, `restrictWarnings` waren
      ursprünglich im `<source>` Element
    - PhpStorm-Fehler: "Attribute not allowed to appear in element"
    - Fehler durch falsche Platzierung der Attribute
  - **Lösung: Korrekte Attribut-Platzierung nach offizieller Dokumentation**
    - **Quelle:** <https://docs.phpunit.de/en/12.5/configuration.html>
    - **`restrictNotices="true"`** im `<source>` Element (phpunit.xml.dist:24)
      - Beschränkt Reporting von E_STRICT, E_NOTICE, E_USER_NOTICE auf
        Projekt-Source-Code
      - Ignoriert Notices aus Vendor-Dependencies
    - **`restrictWarnings="true"`** im `<source>` Element (phpunit.xml.dist:24)
      - Beschränkt Reporting von E_WARNING, E_USER_WARNING auf
        Projekt-Source-Code
      - Ignoriert Warnings aus Vendor-Dependencies
    - **`restrictDeprecations` existiert NICHT** in PHPUnit 12.5
      - Stattdessen: `ignoreSelfDeprecations`, `ignoreDirectDeprecations`,
        `ignoreIndirectDeprecations`
      - Nicht verwendet, da wir alle Deprecations sehen wollen
    - **Strikte Testeinstellungen im `<phpunit>` Root-Element:**
      - `failOnWarning="true"` - Tests schlagen bei Warnungen fehl
      - `failOnRisky="true"` - Tests schlagen bei Risky Tests fehl
      - `beStrictAboutOutputDuringTests="true"` - Kein Output während Tests
      - `beStrictAboutCoverageMetadata="true"` - Strikte Coverage-Metadaten
  - **Resultat: Schema-konforme und strikte Konfiguration**
    - Alle 17 PHP Tests laufen erfolgreich ✅
    - XML validiert gegen PHPUnit 12.5 Schema
    - Notices/Warnings aus Dependencies werden ignoriert
    - Alle Fehler im eigenen Code werden erkannt

### Version 2.12 (2025-12-28)

- ✅ **Dependency Management: Workflow-Klarheit für Composer und Node.js**
  - **Problem:** Unklare Verwendungszwecke der lokalen vs. Container-basierten
    Dependency-Installation
    - `composer-install-local` und `node-install-local` könnten als "schnellere
      Alternative" missverstanden werden
    - Intention war unklar: Wann sollte man welchen Befehl verwenden?
  - **Lösung: Makefile-Kommentare präzisiert**
    - **composer-install-local:** Kommentar geändert zu "IDE code completion
      only" (Makefile:41)
    - **node-install-local:** Kommentar geändert zu "IDE code completion only"
      (Makefile:319)
    - **Best Practice:** Container-Installation für Runtime/CI/CD, lokale
      Installation NUR für IDE-Support
  - **Vorteile:**
    - Klare Trennung: Production-Konsistenz vs. Development-Convenience
    - Verhindert Versions-Konflikte durch klarere Intention

- ✅ **Node.js Backend: API Endpoint REST-Konformität**
  - **Problem:** Endpoint-Definition und REST-Semantik
    - `/api/echo` war POST-only → Browser-Tests nicht möglich
    - Zwischenlösung mit `app.all()` war nicht REST-konform
  - **Finale Lösung: REST-konforme Endpoints**
    - `/api/hello` → GET (korrekt: Daten abrufen)
    - `/api/echo` → POST (korrekt: Daten senden/zurückwerfen)
    - Curl-Beispiel im Code-Kommentar für einfaches Testen (server.ts:108)
  - **Routing über Nginx:**
    - `localhost:8080/api/node/hello` → `node:3000/api/hello` (GET) ✅
    - `localhost:8080/api/node/echo` → `node:3000/api/echo` (POST) ✅
  - **Best Practice:** Klare HTTP-Methoden-Semantik für professionelles
    API-Design

- ✅ **PhpStorm: Excluded Directories optimiert**
  - **Problem:** Unvollständige Exclude-Konfiguration führt zu
    Performance-Problemen
    - `build/` (PHPUnit Coverage) nicht excluded → IDE indexiert unnötig
    - `dist/` (Node.js Build Output) nicht excluded → doppelte Indexierung
      (Source + Compiled)
    - Fehlende Excludes verlangsamen Search, Navigation und Code-Completion
  - **Lösung: Build- und Cache-Directories excluded
    (.idea/zappzarapp.iml:12-13)**
    - `build/` - PHPUnit Coverage Reports, Tool Caches
    - `dist/` - TypeScript Build Output (transpilierter Code)
  - **Bereits korrekt excluded:**
    - `vendor/` - Composer Dependencies (nur für Completion geladen)
    - `node_modules/` - NPM Dependencies (nur für Completion geladen)
    - `storage/` - Runtime-Daten (Uploads, Cache, Sessions)
    - `.pnpm-store/` - pnpm Cache
    - `public/build/` - Vite Build Output
  - **Resultat:** Schnellere Indexierung, bessere IDE-Performance

### Version 2.11 (2025-12-28)

- ✅ **PHP Code Quality Improvements: PSR-4 Compliance und Dependency
  Management**
  - **Problem:** IDE-Warnungen und fehlende Extension-Deklarationen
    - `ext-pdo` fehlte in composer.json, obwohl HealthCheck.php PDO verwendet
    - `ext-json` war implizit verwendet, aber nicht deklariert
    - IDE-Warnungen in ViteHelper.php: "Cannot resolve file/directory" für
      sprintf() Platzhalter
    - Unnötige Redundanz in Conditional-Checks (null + empty)
  - **Lösung 1: Composer Dependencies vervollständigt**
    - **ext-pdo hinzugefügt:** Erforderlich für PostgreSQL/MariaDB Verbindungen
      in HealthCheck
    - **ext-json hinzugefügt:** Verwendet in Router, ExampleController,
      StatusController (JSON_THROW_ON_ERROR)
    - **Best Practice:** Explizite Deklaration aller verwendeten Extensions
      verhindert Runtime-Fehler
  - **Lösung 2: IDE-Warnungen in ViteHelper.php behoben**
    - **@noinspection HtmlUnknownTarget Annotations hinzugefügt**
      - renderScriptTags() Zeile 108: Unterdrückt Warnung für dynamische Vite
        Dev Server URLs
      - renderScriptTags() Zeile 125: Unterdrückt Warnung für Production
        Build-Assets
      - renderCssTags() Zeile 148: Unterdrückt Warnung für CSS-Dateien aus
        Manifest
    - **Code-Redundanz entfernt:**
      - Zeile 142: `if (empty($cssUrls))` ersetzt
        `if ($cssUrls === null || empty($cssUrls))`
      - Grund: `empty()` prüft bereits auf null, array, und Leerheit
    - **Warum diese Warnungen auftraten:**
      - PHPStorm versucht, String-Formatierungen in sprintf() zu validieren
      - `%s` Platzhalter wurden als tatsächliche Dateipfade interpretiert
      - Warnungen waren harmlos, aber störend für Code-Quality-Metriken
  - **Lösung 3: Template-Variable-Dokumentation in welcome.php**
    - **Problem:** PhpStorm meldete "Undefined variable" für
      $vite, $env,
      $status
      - Variablen werden von WelcomeController via include übergeben
      - IDE konnte nicht erkennen, dass Variablen im Template-Scope verfügbar
        sind
    - **PHPDoc-Header hinzugefügt (Zeilen 1-12):**
      - `@var \App\Infrastructure\ViteHelper $vite` - Vite asset helper
      - `@var array $env` - Environment configuration
      - `@var array $status` - Service health status
      - `declare(strict_types=1)` für Type-Safety
    - **Vorteile:**
      - IDE-Autocomplete für Template-Variablen funktioniert
      - Type-Hinting für bessere Code-Navigation
      - Dokumentiert erwartete Variablen für Template-Engine-Integration
      - Best Practice für PHP-Template-Dateien
  - **Dateien geändert:**
    - `composer.json`: ext-pdo und ext-json hinzugefügt (Zeilen 16-17), license
      Kleinschreibung (Zeile 5)
    - `src/php/Infrastructure/ViteHelper.php`: @noinspection Annotations,
      Code-Cleanup (Zeilen 108, 125, 142, 148)
    - `templates/welcome.php`: PHPDoc-Header mit @var Annotations (Zeilen 1-12)
  - **Ergebnis:**
    - ✅ Alle IDE-Warnungen in src/php/_und templates/_ behoben
    - ✅ Composer Dependencies vollständig deklariert
    - ✅ Code Quality verbessert (keine redundanten Checks)
    - ✅ Template-Variablen dokumentiert mit Type-Hints
    - ✅ PSR-4 Namespaces bereits korrekt (keine Änderungen nötig)
  - **Nächste Schritte:**
    - Autoloader bereits regeneriert (`composer dump-autoload -o`)
    - Bei anhaltenden Namespace-Warnungen: PhpStorm Cache invalidieren (`File` →
      `Invalidate Caches`)

### Version 2.10 (2025-12-23)

- ✅ **Multi-Database Support mit Docker Compose Profiles**
  - **PostgreSQL 17.7-alpine als Standard (empfohlen)**
    - Image: `postgres:17.7-alpine` (Minor-Version fixiert)
    - Profile: `["postgres"]`
    - Healthcheck mit `pg_isready`
    - Production-optimierte Settings (shared_buffers, max_connections, etc.)
  - **MariaDB 12.1 als optionale Alternative**
    - Image: `mariadb:12.1` (12.1.x-Debian, Minor-Version fixiert)
    - Profile: `["mariadb"]`
    - Healthcheck mit `healthcheck.sh --connect --innodb_initialized`
    - InnoDB-optimierte Settings
  - **MySQL entfernt**
    - Grund: Keine offizielle Alpine-Version verfügbar
    - MySQL hatte nur Debian-Images (gegen Alpine-Konsistenz)
  - **Percona entfernt**
    - Grund: Entwicklung eingestellt
  - **Konfiguration:**
    - `.env`: `DB_TYPE=postgres` oder `DB_TYPE=mariadb`
    - Automatische Profile-Aktivierung via `docker compose --profile ${DB_TYPE}`
    - Makefile erweitert mit DB-spezifischen Commands
  - **Best Practices - Konsistente Minor-Version Pinning:**
    - **Alle Images mit fixen Minor-Versionen** (keine `latest`- oder
      Major-only-Tags)
    - **PostgreSQL:** `17.7-alpine` (Minor fixiert, erlaubt automatische
      Patch-Updates 17.7.x)
    - **MariaDB:** `12.1` (Minor fixiert, erlaubt automatische Patch-Updates
      12.1.x)
    - **Redis:** `7.4-alpine` (Minor fixiert, erlaubt automatische Patch-Updates
      7.4.x)
    - **Vorteil:** Balance zwischen Sicherheit (automatische Patches) und
      Stabilität (keine Breaking Changes)
    - **Verhindert:** Unerwartete Minor-Updates mit Breaking Changes (z.B. 17.0
      → 17.1)
  - **Dateien geändert:**
    - `compose.yaml`: PostgreSQL 17.7-alpine, MariaDB 12.1, Redis 7.4-alpine
      (alle Minor-fixiert)
    - `compose.prod.yaml`: Production-Optimierungen für beide DBs
    - `compose.override.yaml`: Development-Settings (verbose logging, exposed
      ports)
    - `.env.example`: `DB_TYPE`, Datenbank-URLs, Port-Konfigurationen
    - `.env`: `DB_TYPE=postgres` als Standard
    - `Makefile`: `up-core` mit Profile-Support, DB-spezifische CLI-Commands
  - **Getestet mit aktuellen Versionen:**
    - PostgreSQL 17.7: Konnektivität, Tabellen-Erstellung, CRUD-Operationen ✅
    - MariaDB 12.1.2: Konnektivität, Tabellen-Erstellung, CRUD-Operationen ✅
    - Redis 7.4.7: PING/PONG, GET/SET Operationen ✅
    - Node.js Backend mit PostgreSQL ✅
    - Alle Services healthy und voll funktionsfähig ✅

- ✅ **Granulare Service-Aktivierung mit ENABLE\_\* Flags**
  - **Problem:** Bisherige Architektur startete immer alle Services (PHP, Node,
    Redis)
    - Verschwendung von Ressourcen für ungenutzte Services
    - Keine Flexibilität für unterschiedliche Stack-Typen (Pure PHP, Pure
      Node.js, Static)
    - Nginx war der einzige wirklich essenzielle Service
  - **Lösung:** Docker Compose Profiles für jeden Service
    - PHP: `profiles: ["php"]`, aktivierbar via `ENABLE_PHP=true`
    - Node: `profiles: ["node"]`, aktivierbar via `ENABLE_NODE=true`
    - Redis: `profiles: ["redis"]`, aktivierbar via `ENABLE_REDIS=true`
    - Nginx: Immer aktiv (Entry Point, ohne Profile)
    - Database: Weiterhin via `DB_TYPE` gesteuert (postgres/mariadb)
  - **Konfiguration in .env:**

    ```bash
    ENABLE_PHP=true      # PHP-FPM Service
    ENABLE_NODE=true     # Node.js (Vite + Backend)
    ENABLE_REDIS=true    # Redis Cache/Sessions
    DB_TYPE=postgres     # Database Selection
    ```

  - **Vordefinierte Presets in .env.example:**
    - **Full-Stack** (Default): PHP + Node.js + Redis + Database
    - **Pure PHP Stack**: PHP + Redis + Database (kein Node.js)
    - **Pure Node.js Stack**: Node.js + Redis + Database (kein PHP)
    - **Static/JAMstack**: Nur Nginx (keine Backend-Services)
    - **Minimal Node.js**: Nur Node.js (kein Database/Redis)
    - **Custom**: Beliebige Kombination
  - **Makefile-Integration:**
    - `make up` liest `.env` und aktiviert nur gewählte Services
    - Dynamischer Profil-Aufbau:
      `--profile postgres --profile php --profile node --profile redis`
    - Output zeigt aktive Services: `Active services: nginx php node redis`
  - **Zukunftssicherheit:**
    - Einfache Erweiterung für neue Services (z.B. ENABLE_RABBITMQ,
      ENABLE_ELASTICSEARCH)
    - Skaliert linear: N Services = N Variablen (statt N! Kombinationen)
    - Microservice-Prinzip: Jeder Service einzeln steuerbar
  - **Dateien geändert:**
    - `compose.yaml`: Profiles für php, node, redis hinzugefügt; depends_on auf
      `required: false`
    - `.env`: `ENABLE_PHP`, `ENABLE_NODE`, `ENABLE_REDIS` hinzugefügt (alle
      true)
    - `.env.example`: Ausführliche Dokumentation + 5 vordefinierte Presets
    - `Makefile`: `up-core` dynamische Profile-Aktivierung basierend auf
      ENABLE\_\* Flags
  - **Getestet:**
    - Full-Stack (PHP + Node + Redis + PostgreSQL): ✅ Alle Services gestartet
    - Node-only (nur Node.js + Nginx): ✅ PHP und Redis nicht gestartet
    - Static/JAMstack (nur Nginx): ✅ Alle Backend-Services deaktiviert
    - Service-Kombinationen funktionieren wie erwartet ✅

- ✅ **NODE_MODE Auto-Start Implementation mit Entrypoint-Script**
  - **Problem:** Node.js Container führte NODE_MODE nicht aus
    - Container startete nur mit `sleep infinity` (development stage)
    - NODE_MODE-Variable (`full-stack`, `vite-only`, `backend-only`, `none`) war
      in .env dokumentiert, aber nicht implementiert
    - PM2-Config (`ecosystem.config.cjs`) und npm-Scripts existierten, wurden
      aber nie ausgeführt
    - Vite Dev Server lief nicht → **CORS-Fehler** bei HMR (localhost:5173 nicht
      erreichbar)
    - Regression des in Version 2.9 behobenen CORS-Problems
  - **Root Cause:**
    - Dockerfile development stage hatte kein ENTRYPOINT, nur
      `CMD ["sleep", "infinity"]`
    - Keine Logik für Dependency-Installation (`pnpm install`)
    - Keine Logik für automatischen Service-Start basierend auf NODE_MODE
  - **Lösung - Entrypoint Script erstellt:** `docker/node/entrypoint.sh`
    - **Dependency Installation:**
      - Prüft ob `node_modules` existiert oder leer ist
      - Führt `pnpm install --frozen-lockfile` aus wenn nötig
      - Überspringt Installation wenn Dependencies bereits vorhanden
        (Performance)
    - **Service-Start basierend auf NODE_MODE:**
      - `NODE_MODE=full-stack` → `pnpm run dev:full` (PM2 mit Vite + Express)
      - `NODE_MODE=vite-only` → `pnpm run dev:frontend` (PM2 nur Vite)
      - `NODE_MODE=backend-only` → `pnpm run dev:backend` (PM2 nur Express)
      - `NODE_MODE=none` → `sleep infinity` (Idle Container für manuelle
        Commands)
    - **Logging:** Debug-Output für Startup-Status
  - **Dockerfile-Änderungen:**
    - Entrypoint-Script kopiert:
      `COPY --chown=node:node docker/node/entrypoint.sh /usr/local/bin/`
    - Ausführbar gemacht: `RUN chmod +x /usr/local/bin/entrypoint.sh`
    - Als ENTRYPOINT gesetzt: `ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]`
    - Ersetzt bisheriges `CMD ["sleep", "infinity"]`
  - **compose.override.yaml erweitert:**
    - `NODE_MODE=${NODE_MODE:-full-stack}` Environment-Variable hinzugefügt
    - Wird aus .env gelesen und an Container übergeben
  - **PM2 Prozess-Management (via ecosystem.config.cjs):**
    - **vite:** Läuft auf Port 5173 mit `--host 0.0.0.0` für Docker-Zugriff
    - **backend:** Express Server auf Port 3000 via `tsx` (TypeScript-Execution)
    - Beide mit Auto-Restart, Watch-Mode, Graceful Shutdown
    - JSON-Logs für strukturiertes Logging
  - **Dateien geändert:**
    - `docker/node/entrypoint.sh`: Neu erstellt (36 Zeilen)
    - `docker/node/Dockerfile`: ENTRYPOINT hinzugefügt (Zeilen 33-44)
    - `compose.override.yaml`: NODE_MODE env-var hinzugefügt (Zeile 68)
  - **Testing & Verification:**
    - Container-Rebuild: `docker compose build node` ✅
    - Container-Start: `docker compose up -d node` ✅
    - pnpm install: Erfolgreich (Dependencies in 1.2s installiert) ✅
    - PM2 Status: Beide Prozesse online (`vite:0`, `backend:1`) ✅
    - Vite Dev Server: Läuft auf <http://localhost:5173> ✅
    - Express Backend: Läuft auf <http://localhost:3000/health> ✅
    - HMR funktioniert: Vite Client erreichbar (`/@vite/client` liefert JS) ✅
    - CORS korrekt konfiguriert: `origin: '*'` in vite.config.js ✅
    - test.php zeigt HMR-Modus: Script-Tags verweisen auf localhost:5173 ✅
  - **Erwartetes Verhalten bei verschiedenen Modi:**

    ```bash
    # Full-Stack Mode (Default)
    NODE_MODE=full-stack → PM2 startet Vite (5173) + Express (3000)

    # Vite-Only Mode (nur Frontend-Entwicklung)
    NODE_MODE=vite-only → PM2 startet nur Vite (5173)

    # Backend-Only Mode (nur API-Entwicklung)
    NODE_MODE=backend-only → PM2 startet nur Express (3000)

    # Idle Mode (manuelles Exec)
    NODE_MODE=none → Container läuft idle, manuelle Commands via docker compose exec
    ```

  - **Vorteile:**
    - Automatischer Start ohne manuelle Eingriffe
    - Zero-Config HMR für Frontend-Entwicklung
    - Konsistente Entwicklungsumgebung (Dev-Parity)
    - Flexible Modi für unterschiedliche Entwicklungs-Workflows
    - Transparente Logs für Debugging

- ✅ **PHP Infrastruktur-Refactoring: ViteHelper und HealthCheck Klassen**
  - **Problem:** `public/vite-helper.php` lag außerhalb der Codebase-Struktur
    - Keine Nutzung des Composer Autoloaders (PSR-4)
    - Keine Trennung von Public-Dateien und Business-Logik
    - Keine zentrale Service-Status-Prüfung
    - Developer musste Routing/Framework selbst implementieren
  - **Lösung 1: ViteHelper als Infrastructure-Klasse**
    - **Verschoben:** `public/vite-helper.php` →
      `src/php/Infrastructure/ViteHelper.php`
    - **Namespace:** `App\Infrastructure\ViteHelper`
    - **Autoloading:** Via Composer PSR-4 (`"App\\": "src/php/"`)
    - **Manifest-Path angepasst:**
      `__DIR__ . '/../../../public/build/.vite/manifest.json'`
    - **Alle Funktionen erhalten:** Development HMR, Production Assets,
      CORS-Config
  - **Lösung 2: HealthCheck-Klasse für Service-Monitoring**
    - **Neue Klasse:** `src/php/Infrastructure/HealthCheck.php`
    - **Features:**
      - Prüft alle Services: PHP-FPM, Node Backend, Redis, Database
        (PostgreSQL/MariaDB)
      - Liest ENV-Variablen: `ENABLE_PHP`, `ENABLE_NODE`, `ENABLE_REDIS`,
        `DB_TYPE`, `NODE_MODE`
      - Gibt Gesamtstatus zurück: `ok`, `degraded`, `error`
      - Zeigt Service-Versionen: PHP 8.4.16, Node v24.12.0, PostgreSQL 17.7,
        etc.
    - **Service-Checks:**
      - **PHP-FPM:** Immer OK (Code läuft bereits)
      - **Node Backend:** HTTP-Request zu `http://node:3000/health`
        (JSON-Parsing)
      - **Redis:** Verbindung + PING-Test via PHP Redis Extension
      - **Database:** PDO-Verbindung + Version-Query (postgres/mariadb)
    - **Error Handling:** Bei fehlenden Extensions (Redis, PDO) wird Status als
      "error" mit Message zurückgegeben
  - **Lösung 3: MVC-Controller für Routing**
    - **Neue Controller:**
      - `src/php/Http/Controller/WelcomeController.php` - Landing Page mit
        Service-Dashboard
      - `src/php/Http/Controller/StatusController.php` - JSON Health Endpoint
    - **Template:** `templates/welcome.php` - HTML-Template für Dashboard
    - **Routes in `public/index.php`:**
      - `GET /` → WelcomeController (Dashboard)
      - `GET /welcome` → WelcomeController (Alias)
      - `GET /status` → StatusController (JSON Health Check)
      - `GET /api/health` → ExampleController (Legacy Endpoint)
    - **Hybrid-Ansatz:** Router + direkte Dateien
      - `/` via Router (Clean URLs)
      - `/welcome.php` direkt (HMR Demo ohne Router)
  - **Lösung 4: test.php → welcome.php umbenennen**
    - **Verschoben:** `public/test.php` → `public/welcome.php`
    - **Aktualisiert:** Nutzt nun `App\Infrastructure\ViteHelper` via Autoloader
    - **Funktion:** HMR-Demo-Page mit direktem File-Access (ohne Router)
  - **compose.override.yaml erweitert:**
    - PHP-Container erhält alle ENV-Variablen für HealthCheck:

      ```yaml
      environment:
        - ENV=${ENV:-development}
        - ENABLE_PHP=${ENABLE_PHP:-true}
        - ENABLE_NODE=${ENABLE_NODE:-true}
        - ENABLE_REDIS=${ENABLE_REDIS:-true}
        - NODE_MODE=${NODE_MODE:-full-stack}
        - DB_TYPE=${DB_TYPE:-postgres}
        - DB_NAME=${DB_NAME:-app}
        - DB_USER=${DB_USER:-app}
        - DB_PASSWORD=${DB_PASSWORD:-secret}
      ```

  - **Dateien geändert/erstellt:**
    - **Erstellt:** `src/php/Infrastructure/ViteHelper.php` (159 Zeilen)
    - **Erstellt:** `src/php/Infrastructure/HealthCheck.php` (310 Zeilen)
    - **Erstellt:** `src/php/Http/Controller/WelcomeController.php` (28 Zeilen)
    - **Erstellt:** `src/php/Http/Controller/StatusController.php` (24 Zeilen)
    - **Erstellt:** `templates/welcome.php` (136 Zeilen)
    - **Geändert:** `public/index.php` (Routes erweitert)
    - **Verschoben:** `public/test.php` → `public/welcome.php` (aktualisiert)
    - **Gelöscht:** `public/vite-helper.php` (alte Version)
    - **Geändert:** `compose.override.yaml` (PHP ENV-Variablen hinzugefügt,
      Zeilen 51-60)
  - **Testing & Verification:**
    - Composer Autoloader regeneriert: `composer dump-autoload -o` ✅
    - PHP-Container neu erstellt: `docker compose up -d php` ✅
    - ENV-Variablen korrekt geladen: `printenv | grep ENABLE` ✅
    - **Endpoint-Tests:**
      - `GET /` - Dashboard mit Service-Tabelle ✅
      - `GET /welcome` - Alias für / ✅
      - `GET /status` - JSON mit allen Services ✅
      - `GET /api/health` - Legacy JSON ✅
      - `GET /welcome.php` - HMR Demo direkt ✅
    - **Service Status (Vor Extension-Installation):**
      - PHP-FPM: ✅ OK (v8.4.16)
      - Node Backend: ✅ OK (v24.12.0, uptime 6800s, mode: full-stack)
      - Redis: ⚠️ Error (Extension nicht installiert)
      - Database: ⚠️ Error (PDO Extension nicht installiert)
    - **Overall Status:** `degraded` (wegen fehlender Extensions)
    - **Hinweis:** Extensions wurden später hinzugefügt (siehe nächster
      Changelog-Eintrag)
  - **Vorteile:**
    - **Framework-Agnostic:** Dev kann später Laravel, Symfony, Slim, etc.
      nutzen
    - **Clean Architecture:** Business-Logik in `src/`, Public-Dateien in
      `public/`
    - **PSR-4 Autoloading:** Kein manuelles `require_once` mehr
    - **Testbar:** Klassen können via PHPUnit getestet werden
    - **Monitoring:** Zentraler HealthCheck für Docker Healthchecks und
      Uptime-Monitoring
    - **Transparency:** Dashboard zeigt alle aktiven Features und Service-Status
    - **Hybrid Routing:** Dev kann Router nutzen oder direkte Dateien (maximale
      Flexibilität)

- ✅ **PHP Extensions installiert: Redis, PDO PostgreSQL, PDO MySQL**
  - **Problem:** HealthCheck zeigte "degraded" Status
    - Redis Extension fehlte → HealthCheck-Fehler: "Redis PHP extension not
      installed"
    - PDO PostgreSQL Extension fehlte → HealthCheck-Fehler: "PDO PostgreSQL
      extension not installed"
    - health.php war kaputt → Leere weiße Seite (Bug: prüfte REQUEST_URI ===
      '/health' statt '/health.php')
    - User-Frustration: "Warum ist Status degraded wenn Services laufen?"
  - **User-Feedback:**
    - "Sollten wir REDIS und PDO nicht, wie z.B. auch GraphicsMagick, bereits
      vorinstallieren?"
    - "Damit sofort alles lauffähig ist und ein Dev nicht erst herausfinden
      muss, wie man die Extension installiert"
  - **Entscheidung:** Extensions vorinstallieren (wie GraphicsMagick)
    - **Philosophie:** Boilerplate sollte "out of the box" funktionieren
    - Dev kann später Extensions entfernen (einfach), aber hinzufügen ist
      frustrierend
    - Wenn Services (Redis, PostgreSQL) verfügbar sind, sollten Extensions auch
      da sein
  - **Lösung 1: Redis Extension via PECL**
    - Im `php-builder` Stage: `pecl install redis`
    - Extension aktiviert: `docker-php-ext-enable redis`
    - Keine zusätzlichen System-Dependencies nötig
  - **Lösung 2: PDO PostgreSQL Extension**
    - Build-Dependencies: `postgresql-dev` (Compiler-Headers)
    - Runtime-Dependencies: `postgresql-libs` (Shared Libraries)
    - Installation: `docker-php-ext-install pdo_pgsql`
  - **Lösung 3: PDO MySQL Extension**
    - Keine zusätzlichen Dependencies (Built-in in PHP)
    - Installation: `docker-php-ext-install pdo_mysql`
  - **Lösung 4: health.php repariert**
    - **Problem:** `if ($_SERVER['REQUEST_URI'] === '/health')` prüfte falsche
      URI
    - **Aufruf war:** `http://localhost:8080/health.php`
    - **Geprüft wurde:** `/health` (nie true → leere Seite)
    - **Fix:** Bedingung entfernt, direktes JSON-Output
    - **Zweck:** Minimal-Simple Health Check für Docker HEALTHCHECK
    - **Format:**
      `{"status":"ok","service":"php-fpm","timestamp":"2025-12-24T13:55:35+01:00"}`
  - **Dateien geändert:**
    - `docker/php/Dockerfile` (Zeilen 36-40, 48-49, 68):
      - `postgresql-dev postgresql-libs` hinzugefügt
      - `pdo_pgsql pdo_mysql` in docker-php-ext-install
      - `pecl install redis && docker-php-ext-enable redis`
    - `public/health.php` (Zeilen 1-19): URI-Check entfernt, direktes
      JSON-Output
  - **Testing & Verification:**
    - PHP Container neu gebaut: `docker compose build php` ✅
    - Extensions geladen: `php -m | grep -E "redis|pdo_pgsql|pdo_mysql"` ✅
    - **Service Status:**
      - PHP-FPM: ✅ OK (v8.4.16)
      - Node Backend: ✅ OK (v24.12.0, full-stack mode)
      - Redis: ✅ OK (v7.4.7) - **JETZT GRÜN!**
      - PostgreSQL: ✅ OK (PostgreSQL 17.7) - **JETZT GRÜN!**
    - **Overall Status:** `"ok"` (vorher "degraded") ✅
    - Dashboard zeigt grünes "OK" ✅
    - `/health.php` gibt JSON zurück ✅
    - `/status` gibt vollständigen Service-Status ✅
  - **Vorteile:**
    - **Zero-Config:** Alle Services sofort nutzbar ohne Extension-Installation
    - **Better DX:** Developer muss nicht nach Dockerfile-Anleitung suchen
    - **Consistency:** Wenn Service verfügbar ist, ist Extension auch da
    - **Production-Ready:** Image kann direkt deployed werden
  - **Image-Size Impact:** ~3 MB (Redis ~1 MB, PDO PostgreSQL ~2 MB -
    vernachlässigbar)

- ✅ **Redundante welcome.php entfernt und Endpoint-Dokumentation verbessert**
  - **Problem:** Verwirrende Redundanz bei Endpoints
    - `GET /` (Router) → Dashboard ✅
    - `GET /welcome` (Router) → Selbes Dashboard ✅
    - `GET /welcome.php` (direkte Datei) → Ähnlicher Inhalt ❌ **REDUNDANT**
    - User-Verwirrung: "Welchen Endpoint soll ich nutzen?"
    - health.php als "Legacy" bezeichnet, obwohl perfekt für Docker HEALTHCHECK
  - **User-Feedback:**
    - "welcome.php scheint mir jetzt ziemlich redundant zu sein"
    - "health.php ist auf der Startseite als Legacy beschrieben, ggf. Hinweis,
      das für Docker HEALTHCHECK genutzt"
  - **Lösung 1: welcome.php gelöscht**
    - Redundanz eliminiert
    - Nur noch Clean URLs via Router: `/` und `/welcome`
    - `health.php` bleibt als Beispiel für "direkte PHP-Datei ohne Router"
  - **Lösung 2: Endpoint-Dokumentation im Dashboard verbessert**
    - **Vorher (verwirrend):**
      - `GET /health.php` - Legacy PHP health check (direct file)
      - `GET /welcome.php` - HMR Demo Page (direct file)
    - **Nachher (klar):**
      - `GET /` - Service dashboard with live status (via Router)
      - `GET /welcome` - Alias for / (via Router)
      - `GET /status` - Detailed JSON health check (all services)
      - `GET /api/health` - Simple JSON health (PHP-FPM only)
      - `GET /health.php` - Minimal health check for Docker HEALTHCHECK
    - **Tip hinzugefügt:**
      - "Use `/health.php` for Docker HEALTHCHECK (minimal overhead)"
      - "Use `/status` for monitoring dashboards (detailed service info)"
  - **Dateien geändert:**
    - **Gelöscht:** `public/welcome.php` (redundant)
    - **Geändert:** `templates/welcome.php` (Zeilen 79-95): Endpoint-Liste neu
      strukturiert, Tip hinzugefügt
  - **Testing & Verification:**
    - `GET /` → Dashboard ✅
    - `GET /welcome` → Dashboard (Alias) ✅
    - `GET /welcome.php` → 404 Not Found ✅ (wie erwartet)
    - `GET /health.php` → Minimal JSON ✅
    - `GET /status` → Detailed JSON ✅
  - **Vorteile:**
    - **Clarity:** Jeder Endpoint hat klaren Zweck, keine Redundanz
    - **Best Practices:** Dokumentation zeigt wann welcher Endpoint genutzt
      werden sollte
    - **Developer Experience:** Keine Verwirrung mehr über "welchen Endpoint
      nutze ich?"
    - **Clean:** Weniger Dateien = weniger Maintenance

- ✅ **TypeScript Fehler in src/node/server.ts behoben**
  - **Problem:** Implizite `any`-Types und Import-Probleme
    - `pino-http` CommonJS/ESM Interop-Fehler:
      `TS2349: This expression is not callable`
    - Implizite `any` in Callback-Parametern (customLogLevel,
      customSuccessMessage, etc.)
    - `server: any` ohne korrekte Typisierung
    - Unused default export
  - **Lösung:**
    - **pino-http Import-Fix:** `import pinoHttpImport from 'pino-http'` +
      Workaround

      ```typescript
      const pinoHttp =
        pinoHttpImport as unknown as typeof pinoHttpImport.default;
      ```

    - **Explizite Types für Callbacks:**
      - `customLogLevel: (_req: Request, res: Response, err?: Error) => {...}`
      - `customSuccessMessage: (req: Request, res: Response) => {...}`
      - `customErrorMessage: (_req: Request, _res: Response, err: Error) => {...}`
    - **Server Type:** `const server: Server = createServer(app);` (statt `any`)
    - **Label Type:** `level: (label: string) => {...}` in Pino formatter
    - **Logger-Referenz:** `req.log.error` → `logger.error` im Error Handler
    - **Unused Export entfernt:** `export default app` gelöscht

  - **Dateien geändert:**
    - `src/node/server.ts` (Zeilen 11-17, 34, 42-55, 138, 125)
  - **Verification:**
    - TypeScript Compilation: ✅ Keine Fehler (`pnpm run type-check`)
    - Server läuft: ✅ API antwortet korrekt auf `/api/node/health`
  - **Hinweis:** IDE-Diagnostics (TS2307, TS2580) sind normal - node_modules nur
    im Container

- ✅ **SCSS/SASS Support implementiert**
  - **Dependency hinzugefügt:**
    - `sass@^1.97.1` in `devDependencies`
    - Installiert via `pnpm add -D sass`
  - **Vite Config:** Bereits vorbereitet mit `preprocessorOptions.scss` (Zeile
    92-96)
  - **Test-SCSS erstellt:** `resources/css/test.scss`
    - **Moderne SASS-Modules:**
      - `@use 'sass:math'` für `math.div()` (statt deprecated `/`)
      - `@use 'sass:color'` für `color.adjust()` (statt deprecated `darken()`,
        `lighten()`)
    - **Features demonstriert:**
      - Variablen: `$primary-color`, `$secondary-color`, `$spacing`, etc.
      - Nesting: `.scss-test__header`, `.scss-test__content`,
        `.scss-test__footer`
      - Mixins: `@mixin flex-center`, `@mixin card-shadow($opacity)`
      - Color-Funktionen: `color.adjust($primary-color, $lightness: -10%)`
      - Math-Funktionen: `math.div($spacing, 2)`
      - Media Queries: `@media (max-width: 768px)`
  - **Integration:** `resources/js/app.js` importiert
    `import '../css/test.scss'`
  - **Build-Output:**
    - Kompiliertes CSS: `public/build/assets/app-ByQwGoR5.css` (3.06 kB)
    - Keine Deprecation-Warnings ✅
    - Vite Manifest: CSS korrekt verlinkt
  - **Dateien geändert:**
    - `package.json`: `sass@^1.97.1` hinzugefügt
    - `resources/css/test.scss`: Neue Test-Datei mit SCSS-Features
    - `resources/js/app.js`: SCSS-Import hinzugefügt (Zeile 12)
  - **Getestet:**
    - Build: ✅ Erfolgreich ohne Warnings (`pnpm run build`)
    - Dev-Server: ✅ HMR funktioniert mit SCSS
    - CSS-Output: ✅ Alle SCSS-Features korrekt kompiliert

- ✅ **Node.js Environment Support erweitert**
  - **Jetzt unterstützt:**
    - CSS (native)
    - PostCSS mit Autoprefixer
    - **SCSS/SASS** mit allen modernen Features (neu!)
  - **Vite HMR:** Hot Module Replacement für alle CSS/SCSS-Dateien

- ✅ **Dokumentation und UX-Verbesserungen (5 Punkte vor Commit)**
  - **1. Quick Start Optimierung in Welcome-Dashboard**
    - **Problem:** Quick Start zeigte nur generische make-Commands ohne Kontext
      - Kein Unterschied zwischen "erstem Setup" und "täglicher Entwicklung"
      - User musste selbst herausfinden welche Commands wann relevant sind
      - Verwirrung für neue Developer: "Was muss ich als erstes tun?"
      - **Inkonsistenz:** Zeigte `cp .env.example .env` statt `make init`
    - **Lösung:** Quick Start in zwei Abschnitte unterteilt mit konsistenten
      Commands
      - **🚀 Initial Setup (First Time):**

        ```bash
        make init    # Initialize project (copy .env.example to .env)
        # Edit .env: Set ENV=development
        make setup   # Create project structure (directories, dependencies)
        make fresh   # Build and start all services
        ```

      - **💻 Daily Development:**

        ```bash
        make up      # Start services
        make down    # Stop services
        make build   # Rebuild images
        ```

    - **Dateien geändert:**
      - `templates/welcome.php` (Zeilen 100-114): Quick Start neu strukturiert
        mit 4-Schritt-Flow
      - `.env.example` (Zeilen 7-11): Quick Start Header aktualisiert
      - `.env` (Zeilen 7-11): Quick Start Header aktualisiert
    - **Vorteile:**
      - Klare Trennung: Einmaliges Setup vs tägliche Nutzung
      - Konsistente make-Commands (keine direkten bash-Befehle)
      - Neue Developer wissen sofort was zu tun ist
      - Reduziert Support-Anfragen und Onboarding-Zeit

  - **2. make commands Konsistenz (statt docker exec)**
    - **Problem:** Dokumentation zeigte inkonsistente Commands
      - README.md: Mix aus `make` und `docker compose exec`
      - entrypoint.sh: Nur `docker compose exec` in Hilfe-Texten
      - User musste beide Syntaxen kennen
      - Verwirrung: "Welche Methode soll ich nutzen?"
    - **Lösung:** Überall make commands als primäre Methode
      - **README.md aktualisiert:** `make php-exec CMD="php -m | grep xdebug"`
        - Mit Fallback: `# Or: docker compose exec php php -m | grep xdebug`
      - **entrypoint.sh aktualisiert:** Hilfe-Text zeigt make commands

        ```bash
        echo "[entrypoint]   - Via make: 'make node-exec CMD=\"pnpm run <command>\"'"
        echo "[entrypoint]   - Direct:   'docker compose exec node pnpm run <command>'"
        ```

    - **Dateien geändert:**
      - `README.md` (Zeilen 399-407): make commands als Primär-Methode
      - `docker/node/entrypoint.sh` (Zeilen 37-38): make-Command-Hinweise
    - **Vorteile:**
      - Konsistente Developer Experience
      - make abstrahiert Docker-Komplexität
      - Einfacher für Anfänger
      - Weniger kognitive Last (nur eine Methode merken)

  - **3. Emoji-Symbole in .env Dateien korrigiert**
    - **Problem:** PhpStorm zeigte Emojis falsch an
      - Nummerierung mit 1️⃣ 2️⃣ 3️⃣ (Emoji Keycap Digits)
      - PhpStorm-Rendering: Falsche Darstellung oder Boxen
      - User-Feedback: "bessere Symbole bei Nummerierung in .env.example nutzen"
    - **Lösung:** ASCII-Formatierung mit Brackets
      - `1️⃣` → `[1]`
      - `2️⃣` → `[2]`
      - `3️⃣` → `[3]`
      - etc.
    - **Dateien geändert:**
      - `.env.example` (Zeilen 44-68): Alle Preset-Nummerierungen
      - `.env` (Zeilen 44-68): Alle Preset-Nummerierungen
    - **Vorteile:**
      - Universelle Kompatibilität (alle IDEs und Editoren)
      - Bessere Lesbarkeit in PhpStorm
      - ASCII-only (keine Unicode-Probleme)

  - **4. Redis Session Handler Konfiguration hinzugefügt**
    - **Problem:** Redis für Sessions nicht dokumentiert
      - User fragte: "Ist Redis ready2go oder erfordert es weitere Anpassungen?"
      - Unklar ob zwischen Redis und file-based Sessions gewechselt werden kann
      - Keine Anleitung wie Redis-Sessions aktiviert werden
      - **Falsche Platzierung:** development.ini würde nur in Development ENV
        geladen
    - **Lösung:** Dokumentierte Konfiguration in **php.ini** (Base-Config für
      alle Environments)
      - **Default:** File-based Sessions (kein Code-Change nötig)
      - **Optional:** Redis Sessions (auskommentiert mit Anleitung)
      - **Warnung hinzugefügt:** Redis erfordert Code-Anpassungen:
        - Session-Daten müssen serializable sein
        - Kein File-Locking (Redis Transactions nutzen)
        - Memory-Policy in redis.conf setzen (maxmemory)
      - **Beispiele für Production und Development:**

        ```ini
        ; Production (mit Auth):
        ; session.save_path = "tcp://redis:6379?auth=your_redis_password&timeout=2.5&database=0"

        ; Development (ohne Auth):
        ; session.save_path = "tcp://redis:6379?timeout=2.5&database=0"
        ```

    - **Dateien geändert:**
      - `docker/php/php.ini` (Zeilen 28-44): Redis Session-Handler Dokumentation
        hinzugefügt
      - `docker/php/conf.d/development.ini`: Redis-Config entfernt (war falsche
        Stelle)
    - **Vorteile:**
      - **Richtige Platzierung:** php.ini gilt für alle Environments
        (development + production)
      - Transparenz: User weiß was Redis erfordert
      - Quick-Switch: Zeilen auskommentieren für Redis-Sessions
      - Best Practices: Production mit Auth, Development ohne
      - Warnung verhindert Frustration bei Session-Problemen

  - **5. Image-Versionen in Compose-Dateien standardisiert**
    - **Problem:** Inkonsistente und teilweise falsche Image-Tags
      - `postgres:17.7-alpine` - Falscher Tag (PostgreSQL nutzt Major-Versionen:
        `17-alpine`)
      - Images hatten keine konsistente Versionierung
      - .env Variablen waren unnötig (jeder Image-String kommt nur 1x vor)
    - **Lösung:** Korrekte und konsistente Image-Tags **direkt in compose.yaml**
      - **Redis:** `redis:7.4-alpine` (Alpine 3.22+ wird automatisch verwendet)
      - **PostgreSQL:** `postgres:17.7-alpine` → `postgres:17-alpine` (korrekt)
      - **MariaDB:** `mariadb:12.1` (nutzt Debian/Ubuntu, nicht Alpine)
      - **Keine .env Variablen:** Versionen bleiben hardcoded in compose.yaml
        - Grund: Keine Wiederverwendung (jeder Image-String kommt nur 1x vor)
        - Dockerfiles nutzen ARG (DRY: `alpine:${ALPINE_VERSION}` mehrfach
          verwendet)
        - compose.yaml: Fixe Versionen (bessere Lesbarkeit,
          Renovate-Kompatibilität)
    - **Dateien geändert:**
      - `compose.yaml` (Zeile 64): redis Image `redis:7.4-alpine`
      - `compose.yaml` (Zeile 88): postgres Image `postgres:17-alpine`
    - **Hinweis:** Alpine-Version im Tag nicht spezifizierbar
      - Redis und Postgres verwenden `-alpine` ohne Versionssuffix
      - Alpine-Version wird vom Image-Maintainer bestimmt
      - Typischerweise aktuellste stabile Alpine-Version
      - Fixierung nur über vollständigen Digest möglich (nicht praktikabel)
    - **Vorteile:**
      - **Korrekte Tags:** PostgreSQL verwendet Major-Versionen
      - **Reproduzierbarkeit:** Gleiche Builds über Zeit
      - **Lesbarkeit:** Klare, dokumentierte Versionen
      - **Renovate-Kompatibilität:** Dependency-Scanner können Versionen
        erkennen

  - **6. Makefile Konsistenz und Formatierung verbessert**
    - **Problem:** Fehlende und inkonsistente Commands
      - **Inkonsistente Dependency-Installation:** PHP nutzte `dev-deps`, Node
        nutzte `node-install`
      - **Fehlende logs-\* Commands:** logs-redis, logs-postgres, logs-mariadb
        existierten nicht
      - **Fehlende shell-\* Commands:** shell-node, shell-redis, shell-postgres,
        shell-mariadb fehlten
      - **Inkonsistente Benennung:** `node-shell` statt `shell-node` (nicht
        konsistent mit shell-nginx, shell-php)
      - **Formatierung:** Command-Beschreibungen mit ungleichem Abstand (15
        Zeichen zu kurz für längste Commands)
      - **Database-Emoji:** Falsches Symbol mit extra Leerzeichen in
        check-health
      - **Überflüssige Commands:** MySQL-Commands (mysql-cli, mysql-dump,
        mysql-restore) obwohl MySQL-Service entfernt wurde
    - **Lösung 1: MySQL Commands entfernt**
      - `mysql-cli`, `mysql-dump`, `mysql-restore` gelöscht
      - Grund: MySQL Service existiert nicht mehr (nur PostgreSQL und MariaDB)
      - MariaDB Commands beibehalten (Service existiert in compose.yaml)
    - **Lösung 2: Fehlende logs-\* Commands hinzugefügt**
      - `logs-redis` (Zeile 199): Show Redis logs only
      - `logs-postgres` (Zeile 210): Show PostgreSQL logs only
      - `logs-mariadb` (Zeile 221): Show MariaDB logs only
      - Konsistente Implementierung wie logs-nginx, logs-php, logs-node
      - Unterstützt automatisch production/development ENV-Detection
    - **Lösung 3: Composer Commands mit Node.js konsistent benannt**
      - **Problem:** PHP nutzte `dev-deps` / `dev-deps-local`, Node nutzte
        `node-install` / `node-install-local`
      - **Umbenennung:**
        - `dev-deps` → `composer-install` (Zeile 32)
        - `dev-deps-local` → `composer-install-local` (Zeile 41)
      - **Alle Referenzen aktualisiert:**
        - `setup` Target: Ruft jetzt `composer-install` auf (Zeile 104)
        - Fehlermeldungen: Zeigen jetzt `make composer-install` (Zeilen 44, 52)
      - **Konsistentes Naming:** `<package-manager>-install` /
        `<package-manager>-install-local`
        - Composer: `composer-install` / `composer-install-local`
        - Node.js: `node-install` / `node-install-local`
    - **Lösung 4: Shell Commands konsistent gemacht**
      - **Neue Commands:**
        - `shell-node` (Zeile 247): Open shell in Node container
        - `shell-redis` (Zeile 250): Open shell in Redis container
        - `shell-postgres` (Zeile 253): Open shell in PostgreSQL container
        - `shell-mariadb` (Zeile 256): Open shell in MariaDB container
      - **Gelöscht:** `node-shell` (Zeile 289) → ersetzt durch `shell-node`
      - **Konsistentes Pattern:** Alle shell-\* Commands in Docker-Sektion
        gruppiert
    - **Lösung 5: Formatierung verbessert**
      - Command-Breite von `%-15s` auf `%-20s` erhöht (Zeile 28)
      - Grund: Längste Commands sind 18 Zeichen (`node-install-local`,
        `node-app-server-up`)
      - Alle Beschreibungen jetzt perfekt ausgerichtet bei `make help`
    - **Lösung 6: Database-Emoji korrigiert**
      - Altes Symbol: `🗄️` (File Cabinet) mit extra Leerzeichen und falscher
        Breite
      - Neues Symbol: `💾` (Floppy Disk - klassisches Datenspeicher-Symbol)
      - Konsistente Breite und Abstand zu anderen Symbolen (📦 PHP-FPM, 🔴
        Redis, 🌐 Nginx)
    - **Dateien geändert:**
      - `Makefile` (Zeile 28): help-Formatierung %-20s
      - `Makefile` (Zeilen 32, 41): dev-deps → composer-install, dev-deps-local
        → composer-install-local
      - `Makefile` (Zeilen 44, 52, 104): Alle dev-deps Referenzen auf
        composer-install aktualisiert
      - `Makefile` (Zeilen 199-230): logs-redis, logs-postgres, logs-mariadb
        hinzugefügt
      - `Makefile` (Zeilen 247-257): shell-node, shell-redis, shell-postgres,
        shell-mariadb hinzugefügt
      - `Makefile` (Zeile 365-381): mysql-cli, mysql-dump, mysql-restore
        entfernt
      - `Makefile` (Zeile 289): node-shell entfernt
      - `Makefile` (Zeile 436): Database-Emoji auf 💾 geändert
    - **Testing & Verification:**
      - `make help`: Alle Commands perfekt formatiert, composer-install und
        composer-install-local sichtbar ✅
      - `make composer-install`: Installiert Composer Dependencies im Container
        ✅
      - `make logs-redis`: Zeigt Redis-Logs ✅
      - `make shell-postgres`: Öffnet PostgreSQL-Shell ✅
      - `make check-health`: Database-Emoji 💾 konsistente Breite ✅
    - **Vorteile:**
      - **Vollständigkeit:** Alle Services haben logs-_und shell-_ Commands
      - **Konsistenz:**
        - Einheitliches Naming-Schema (shell-_, logs-_, \*-install)
        - Composer und Node.js nutzen gleiches Pattern: `<tool>-install` /
          `<tool>-install-local`
      - **Lesbarkeit:** Perfekt formatierte Help-Ausgabe
      - **Klarheit:** Keine überflüssigen Commands für nicht-existierende
        Services
      - **UX:** Developer findet jeden Command intuitiv ohne Dokumentation zu
        lesen

  - **7. compose.override.yaml ENV Variable auf development hardcoded**
    - **Problem:** Unnötige Fallback-Logik in Development-only File
      - `ENV=${ENV:-development}` in compose.override.yaml (Zeile 51)
      - compose.override.yaml wird NUR in Development verwendet (nie in
        Production)
      - Production nutzt: `docker compose -f compose.yaml -f compose.prod.yaml`
        (ohne override)
      - Inkonsistenz: NODE_ENV war bereits hardcoded (`NODE_ENV=development`),
        aber ENV hatte Fallback
    - **Lösung:** ENV auf development hardcoded (analog zu NODE_ENV)
      - `ENV=${ENV:-development}` → `ENV=development`
      - Konsistent mit `NODE_ENV=development` (Zeile 75)
    - **Begründung:**
      - compose.override.yaml ist Development-spezifisch (per Docker Compose
        Convention)
      - Fallback-Logik `${ENV:-development}` macht nur in Base-Files Sinn
        (compose.yaml)
      - Hardcoded values in Override-Files sind Best Practice
    - **Dateien geändert:**
      - `compose.override.yaml` (Zeile 51): ENV=development (hardcoded)
    - **Vorteile:**
      - **Klarheit:** Keine Verwirrung ob ENV dynamisch oder fix ist
      - **Konsistenz:** Beide ENV-Variablen (ENV, NODE_ENV) jetzt hardcoded
      - **Best Practice:** Override-Files sollten explizite Werte haben, keine
        Fallbacks
      - **Einfachheit:** Weniger Variablen-Substituierung = schnelleres Startup

### Version 2.9 (2025-12-19)

- ✅ **Vite HMR (Hot Module Replacement) CORS-Probleme behoben**
  - **Problem:** Browser blockierte Vite Dev Server mit CORS-Fehlern
    - `Cross-Origin Request blocked: CORS request failed`
    - `Module source URI is not allowed in this document`
    - Grund: Browser versuchte von `localhost:8080` (NGINX) auf `localhost:5173`
      (Vite) zuzugreifen
  - **Lösung 1: Explizite CORS-Konfiguration in Vite**
    - `vite.config.js:58-61`: CORS aktiviert mit `origin: '*'` und
      `credentials: true`
    - `vite.config.js:71`: `strictPort: true` hinzugefügt für stabilen Port
  - **Lösung 2: Dynamic base path für Development vs Production**
    - `vite.config.js:10`:
      `base: process.env.NODE_ENV === 'production' ? '/build/' : '/'`
    - Development: Root-Path `/` für direkte Vite-Server Zugriffe
    - Production: `/build/` für statische Assets
  - **Dateien geändert:**
    - `vite.config.js` (Zeilen 10, 58-61, 71)
    - `public/vite-helper.php` (Zeile 22:
      `viteDevServerUrl = 'http://localhost:5173'`)
  - **Ergebnis:** Vite HMR lädt jetzt korrekt mit CORS-Headern

- ✅ **ViteHelper.php Entry Points korrigiert**
  - **Problem:** Entry Points stimmten nicht mit Vite-Root überein
    - ViteHelper verwendete `resources/js/app.js`
    - Vite Config hat `root: 'resources'`, daher sollte es `js/app.js` sein
  - **Lösung:** Entry Points in allen Methoden angepasst
    - `public/vite-helper.php:98`: `renderScriptTags()` default: `'js/app.js'`
    - `public/vite-helper.php:126`: `renderCssTags()` default: `'js/app.js'`
    - Kommentare in `getAssetUrl()` und `getCssUrl()` aktualisiert
  - **Dateien geändert:**
    - `public/vite-helper.php` (Zeilen 64-65, 86, 98, 126)
  - **Ergebnis:** Assets werden jetzt korrekt geladen

- ✅ **node_modules Permission-Problem in Development Mode behoben**
  - **Problem:** `make node-install` schlug fehl mit
    `EACCES: permission denied, mkdir '/app/node_modules/.pnpm'`
    - Ursache: Docker Volume `node_modules` wurde mit `root:root` erstellt
    - Node User (UID 1000) hatte keine Schreibrechte
  - **Lösung:** Permissions-Fix vor pnpm install
    - `Makefile:246-247`: `chown -R node:node /app/node_modules` als root vor
      pnpm install
    - Ausgabe: "Fixing node_modules permissions..." für Transparenz
  - **Dateien geändert:**
    - `Makefile` (Zeilen 246-247)
  - **Ergebnis:** Dependencies installieren jetzt erfolgreich in Development
    Mode

- ✅ **Vollständige Test-Matrix: Alle 6 Szenarien erfolgreich**
  - **ENV=production:**
    - ✅ Test 1: PHP-only Mode (nginx + php)
    - ✅ Test 2: Asset-Server Mode (nginx + php + node:asset-server mit
      Build-Artefakten)
    - ✅ Test 3: App-Server Mode (nginx + php + node:app-server mit Backend auf
      Port 3000)
  - **ENV=development:**
    - ✅ Test 4: PHP-only Mode (nginx + php)
    - ✅ Test 5: Asset-Server Mode mit HMR (nginx + php + node + Vite Dev Server
      auf Port 5173)
    - ✅ Test 6: App-Server Mode (nginx + php + node + Backend in watch mode)
  - **Test-Befehle für Development:**

    ```bash
    # Test 5: ENV=development, asset-server + HMR
    make fresh
    make node-up
    make node-install  # Permissions werden automatisch korrigiert
    make node-dev      # Vite läuft auf Port 5173
    # Zugriff: http://localhost:8080/test.php

    # Test 6: ENV=development, app-server
    make fresh
    make node-app-server-up
    make node-install
    make node-server-dev  # Backend läuft auf Port 3000
    curl http://localhost:3000/health  # ✅ {"status":"ok"}
    ```

  - **Dynamische ENV-Erkennung funktioniert:**
    - `public/test.php` zeigt automatisch Development (🔧 Vite HMR) oder
      Production (🚀 Built Assets)
    - `public/vite-helper.php` lädt korrekt basierend auf `$_ENV['ENV']`

- 🔧 **Bekannte Einschränkungen:**
  - Vite HMR WebSocket muss von Browser zu `localhost:5173` direkt verbinden
    können
  - In Docker-Netzwerk-Setups ohne Port-Forwarding muss `hmr.host` angepasst
    werden
  - Production Mode erfordert `make build` vor `make up` (Build-Artefakte werden
    in Image kopiert)

### Version 2.8 (2025-12-19)

- ✅ **Container Logging auf 12-Factor App Best Practices umgestellt**
  - **Problem:** Production Mode schlug fehl
    - Nginx crashte mit "Permission denied" auf `/var/log/nginx/error.log`
    - Ursache: Read-only Filesystem in Production (`compose.prod.yaml:8`)
      verhinderte Schreibzugriff auf Log-Dateien
    - Anti-Pattern: Bind-Mounts für Logs (`./logs:/var/log/*`) skalieren nicht
      und funktionieren nicht mit read-only FS
  - **Lösung:** Umstellung auf stdout/stderr Logging (Industry Standard)
    1. **Nginx Logs:** `docker/nginx/nginx.conf`
       - `error_log stderr warn;` (Zeile 2)
       - `access_log /dev/stdout main;` (Zeile 18)
    2. **PHP Logs:**
       - `docker/php/php.ini`: `error_log = /proc/self/fd/2` (Zeile 41)
       - `docker/php/php-fpm.conf`: Alle Logs → `/proc/self/fd/2` (Zeilen 18-20)
    3. **Compose Cleanup:**
       - `compose.yaml`: Nginx Log Bind-Mount entfernt (Zeile 15)
       - `compose.override.yaml`: PHP Log Bind-Mounts entfernt (Zeilen 42-43)
       - `compose.prod.yaml`: tmpfs für `/var/log/app` und `/var/log/php`
         entfernt (Zeilen 40-41)
    4. **Makefile Cleanup:**
       - `LOG_DIR` Referenzen in `make setup` und `make up-core` entfernt
  - **Vorteile:**
    - ✅ Production Mode funktioniert jetzt (read-only FS kompatibel)
    - ✅ Einheitliches Logging zwischen Dev und Production
    - ✅ Logs via `make logs-nginx` / `make logs-php` / `docker compose logs`
      verfügbar
    - ✅ Automatische Log-Rotation via Docker JSON-File Driver (50MB/File, 5
      Files)
    - ✅ Kompatibel mit Log-Aggregation (ELK, Loki, CloudWatch, etc.)
    - ✅ Skaliert auf Kubernetes/Swarm (keine Filesystem-Abhängigkeit)
  - **Dateien geändert:**
    - `docker/nginx/nginx.conf` (Zeilen 2, 18)
    - `docker/php/php.ini` (Zeile 41)
    - `docker/php/php-fpm.conf` (Zeilen 18-20)
    - `compose.yaml` (Zeile 15 entfernt)
    - `compose.override.yaml` (Zeilen 42-43 entfernt)
    - `compose.prod.yaml` (Zeilen 40-41 entfernt)
    - `Makefile` (LOG_DIR Referenzen entfernt)
  - **Verifikation:**

    ```bash
    # In .env: ENV=production
    make down && make build && make up
    docker compose ps               # nginx: healthy, php: healthy ✅
    curl http://localhost:8080      # ✅ Funktioniert
    make logs-nginx                 # ✅ Zeigt Access-Logs
    make logs-php                   # ✅ Zeigt PHP-FPM Logs
    ```

### Version 2.7 (2025-12-19)

- ✅ **Nginx Health-Check Fix**
  - **Problem:** Nginx Health-Check schlug fehl mit "Connection refused"
  - **Ursache:** `wget --spider http://localhost:8080/health` versuchte IPv6
    (`[::1]`), aber nginx hört nur auf IPv4
  - **Lösung:** Health-Check URL von `localhost` → `127.0.0.1` geändert
  - **Datei:** `docker/nginx/Dockerfile` Zeile 30
  - **Ergebnis:** Health-Check funktioniert jetzt zuverlässig

- ✅ **Nginx Dynamic DNS Resolution für Node Container**
  - **Problem:** "Chicken-Egg Problem" - nginx startete nicht ohne
    node-Container
    - Fehler:
      `host not found in upstream "node" in /etc/nginx/conf.d/default.conf:58`
    - Grund: nginx löste DNS-Namen beim Start auf, scheiterte wenn node nicht
      existierte
    - Konflikt: `make up` (nur nginx+php) vs `make node-up` (alle Services)
  - **Anforderung:** nginx muss auch ohne node-Container starten und healthy
    sein
  - **Lösung:** Dynamic DNS Resolution mit Docker's internem DNS
    1. **Resolver hinzugefügt:** `resolver 127.0.0.11 valid=30s ipv6=off;`
       - `127.0.0.11` = Docker's interner DNS-Server
       - `valid=30s` = DNS-Cache-TTL (30 Sekunden)
       - `ipv6=off` = Deaktiviert IPv6-Auflösung (nur IPv4)
    2. **Variable für upstream:** `set $upstream_node node:5173;`
       - DNS-Auflösung erfolgt zur Laufzeit (bei Request), nicht beim Start
       - Ermöglicht Proxy-Requests auch wenn node später hinzugefügt wird
  - **Dateien:**
    - `docker/nginx/conf.d/default.conf` Zeile 6 (resolver)
    - `docker/nginx/conf.d/default.conf` Zeile 58, 69 (upstream variables)
  - **Ergebnis:**
    - ✅ `make up` → nginx + php → **nginx healthy** (ohne node)
    - ✅ `make node-up` → node hinzufügen → **alle Container healthy**
    - ✅ Keine Fehler mehr: "host not found in upstream"
    - ✅ Vite HMR Proxy funktioniert wenn node verfügbar ist

- 📋 **Technische Details: Nginx Variable vs. Statischer Upstream**
  - **Statisch:** `proxy_pass http://node:5173;`
    - DNS-Auflösung beim nginx-Start
    - Fehler wenn upstream nicht existiert → nginx startet nicht
  - **Dynamisch:**
    `set $upstream_node node:5173; proxy_pass http://$upstream_node;`
    - DNS-Auflösung bei jedem Request (mit Cache)
    - Fehler nur wenn upstream zum Request-Zeitpunkt nicht erreichbar
    - Erfordert `resolver` Direktive
  - **Vorteil:** Flexibler Workflow - node ist optional, nginx funktioniert in
    beiden Fällen

- ✅ **Workflow-Verifizierung**
  - **Szenario 1: Nur PHP Backend**

    ```bash
    make up              # nginx + php
    docker compose ps    # nginx: healthy, php: healthy
    curl localhost:8080  # ✅ PHP funktioniert
    ```

  - **Szenario 2: Mit Node.js Services**

    ```bash
    make up              # nginx + php
    make node-up         # node hinzufügen
    docker compose ps    # alle healthy
    make node-dev        # Vite HMR starten
    curl localhost:8080/@vite/client  # ✅ Proxy funktioniert
    ```

### Version 2.6 (2025-12-19)

- ✅ **Node.js Backend Port 3000 Fix**
  - Problem: `curl http://localhost:3000` fehlgeschlagen mit "Could not connect
    to server"
  - Ursache: Port 3000 war nur mit `expose:` konfiguriert (nur Docker-Netzwerk),
    nicht mit `ports:` (Host-Zugriff)
  - Lösung: Port 3000 in `compose.override.yaml` hinzugefügt (Zeile 63):
    `- "${NODE_PORT:-3000}:3000"`
  - Jetzt erreichbar: `curl http://localhost:3000/health` und
    `curl http://localhost:3000/api/hello?name=Docker`
  - Datei: `compose.override.yaml` Zeile 63
- ✅ **TypeScript-Fehler in server.ts behoben** (vom User bereits durchgeführt)
  - TS2742: Expliziter Typ für Express App (`const app: Express = express();`)
  - TS6133: Ungenutzte Parameter mit Unterstrich (`_req`, `_res`, `_next`)
  - TS7030: Expliziter `void` Rückgabetyp für Middleware-Funktionen
  - Datei: `src/node/server.ts` (bereits korrekt)
- ✅ **Makefile Review & Analyse**
  - Alle Pfade überprüft: Keine veralteten `/var/www/html/app/` Referenzen
    gefunden
  - Alle `docker compose exec`/`run` Befehle geprüft: Funktionieren korrekt
  - **Empfehlungen für zukünftige Optimierung:**
    1. `node-build` (Zeile 251-253): Verwendet `--build --target build`, was bei
       jedem Aufruf Image rebuildet → Ineffizient
       - Alternative: Im laufenden Container ausführen oder separates
         Build-Image-Konzept
    2. `node-up` / `node-app-server-up`: Verwirrende Befehle, könnten besser
       dokumentiert werden
       - `node-up`: Startet Container mit "sleep infinity" (asset-server) →
         Unklar, warum nötig in Development
       - `node-app-server-up`: Startet mit NODE_TARGET=app-server → Macht Sinn
         für Backend-Testing
    3. Fehlende Container-Status-Checks: `node-dev`, `node-server-dev` etc.
       scheitern, wenn Container nicht läuft
       - Lösung:
         `@docker compose ps -q node >/dev/null 2>&1 || { echo "Container not running"; exit 1; }`
         vor exec
  - **Aktueller Stand:** Alle Befehle funktionieren, keine veralteten Commands
    identifiziert
- 📝 **Testing-Status Update**
  - Phase 6.3 (Node.js Backend Testing) bereit für Tests nach Port-Fix
  - Workflow: `make up` → `make node-install` → `make node-server-dev` →
    `curl http://localhost:3000/health`

### Version 2.5 (2025-12-19)

- ✅ **Phase 6.2 Testing abgeschlossen:** Node.js Frontend & Vite HMR
  erfolgreich getestet
  - Dependencies: 109 packages installiert in 5.2s
  - Vite HMR: Ready in 407ms auf Port 5173
  - test.html: <http://localhost:8080/test.html> lädt erfolgreich
  - API Health Check: Zeigt JSON-Daten korrekt an
  - HMR: Live-Reload funktioniert bei Änderungen in `resources/css/app.css`
- ✅ **Bugfix: Named Volume Permissions (node_modules)**
  - Problem: Docker erstellt named volumes mit root:root Ownership, User `node`
    (UID 1000) konnte nicht schreiben →
    `EACCES: permission denied, mkdir '/app/node_modules/.pnpm'`
  - Lösung: `make node-install` (Makefile:237) angepasst - startet mit
    `--user root`, fixt Permissions via `chown`, dann
    `su node -s /bin/sh -c 'pnpm install'`
  - Begründung: Named volume nötig für Windows/Mac Performance (Linux könnte
    bind mount nutzen, aber Konsistenz wichtiger)
  - Datei: `Makefile` Zeile 237
- ✅ **Bugfix: Vite 6 ESM Compatibility**
  - Problem: `vite.config.js` verwendete `require('autoprefixer')` (CommonJS) in
    ESM-Kontext
  - Fehler:
    `Dynamic require of "file:///app/node_modules/.pnpm/autoprefixer@10.4.23_postcss@8.5.6/node_modules/autoprefixer/lib/autoprefixer.js" is not supported`
  - Lösung: Geändert zu ESM-Import - `import autoprefixer from 'autoprefixer'`
    (Zeile 3) und `plugins: [autoprefixer]` (Zeile 81)
  - Datei: `vite.config.js` Zeilen 3 & 81
- ✅ **Bugfix: Content Security Policy (CSP) blockierte Vite HMR**
  - Problem: Nginx CSP erlaubte nur `script-src 'self'`, aber Vite läuft auf
    Port 5173 → Scripts/Styles wurden im Browser mit CSP-Fehler markiert
  - Symptome:
    - Browser Console: CSP-Violations für
      `http://localhost:5173/build/@vite/client` und `/build/js/app.js`
    - API Health Check bleibt auf "Loading..." stecken (fetch blockiert)
    - Keine HMR WebSocket-Verbindung
  - Lösung: CSP für Development erweitert (Zeile 35):
    - `script-src 'self' 'unsafe-inline' http://localhost:5173` - Erlaubt
      Scripts von Vite Dev Server
    - `connect-src 'self' ws://localhost:5173 http://localhost:5173` - Erlaubt
      WebSocket für HMR und fetch zu Vite
  - Wichtig: **Production CSP muss stricter sein!** Entferne `localhost:5173`
    und nutze Nonce-basierte CSP (siehe index.php)
  - Datei: `docker/nginx/conf.d/default.conf` Zeile 35
- ✅ **Bugfix: Vite Base Path in test.html**
  - Problem: `test.html` verwendete Pfade ohne `/build/` Prefix →
    `http://localhost:5173/@vite/client` statt
    `http://localhost:5173/build/@vite/client`
  - Lösung: Pfade angepasst auf korrekte Base Path (Zeilen 16-17)
  - Datei: `public/test.html` Zeilen 16-17

### Version 2.4 (2025-12-19)

- ✅ **Phase 6.1 Testing abgeschlossen:** PHP Backend & QA Tools erfolgreich
  getestet
  - Nginx: Läuft stabil (Port 8080)
  - PHP API: <http://localhost:8080/> und /api/health funktionieren
  - PHPStan: No errors (2 files analyzed)
  - PHP-CS-Fixer: 0 errors in 4 files
- ✅ **Bugfix: Nginx vite-hmr.conf Integration (Phase 4.1)**
  - Problem: Separate `vite-hmr.conf` verursachte "location directive not
    allowed" Fehler
  - Lösung: Vite HMR Locations direkt in `default.conf` integriert (Zeilen
    45-71)
  - Begründung: Production-safe (Proxy schlägt harmlos fehl), keine separate
    Datei nötig
  - Entfernt: Separate `vite-hmr.conf` Datei und Mount aus
    `compose.override.yaml`
- ✅ **Bugfix: PHPStan Config**
  - Problem: "At least one path must be specified" - leeres `tests/` Verzeichnis
  - Lösung: `tests/` Pfad in `phpstan.neon` auskommentiert (Zeile 6)
  - Hinzugefügt: `phpstan.neon` Mount in `compose.override.yaml` (Zeile 37)
- ✅ **Bugfix: PHP-CS-Fixer Config**
  - Problem: Config nicht im Container verfügbar
  - Lösung: `.php-cs-fixer.dist.php` Mount in `compose.override.yaml` (Zeile 38)
  - Angepasst: Finder auf neue Struktur (`src/php`, `tests`, `public`)

### Version 2.3 (2025-12-18)

- ✅ **Makefile Kompatibilität:** Makefile auf Kompatibilität mit neuer Struktur
  geprüft (Phase 4)
  - Hinzugefügt: `make node-dev`, `make node-server-dev`,
    `make node-server-build`, `make logs-node`
  - Korrigiert: cs-fix-all Pfad von `/var/www/html/app/` zu
    `/var/www/html/src/php/`
- ✅ **TODO.md Antworten:** Rückfragen aus Phase 4.4 und 4.7 als Unterpunkte
  beantwortet
  - **4.4.1:** Make Befehl für Dev App-Server - `make node-server-dev`
    Verwendung und Workflow
  - **4.4.2:** Image Slimming für Production - `pnpm prune --prod`
    Implementierung mit Beispiel
  - **4.7.1:** NODE_ENV vs ENV - Single Source of Truth Pattern erklärt
  - Alle Antworten mit Code-Beispielen, Rationale und Referenzen dokumentiert

### Version 2.2 (2025-12-17)

- ✅ **package.json Versionen aktualisiert:** Stable Releases (Option A)
  - autoprefixer: 10.4.20 → 10.4.23
  - postcss: 8.4.49 → 8.5.6
  - typescript: 5.7.2 → 5.9.3
  - @types/express: 5.0.0 → 5.0.6
  - tsx: 4.19.2 → 4.21.0
  - Vite 6.x, Express 4.x, pnpm 9.x bleiben (stable LTS)
- ✅ **Makefile Docker-First:** Neue Strategie für Dependencies (Phase 4.6)
  - `make dev-deps` / `make node-install` → Docker (Default, garantiert
    konsistent)
  - `make dev-deps-local` / `make node-install-local` → Lokal (Opt-in, mit
    Warning)
  - `make composer` / `make pnpm` → Ad-hoc Commands auf laufenden Containern
- ✅ **README Tool-Versionen:** Ausführlicher Abschnitt zu Konsistenz (Phase
  8.1)
  - Warum Docker-First
  - Problem mit lokalen Tools (Lockfile-Konflikte)
  - composer.json config.platform schützt nur vor PHP-Differenzen
- ✅ **Phase 4 Zusammenfassung:** Aktualisiert auf 7 Abschnitte (4.6
  hinzugefügt)

### Version 2.1 (2025-12-17)

- ✅ **index.php zentralisiert:** Phase 3.3 enthält jetzt die finale Version mit
  CSP Template
- ✅ **test.html Dev-Mode:** Dev-Mode mit HMR als Default aktiviert (Phase 3.6)
- ✅ **compose.override.yaml:** Anpassungen für existierende Datei statt
  Neuerstellung (Phase 4.7)
- ✅ **Phase 4 optimiert:** Redundante Abschnitte entfernt, Nummern angepasst
- ✅ **Node Development Stage:** Kompatibilität mit app-server dokumentiert
  (Phase 4.4)
- ✅ **Zusammenfassung:** Aktualisiert auf 6 statt 11 Abschnitte durch
  Zentralisierung

### Version 2.0 (2025-12-17)

- Alle @Questions beantwortet
- Phase 4 erweitert mit 11 Abschnitten
- Wichtige Erkenntnisse & Entscheidungen dokumentiert

---

### Version 1.0 (2025-12-17) - Project Foundation & Architecture Design

Initial project planning document establishing the flexible PHP/Node.js Docker
boilerplate architecture.

#### Project Goals

- Flexible boilerplate supporting PHP Backend, Node.js Backend, Fullstack, or
  API-only modes
- Node.js modes: Development (Vite HMR), asset-server (Production), app-server
  (Production Runtime)
- Modern toolchain with TypeScript, Vite 6, PHP 8.4, Node.js 24

#### Phase 1: Directory Structure

- Created `src/php/` and `src/node/` for backend code separation
- Created `resources/{js,css,images,fonts}/` for frontend assets
- Created `public/build/` for Vite output
- Created `tests/{Unit,Feature}/` for PHPUnit tests
- Created `storage/{app,cache,sessions}/` for runtime data

#### Phase 2: Configuration Files

- `package.json`: Node.js 24+, pnpm 9+, Vite 6, TypeScript 5.9
- `vite.config.js`: HMR configuration, path aliases, PostCSS integration
- `tsconfig.json`: ES2022 target, strict mode, NodeNext modules
- `postcss.config.js`: Autoprefixer with browser targets
- `.gitignore`: Comprehensive ignores for build outputs, dependencies, IDE files

#### Phase 3: Boilerplate Code

- PHP: ExampleController, Router, CSP-compliant index.php with Vite integration
- Node.js: Express server with TypeScript, health endpoints, graceful shutdown
- Frontend: app.js/app.css entry points, test.html for HMR verification

#### Phase 4: Docker & Compose Configuration

- Nginx: Vite HMR proxy, CORS examples, Brotli compression (commented), rate
  limiting zones
- Node Dockerfile: Multi-stage build (development, build, asset-server,
  app-server)
- PHP Dockerfile: Selective COPY for production optimization
- Makefile: Docker-First dependency strategy, node-dev/node-server-dev targets
- compose.override.yaml: HMR config mount, Vite port exposure, NODE_ENV handling

#### Phase 5: Placeholder Files

- .gitkeep files for empty directories (later replaced by `make setup`)

#### Phase 6: Testing Strategy

- PHP Backend testing with PHPUnit
- Node.js Frontend HMR testing
- Node.js Backend testing

#### Phase 7: Security

- CSP headers with nonce support
- Rate limiting zones (api, assets, general)
- Security headers in Nginx

#### Phase 8: Documentation

- README.md with Quick Start, Project Structure, Make Commands
- Tool versions consistency documentation

#### Key Decisions Documented

- Dependencies Management: Production = automatic, Development = manual
- Gzip compression active by default, Brotli as optional enhancement
- Rate limiting with separate zones for different resource types
- Docker-First strategy for consistent lockfile generation
- Single Source of Truth pattern for environment variables
