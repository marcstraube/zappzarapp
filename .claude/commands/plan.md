description: Create implementation plan before making changes
context: fork
allowed-tools: Read, Grep, Glob, Bash(git:*), Bash(ls:*), Bash(make:*),
  Bash(docker:*), Bash(docker compose:*), AskUserQuestion
argument-hint: [--scope-only] <task description>
---

# Plan Mode

Create a detailed implementation plan before writing any code.

## Purpose

Planning before implementation:

- Reduces wasted effort from wrong approaches
- Catches architectural issues early
- Allows user correction before code is written
- Documents the reasoning for future reference

## Arguments

`$ARGUMENTS` contains the task description and optional flags.

- `--scope-only`: Only analyze scope and affected files, skip detailed implementation steps
- (default): Full implementation plan

Examples:

```bash
/plan add user authentication with JWT
/plan --scope-only refactor the payment service
/plan fix the race condition in order processing
```

### --scope-only Mode

When `--scope-only` is specified, output only:

1. **Scope Analysis**: What the task involves
2. **Affected Files**: Which files will be touched
3. **Dependencies**: What this depends on / what depends on this
4. **Risks**: Potential issues or blockers
5. **Complexity Estimate**: Low / Medium / High

Skip the detailed implementation steps. Useful for:

- Quick assessment before deciding to proceed
- Estimating effort for multiple tasks
- Identifying if more research is needed

## Planning Process

### Step 1: Understand the Task

Read the task description and identify:

- What is the goal?
- What are the constraints?
- What is the scope?

### Step 2: Research the Codebase

Explore relevant parts of the codebase:

```bash
# Find related files
git ls-files | grep -i <keyword>

# Search for existing patterns
grep -r "pattern" src/

# Check existing implementations
```

Identify:

- Existing patterns to follow
- Files that need modification
- Dependencies and interactions
- Potential conflicts

### Step 3: Draft the Plan

Create a structured plan with:

```markdown
## Overview

Brief description of what will be implemented.

## Affected Files

| File | Action | Changes |
|------|--------|---------|
| src/php/Service/AuthService.php | Create | New JWT authentication service |
| src/php/Controller/LoginController.php | Modify | Add JWT token generation |
| tests/php/Unit/AuthServiceTest.php | Create | Unit tests for AuthService |

## Implementation Steps

1. **Step name**
   - Detail 1
   - Detail 2

2. **Step name**
   - Detail 1
   - Detail 2

## Architecture Decisions

- Decision 1: Rationale
- Decision 2: Rationale

## Risks & Considerations

- Risk 1: Mitigation
- Risk 2: Mitigation

## Testing Strategy

- Unit tests for X
- Integration tests for Y
- Manual verification of Z
```

### Step 4: Present for Approval

Present the plan and ask:

1. **Approve** → Proceed with implementation
2. **Modify** → Adjust plan based on feedback
3. **Reject** → Discard plan, discuss alternatives

## Plan File

Save the plan to `.claude/plans/plan-YYYY-MM-DD-<short-description>.md` for reference.

Example: `.claude/plans/plan-2026-01-17-jwt-auth.md`

## After Approval

Once approved:

1. Use the plan as implementation guide
2. Check off steps as completed
3. Note any deviations from the plan
4. Update plan if significant changes occur

## When to Use This Command

Use `/plan` for:

- New features
- Refactoring tasks
- Architecture changes
- Multi-file modifications
- Anything non-trivial

Skip `/plan` for:

- Single-line fixes
- Typo corrections
- Simple additions with clear requirements

## Notes

- Plans are saved for future reference
- Good plans prevent wasted implementation effort
- When in doubt, plan first
