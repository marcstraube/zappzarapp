<!-- zappzarapp-boilerplate-claude -->

# Claude Instructions

## Knowledge Files

**Write learnings, decisions, and references IMMEDIATELY when discovered:**

| Discovery                      | Action                             |
| ------------------------------ | ---------------------------------- |
| New insight / gotcha / pattern | Append to `LEARNINGS.md` (inbox)   |
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

---

## Feature-Branch Workflow

**Before starting any implementation task:**

1. **Check current branch**: `git branch --show-current`
2. **If on develop/main/master → Create feature branch FIRST**:

   ```bash
   git checkout -b <type>/<slug>  # e.g., feature/, fix/, chore/, docs/
   ```

3. **If on any other branch → Use git worktree for parallel work**:

   ```bash
   # Create worktree for new task
   git worktree add ../zappzarapp-wt-<name> -b <branch> develop
   cd ../zappzarapp-wt-<name>
   # Work in isolated environment
   ```

**Exception:** Trivial tasks (1 file, typo/config/one-liner) can stay on
develop.

**Why?**

- Isolation: Changes don't affect develop during development
- Review: User reviews entire feature branch before merge
- Rollback: Simply delete branch if needed
- Clean history: One merge per feature

See `.claude/agents/workflow.md` for the detailed workflow.

---

## Solution Principles

When proposing solutions, prioritize in this order:

1. **Security** - No compromises on security (input validation, secrets
   handling, OWASP compliance)
2. **Architecture** - Clean separation, SOLID principles, maintainability,
   testability
3. **Performance** - Efficient solutions, no unnecessary dependencies
4. **Simplicity** - Only after 1-3 are satisfied, choose the simplest approach

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

**Note:** Task management is handled via `/tasks` skill with 4-tier model:

| Flag           | Target                 | Use Case                     |
| -------------- | ---------------------- | ---------------------------- |
| (default)      | origin repo            | Your project tasks           |
| `--upstream`   | upstream remote        | Contribute to forked project |
| `--zappzarapp` | marcstraube/zappzarapp | Boilerplate feature requests |
| `--private`    | ~/.local/share/        | Personal, offline tasks      |

**LEARNINGS - 2 Layer:**

| Priority | Path              | Condition                 |
| -------- | ----------------- | ------------------------- |
| 1        | `.ai/`            | `.ai/LEARNINGS.md` exists |
| 2        | `.zappzarapp/ai/` | fallback                  |

**ADRs - one document per decision:**

| Scope                   | Path                    |
| ----------------------- | ----------------------- |
| Boilerplate development | `.zappzarapp/docs/adr/` |
| User project            | `docs/adr/`             |

**CHANGELOG - Root level:**

| File                       | Purpose                                     |
| -------------------------- | ------------------------------------------- |
| `CHANGELOG.md`             | Project changelog (Keep a Changelog format) |
| `.zappzarapp/CHANGELOG.md` | Boilerplate changelog (after `make setup`)  |

---

## Agent-Workflow

Suggested agent workflow (adapt as needed): `.claude/agents/workflow.md`

**Use parallel agents when it pays off:** for ≥2 independent, pre-planned tasks
— or a large change spanning many files or multiple languages — dispatch the
specialist coder agents in parallel (see `.claude/agents/workflow.md`) instead
of working through them serially.

Project context: `.claude/context/zappzarapp.md` (platform — synced from
upstream, do not edit) + `.claude/context/project.md` (this application)

## Code Standards

Standards: `.zappzarapp/standards/` (php.md, node.md, shell.md, etc.)

## Project Structure

Key: `.claude/` (tooling), `.ai/` (knowledge), `.zappzarapp/` (boilerplate),
`src/` (code), `tests/`

## Key Make Targets

```bash
make up / make down    -> Start/stop containers
make check             -> Fast pre-check (static checks + tests, no coverage)
make ci                -> Faithful CI gate (coverage strictness + dependency audit)
make test              -> Run all tests
make fresh             -> Rebuild everything
```

For all lint/test/fix targets: See `.zappzarapp/standards/make-targets.md`

## Skills

Skills in `.claude/skills/`: `/tasks`, `/sync-check`

Built-in Claude Code skills (code review, security review, research, ...)
complement these — the project only ships skills the built-ins do not cover.

## Git & Commits

See `.claude/agents/workflow.md` for the feature-branch workflow and branch
naming.

## Error Prevention

See `.zappzarapp/standards/make-targets.md` for container prerequisites and
package manager usage. Direct package manager commands are blocked via
`settings.json` deny rules.

**Sandbox Policy:**

- **Work inside project directory only**: All tool operations (Read, Write,
  Edit, Bash) must stay within project directory (or active worktree)
- **No access outside project**: Never use tools to read/write `~/.config/`,
  `/tmp/`, `/usr/local/`, etc.
- **Temporary files**: Use `./build/tmp/` within project, not system `/tmp`
- **Why**: Sandbox is enabled in `.claude/settings.json` for security -
  violations trigger permission prompts
- **Note**: Global configs (`~/.claude/CLAUDE.md`) are automatically loaded as
  context - no tool access needed

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
