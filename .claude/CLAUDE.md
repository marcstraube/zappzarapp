<!-- zappzarapp-boilerplate-claude -->

# Claude Instructions

## Session Auto-Start

**At conversation start (first user message), automatically:**

1. Find and read last session:
   `find .claude/sessions -name "session-*.md" -type f | xargs ls -1t | head -1`
2. Extract: Goal, Summary, References
3. Read backlog for open items (see "Knowledge File Paths" for resolution)
4. Create new session in year/month folder:
   `.claude/sessions/YYYY/MM/session-YYYY-MM-DD-HHMM-<task-slug>.md`
   - Use template from `.zappzarapp/ai/templates/SESSION-TEMPLATE.md`
5. Brief user on context (previous session, backlog)
6. Ask: "What would you like to work on?"

**Skip if:** User's first message is a direct task (then create session silently
and start working).

**Session path format:**
`.claude/sessions/YYYY/MM/session-YYYY-MM-DD-HHMM-<task-slug>.md`

Example: `.claude/sessions/2026/01/session-2026-01-21-1311-agent-workflow.md`

## Session End

When ending a session (user confirms):

1. Update session log with Summary, Open Items, Next Steps
2. Tell user: "Bitte `/clear` eingeben für neuen Kontext."
3. New conversation will auto-start new session

**Note:** `/clear` is a built-in CLI command - only the user can execute it.

## Context Overflow / Continued Sessions

**When a session is "continued from previous conversation" after context
overflow:**

1. **Find previous session by task-slug from Summary:**

   ```bash
   # Extract task theme from Summary, search for matching slug
   find .claude/sessions -name "session-*<slug>*.md" -type f | xargs ls -1t | head -1
   ```

2. **Fallback if unclear:**
   - Filter by current branch:
     `grep -rl "Branch.*$(git branch --show-current)" .claude/sessions/`
   - Filter by time window (last 2h)
   - If still ambiguous: Ask user which session to continue
3. **Create continuation session file** in current year/month with same slug
4. Continue with normal session logging

**This is NOT optional** — the summarized context loses session file updates!

## Session Log Updates

**Update the session log DURING work, not just at the end:**

- After each significant change: add to Changes table
- After each decision: add to Decisions section
- After each commit: note commit hash in Changes or Summary
- After discovering something: add to Learnings

**Rule of thumb:** If you completed a todo item, update the session log.

**Subagents:** Do NOT update session file directly. Report changes back to main
agent, who updates centrally (prevents conflicts).

---

## Solution Principles

When proposing solutions, prioritize in this order:

1. **Security** – No compromises on security (input validation, secrets
   handling, OWASP compliance)
2. **Architecture** – Clean separation, SOLID principles, maintainability,
   testability
3. **Performance** – Efficient solutions, no unnecessary dependencies
4. **Simplicity** – Only after 1-3 are satisfied, choose the simplest approach

In practice this means:

- Choose secure defaults (e.g., prepared statements instead of string
  concatenation)
- Prefer dependency injection over global state
- Favor immutable data structures where appropriate
- Use established patterns (Repository, Service Layer, DTOs)
- Avoid quick fixes that create technical debt
- For trade-offs: explain options with pros/cons

---

## Knowledge File Paths

Different files have different layer support:

**BACKLOG — 3 Layer:**

| Priority | Path                         | Condition                        |
| -------- | ---------------------------- | -------------------------------- |
| 1        | `~/.local/share/zappzarapp/` | `.claude/config.local.md` exists |
| 2        | `.ai/`                       | `.ai/BACKLOG.md` exists          |
| 3        | `.zappzarapp/ai/`            | fallback                         |

**LEARNINGS, DECISIONS, REFERENCES — 2 Layer:**

| Priority | Path              | Condition                 |
| -------- | ----------------- | ------------------------- |
| 1        | `.ai/`            | `.ai/LEARNINGS.md` exists |
| 2        | `.zappzarapp/ai/` | fallback                  |

**CHANGELOG — 2 Layer (different paths):**

