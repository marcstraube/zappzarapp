# Project Backlog

Team-shared backlog for features, bugs, and improvements.

## Task Size Guide

| Size   | Time        | Files                   | AI Context                |
| ------ | ----------- | ----------------------- | ------------------------- |
| Small  | <30 min     | 1-3 files               | Same context OK           |
| Medium | 30 min - 2h | 3-10 files              | Flexible                  |
| Large  | >2h         | Many files, exploration | Fresh context recommended |

---

## High Priority

### CHANGELOG.md + Git History Cleanup (Release Blocker)

- **Priority**: High (Release Blocker)
- **Scope**: Medium
- **Context**: CHANGELOG.md is overfilled with development entries pre-1.0.
  Additionally, Prettier corrupts glob patterns (`*` → `_`) in historical
  entries.
- **Problem**: Too much development noise in changelog and git history for a
  boilerplate release
- **Goal**: Clean slate for 1.0 release
  1. Create backup branch with full development history
  2. Squash all commits on main/master to single "Initial commit"
  3. CHANGELOG.md will contain only one entry: "1.0.0 - Initial Release"
- **Files**: `documentation/CHANGELOG.md`, git history
- **Note**: Must be completed before 1.0 release. Prettier issue resolves itself
  since problematic entries will be removed.

---

## Medium Priority

### Quick Wins

#### Verify File Permissions Match CaptainHook Rules

**Status:** Open **Scope:** chore **Size:** Small **Created:** 2026-01-20
**Created by:** Marc Straube <email@marcstraube.de>

**Task:** Audit all files and directories for correct permissions that match
CaptainHook's commit/checkout hooks. Ensures clean first checkout without
permission warnings.

**CaptainHook enforces:**

- Shell scripts (`*.sh`): 755
- Config files (root): 644 (`*.json`, `*.js`, `*.cjs`, `*.ts`, `*.yaml`,
  `*.yml`, `*.neon`, `*.md`, `.prettier*`, `.npmrc`, `.markdown*`, `.depcheck*`,
  `.gitmessage`, `LICENSE`)
- Documentation (`documentation/*.md`): 644
- Docker scripts (`docker/*.sh`): 755

**Steps:**

1. Run permission check across entire repo
2. Fix any mismatches
3. Verify with `make check` (CaptainHook pre-commit)

**Files to check:**

- All files matching patterns above
- `captainhook.json` (reference for rules)

---

### Code Quality

(No tasks yet)

### Testing Enhancements

(No tasks yet)

### Infrastructure

(No tasks yet)

---

## Low Priority

(No tasks yet)

---

## Completed

(Move completed items to CHANGELOG.md when committing)
