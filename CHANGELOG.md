# zappzarapp - Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - TBD

Initial public release.

### Added

- **Dual-language developer platform** — PHP 8.5 and Node.js 24 as equal
  citizens: complete Docker infrastructure, toolchain, and IDE integration
  around your application code, with no runtime dependencies added to it
- **16 pre-configured Docker services** — Nginx, PHP-FPM, Node backend/frontend
  (Vite HMR), PostgreSQL, MariaDB, Redis, Mercure, Meilisearch, Elasticsearch,
  Mailpit, SeaweedFS, RabbitMQ and more; optional services are one `.env` toggle
  away
- **7 stack presets** — from `fullstack` over `php-only`/`node-only`,
  `framework` (Next.js, Nuxt, SvelteKit, Remix scaffolding), `assets` and `idle`
  down to `minimal` static serving
- **One-command setup** — `make init` + `make setup` build images, generate SSL
  certificates and secrets, install dependencies, run migrations, and start the
  stack; 258 make targets across 17 categories (`make help`)
- **Security by default** — file-based Docker secrets, internal TLS between
  services, network segmentation, every container non-root with read-only rootfs
  in the production presets, encryption and backup helpers, GDPR tooling (audit
  logging, retention policy)
- **Production parity** — the same stack from development to deployment: Compose
  production overlay and a Kubernetes Helm chart with all services
  PSS-`restricted`
- **Integrated quality toolchain** — PHPStan (level 8), PHPMD, Rector,
  PHP-CS-Fixer, PHPUnit; TypeScript (strict), ESLint + sonarjs, Prettier,
  Vitest, Knip, depcheck; Hadolint, ShellCheck, SQLFluff, Markdownlint and YAML
  validation for infrastructure; GOSS container tests and BATS Makefile tests;
  CaptainHook git hooks with conventional-commit validation
- **Security scanning** — composer/pnpm audit, Trivy image + SBOM scans, Semgrep
  and OWASP ZAP wiring for CI
- **CI/CD** — GitHub Actions workflows (quality gate, coverage upload, security
  scans, self-hosted Renovate) and a GitLab CI mirror configuration
- **IDE integration** — PHPStorm/WebStorm and VS Code pre-configured: run
  configurations, Xdebug, database connections, tasks and extension
  recommendations
- **AI-assisted development** — shipped agent tooling (`.claude/` skills,
  agents, context split) and a tool-neutral root `AGENTS.md`; templates are
  swapped to project-owned versions on first setup
- **Ecosystem packages pre-wired** — `zappzarapp/security`,
  `zappzarapp/devtoolbar`, `zappzarapp/audit-logger` (Packagist),
  `@zappzarapp/audit-logger` (npm) — each independently usable
- **Upstream sync** — `make boilerplate-sync` pulls infrastructure updates into
  derived projects while preserving project-owned files and keeping boilerplate
  release tags out
- **Documentation** — 40+ guides in `.zappzarapp/docs/` (getting started,
  development, testing, infrastructure, security), served via the Dev Dashboard

[1.0.0]: https://github.com/marcstraube/zappzarapp/releases/tag/v1.0.0
