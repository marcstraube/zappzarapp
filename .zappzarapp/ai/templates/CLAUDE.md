# Claude Instructions

## Knowledge Files

**Write learnings, decisions, and references IMMEDIATELY when discovered:**

| Discovery                      | Action                             |
| ------------------------------ | ---------------------------------- |
| New insight / gotcha / pattern | Append to `.ai/LEARNINGS.md`       |
| Architecture decision made     | New ADR document under `docs/adr/` |

`LEARNINGS.md` is a fast-capture inbox: mature entries graduate into the regular
documentation during periodic triage.

Knowledge files are committed and team-shared — they are the project's
persistent memory. Do not defer these writes to "later"; context may be
compacted at any time.

**Subagents:** Do NOT write knowledge files directly. Report findings back to
the Main Agent, who writes them centrally (prevents conflicts).

**On fresh conversations:** brief the user on open tasks (`/tasks --list`) and
ask what to work on.

<!-- zappzarapp:first-run-help -->

**First run:** also run `make customize`. If it lists templates that still
contain placeholders, offer to help the user fill them in. Once `make customize`
reports that everything is customized, this guidance has done its job — remove
this paragraph and its `zappzarapp:first-run-help` marker above.

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

**Before any implementation task:** Read `.claude/agents/workflow.md` to
determine scope (Trivial/Small/Medium/Large) and select the appropriate
workflow. The workflow.md contains scope detection and agent roles.

**Use parallel agents when it pays off:** for ≥2 independent, pre-planned tasks
— or a large change spanning many files or multiple languages — dispatch the
specialist coder agents in parallel instead of working through them serially.

**Project context:** `.claude/context/zappzarapp.md` holds platform context
(tech stack, architecture, test conventions) and is synced from upstream — do
not edit it. Describe YOUR application in `.claude/context/project.md`.

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

<!-- zappzarapp:customize: adjust this tree to your project's real layout -->

```text
.ai/                → Project AI knowledge (LEARNINGS inbox)
docs/adr/           → Architecture Decision Records (one file per ADR)
.claude/            → Claude tooling (agents, skills, hooks)
.zappzarapp/        → Boilerplate config & docs
docker/             → Docker configurations
src/                → Source code
tests/              → Test files
```

**Claude-specific folders:**

| Folder            | Purpose                      | Committed |
| ----------------- | ---------------------------- | --------- |
| `.claude/agents/` | Agent workflow documentation | Yes       |
| `.claude/skills/` | Skills (slash commands)      | Yes       |
| `.ai/`            | Project knowledge files      | Yes       |
| `.zappzarapp/ai/` | Boilerplate knowledge        | Yes       |

## Key Make Targets

<!-- zappzarapp:customize: list the make targets your team uses most -->

```bash
make up / make down    → Start/stop containers
make check             → All quality checks
make test              → Run all tests
```

---

## Skills

Available skills in `.claude/skills/`:

| Skill         | Purpose                                                    |
| ------------- | ---------------------------------------------------------- |
| `/tasks`      | Task management (4-tier: zappzarapp/upstream/repo/private) |
| `/sync-check` | Verify related config files stay in sync                   |

Built-in Claude Code skills (e.g. code review, security review, research)
complement these — the project only ships skills for workflows the built-ins do
not cover.

---

## Git & Commits

### Feature-Branch Workflow

```text
git checkout -b feature/<task-slug>
    ↓
[Development + Commits]
    ↓
Claude: "Branch ready for review"
    ↓
User reviews → Merge → `/tasks --close <id>`
```

**Branch naming:** `feature/<slug>`, `fix/<slug>`, `refactor/<slug>`

### Commit Rules

- Commit regularly on feature branches (intermediate commits encouraged)
- Conventional commit format is enforced via commitlint (CaptainHook)
- Never commit directly to your protected/default branch(es) — only via feature
  branch merges

## Error Prevention

See `.zappzarapp/standards/make-targets.md` for container prerequisites and
package manager usage. Direct package manager commands are blocked via
`settings.json` deny rules.

---

## Knowledge File Paths

**LEARNINGS - 2 Layer:**

| Priority | Path              | Condition                 |
| -------- | ----------------- | ------------------------- |
| 1        | `.ai/`            | `.ai/LEARNINGS.md` exists |
| 2        | `.zappzarapp/ai/` | fallback                  |

**ADRs:** one document per decision under `docs/adr/` (`0000-template.md` is the
template).

**Note:** Task management is handled via `/tasks` command with 4-tier model:

| Flag           | Target                 | Use Case                     |
| -------------- | ---------------------- | ---------------------------- |
| (default)      | origin repo            | Your project tasks           |
| `--upstream`   | upstream remote        | Contribute to forked project |
| `--zappzarapp` | marcstraube/zappzarapp | Boilerplate feature requests |
| `--private`    | ~/.local/share/        | Personal, offline tasks      |

---

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
