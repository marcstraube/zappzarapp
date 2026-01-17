---
description: Interactive documentation logic review with optional auto-fix
context: fork
allowed-tools: Read, Write, Edit, Grep, Glob, Bash(make:*), Bash(find:*),
  Bash(grep:*), Bash(git:*), Bash(ls:*), Bash(cat:*), Bash(head:*), Bash(test:*),
  Bash(docker:*), AskUserQuestion
argument-hint: [--fix] [--file <path>] [--since <commit>]
---

# Documentation Logic Review

Interactive review of documentation for logical correctness, accuracy, and
completeness. Validates that documented processes match actual implementation.

## Modes

Parse `$ARGUMENTS`:

- `--fix`: Enable auto-fix for safe corrections (paths, names, typos)
- `--file <path>`: Review only specific file
- `--since <commit>`: Review only files changed since commit
- No arguments: Review all documentation files interactively

Examples:

```bash
/docs-review                              # Full interactive review
/docs-review --fix                        # With auto-fix enabled
/docs-review --file documentation/QUICKSTART.md
/docs-review --since main --fix
```

## Progress File

Progress saved to `.claude/docs-review-progress.json` for resumability.

```json
{
  "audit_type": "docs-review",
  "mode": "full | incremental | single-file",
  "auto_fix": true | false,
  "started_at": "<ISO timestamp>",
  "last_updated": "<ISO timestamp>",
  "git_commit": "<commit hash>",
  "git_branch": "<branch name>",
  "status": "in_progress",
  "files": {
    "documentation/QUICKSTART.md": {
      "status": "reviewed | pending | skipped",
      "findings": [],
      "fixes_applied": []
    }
  },
  "summary": null
}
```

## Review Process

For each documentation file, perform these checks:

### 1. Document Purpose & Scope

- What is this document about?
- Is the title/heading accurate?
- Is the scope clearly defined?

### 2. Prerequisites Validation

Find all mentioned prerequisites and validate:

- Required tools installed? (`docker`, `make`, etc.)
- Required files exist? (`.env`, configs)
- Required services running?
- Are prerequisites complete or are some missing?

### 3. Step-by-Step Workflow Validation

For each documented workflow/process:

**a) Identify Steps:**

- Extract numbered steps or sequential instructions
- Note any conditional branches ("if X, then Y")

**b) Validate Order:**

- Are steps in correct dependency order?
- Would following steps 1-N actually work?
- Are there implicit steps not documented?

**c) Validate Commands:**

- Execute or dry-run commands where safe
- Check command output matches documented output
- Verify file paths and targets exist

**d) Validate Results:**

- Does described outcome match reality?
- Are success criteria accurate?
- Are error scenarios documented?

### 4. Code Example Validation

For each code example:

- Is syntax correct for the language?
- Would this code actually work in context?
- Are imports/dependencies available?
- Does output match documented output?

### 5. Configuration Example Validation

For each config snippet:

- Compare against actual config file
- Are keys/values still valid?
- Are defaults accurate?
- Are comments/explanations correct?

### 6. Cross-Reference Validation

- References to other docs accurate?
- References to external resources valid?
- Version numbers current?
- Feature descriptions match implementation?

### 7. Completeness Check

- Are all relevant options documented?
- Are edge cases covered?
- Are troubleshooting tips included?
- Is there a "next steps" or "see also" section?

## Interactive Flow

For each file:

```text
┌─────────────────────────────────────────────────────────────┐
│ Reviewing: documentation/security/ENCRYPTION.md             │
├─────────────────────────────────────────────────────────────┤
│ 1. Read and understand document purpose                     │
│ 2. Identify all workflows/processes described               │
│ 3. For each workflow:                                       │
│    - Trace through actual codebase                          │
│    - Verify steps work as documented                        │
│    - Note discrepancies                                     │
│ 4. Report findings                                          │
│ 5. If --fix: Apply safe corrections                         │
│ 6. Ask user: "Continue to next file?" or "Review findings?" │
└─────────────────────────────────────────────────────────────┘
```

## Auto-Fix Rules (when --fix enabled)

### Safe to Auto-Fix

| Issue | Fix Action |
| ----- | ---------- |
| Wrong file path | Update to correct path |
| Renamed Make target | Update target name |
| Renamed env variable | Update variable name |
| Typo in command | Fix obvious typo |
| Wrong config key name | Update key name |
| Broken internal link | Fix link path |
| Missing code fence language | Add language |

