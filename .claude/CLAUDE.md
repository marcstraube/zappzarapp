<!-- zappzarapp-boilerplate-claude -->

# Claude Instructions

## Session Management

**Hooks handle session lifecycle automatically:**

| Hook               | Action                                                   |
| ------------------ | -------------------------------------------------------- |
| `SessionStart`     | Creates `.claude/sessions/YYYY/MM/session-...-<slug>.md` |
| `UserPromptSubmit` | Detects context continuation, shows previous session     |
| `SessionEnd`       | Reminds about Summary, Learnings, Decisions              |
| `PreCompact`       | Reminds before context compaction                        |

**Session naming is automatic:**

- Slug is derived from branch name (e.g., `feature/add-auth` -> `add-auth`)
- Falls back to changed file directory if on develop/main/master
- Falls back to `pending` only if no context available

**Your tasks:**

1. **Fresh conversation:** Brief user on previous session, ask what to work on
2. **Context continuation:** Hook shows previous session - read and continue it

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

`SessionEnd` hook reminds you. Checklist:

1. Fill in `## Summary`
2. Verify learnings/decisions written to knowledge files
3. Tell user: "Bitte `/clear` eingeben."

---

## Feature-Branch Workflow

**Before starting any implementation task:**

1. **Check current branch**: `git branch --show-current`
2. **If on develop/main/master → Create feature branch FIRST**:

   ```bash
   git checkout -b feature/<slug>  # or: fix/<slug>, refactor/<slug>
   ```

3. **For parallel work**: Use `/worktree --create feature/<slug>`

**Exception:** Trivial tasks (1 file, typo/config/one-liner) can stay on
develop.

**Why?**

- Isolation: Changes don't affect develop during development
- Review: User reviews entire feature branch before merge
- Rollback: Simply delete branch if needed
- Clean history: One merge per feature

See `.claude/agents/workflow.md:54-85` for detailed workflow.

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

**LEARNINGS, DECISIONS, REFERENCES - 2 Layer:**

| Priority | Path              | Condition                 |
| -------- | ----------------- | ------------------------- |
| 1        | `.ai/`            | `.ai/LEARNINGS.md` exists |
| 2        | `.zappzarapp/ai/` | fallback                  |

**CHANGELOG - Root level:**

| File                       | Purpose                                     |
| -------------------------- | ------------------------------------------- |
| `CHANGELOG.md`             | Project changelog (Keep a Changelog format) |
| `.zappzarapp/CHANGELOG.md` | Boilerplate changelog (after `make setup`)  |

---

## Agent-Workflow

`UserPromptSubmit` hook reminds about workflow. Details:
`.claude/agents/workflow.md`

Project context: `.claude/context/project.md`

## Code Standards

Standards: `.zappzarapp/standards/` (php.md, node.md, shell.md, etc.)

## Project Structure

Key: `.claude/` (tooling), `.ai/` (knowledge), `.zappzarapp/` (boilerplate),
`src/` (code), `tests/`

## Key Make Targets

```bash
make up / make down    -> Start/stop containers
make check             -> All quality checks
make test              -> Run all tests
make fresh             -> Rebuild everything
```

For all lint/test/fix targets: See `.zappzarapp/standards/make-targets.md`

## Skills

Skills in `.claude/skills/`: `/status`, `/tasks`, `/commit`, `/audit`,
`/learnings`, `/research`, `/optimize`, `/worktree`

## Git & Commits

See `.claude/agents/workflow.md` (Branch Naming, Commit Strategy, Branch
Management)

## Error Prevention

See `.zappzarapp/standards/make-targets.md` for container prerequisites and
package manager usage. Direct package manager commands are blocked via
`settings.json` deny rules.

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
