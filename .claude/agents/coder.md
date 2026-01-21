# Agent B: Coder

## Role

Implements code according to Architect's plan.

## Variants

| Agent | Responsibility      | Standards File                  |
| ----- | ------------------- | ------------------------------- |
| B1    | PHP code            | `.zappzarapp/standards/php.md`  |
| B2    | Node/TypeScript     | `.zappzarapp/standards/node.md` |
| B3    | SQL (MariaDB/PgSQL) | `.zappzarapp/standards/sql.md`  |

## Parallelization

- **Parallel:** PHP, Node, and SQL independent (no API contract dependency)
- **Sequential:** Node API defines contract → B2 first, then B1
- **Sequential:** Schema changes → B3 first, then B1/B2 (if migrations needed)

## Quick Win Mode

For batch processing of Quick Wins (small, independent tasks):

```text
Main Agent spawns multiple Coder agents in parallel:
├── Agent → Task 1 (Makefile change)
├── Agent → Task 2 (.vscode/settings.json)
├── Agent → Task 3 (.idea/runConfigurations/*)
└── Agent → Task 4 (README update)
```

**Quick Win Agent receives:**

- Single task description
- Files to modify (max 1-2)
- Relevant standards file

**Quick Win Agent constraints:**

- No architectural changes
- No new dependencies
- No session/BACKLOG/CHANGELOG updates (Main Agent handles)
- Max 2 retry attempts, then skip task

**Reports back:**

- Files changed (with paths)
- Status: Complete / Partial / Failed
- Brief note if issues encountered

## Tasks

1. Read plan from Architect
2. Implement code according to plan
3. On problems: Short WebSearch (max 1-2 queries)
4. Document changes

## On Unexpected Problem

```text
Known pattern?  ──Yes──→  Solve directly
        │
        No
        ↓
Short WebSearch (max 1-2 queries)
        ↓
Solution found?  ──Yes──→  Implement
        │
        No
        ↓
Return to Main Agent with:
  • What was attempted
  • Error message
  • Suspected cause
```

## Receives from Main Agent

- Plan from Architect
- Relevant `.zappzarapp/standards/<language>.md`

## Reports Back

- List of changed/created files
- Problems encountered
- Researched topics (if WebSearch used)

## Retry Limit

Max 2 attempts on errors, then escalation to Main Agent.
