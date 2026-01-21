---
description: Self-optimization of Claude configuration, learnings, and commands
context: fork
allowed-tools:
  Read, Write, Edit, Grep, Glob, Bash(date:*), Bash(git:*), Bash(wc:*),
  Bash(ls:*), Bash(jq:*)
argument-hint:
  [--config | --learnings | --commands | --sessions | --settings | --all]
---

# Self-Optimization

Analyze and improve Claude's own configuration for better effectiveness.

## Arguments

Parse `$ARGUMENTS`:

- `--config`: Optimize CLAUDE.md (structure, clarity, redundancy)
- `--learnings`: Clean up and reorganize LEARNINGS.md
- `--commands`: Audit slash commands (usefulness, gaps, redundancy)
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
# Check if referenced commands exist
grep -oP '/\w+' .claude/CLAUDE.md | sort -u
ls .claude/commands/*.md | xargs -I{} basename {} .md

# Check if referenced files exist
grep -oP '`[^`]+\.(md|json|yaml|sh)`' .claude/CLAUDE.md
```

### 1.3 Completeness Check

Verify CLAUDE.md covers:

- [ ] All active slash commands listed?
- [ ] Current project structure accurate?
- [ ] Make targets up-to-date?
- [ ] Hooks documented?

### 1.4 Clarity Improvements

For each section, ask:

- Is the instruction actionable?
- Could it be misinterpreted?
- Is the "why" clear, not just the "what"?

### 1.5 Recommendations

Present findings as:

```text
CLAUDE.md Optimization Report
═════════════════════════════

Structure:
  [OK] Logical grouping
  [FIX] Section "X" should be merged with "Y"

Redundancy:
  [WARN] Lines 45 and 89 say the same thing
  [CRIT] Line 23 contradicts line 67

Completeness:
  [MISS] Command /optimize not listed in table
  [STALE] Make target "old-target" no longer exists

Clarity:
  [IMPROVE] "Error Prevention" could use examples
```

**IMPORTANT**: Present changes for user approval before modifying.

---

## Phase 2: Learnings Optimization (`--learnings`)

### 2.1 Duplicate Detection

Find semantic duplicates:

- Same concept explained differently
- Learnings that evolved (keep latest, archive old)
- Overlapping information across categories

### 2.2 Category Review

For each category in LEARNINGS.md:

- Still relevant to current project?
- Learnings correctly categorized?
- Any orphaned learnings (no category)?

### 2.3 Freshness Check

For each learning:

- Still accurate? (technology/approach may have changed)
- Still applicable? (project structure may have changed)
- Source still exists? (session log reference valid?)

### 2.4 Consolidation Strategy

When learnings overlap:

1. **Keep the most complete version**
2. **Preserve unique context from others**
3. **Update "Last Updated" with merge date**
4. **Archive originals to `.claude/archive/learnings-{date}.md`**

### 2.5 Output Format

```text
Learnings Optimization Report
═════════════════════════════

Duplicates Found: 3
  1. "Docker secrets" appears in lines 11, 45, 89
     → Recommend: Merge into single entry

  2. "pnpm workspace" in Docker & Node.js sections
     → Recommend: Keep in Node.js, reference from Docker

Stale Learnings: 1
  - "Use compose V1 syntax" - Compose V2 is now default
    → Recommend: Update or remove

Category Suggestions:
  - Move "Bash arithmetic" from Testing to Development Workflow
  - New category "Security" has enough entries (5+)

Actions:
  [1] Auto-merge duplicates (creates archive)
  [2] Review each manually
  [3] Skip learnings optimization
```

---

## Phase 3: Commands Audit (`--commands`)

### 3.1 Usage Analysis

Scan session logs for command usage:

```bash
# Count command invocations in sessions
grep -rh "^/[a-z-]*" .claude/sessions/*.md | sort | uniq -c | sort -rn
```

Output:

```text
Command Usage (from session logs)
═════════════════════════════════

  15  /commit
  12  /test
   8  /status
   5  /session-start
   3  /learnings
   1  /plan
   0  /docs-review      ← Never used
   0  /sync-check       ← Never used
```

### 3.2 Redundancy Check

Identify overlapping commands:

- `/review` vs `/commit` (both check changes)
- `/docs-audit` vs `/docs-review` (similar purpose)
- `/quality-audit` vs `/security-audit` (could be one with flags?)

### 3.3 Gap Analysis

What's missing based on actual workflow?

- Common manual tasks that could be automated
- Frequently typed bash commands
- Repetitive multi-step processes

### 3.4 Command Health

For each command, check:

- [ ] Description accurate?
- [ ] Allowed-tools sufficient?
- [ ] Prompt clear and complete?
- [ ] Works with current project structure?

### 3.5 Recommendations

```text
Commands Audit Report
═════════════════════

Usage Patterns:
  High:   /commit, /test, /status
  Medium: /session-start, /learnings
  Low:    /plan, /review
  Unused: /docs-review, /sync-check

Recommendations:
  [CONSIDER] /docs-review unused for 30+ days - deprecate?
  [MERGE] /review functionality into /commit?
  [NEW] Consider /quick-fix for single-file changes
  [UPDATE] /plan description outdated

Structural Issues:
  [FIX] /changelog references removed function
  [WARN] /security-audit very long - consider splitting
```

---

## Phase 4: Sessions Cleanup (`--sessions`)

### 4.1 Session Inventory

```bash
# List sessions with age and size
ls -lth .claude/sessions/session-*.md
wc -l .claude/sessions/session-*.md
```

### 4.2 Archival Criteria

Sessions older than 30 days:

1. Extract key information:
   - Session Summary
   - Learnings (should already be in LEARNINGS.md)
   - Open Items (should be in BACKLOG.md)
2. Create condensed archive entry
3. Move original to `.claude/archive/sessions/`

### 4.3 Integrity Check

Before archiving, verify:

- [ ] All learnings synced to LEARNINGS.md?
- [ ] Open items captured in BACKLOG.md?
- [ ] Summary present?

### 4.4 Archive Format

Create `.claude/archive/sessions-{year}.md`:

```markdown
# Archived Sessions 2026

## session-2026-01-15-0930

**Goal**: Implement GOSS testing **Duration**: 4 hours **Key Outcomes**:

- Added container tests
- Fixed nginx config **Learnings**: 3 (synced to LEARNINGS.md)

---

## session-2026-01-16-0552

...
```

### 4.5 Cleanup Actions

```text
Sessions Cleanup Report
═══════════════════════

Active Sessions: 3 (last 30 days)
Archive Candidates: 5 (older than 30 days)

Pre-Archive Check:
  session-2026-01-10:
    [OK] Summary present
    [OK] Learnings synced
    [WARN] 2 open items not in BACKLOG

Actions:
  [1] Archive all (moves to .claude/archive/)
  [2] Review each session
  [3] Skip sessions cleanup
```

---

## Phase 5: Settings Optimization (`--settings`)

Analyze and improve Claude Code settings files.

### 5.1 File Overview

| File                  | Purpose                                | Committed |
| --------------------- | -------------------------------------- | --------- |
| `settings.json`       | Shared team settings (hooks)           | Yes       |
| `settings.local.json` | Personal settings (permissions, hooks) | No        |

### 5.2 Permissions Analysis

**File Responsibilities:**

| File                  | Purpose               | Contains                      |
| --------------------- | --------------------- | ----------------------------- |
| `settings.json`       | Shared (committed)    | Project-specific scripts only |
| `settings.local.json` | Personal (gitignored) | General tools, system utils   |

**Project-specific permissions (→ settings.json):**

- `Bash(./docker/hooks/*:*)`
- `Bash(./tests/goss/*:*)`
- `Bash(COMPOSE_PROFILES=* docker compose:*)`
- `Bash(ENABLE_SEAWEEDFS=true docker compose:*)`
- Other project-specific env var + command combos

**Personal permissions (→ settings.local.json):**

- General tools: `make`, `docker`, `git`, `composer`, `pnpm`
- System utilities: `ls`, `cat`, `grep`, `find`, etc.
- Personal preferences

**Pattern Matching Behavior:**

- `Bash(command:*)` matches `command <any args>` including subcommands
- Example: `Bash(docker compose:*)` covers `logs`, `exec`, `run`, etc.
- Example: `Bash(make:*)` covers all make targets
- Specific subcommand patterns are redundant but harmless

**Dangerous patterns to flag:**

- `Bash(sudo rm:*)` - allows `sudo rm -rf /`
- `Bash(bash:*)` - allows arbitrary script execution
- `Bash(rm:*)` - allows `rm -rf` (very dangerous)

**Unused permissions:**

- Cross-reference with session logs to find never-used permissions
- Identify permissions added for one-time tasks

**Security review:**

- Overly broad patterns (e.g., `Bash(rm:*)` is dangerous)
- Sensitive command access (check against best practices)

**Organization:**

- Logical grouping (file ops, git, docker, etc.)
- Alphabetical within groups for readability

### 5.3 Hooks Analysis

For both `settings.json` and `settings.local.json`:

**Effectiveness check:**

- Do hooks still serve their purpose?
- Are matchers too broad or too narrow?
- Do commands still work? (paths, tools exist?)

**Redundancy:**

- Same hook in both files?
- Overlapping matchers?

**Performance:**

- Are hook commands fast? (shouldn't slow down workflow)
- Could expensive checks be optimized?

**Coverage gaps:**

- Common mistakes not caught by hooks?
- Workflow improvements possible?

### 5.4 Consistency Check

Between `settings.json` and `settings.local.json`:

- Conflicting hooks (same matcher, different behavior)?
- Team hooks overridden locally without reason?
- Local hooks that should be shared?

### 5.5 Best Practices Audit

**Permissions:**

```text
[WARN] Broad pattern: Bash(rm:*) - consider restricting
[OK] Git commands properly scoped
[SUGGEST] Group related permissions with comments
```

**Hooks:**

```text
[OK] PreToolUse: Container check for tests
[IMPROVE] PostToolUse: Could add success/failure to notification
[STALE] Hook references removed script path
```

### 5.6 Output Format

```text
Settings Optimization Report
════════════════════════════

settings.json (shared):
  Hooks: 3 defined
    PreToolUse: 1 (container check)
    PostToolUse: 2 (config reminder, session log)
  Issues:
    [OK] All hooks functional
    [SUGGEST] Add hook for lockfile commits

settings.local.json (personal):
  Permissions: 78 entries
    [OK] No exact duplicates found
    [INFO] 12 unused in last 30 days (review if needed)
    [OK] No security concerns
  Hooks: 1 defined
    PostToolUse: 1 (ntfy notification)
  Issues:
    [OK] Notification hook working

Cross-file:
  [OK] No conflicts between shared and local hooks
  [SUGGEST] Move container check to settings.json for team

Actions:
  [1] Remove exact duplicates (if any)
  [2] Review unused permissions
  [3] Apply hook improvements
  [4] Skip settings optimization
```

### 5.7 Optimization Actions

**Safe auto-fixes (with confirmation):**

- Remove exact duplicate permissions (same pattern twice)
- Sort permissions alphabetically within groups
- Fix obvious typos in patterns

**Manual review required:**

- Removing "unused" permissions (might be needed occasionally)
- Modifying hooks (could affect workflow)
- Moving hooks between files

Note: Specific patterns like `Bash(docker compose logs:*)` ARE redundant with
`Bash(docker compose:*)`, but keeping them is harmless and can serve as
documentation of commonly used commands.

---

## Phase 6: Full Optimization (`--all`)

Run all phases sequentially:

1. CLAUDE.md optimization
2. Learnings cleanup
3. Commands audit
4. Sessions archival
5. Settings optimization

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

## Integration

After optimization:

- Update CLAUDE.md command table if commands changed
- Run `/sync-check` to verify configuration alignment
- Consider running `/learnings --sync` if sessions were processed

---

## Example Workflow

```text
$ /optimize --all

╔════════════════════════════════════════════════════════════╗
║ Self-Optimization                                          ║
╠════════════════════════════════════════════════════════════╣
║ Phase 1: CLAUDE.md Analysis                                ║
║   Checking structure... OK                                 ║
║   Finding redundancies... 2 found                          ║
║   Verifying completeness... 1 missing command              ║
╠════════════════════════════════════════════════════════════╣
║ Phase 2: Learnings Cleanup                                 ║
║   Scanning 158 lines...                                    ║
║   Duplicates: 3                                            ║
║   Stale: 1                                                 ║
║   Category issues: 2                                       ║
╠════════════════════════════════════════════════════════════╣
║ Phase 3: Commands Audit                                    ║
║   Analyzing 13 commands...                                 ║
║   Unused: 2                                                ║
║   Outdated: 1                                              ║
║   Gaps: 1 suggestion                                       ║
╠════════════════════════════════════════════════════════════╣
║ Phase 4: Sessions Cleanup                                  ║
║   Active: 3 | Archive candidates: 5                        ║
║   Unsynced learnings: 0                                    ║
╠════════════════════════════════════════════════════════════╣
║ Phase 5: Settings Analysis                                 ║
║   Permissions: 78 entries (0 duplicates)                   ║
║   Hooks: 4 total (all functional)                          ║
║   Cross-file conflicts: 0                                  ║
╠════════════════════════════════════════════════════════════╣
║ Summary                                                    ║
║   Total issues: 17                                         ║
║   Quick wins: 6                                            ║
║   Requires decision: 11                                    ║
╚════════════════════════════════════════════════════════════╝

Review findings? [Y/n]
```

---

## Notes

- Run periodically (monthly recommended)
- Best run at session start or end
- Creates no git changes until user approves
- Archive directory is gitignored
