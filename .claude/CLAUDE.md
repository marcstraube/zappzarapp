# Claude Instructions

## Solution Principles

When proposing solutions, prioritize in this order:

1. **Security** – No compromises on security (input validation, secrets handling, OWASP compliance)
2. **Architecture** – Clean separation, SOLID principles, maintainability, testability
3. **Performance** – Efficient solutions, no unnecessary dependencies
4. **Simplicity** – Only after 1-3 are satisfied, choose the simplest approach

In practice this means:

- Choose secure defaults (e.g., prepared statements instead of string concatenation)
- Prefer dependency injection over global state
- Favor immutable data structures where appropriate
- Use established patterns (Repository, Service Layer, DTOs)
- Avoid quick fixes that create technical debt
- For trade-offs: explain options with pros/cons

## Project Structure

```
src/php/App/           → Main PHP application
src/php/DevDashboard/  → Dev Dashboard (separate module)
src/node/backend/      → Node.js backend (Express API)
src/node/frontend/     → Node.js frontend (optional, e.g., Nuxt/Next.js)
resources/             → Frontend assets (JS/CSS/Images)
templates/             → PHP templates (app/, dev-dashboard/)
tests/php/, tests/node/→ Tests mirror src/ structure
tests/goss/            → Container tests (Goss YAML specs)
documentation/         → Project documentation (new docs go here!)
documentation/CHANGELOG.md → Boilerplate changelog
docker/                → Docker configurations
```

## Key Make Targets

```
make up / make down    → Start/stop containers
make check             → All quality checks (before commit!)
make test              → Run all tests
make goss-test         → Container tests with Goss
make goss-test-matrix  → Test all configuration presets
make fresh             → Rebuild everything
make logs              → Show container logs
make build-php         → Rebuild PHP container
make build-node        → Rebuild Node container
```

## Architecture

- DevDashboard is a standalone module (own routes, controllers, services)
- Vite for frontend build (resources/ → public/build/)
- Multi-DB support: PostgreSQL (default), MariaDB (optional)

## Code Style

- PHP: PSR-12 (PHP-CS-Fixer), PHPStan level max
- Node: ESLint + Prettier, TypeScript strict mode
- No `any` in TypeScript, no `@var` without type hint in PHP
- Code comments and documentation always in English!

## Test Conventions

- PHP: tests/php/{Module}/Unit/ and tests/php/{Module}/Feature/
- Node: tests/node/backend/unit/ and tests/node/backend/integration/
- Test class suffix: *Test.php / *.test.ts
- Coverage reports: build/coverage/
- New code always requires corresponding tests (Unit/Feature)

## Slash Commands

Available commands in `.claude/commands/`:

| Command | Purpose |
|---------|---------|
| `/plan` | Create implementation plan before coding |
| `/commit` | Guided commit workflow with quality checks |
| `/changelog` | Generate changelog from session logs |
| `/sync-check` | Check configuration files for sync |
| `/test` | Smart test runner (detects changed files) |
| `/status` | Project overview (Git, Docker, backlog) |
| `/quality-audit` | Comprehensive quality audit with progress tracking |
| `/security-audit` | Security audit of all components |
| `/docs-audit` | Documentation validation |
| `/docs-review` | Interactive documentation review |

## Git & Commits

- Do not commit unless the user explicitly requests it
- Use `/commit` for the guided commit workflow
- Run `/sync-check` before commits that touch configuration files

## Bash Commands

- **Single commands instead of chaining**: Use separate Bash calls instead of `cmd1 && cmd2 && cmd3`. Chained commands require manual confirmation, single ones don't.
- **Better error handling**: With single commands, errors can be handled precisely instead of the entire chain aborting.

## Error Prevention

- Containers not running? → `make up` first
- Composer/pnpm changes always via `make composer`/`make pnpm`, never directly in composer.json/package.json
- After changes to Dockerfiles, compose.*, entrypoints, php.ini or other Docker configurations: rebuild containers (`make build-*`) and restart (`make down && make up`) for changes to take effect
- For problems with Make commands or Docker: analyze and fix the root cause! Never manually edit files to work around tooling issues

## Session Log

Maintain a session log in `.claude/sessions/session-YYYY-MM-DD-HHMM.md`:

- **For every file change**: timestamp, action (add/modify/delete/move), file, purpose
- **For learnings**: document insights, decisions, trade-offs
- **Don't log**: unimportant intermediate communication, read-only access

The log serves as the basis for `/changelog` to generate changelog entries.

On session start: create new log. On session continuation: continue using existing log.

## Hooks

Automatic hooks in `settings.local.json`:

- **PreToolUse**: Warning when containers not running (for test commands)
- **PostToolUse**: Notification via ntfy after task completion

## Miscellaneous

- Answer user questions directly. Do not make unsolicited changes.
