# Claude Instructions

## Session Auto-Start

**At conversation start (first user message), automatically:**

1. Find and read last session:
   `find .claude/sessions -name "session-*.md" -type f | xargs ls -1t | head -1`
2. Extract: Goal, Summary, References
3. Read `.ai/BACKLOG.md` for open items (if exists)
4. Create new session in year/month folder:
   `.claude/sessions/YYYY/MM/session-YYYY-MM-DD-HHMM-<task-slug>.md`
5. Brief user on context (previous session, backlog)
6. Ask: "What would you like to work on?"

**Skip if:** User's first message is a direct task (then create session silently
and start working).

**Session path format:**
`.claude/sessions/YYYY/MM/session-YYYY-MM-DD-HHMM-<task-slug>.md`

## Session End

When ending a session (user confirms):

1. Update session log with Summary, Open Items, Next Steps
2. Tell user: "Please type `/clear` for new context."
3. New conversation will auto-start new session

**Note:** `/clear` is a built-in CLI command - only the user can execute it.

## Context Overflow / Continued Sessions

**When a session is "continued from previous conversation" after context
overflow:**

1. **Find previous session by task-slug from Summary:**

   ```bash
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

## Agent-Workflow

For complex tasks, use specialized agents. See `.claude/agents/` for details:

| File            | Content                                     |
| --------------- | ------------------------------------------- |
| `workflow.md`   | Scope decision, Main Agent responsibilities |
| `architect.md`  | Agent A: Analysis, plan creation            |
| `coder.md`      | Agent B: Implementation                     |
| `reviewer.md`   | Agent C: Code review, feedback loop         |
| `documenter.md` | Agent D: Documentation check & update       |

## Code Standards

See `.zappzarapp/standards/` for language-specific rules:

| File              | Content                     |
| ----------------- | --------------------------- |
| `php.md`          | PHP coding standards        |
| `node.md`         | TypeScript/ESLint standards |
| `sql.md`          | SQL dialect standards       |
| `shell.md`        | ShellCheck, Bash/POSIX      |
| `markdown.md`     | Markdown linting            |
| `docker.md`       | Dockerfile standards        |
| `make-targets.md` | Available make targets      |

---

## Project Structure

<!-- TODO: Customize for your project -->

```text
.ai/                → Project AI knowledge (BACKLOG, LEARNINGS, etc.)
.zappzarapp/        → Boilerplate config & docs
docker/             → Docker configurations
src/                → Source code
tests/              → Test files
```

## Key Make Targets

<!-- TODO: Customize for your project -->

```bash
make up / make down    → Start/stop containers
make check             → All quality checks
make test              → Run all tests
```

---

## Slash Commands

Available commands in `.claude/commands/`:

| Command      | Purpose                                      |
| ------------ | -------------------------------------------- |
| `/status`    | Project overview (Git, Docker, backlog)      |
| `/backlog`   | Manage backlog tasks (add, list, prioritize) |
| `/commit`    | Guided commit workflow with quality checks   |
| `/learnings` | View and manage project learnings            |

---

## Git & Commits

### Commit Rules

- Do not commit unless the user explicitly requests it
- Use `/commit` for the guided commit workflow
- Run `/sync-check` before commits that touch configuration files

### Feature-Branch Workflow

```text
git checkout -b feature/<task-slug>
    ↓
[Development + Commits + CHANGELOG entries]
    ↓
User review (entire branch)
    ↓
Merge → Remove BACKLOG task
```

**Branch-Naming:** `feature/<slug>`, `fix/<slug>`, `refactor/<slug>`

---

## Knowledge Management

### 3-Layer Architecture

| Layer       | Location                     | Purpose               |
| ----------- | ---------------------------- | --------------------- |
| Personal    | `~/.local/share/zappzarapp/` | Private notes         |
| Boilerplate | `.zappzarapp/ai/`            | Boilerplate knowledge |
| Project     | `.ai/`                       | Team-shared knowledge |

Files: BACKLOG.md, LEARNINGS.md, DECISIONS.md, REFERENCES.md

### Session Workflow

1. Capture new knowledge in session log
2. At session end, move relevant items to `.ai/`
3. Team reviews and maintains shared knowledge

### Folder Structure

| Folder              | Purpose                              | Git Status |
| ------------------- | ------------------------------------ | ---------- |
| `.claude/agents/`   | Agent workflow documentation         | Committed  |
| `.claude/commands/` | Slash command definitions            | Committed  |
| `.claude/sessions/` | Session logs (YYYY/MM/)              | Ignored    |
| `.zappzarapp/ai/`   | Boilerplate knowledge                | Committed  |
| `.ai/`              | Project knowledge (created by setup) | Committed  |

---

## Hooks

Default hooks in `settings.json`:

- **PostToolUse (Edit)**: Reminder to rebuild after Docker config file changes

**Note:** `settings.local.json` completely overrides hooks from `settings.json`
(no merging). If you create a local settings file, copy any desired hooks.

---

## Language

- **Documentation**: Always in English
- **Communication**: In user's language (respond in the language the user uses)

## Communication

Rules for effective collaboration:

- **Answer questions first**: When asked a question, ALWAYS answer and wait for
  response BEFORE making changes
- **On errors**: Provide error message + context
- **On options**: State preference or say "you decide"
- **Limit scope**: "Only X, not Y" when boundaries matter
- **Feedback**: Brief "worked" or "problem with X" helps

## Miscellaneous

- Answer user questions directly. Do not make unsolicited changes.
