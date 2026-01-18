---
description: View, search, and aggregate project learnings
context: fork
allowed-tools: Read, Glob, Grep, Edit, Write, Bash(date:*)
argument-hint: [--search <term> | --add | --sync | --category <name>]
---

# Learnings Management

Manage the central knowledge base of project-specific learnings.

## Arguments

Parse `$ARGUMENTS`:

- (default): Show all learnings organized by category
- `--search <term>`: Search learnings for specific topic
- `--add`: Interactive mode to add a new learning
- `--sync`: Aggregate learnings from all session logs into LEARNINGS.md
- `--category <name>`: Show only learnings from specific category

## Categories

Standard categories in LEARNINGS.md:

- **Docker & Containers**: Container runtime, volumes, networking
- **Node.js**: pnpm, workspaces, Express, frameworks
- **PHP**: Composer, PDO, Symfony components
- **Nginx**: Configuration, proxying, SSL
- **Testing**: GOSS, PHPUnit, Jest, integration tests
- **Kubernetes**: Helm, pods, services, security
- **Development Workflow**: Git, Make, tooling
- **Documentation**: Markdown, conventions

## Commands

### View All (default)

```bash
/learnings
```

Output: Display LEARNINGS.md content with table of contents.

### Search

```bash
/learnings --search secrets
/learnings --search "pnpm workspace"
```

Search across:
1. LEARNINGS.md
2. All session logs (`.claude/sessions/*.md`)

Output format:

```text
Search Results for "secrets"
════════════════════════════

LEARNINGS.md:
  Line 15: Docker Compose ignores `mode`, `uid`, `gid` for secrets
  Line 18: `cap_drop: ALL` affects root too - removes ALL capabilities

session-2026-01-16-0552.md:
  Line 734: Secrets mode/uid/gid nur in Swarm

Found 3 matches in 2 files
```

### Add New Learning

```bash
/learnings --add
```

Interactive prompts:

1. **Category**: Select from existing or create new
2. **Title**: Short descriptive title
3. **Description**: Detailed explanation
4. **Context**: Optional - where this was learned (session, ticket)

Then appends to LEARNINGS.md under the appropriate category.

### Sync from Sessions

```bash
/learnings --sync
```

Process:

1. Read all session logs
2. Extract content from `## Learnings` sections
3. Check for duplicates against existing LEARNINGS.md
4. Present new learnings for confirmation
5. Append confirmed learnings to LEARNINGS.md
6. Update "Last Updated" timestamp

Output:

```text
Syncing Learnings from Sessions
═══════════════════════════════

Scanning 5 session files...

New learnings found:

1. [Docker] Volume permissions differ between dev/prod
   Source: session-2026-01-17-0548.md
   [Add] [Skip] [Edit]

2. [Testing] GOSS arithmetic in bash requires special handling
   Source: session-2026-01-17-0548.md
   [Add] [Skip] [Edit]

Summary: 2 new, 15 existing (no duplicates)
```

### View Category

```bash
/learnings --category docker
/learnings --category "Node.js"
```

Shows only learnings from that category.

## LEARNINGS.md Structure

```markdown
# Project Learnings

Aggregated knowledge from all development sessions.

---

## Docker & Containers

### [Learning Title]

- **[Subtopic]**: Description of the learning

---

## Node.js

...

---

## Last Updated

YYYY-MM-DD (aggregated from sessions YYYY-MM-DD to YYYY-MM-DD)
```

## Best Practices

1. **Be specific**: "pnpm prune breaks symlinks" > "pnpm has issues"
2. **Include context**: Why is this important? When does it apply?
3. **Link to solutions**: What's the fix or workaround?
4. **Categorize correctly**: Makes searching easier

## Integration

- Session logs should always have a `## Learnings` section
- Run `/learnings --sync` periodically to aggregate
- Reference LEARNINGS.md when encountering similar issues
- Update if a learning becomes outdated
