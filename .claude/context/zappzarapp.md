# Zappzarapp Platform Context

<!-- DO NOT EDIT — this describes the zappzarapp platform itself and is kept in
     sync with upstream via `make boilerplate-sync`. Local edits will be
     overwritten. Document YOUR application in `project.md` instead. -->

Shared context that is the same for every project built with zappzarapp. It
answers "what is the platform" — the tech stack, architecture, and conventions
Claude can assume in any zappzarapp repo.

## Platform Overview

- Dual-language web development platform: PHP 8.5 (PHP-FPM) + Node.js 24
- Security-by-design, GDPR-ready, production-ready from day one
- Docker-based; every service runs in its own container

## Tech Stack

- Backend: PHP 8.5 (PHP-FPM), Node.js 24
- Frontend: Vite, TypeScript, HMR
- Web server: Nginx (reverse proxy, SSL/TLS)
- Databases: PostgreSQL (default), MariaDB (optional)
- Cache: Redis

## Architecture

- Modular design: application code is organised into self-contained modules (own
  routes, controllers, services)
- Network segmentation: internal / external / secure Docker networks
- Vite build pipeline: `resources/` → `public/build/`
- Multi-DB support selected via `DB_TYPE`

## Core Principles

Priority order for every solution: Security → Architecture → Performance →
Simplicity.

- Security first: secrets via Docker Secrets, internal TLS, CSP
- GDPR compliance: audit logging, encryption, retention policies
- Testability: new code always ships with tests (Unit/Feature)
- No quick fixes that create technical debt

## Test Conventions

- PHP: `tests/php/{Module}/Unit/` and `tests/php/{Module}/Feature/`
- Node: `tests/node/backend/unit/` and `tests/node/backend/integration/`
- Test class suffix: `*_Test.php` / `*.test.ts`
- Coverage reports: `build/coverage/`
