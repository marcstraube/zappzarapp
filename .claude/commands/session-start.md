---
description: Initialize new session with context from previous work
context: fork
allowed-tools: Read, Glob, Bash(git:*), Bash(docker:*), Bash(docker compose:*),
  Bash(ls:*), Bash(date:*)
argument-hint: [--continue | --fresh]
---

# Session Start

Initialize a new development session with full context awareness.

## Arguments

Parse `$ARGUMENTS`:

- (default): Auto-detect if continuing or fresh start
- `--continue`: Explicitly continue last session
- `--fresh`: Start completely fresh, ignore previous session

## Workflow

### 1. Find Last Session

```bash
# Get most recent session log
ls -1t .claude/sessions/session-*.md 2>/dev/null | grep -v TEMPLATE | head -1
```

### 2. Read Previous Session Summary

If last session exists, read and extract:

- **Goal**: What was being worked on
- **Open Items**: Uncompleted tasks
- **Session Summary**: Final state
- **Next Steps**: Recommended actions

### 3. Check Backlog

Read `.claude/BACKLOG.md` for:

- High priority items
- Items related to last session work

### 4. Git Context

```bash
# Current state
git branch --show-current
git status --short
git log -3 --oneline
```

### 5. Docker Status

```bash
# Quick container check
docker compose ps --format "table {{.Name}}\t{{.Status}}" 2>/dev/null || echo "Containers not running"
```

### 6. Create New Session Log

Create new session file from template:

```bash
# Generate filename
date +"%Y-%m-%d-%H%M"
# → session-2026-01-18-0930.md
```

Copy from `SESSION-TEMPLATE.md` and fill in:

- Date and time
- Link to previous session
- Git branch
- Initial goal (from user or inferred)

## Output Format

```text
╔══════════════════════════════════════════════════════════════╗
║ Session Start                                                ║
╠══════════════════════════════════════════════════════════════╣
║ Previous Session: 2026-01-17-0548                           ║
║   Goal: GOSS Test Implementation                             ║
║   Status: Completed                                          ║
║   Open Items: 0                                              ║
╠══════════════════════════════════════════════════════════════╣
║ Backlog                                                      ║
║   High Priority: 1 item                                      ║
║     - Internal TLS Migration                                 ║
╠══════════════════════════════════════════════════════════════╣
║ Environment                                                  ║
║   Branch: node-testing-backup                                ║
║   Changes: 2 untracked files                                 ║
║   Containers: Not running                                    ║
╠══════════════════════════════════════════════════════════════╣
║ New Session: session-2026-01-18-0930.md                     ║
╚══════════════════════════════════════════════════════════════╝

What would you like to work on today?
```

## Quick Reference

After session start, remind user of available commands:

```text
Quick Commands:
  /status     - Project overview
  /test       - Run tests
  /commit     - Guided commit
  /learnings  - View/update learnings
```

## Notes

- Always creates a new session log file
- Reads but does not modify previous session
- If containers not running, suggests `make up`
- Links relevant LEARNINGS.md entries if topic matches