| Priority | Path             | Condition                           |
| -------- | ---------------- | ----------------------------------- |
| 1        | `documentation/` | `documentation/CHANGELOG.md` exists |
| 2        | `.zappzarapp/`   | fallback                            |

**Note:** Personal layer only exists for BACKLOG (cross-project task tracking).

---

## Agent-Workflow

For complex tasks, use specialized agents. See `.claude/agents/` for details:

| File            | Content                                      |
| --------------- | -------------------------------------------- |
| `workflow.md`   | Scope decision, Main Agent responsibilities  |
| `architect.md`  | Agent A: Analysis, plan creation             |
| `coder.md`      | Agent B: Implementation (B1/B2 for PHP/Node) |
| `reviewer.md`   | Agent C: Code review, feedback loop          |
| `documenter.md` | Agent D: Documentation check & update        |

## Code Standards

See `.zappzarapp/standards/` for language-specific rules (shared across all AI
agents):

| File              | Content                      |
| ----------------- | ---------------------------- |
| `php.md`          | Suppressions, PHPStan, PHPMD |
| `node.md`         | ESLint, Prettier, TypeScript |
| `sql.md`          | Dialekte, sqlfluff           |
| `markdown.md`     | Code blocks, markdownlint    |
| `docker.md`       | Hadolint                     |
| `make-targets.md` | All lint/test/fix targets    |

## Project Structure

```text
.ai/                   → Project AI knowledge (team backlog, decisions, learnings)
.zappzarapp/           → Boilerplate config (standards, docs, changelog)
docker/                → Docker configurations
resources/             → Frontend assets (JS/CSS/Images)
src/node/backend/      → Node.js backend (Express API)
src/node/frontend/     → Node.js frontend (optional, e.g., Nuxt/Next.js)
src/php/App/           → Main PHP application
src/php/DevDashboard/  → Development Dashboard (separate module)
templates/             → PHP templates (app/, dev-dashboard/)
tests/goss/            → Container tests (Goss YAML specs)
tests/node/            → Vitest tests (mirrors src/node/)
tests/php/             → PHPUnit tests (mirrors src/php/)
```

## Key Make Targets

```bash
make up / make down    → Start/stop containers
make check             → All quality checks
make test              → Run all tests
make fresh             → Rebuild everything
```

For all lint/test/fix targets: See `.zappzarapp/standards/make-targets.md`

## Architecture

- DevDashboard is a standalone module (own routes, controllers, services)
- Vite for frontend build (resources/ → public/build/)
- Multi-DB support: PostgreSQL (default), MariaDB (optional)
- Code comments and documentation always in English!

## Test Conventions

- PHP: tests/php/{Module}/Unit/ and tests/php/{Module}/Feature/
- Node: tests/node/backend/unit/ and tests/node/backend/integration/
- Test class suffix: _Test.php /_.test.ts
- Coverage reports: build/coverage/
- New code always requires corresponding tests (Unit/Feature)

## Slash Commands

Available commands in `.claude/commands/`:

| Command       | Purpose                                              |
| ------------- | ---------------------------------------------------- |
| `/status`     | Project overview (Git, Docker, backlog)              |
| `/backlog`    | Manage backlog tasks (add, list, prioritize)         |
| `/commit`     | Guided commit workflow with quality checks           |
| `/audit`      | Project audit (quality, security, docs) - quick/full |
| `/sync-check` | Check configuration files for sync                   |
| `/learnings`  | View, search, and aggregate project learnings        |
| `/research`   | Research topics (local knowledge + optional web)     |
| `/optimize`   | Self-optimization of config, learnings, commands     |

**Note:** Code review and tests are handled by Agent C (Reviewer) in the agent
workflow. Use `make test` for manual testing.

## Git & Commits

### Feature-Branch Workflow

Jeder Task wird auf einem eigenen Feature-Branch entwickelt:

```text
git checkout -b feature/<task-slug>
    ↓
[Entwicklung + Zwischen-Commits + CHANGELOG-Einträge]
    ↓
User-Review (gesamter Branch)
    ↓
Merge → BACKLOG-Task entfernen
```

**Branch-Naming:** `feature/<slug>`, `fix/<slug>`, `refactor/<slug>`

