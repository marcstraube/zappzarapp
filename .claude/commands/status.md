---
description: Quick project status overview
context: fork
allowed-tools:
  Read, Glob, Bash(git:*), Bash(docker:*), Bash(docker compose:*), Bash(make:*),
  Bash(ls:*), Bash(cat:*), Bash(head:*), Bash(tail:*), Bash(wc:*)
argument-hint: [--git | --docker | --todo | --all]
---

# Project Status

Quick overview of project state: Git, Docker, and pending tasks.

## Arguments

Parse `$ARGUMENTS`:

- (default): Show all sections
- `--git`: Only Git status
- `--docker`: Only Docker/container status
- `--todo`: Only pending tasks from backlog
- `--all`: Verbose output with details

Examples:

```bash
/status              # Quick overview
/status --git        # Just Git info
/status --docker     # Just container status
/status --all        # Detailed output
```

## Sections

### 1. Git Status

```bash
# Current branch
git branch --show-current

# Commits ahead/behind remote
git rev-list --left-right --count HEAD...@{upstream} 2>/dev/null

# Uncommitted changes
git status --short

# Last commit
git log -1 --oneline
```

Output format:

```text
Git Status
══════════
Branch:     feature/new-thing
Remote:     ↑2 ahead, ↓0 behind
Changes:    3 modified, 1 untracked
Last commit: abc1234 feat(php): add user service
```

### 2. Docker Status

```bash
# Running containers
docker compose ps --format "table {{.Name}}\t{{.Status}}\t{{.Ports}}"

# Container health
docker compose ps --format json | jq '.[] | {name: .Name, health: .Health}'

# Resource usage (if --all)
docker stats --no-stream --format "table {{.Name}}\t{{.CPUPerc}}\t{{.MemUsage}}"
```

Output format:

```text
Docker Status
═════════════
Running: 5/5 containers healthy

  nginx      healthy   80, 443
  php        healthy   9000
  postgres   healthy   5432
  redis      healthy   6379
  node       healthy   5173, 3000

Disk: 2.3GB images, 150MB volumes
```

### 3. Session Info

```bash
# Current/latest session log
ls -1t .claude/sessions/session-*.md | head -1

# Session duration (if active)
# Changes logged in current session
```

Output format:

```text
Session
═══════
Log:      session-2026-01-17-0600.md
Duration: 45 minutes
Changes:  12 files modified
```

### 4. Pending Tasks

Read from backlog (3-layer: personal → project → boilerplate, same as
`/backlog`):

```text
Backlog
═══════
High Priority:
  - [ ] Fix authentication timeout
  - [ ] Add rate limiting

Medium Priority:
  - [ ] Refactor UserService
  - [ ] Update documentation

Total: 4 pending tasks
```

### 5. Recent Reports

Check for audit reports:

```bash
ls -1t .claude/reports/*.md 2>/dev/null | head -3
```

```text
Recent Reports
══════════════
- quality-audit-2026-01-16-abc1234.md (yesterday)
- security-audit-2026-01-15-def5678.md (2 days ago)
```

## Combined Output (Default)

```text
╔══════════════════════════════════════════════════════════════╗
║ Project Status                                               ║
╠══════════════════════════════════════════════════════════════╣
║ Git                                                          ║
║   Branch: feature/goss-tests  ↑2 ahead                      ║
║   Changes: 3 modified, 1 untracked                          ║
╠══════════════════════════════════════════════════════════════╣
║ Docker                                                       ║
║   Containers: 5/5 healthy                                    ║
║   Services: nginx, php, postgres, redis, node               ║
╠══════════════════════════════════════════════════════════════╣
║ Session                                                      ║
║   Active: 45 min, 12 changes logged                         ║
╠══════════════════════════════════════════════════════════════╣
║ Backlog                                                      ║
║   Pending: 4 tasks (2 high priority)                        ║
╚══════════════════════════════════════════════════════════════╝
```

## Quick Checks

### Environment Check

Verify development environment is ready:

```bash
# .env exists?
test -f .env && echo "✓ .env configured" || echo "✗ .env missing (run make init)"

# Dependencies installed?
test -d vendor && echo "✓ Composer installed" || echo "✗ Run make composer-install"
test -d node_modules && echo "✓ pnpm installed" || echo "✗ Run make pnpm-install"

# Containers running?
docker compose ps -q | wc -l
```

### Health Summary

Quick health indicator:

```text
Environment Health
══════════════════
✓ .env configured
✓ Composer dependencies installed
✓ Node dependencies installed
✓ Containers running (5/5)
✓ All services healthy
✗ Uncommitted changes (3 files)
```

## Notes

- Run at session start for quick orientation
- Use `--all` for verbose output before commits
- Backlog path: 3-layer resolution (personal → project → boilerplate)
- Session logs are in `.claude/sessions/`
