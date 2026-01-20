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

(No tasks yet)

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