### Commit-Regeln

- Do not commit unless the user explicitly requests it
- Use `/commit` for the guided commit workflow
- Run `/sync-check` before commits that touch configuration files

### CHANGELOG & BACKLOG Timing

| Phase                              | CHANGELOG             | BACKLOG           |
| ---------------------------------- | --------------------- | ----------------- |
| Zwischen-Commit (Feature-Branch)   | ✅ Eintrag hinzufügen | ❌ Task bleibt    |
| Merge to main (nach User-Approval) | Bereits eingetragen   | ✅ Task entfernen |

### Task-Abschluss Workflow

1. Claude erledigt Task auf Feature-Branch
2. Claude informiert User: "Branch ready for review"
3. User reviewed den gesamten Branch
4. User approved → Merge + BACKLOG-Task entfernen

## Bash Commands

- **Single commands instead of chaining**: Use separate Bash calls instead of
  `cmd1 && cmd2 && cmd3`. Chained commands require manual confirmation, single
  ones don't.
- **Better error handling**: With single commands, errors can be handled
  precisely instead of the entire chain aborting.
- **Use `git mv` for file operations**: Prefer `git mv` over plain `mv` for
  renaming/moving tracked files. This updates the Git index immediately and
  provides better IDE integration.

## Error Prevention

- Containers not running? → `make up` first
- Composer/pnpm changes always via `make composer`/`make pnpm`, never directly
  in composer.json/package.json
- **Never commit lockfiles** (`composer.lock`, `pnpm-lock.yaml`) - this is a
  boilerplate, users generate their own
- After changes to Dockerfiles, compose._, entrypoints, php.ini or other Docker
  configurations: rebuild containers (`make build-_`) and restart (`make down &&
  make up`) for changes to take effect
- For problems with Make commands or Docker: analyze and fix the root cause!
  Never manually edit files to work around tooling issues

## Forbidden Commands

**NEVER use local package managers for dependency changes.** Always use make
targets:

| ❌ Forbidden                    | ✅ Use instead                  |
| ------------------------------- | ------------------------------- |
| `composer install`              | `make composer-install`         |
| `composer update`               | `make composer-update`          |
| `composer require X`            | `make composer CMD="require X"` |
| `composer remove X`             | `make composer CMD="remove X"`  |
| `pnpm install`                  | `make pnpm-install`             |
| `pnpm update`                   | `make pnpm-update`              |
| `pnpm add X`                    | `make pnpm CMD="add X"`         |
| `pnpm remove X`                 | `make pnpm CMD="remove X"`      |
| `npm install/add/update/remove` | Use pnpm equivalents above      |

**Allowed** (info only, no changes): `composer --version`, `composer show`,
`pnpm list`, etc.

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

Session log contains only: Goal, Branch, Changes, References, Summary.

Use `.zappzarapp/ai/templates/SESSION-TEMPLATE.md` as the base.

### Knowledge File Updates

**Write learnings, decisions, and references IMMEDIATELY when discovered — not
at session end.**

| Discovery                       | Action                    |
| ------------------------------- | ------------------------- |
| New insight / gotcha / pattern  | Append to `LEARNINGS.md`  |
| Architecture decision made      | Add ADR to `DECISIONS.md` |
| Useful documentation link found | Add to `REFERENCES.md`    |

**Why immediately?**

- Session files are not committed (lost on context overflow)
- Knowledge files are committed (persistent across sessions)
- Prevents knowledge loss

**Session file:** Only note "Added learning: <title>" as reference, not the full
content.

**Path:** Use resolved path from "Knowledge File Paths" section above.

### Ending a Session

1. Complete session log: Fill in `## Summary`
2. Verify all learnings/decisions were written to knowledge files (should
   already be done during session)
3. Tell user: "Bitte `/clear` eingeben für neuen Kontext."

### Knowledge Management

