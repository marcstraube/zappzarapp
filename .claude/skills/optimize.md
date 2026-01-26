---
name: optimize
description: Self-optimization of Claude configuration, learnings, and skills
model: sonnet
context: fork
allowed-tools:
  - Read
  - Write
  - Edit
  - Grep
  - Glob
  - Bash(date:*)
  - Bash(git:*)
  - Bash(wc:*)
  - Bash(ls:*)
  - Bash(jq:*)
argument-hint:
  '[--config | --template | --terminology | --docs | --learnings | --skills |
  --sessions | --settings | --all]'
---

# Self-Optimization

Analyze and improve Claude's own configuration for better effectiveness.

## Arguments

Parse `$ARGUMENTS`:

- `--config`: Optimize CLAUDE.md (structure, clarity, redundancy)
- `--template`: Sync check CLAUDE.md <-> CLAUDE.template.md
- `--terminology`: Find outdated terms across all AI config files
- `--docs`: Check AI-INTEGRATION.md consistency with actual config
- `--learnings`: Clean up and reorganize LEARNINGS.md
- `--skills`: Audit skills (usefulness, gaps, redundancy)
- `--sessions`: Archive old sessions, clean up session directory
- `--settings`: Analyze and optimize settings.json and settings.local.json
- `--all`: Run all optimization phases (default if no argument)

## Philosophy

This command enables continuous self-improvement by:

1. **Reducing noise** - Remove outdated or redundant information
2. **Improving clarity** - Make instructions clearer and more actionable
3. **Preserving knowledge** - Never delete, only consolidate and archive
4. **Staying current** - Ensure configuration reflects actual project state

---

## Phase 1: CLAUDE.md Optimization (`--config`)

### 1.1 Structure Analysis

Check CLAUDE.md for:

- [ ] **Logical grouping**: Related sections together?
- [ ] **Consistent formatting**: Headers, lists, code blocks uniform?
- [ ] **Appropriate depth**: Not too detailed, not too sparse?
- [ ] **Scanability**: Can key info be found quickly?

### 1.2 Redundancy Detection

Search for:

