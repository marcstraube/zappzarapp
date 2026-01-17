---
description: Generate changelog entry from session logs
context: fork
allowed-tools: Read, Write, Edit, Grep, Glob, Bash(git:*), Bash(ls:*),
  AskUserQuestion
argument-hint: [--all | --select] [--dry-run] [--format <type>]
---

# Changelog

Generate a changelog entry from session logs.

## Purpose

Analyzes session logs in `.claude/sessions/` and generates a properly formatted
changelog entry for `documentation/CHANGELOG.md`.

## Arguments

Parse `$ARGUMENTS`:

**Session Selection:**

- (default): Only use the current session's log
- `--all`: Use all unprocessed session logs
- `--select`: Interactively select which logs to include

**Output Options:**

- `--dry-run`: Show generated entry without writing to file
- `--format <type>`: Output format (keepachangelog, conventional, simple)

Examples:

```bash
/changelog                # Current session only (default)
/changelog --all          # All unprocessed session logs
/changelog --select       # Choose specific logs interactively
/changelog --dry-run      # Preview without writing
/changelog --format simple # Simplified format
```

## Workflow

### Step 1: Determine Session Scope

**Find available logs:**

```bash
ls -1t .claude/sessions/session-*.md
```

**Identify current session:** Most recent log file by timestamp in filename.

**Filter logs based on flag:**

| Flag       | Logs Included                          |
| ---------- | -------------------------------------- |
| (default)  | Only the current/most recent session   |
| `--all`    | All logs not marked as processed       |
| `--select` | Show list, let user pick multiple      |

Skip logs already marked as processed (have `processed:` frontmatter).

### Step 2: Parse Each Log

For each session log, extract:

**File Changes:**

| Field   | Source                 |
| ------- | ---------------------- |
| Action  | add/modify/delete/move |
| File    | Path from log          |
| Purpose | Description from log   |

**Learnings:**

- Decisions made and why
- Trade-offs considered
- Important discoveries

### Step 3: Consolidate Changes

Merge changes across all logs:

- `add` then `modify` same file → just `add`
- `add` then `delete` same file → remove from list
- `modify` multiple times → single `modify`
- `move` A→B then `modify` B → `move` A→B + `modify`

Group by category:

| Category | Patterns                           |
| -------- | ---------------------------------- |
| Added    | New files, new features            |
| Changed  | Modified behavior, updated configs |
| Fixed    | Bug fixes                          |
| Removed  | Deleted files, removed features    |
| Security | Security-related changes           |
| Docs     | Documentation updates              |
| Infra    | Docker, CI/CD, build system        |

### Step 4: Generate Entry

#### Format: keepachangelog (default)

Following [Keep a Changelog](https://keepachangelog.com/) format:

```markdown
## [Unreleased]

### Added

- New `/commit` command for guided commit workflow
- New `/sync-check` command for config synchronization
- Session logging system in `.claude/sessions/`

### Changed

- Moved `CLAUDE.md` to `.claude/CLAUDE.md`
- Simplified CLAUDE.md by extracting sync rules to `/sync-check`

### Infrastructure

- Added 7 new Claude Code slash commands
```

#### Format: conventional

Conventional Commits style:

```markdown
## [Unreleased]

- feat(claude): add /commit command for guided commits
- feat(claude): add /sync-check command for config sync
- refactor(claude): move CLAUDE.md to .claude/ directory
- docs(claude): extract sync rules to dedicated command
```

#### Format: simple

Simplified bullet list:

```markdown
## [Unreleased]

- Added Claude Code commands: /commit, /sync-check, /changelog
- Added session logging for change tracking
- Reorganized Claude config into .claude/ directory
```

### Step 5: User Review

Present the generated entry and ask:

1. "Add to CHANGELOG.md?" → Prepend to file
2. "Edit first?" → Let user modify
3. "Discard?" → Cancel

### Step 6: Write Changelog

If approved:

```bash
# Prepend new entry after the header
# Keep existing entries intact
```

Update `documentation/CHANGELOG.md` with new entry.

### Step 7: Mark Logs Processed

Add marker to processed session logs:

```markdown
---
processed: 2026-01-15T16:30:00
changelog_entry: '[Unreleased] - 2026-01-15'
---
```

This prevents duplicate processing.

## Session Log Format

Expected format in `.claude/sessions/session-*.md`:

```markdown
# Session Log: 2026-01-15 14:30

## Changes

| Time  | Action | File                           | Purpose                |
| ----- | ------ | ------------------------------ | ---------------------- |
| 14:30 | add    | .claude/commands/commit.md     | Guided commit workflow |
| 14:35 | add    | .claude/commands/sync-check.md | Config sync checking   |
| 14:40 | modify | .claude/CLAUDE.md              | Extract sync rules     |

## Learnings

- **Topic**: Description of what was learned
- **Decision**: Why a particular approach was chosen
```

## Edge Cases

### No Session Logs

If no logs found:

```text
No session logs found in .claude/sessions/
Create logs during sessions or specify --since date
```

### Empty Changes

If logs exist but no file changes:

- Still include Learnings section if present
- Ask user if they want to skip changelog entry

### Merge Conflicts

If CHANGELOG.md has conflicts:

- Show current state
- Ask user to resolve manually
- Retry after resolution

## Notes

- Session logs are gitignored by default
- CHANGELOG.md follows Keep a Changelog format
- Consider running before commits with significant changes
- Use `--dry-run` to preview before writing
