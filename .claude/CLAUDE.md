# Claude Instructions

## Session Auto-Start

**At conversation start (first user message), automatically:**

1. Find and read last session: `ls -1t .claude/sessions/session-*.md | grep -v TEMPLATE | head -1`
2. Extract: Goal, Open Items, Session Summary, Next Steps
3. Read `.claude/BACKLOG.md` for high-priority items
4. Create new session log from `SESSION-TEMPLATE.md` with timestamp
5. Brief user on context (previous session, open items, backlog)
6. Ask: "What would you like to work on?"

**Skip if:** User's first message is a direct task (then create session silently and start working).

## Session End

When ending a session (user confirms):
1. Update session log with Summary, Open Items, Next Steps
2. Tell user: "Bitte `/clear` eingeben für neuen Kontext."
3. New conversation will auto-start new session

**Note:** `/clear` is a built-in CLI command - only the user can execute it.

---

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

```text
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

```bash
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
| `/status` | Project overview (Git, Docker, backlog) |
| `/review` | Review changes before committing |
| `/test` | Smart test runner (detects changed files) |
| `/commit` | Guided commit workflow with quality checks |
| `/changelog` | Generate changelog from session logs |
| `/learnings` | View, search, and aggregate project learnings |
| `/backlog` | Manage backlog tasks (add, list, prioritize) |
| `/sync-check` | Check configuration files for sync |
| `/quality-audit` | Comprehensive quality audit with progress tracking |
| `/security-audit` | Security audit of all components |
| `/docs-audit` | Documentation validation |
| `/docs-review` | Interactive documentation review |
| `/optimize` | Self-optimization of config, learnings, commands |

## Git & Commits

- Do not commit unless the user explicitly requests it
- **After each completed todo**: Inform user that changes are ready for review, then wait for approval before committing
- One todo = one focused commit (prevents mixed changes that need splitting later)
- Use `/commit` for the guided commit workflow
- Run `/sync-check` before commits that touch configuration files
- **Before committing**: Update `BACKLOG.md` (remove completed tasks) and `CHANGELOG.md` (add new version entry)

## Bash Commands

- **Single commands instead of chaining**: Use separate Bash calls instead of `cmd1 && cmd2 && cmd3`. Chained commands require manual confirmation, single ones don't.
- **Better error handling**: With single commands, errors can be handled precisely instead of the entire chain aborting.

## Error Prevention

- Containers not running? → `make up` first
- Composer/pnpm changes always via `make composer`/`make pnpm`, never directly in composer.json/package.json
- **Never commit lockfiles** (`composer.lock`, `pnpm-lock.yaml`) - this is a boilerplate, users generate their own
- After changes to Dockerfiles, compose.*, entrypoints, php.ini or other Docker configurations: rebuild containers (`make build-*`) and restart (`make down && make up`) for changes to take effect
- For problems with Make commands or Docker: analyze and fix the root cause! Never manually edit files to work around tooling issues

## Forbidden Commands

**NEVER use local package managers for dependency changes.** Always use make targets:

| ❌ Forbidden | ✅ Use instead |
|--------------|----------------|
| `composer install` | `make composer-install` |
| `composer update` | `make composer-update` |
| `composer require X` | `make composer CMD="require X"` |
| `composer remove X` | `make composer CMD="remove X"` |
| `pnpm install` | `make pnpm-install` |
| `pnpm update` | `make pnpm-update` |
| `pnpm add X` | `make pnpm CMD="add X"` |
| `pnpm remove X` | `make pnpm CMD="remove X"` |
| `npm install/add/update/remove` | Use pnpm equivalents above |

**Allowed** (info only, no changes): `composer --version`, `composer show`, `pnpm list`, etc.

**Why blocked?**
- Ensures correct PHP/Node version (container vs local mismatch)
- Guarantees consistent environment across team
- Proper volume mounts and permissions
- Lockfiles generated with correct platform

## Session Workflow

### Starting a Session

Sessions start automatically (see "Session Auto-Start" at top of this file).

### Session Log

Maintain a session log in `.claude/sessions/session-YYYY-MM-DD-HHMM.md`:

- **For slash commands**: log each command used in `## Commands Used` table
- **For every file change**: timestamp, action (add/modify/delete/move), file, purpose
- **For learnings**: document insights in `## Learnings` section
- **For decisions**: record trade-offs and reasoning
- **Don't log**: unimportant intermediate communication, read-only access

Use `SESSION-TEMPLATE.md` as the base for new session logs.

### Ending a Session

Before ending:
- Fill in `## Session Summary` with what was accomplished
- List `## Open Items` for follow-up
- Add `**Next Steps**` recommendations
- Run `/learnings --sync` to aggregate new learnings

### Knowledge Management

- **LEARNINGS.md**: Central repository of project knowledge
- **BACKLOG.md**: Pending tasks and improvements
- Session logs serve as basis for `/changelog`

## Hooks

Automatic hooks in `settings.local.json`:

- **PreToolUse**: Warning when containers not running (for test commands)
- **PostToolUse**: Notification via ntfy after task completion

## Communication

Rules for effective collaboration:

- **Answer questions first**: When asked a question, ALWAYS answer and wait for response BEFORE making changes
- **On errors**: Provide error message + context
- **On options**: State preference or say "you decide"
- **Limit scope**: "Only X, not Y" when boundaries matter
- **Feedback**: Brief "worked" or "problem with X" helps

## Miscellaneous

- Answer user questions directly. Do not make unsolicited changes.