- Duplicate instructions (same rule stated differently)
- Contradicting instructions (conflicting guidance)
- Obsolete references (files/commands that don't exist)

Cross-reference with actual project:

```bash
# Check if referenced skills exist
grep -oP '/\w+' .claude/CLAUDE.md | sort -u
ls .claude/skills/*.md | xargs -I{} basename {} .md

# Check if referenced files exist
grep -oP '`[^`]+\.(md|json|yaml|sh)`' .claude/CLAUDE.md
```

### 1.3 Completeness Check

Verify CLAUDE.md covers:

- [ ] All active skills listed?
- [ ] Current project structure accurate?
- [ ] Make targets up-to-date?
- [ ] Hooks documented?

### 1.4 Recommendations

Present findings as:

```text
CLAUDE.md Optimization Report
=============================

Structure:
  [OK] Logical grouping
  [FIX] Section "X" should be merged with "Y"

Redundancy:
  [WARN] Lines 45 and 89 say the same thing
  [CRIT] Line 23 contradicts line 67

Completeness:
  [MISS] Skill /optimize not listed in table
  [STALE] Make target "old-target" no longer exists

Clarity:
  [IMPROVE] "Error Prevention" could use examples
```

**IMPORTANT**: Present changes for user approval before modifying.

---

## Phase 5: Learnings Optimization (`--learnings`)

### 5.1 Duplicate Detection

Find semantic duplicates:

- Same concept explained differently
- Learnings that evolved (keep latest, archive old)
- Overlapping information across categories

### 5.2 Category Review

For each category in LEARNINGS.md:

- Still relevant to current project?
- Learnings correctly categorized?
- Any orphaned learnings (no category)?

### 5.3 Freshness Check

For each learning:

- Still accurate? (technology/approach may have changed)
- Still applicable? (project structure may have changed)
- Source still exists? (session log reference valid?)

### 5.4 Consolidation Strategy

When learnings overlap:

1. **Keep the most complete version**
2. **Preserve unique context from others**
3. **Update "Last Updated" with merge date**
4. **Archive originals to `.claude/archive/learnings-{date}.md`**

---

## Phase 6: Skills Audit (`--skills`)

### 6.1 Usage Analysis

Scan session logs for skill usage:

```bash
# Count skill invocations in sessions
grep -rh "^/[a-z-]*" .claude/sessions/*.md | sort | uniq -c | sort -rn
```

Output:

```text
Skill Usage (from session logs)
===============================

  15  /commit
  12  /status
   8  /tasks
   5  /learnings
   3  /audit
   1  /research
   0  /sync-check       <- Never used
```

### 6.2 Redundancy Check

Identify overlapping skills:

- Skills with similar purposes
- Skills that could be combined

### 6.3 Gap Analysis

What's missing based on actual workflow?

- Common manual tasks that could be automated
- Frequently typed bash commands
- Repetitive multi-step processes

### 6.4 Skill Health

For each skill, check:

- [ ] Description accurate?
- [ ] Allowed-tools sufficient?
- [ ] Prompt clear and complete?
- [ ] Works with current project structure?

---

## Phase 7: Sessions Cleanup (`--sessions`)

### 7.1 Session Inventory

```bash
# List sessions with age and size
find .claude/sessions -name "session-*.md" -type f | xargs ls -lth
find .claude/sessions -name "session-*.md" -type f | xargs wc -l
```

### 7.2 Archival Criteria

Sessions older than 30 days:

1. Extract key information:
   - Session Summary
   - Learnings (should already be in LEARNINGS.md)
   - Open Items (should be captured as tasks)
2. Create condensed archive entry
3. Move original to `.claude/archive/sessions/`

### 7.3 Integrity Check

Before archiving, verify:

- [ ] All learnings synced to LEARNINGS.md?
- [ ] Open items captured as tasks?
- [ ] Summary present?

---

## Phase 8: Settings Optimization (`--settings`)

Analyze and improve Claude Code settings files.

### 8.1 File Overview

| File                  | Purpose                                | Committed |
| --------------------- | -------------------------------------- | --------- |
| `settings.json`       | Shared team settings (hooks)           | Yes       |
| `settings.local.json` | Personal settings (permissions, hooks) | No        |

### 8.2 Permissions Analysis

**Project-specific permissions (-> settings.json):**

- `Bash(./docker/hooks/*:*)`
- `Bash(./tests/goss/*:*)`
- Other project-specific env var + command combos

**Personal permissions (-> settings.local.json):**

- General tools: `make`, `docker`, `git`, `composer`, `pnpm`
- System utilities: `ls`, `cat`, `grep`, `find`, etc.
- Personal preferences

**Dangerous patterns to flag:**

- `Bash(sudo rm:*)` - allows `sudo rm -rf /`
- `Bash(bash:*)` - allows arbitrary script execution
- `Bash(rm:*)` - allows `rm -rf` (very dangerous)

### 8.3 Hooks Analysis

For both `settings.json` and `settings.local.json`:

**Effectiveness check:**

- Do hooks still serve their purpose?
- Are matchers too broad or too narrow?
- Do commands still work? (paths, tools exist?)

**Redundancy:**

- Same hook in both files?
- Overlapping matchers?

---

## Phase 9: Full Optimization (`--all`)

Run all phases sequentially:

1. CLAUDE.md optimization
2. Template sync
3. Terminology check
4. Documentation sync
5. Learnings cleanup
6. Skills audit
7. Sessions archival
8. Settings optimization

Generate combined report.

---

## Output Locations

| Artifact            | Location                               |
| ------------------- | -------------------------------------- |
| Optimization Report | `.claude/reports/optimize-{date}.md`   |
| Archived Learnings  | `.claude/archive/learnings-{date}.md`  |
| Archived Sessions   | `.claude/archive/sessions-{year}.md`   |
| Backup of CLAUDE.md | `.claude/archive/CLAUDE-{date}.md`     |
| Backup of settings  | `.claude/archive/settings-{date}.json` |

---

## Safety Guarantees

1. **Never delete without archiving** - All removed content goes to
   `.claude/archive/`
2. **User approval required** - No automatic modifications to CLAUDE.md or
   LEARNINGS.md
3. **Backup before modify** - Create backup before any edit
4. **Dry-run by default** - Show what would change before doing it

---

## Notes

- Run periodically (monthly recommended)
- Best run at session start or end
- Creates no git changes until user approves
- Archive directory is gitignored
