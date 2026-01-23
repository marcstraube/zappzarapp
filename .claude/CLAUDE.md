<!-- zappzarapp-boilerplate-claude -->

# Claude Instructions

## Session Auto-Start

**At conversation start (first user message), automatically:**

1. Find and read last session:
   `find .claude/sessions -name "session-*.md" -type f | xargs ls -1t | head -1`
2. Extract: Goal, Summary, References
3. Run `/tasks --list` for open items (respects configured storage mode)
4. Create new session from `.zappzarapp/ai/templates/SESSION-TEMPLATE.md`

**If fresh context (new conversation):**

1. Brief user on context (previous session, tasks)
2. Ask: "What would you like to work on?"

**If continued from context compression:**

1. Find and continue previous session file (same task-slug)
2. Continue working silently

**Skip steps 5-6 if:** User's first message is a direct task (then create
session silently and start working).

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

**Update DURING work, not just at the end:**

- After each significant change: add to Changes table
- After each decision: add to Decisions section
- After each commit: note commit hash in Changes or Summary
- After discovering something: add to Learnings

**Rule of thumb:** If you completed a todo item, update the session log.

**Subagents:** Do NOT update session file directly. Report changes back to main
agent, who updates centrally (prevents conflicts).

### Knowledge File Updates

**Write learnings, decisions, and references IMMEDIATELY when discovered:**

| Discovery                       | Action                    |
| ------------------------------- | ------------------------- |
| New insight / gotcha / pattern  | Append to `LEARNINGS.md`  |
| Architecture decision made      | Add ADR to `DECISIONS.md` |
| Useful documentation link found | Add to `REFERENCES.md`    |

Session files are not committed (lost on context overflow). Knowledge files are
committed (persistent). In session file, only note "Added learning: <title>".

### Ending a Session

1. Complete session log: Fill in `## Summary`
2. Verify learnings/decisions were written to knowledge files
3. Tell user: "Please enter `/clear` for fresh context."

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

**Note:** Task management is handled via `/tasks` command with 4-tier model:

| Flag           | Target                 | Use Case                     |
| -------------- | ---------------------- | ---------------------------- |
| (default)      | origin repo            | Your project tasks           |
| `--upstream`   | upstream remote        | Contribute to forked project |
| `--zappzarapp` | marcstraube/zappzarapp | Boilerplate feature requests |
| `--private`    | ~/.local/share/        | Personal, offline tasks      |

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

---

## Agent-Workflow

**Before any implementation task:** Read `.claude/agents/workflow.md` to
determine scope (Trivial/Small/Medium/Large) and select appropriate workflow.

Agents are selected automatically based on file count, complexity, and
languages. The workflow.md contains scope detection, agent roles, pre-flight
checks, and user checkpoints.

**Project context:** See `.claude/context/project.md` for architecture and test
conventions.

## Code Standards

See `.zappzarapp/standards/` for language-specific rules (shared across all AI
agents):

| File              | Content                      |
| ----------------- | ---------------------------- |
| `php.md`          | Suppressions, PHPStan, PHPMD |
| `node.md`         | ESLint, Prettier, TypeScript |
| `sql.md`          | Dialects, sqlfluff           |
| `shell.md`        | ShellCheck, Bash/POSIX       |
| `markdown.md`     | Code blocks, markdownlint    |
| `docker.md`       | Hadolint                     |
| `make-targets.md` | All lint/test/fix targets    |

## Project Structure

```text
.ai/                   → Project AI knowledge (decisions, learnings, references)
.claude/               → Claude tooling (agents, commands, sessions)
.zappzarapp/           → Boilerplate config (standards, docs, changelog)
docker/                → Docker configurations
documentation/         → Project documentation (CHANGELOG)
resources/             → Frontend assets (JS/CSS/Images)
src/node/              → Node.js code (backend/, frontend/)
src/php/               → PHP code (App/, DevDashboard/)
templates/             → PHP templates (app/, dev-dashboard/)
tests/                 → Tests (goss/, node/, php/)
```

**Claude-specific folders:**

| Folder              | Purpose                      | Committed |
| ------------------- | ---------------------------- | --------- |
| `.claude/agents/`   | Agent workflow documentation | Yes       |
| `.claude/commands/` | Slash command definitions    | Yes       |
| `.claude/sessions/` | Session logs (YYYY/MM/)      | No        |
| `.claude/temp/`     | Agent work files             | No        |
| `.ai/`              | Project knowledge files      | Yes       |
| `.zappzarapp/ai/`   | Boilerplate knowledge        | Yes       |

## Key Make Targets

```bash
make up / make down    → Start/stop containers
make check             → All quality checks
make test              → Run all tests
make fresh             → Rebuild everything
```

For all lint/test/fix targets: See `.zappzarapp/standards/make-targets.md`

## Slash Commands

Available commands in `.claude/commands/`:

| Command       | Purpose                                                    |
| ------------- | ---------------------------------------------------------- |
| `/status`     | Project overview (Git, Docker, tasks)                      |
| `/tasks`      | Task management (4-tier: zappzarapp/upstream/repo/private) |
| `/commit`     | Guided commit workflow with quality checks                 |
| `/audit`      | Project audit (quality, security, docs) - quick/full       |
| `/sync-check` | Check configuration files for sync                         |
| `/learnings`  | View, search, and aggregate project learnings              |
| `/research`   | Research topics (local knowledge + optional web)           |
| `/optimize`   | Self-optimization of config, learnings, commands           |

## Git & Commits

### Feature-Branch Workflow

Each task is developed on its own feature branch:

```text
git checkout -b feature/<task-slug>
    ↓
[Development + Commits + CHANGELOG entries]
    ↓
Claude: "Branch ready for review"
    ↓
User reviews → Merge → `/tasks --close <id>`
```

**Branch naming:** `feature/<slug>`, `fix/<slug>`, `refactor/<slug>`

### Commit Rules

- Commit regularly on feature branches (intermediate commits encouraged)
- Use `/commit` for the guided commit workflow
- Never commit directly to develop or master — only via feature branch merges

## Error Prevention

- Containers not running? → `make up` first
- After Docker config changes: `make build-*` + `make down && make up`
- Never commit lockfiles (`composer.lock`, `pnpm-lock.yaml`)
- For Make/Docker problems: fix root cause, don't work around

**Package manager commands** — always use make targets:

| ❌ Forbidden         | ✅ Use instead                  |
| -------------------- | ------------------------------- |
| `composer require X` | `make composer CMD="require X"` |
| `pnpm add X`         | `make pnpm CMD="add X"`         |

Same pattern for `install`, `update`, `remove`. Info commands allowed
(`composer show`, `pnpm list`).

## Hooks

Default hooks in `settings.json`:

- **PostToolUse (Edit)**: Reminder to rebuild after Docker config file changes

Example personal hooks in `settings.local.json`:

- **PreToolUse (Bash)**: Task/CHANGELOG reminder before commits
- **PostToolUse (TodoWrite)**: Session log update reminder

## Language

- **English always**: Documentation, code, technical content, task summaries,
  error descriptions, implementation details
- **User's language**: Only for direct questions, confirmations, process
  explanations

Rule of thumb: If it could be copy-pasted into documentation, use English.

## Communication

Rules for effective collaboration:

- **Answer questions first**: When asked a question, ALWAYS answer and wait for
  response BEFORE making changes
- **On errors**: Provide error message + context
- **On options**: State preference or say "you decide"
- **Limit scope**: "Only X, not Y" when boundaries matter
- **Feedback**: Brief "worked" or "problem with X" helps