### Requires User Confirmation

| Issue | Action |
| ----- | ------ |
| Outdated workflow | Show diff, ask to update |
| Missing steps | Propose addition, ask to confirm |
| Wrong output example | Show actual output, ask to replace |
| Deprecated feature | Ask how to handle |

### Never Auto-Fix

| Issue | Action |
| ----- | ------ |
| Major rewrite needed | Report as [CRIT], manual fix |
| Unclear intent | Ask user for clarification |
| Multiple valid options | Ask user to choose |
| Security-sensitive | Always manual review |

## Findings Format

For each finding:

```json
{
  "file": "documentation/QUICKSTART.md",
  "line": 42,
  "severity": "[CRIT] | [MED] | [LOW]",
  "category": "workflow | example | reference | completeness",
  "issue": "Step 3 requires .env but step 2 doesn't create it",
  "evidence": "Actual behavior vs documented behavior",
  "recommendation": "Add 'make init' before 'make up'",
  "auto_fixable": true | false,
  "fixed": true | false
}
```

## Review Checklist per File Type

### QUICKSTART / Getting Started

- [ ] Fresh clone scenario works?
- [ ] All prerequisites listed?
- [ ] Steps in correct order?
- [ ] Estimated time realistic?
- [ ] Success verification included?

### Security Documentation

- [ ] Threat model accurate?
- [ ] Mitigations actually implemented?
- [ ] Commands work as shown?
- [ ] Secrets handling correct?
- [ ] Compliance claims valid?

### Infrastructure Documentation

- [ ] Architecture diagrams current?
- [ ] Service names match compose.yaml?
- [ ] Port numbers correct?
- [ ] Environment variables accurate?
- [ ] Scaling instructions work?

### Development Documentation

- [ ] Setup instructions complete?
- [ ] Tool versions current?
- [ ] Workflows match reality?
- [ ] Examples run successfully?
- [ ] Debugging tips work?

### Testing Documentation

- [ ] Test commands work?
- [ ] Coverage thresholds accurate?
- [ ] CI pipeline matches docs?
- [ ] Fixtures/mocks documented?

## Synthesis & Report

After reviewing all files:

1. Compile all findings from progress file
2. Count by severity and category
3. List all applied fixes
4. Generate recommendations for manual fixes
5. Write report to `.claude/reports/docs-review-{date}-{commit}.md`

### Report Format

```markdown
# Documentation Logic Review Report

**Date**: YYYY-MM-DD
**Branch**: <git_branch>
**Commit**: <git_commit>
**Mode**: full | incremental | single-file
**Auto-fix**: enabled | disabled
**Files reviewed**: X of Y

## Summary

| Category    | [CRIT] | [MED] | [LOW] | Auto-Fixed |
| ----------- | ------ | ----- | ----- | ---------- |
| Workflow    | X      | X     | X     | X          |
| Examples    | X      | X     | X     | X          |
| References  | X      | X     | X     | X          |
| Completeness| X      | X     | X     | X          |
| **Total**   | X      | X     | X     | X          |

## Critical Issues (Manual Fix Required)

| File | Line | Issue | Recommendation |
| ---- | ---- | ----- | -------------- |
| ...  | ...  | ...   | ...            |

## Auto-Applied Fixes

| File | Line | Issue | Fix Applied |
| ---- | ---- | ----- | ----------- |
| ...  | ...  | ...   | ...         |

## Files Reviewed

### documentation/QUICKSTART.md
- Status: PASS | FAIL
- Findings: X critical, Y medium, Z low
- Fixes applied: N

### documentation/security/ENCRYPTION.md
- Status: PASS | FAIL
- Findings: ...

[...continue for all files...]

## Recommendations

1. High-priority manual fixes
2. Documentation improvements
3. Process changes needed

## Next Steps

1. Fix all [CRIT] issues immediately
2. Review and apply suggested [MED] fixes
3. Consider [LOW] improvements for next iteration
```

## Resume Instructions

If review was interrupted:

1. Progress file contains all reviewed files and findings
2. Run `/docs-review` again with same arguments to resume
3. Review will continue from last unreviewed file
4. To start fresh, delete `.claude/docs-review-progress.json`

## Notes

- Interactive review requires user presence for confirmations
- Auto-fix only applies safe, reversible changes
- All fixes are shown before applying (when in doubt, asks)
- Progress file is gitignored
- Consider running `git diff` after review to inspect changes
