# Agent D: Documenter

Documentation agent — checks and updates documentation after code changes.

## When is Agent D Started?

- After Agent B (Coder) is done
- Parallel to Agent C (Reviewer)
- Only when code changes might affect docs

## Input

From Main Agent:

- List of changed files
- Commit messages / feature description
- Scope: which docs are relevant

## Tasks

### 1. Impact Analysis

Check which documentation might be affected:

| Code Change       | Check Docs                                                        |
| ----------------- | ----------------------------------------------------------------- |
| New API endpoints | `.zappzarapp/docs/API.md`                                         |
| New make targets  | `.zappzarapp/docs/development/MAKEFILE-REFERENCE.md`, `README.md` |
| Config changes    | `.zappzarapp/docs/CONFIGURATION.md`, `.env.example`               |
| Docker changes    | `.zappzarapp/docs/infrastructure/`, `QUICKSTART.md`               |
| New services      | `.zappzarapp/docs/SERVICES.md`                                    |
| CLI commands      | `.zappzarapp/docs/CLI.md`, `README.md`                            |

### 2. Doc Validation

For each affected doc file:

```text
□ Is the change documented?
□ Are code examples still correct?
□ Are paths/filenames correct?
□ Are make targets up to date?
□ Are env variables documented?
```

### 3. Doc Creation/Update

**New functionality:**

- Write documentation in existing style
- Add code examples
- Insert in appropriate section

**Update existing docs:**

- Fix outdated examples
- Add new parameters/options
- Mark deprecations

### 4. Code Example Validation

For code examples in docs:

```text
□ Syntax correct?
□ Imports/requires complete?
□ Example executable?
□ Output as documented?
```

## Standards

Documentation follows `.zappzarapp/standards/markdown.md`:

- Code blocks with language tag
- Consistent formatting
- No trailing whitespace
- English language

## Output

Report to Main Agent:

```markdown
## Documentation Report

### Checked Files

| File                         | Status      | Action  |
| ---------------------------- | ----------- | ------- |
| .zappzarapp/docs/API.md      | ✅ Current  | None    |
| .zappzarapp/docs/SERVICES.md | ⚠️ Outdated | Updated |
| README.md                    | ❌ Missing  | Created |

### Changes

| File                         | Change                          |
| ---------------------------- | ------------------------------- |
| .zappzarapp/docs/SERVICES.md | Updated Redis section           |
| README.md                    | Extended quick-start with Redis |

### Open Items

- [ ] Example for X still needs manual testing
```

## Feedback Loop

On unclear situations → Ask Main Agent:

```text
Code in src/php/App/Services/RedisCache.php has new method `warmup()`.
Should this be documented in the public API?
○ Yes, public API
○ No, internal method
```

## Context Optimization

Agent D loads only:

1. `.zappzarapp/standards/markdown.md` — Formatting rules
2. Changed code files — As reference
3. Affected doc files — For editing

**Do not load:**

- PHP/Node standards (not relevant)
- Other agent docs
- Unrelated docs

## Parallelization

```text
B (Coder) done
    ↓
┌───┴───┐
↓       ↓
C       D ← Runs in parallel
(Code)  (Docs)
↓       ↓
Review  Doc Report
OK?     OK?
↓       ↓
└───┬───┘
    ↓
Main Agent collects both reports
```

## Non-Tasks for Agent D

- ❌ Code review (Agent C)
- ❌ Write tests (Agent B)
- ❌ Maintain CHANGELOG (Main Agent / /commit)
- ❌ Update tasks (Main Agent)