| Layer       | Location                     | Purpose               | Git Status | Files                                     |
| ----------- | ---------------------------- | --------------------- | ---------- | ----------------------------------------- |
| Personal    | `~/.local/share/zappzarapp/` | Private notes         | Outside    | BACKLOG only                              |
| Project     | `.ai/`                       | Team-shared knowledge | Committed  | BACKLOG, LEARNINGS, DECISIONS, REFERENCES |
| Boilerplate | `.zappzarapp/ai/`            | Boilerplate knowledge | Committed  | BACKLOG, LEARNINGS, DECISIONS, REFERENCES |

**Note:** Personal layer only supports BACKLOG (cross-project task tracking).
Other knowledge files (LEARNINGS, DECISIONS, REFERENCES) are always project or
boilerplate level.

See "Knowledge File Paths" section for full resolution logic.

Session logs are minimal (Goal, Changes, References, Summary).

### Folder Structure

**`.zappzarapp/` (boilerplate config, committed):**

| Folder/File          | Purpose                             |
| -------------------- | ----------------------------------- |
| `ai/`                | Knowledge files (all AI agents)     |
| `standards/`         | Coding standards (all AI agents)    |
| `docs/`              | Boilerplate documentation           |
| `CHANGELOG.md`       | Boilerplate version history         |
| `CLAUDE.template.md` | Generic CLAUDE.md for user projects |

**`.claude/` (Claude tooling):**

| Folder      | Purpose                      | Committed |
| ----------- | ---------------------------- | --------- |
| `CLAUDE.md` | Claude instructions          | Yes       |
| `agents/`   | Agent workflow documentation | Yes       |
| `commands/` | Slash command definitions    | Yes       |
| `sessions/` | Session logs (YYYY/MM/)      | No        |
| `state/`    | Persistent state (audit)     | No        |
| `cache/`    | Temporary data               | No        |
| `temp/`     | Agent work files             | No        |
| `reports/`  | Generated audit reports      | No        |

**`.ai/` (project AI knowledge, created by make setup):**

| File            | Purpose                       |
| --------------- | ----------------------------- |
| `BACKLOG.md`    | Team backlog                  |
| `DECISIONS.md`  | Architecture decisions (ADRs) |
| `LEARNINGS.md`  | Project learnings             |
| `REFERENCES.md` | Documentation links           |

**`documentation/` (project docs, created by make setup):**

| File           | Purpose                 |
| -------------- | ----------------------- |
| `CHANGELOG.md` | Project version history |

**CHANGELOG path resolution:**

- Project: `documentation/CHANGELOG.md` (if exists)
- Boilerplate: `.zappzarapp/CHANGELOG.md` (fallback)

## Hooks

Default hooks in `settings.json`:

- **PostToolUse (Edit)**: Reminder to rebuild after Docker config file changes

Example personal hooks in `settings.local.json`:

- **PreToolUse (Bash)**: BACKLOG/CHANGELOG reminder before commits
- **PostToolUse (TodoWrite)**: Session log update reminder

## Task Notifications (ntfy)

Send notifications at key points during agent workflow.

**Setup:** Copy `.claude/config.local.md.example` to `.claude/config.local.md`
and set your ntfy topic.

**Read topic from** `.claude/config.local.md` before sending notifications.

**Task completed:**

```bash
curl -s -d "[zappzarapp] ✓ <task-description>" ntfy.sh/<NTFY_TOPIC>
```

**Waiting for user input** (plan review, errors, decisions):

```bash
curl -s -H "Priority: high" -H "Tags: hourglass" \
  -d "[zappzarapp] ⏳ <context> - waiting for input" ntfy.sh/<NTFY_TOPIC>
```

Examples:

- `⏳ Plan ready for review`
- `⏳ Undocumented warning - decision needed`
- `⏳ Reviewer found errors - user input required`
- `✓ SearchService implementation completed`

## Language

- **Documentation**: Always in English (agents/, standards/, commands/, code
  comments, CHANGELOG, README, etc.)
- **Communication**: In user's language (respond in the language the user uses)

## Communication

Rules for effective collaboration:

- **Answer questions first**: When asked a question, ALWAYS answer and wait for
  response BEFORE making changes
- **On errors**: Provide error message + context
- **On options**: State preference or say "you decide"
- **Limit scope**: "Only X, not Y" when boundaries matter
- **Feedback**: Brief "worked" or "problem with X" helps
