---
description: Project audit (quality, security, docs) with incremental tracking
context: fork
allowed-tools:
  Read, Write, Edit, Grep, Glob, Bash(make:*), Bash(find:*), Bash(grep:*),
  Bash(diff:*), Bash(git:*), Bash(ls:*), Bash(cat:*), Bash(head:*), Bash(wc:*),
  Bash(docker:*), Bash(docker compose:*), Bash(test:*), AskUserQuestion
argument-hint: '[--quick | --full] [--quality | --security | --docs]'
---

# Project Audit

Unified audit command with incremental tracking. Combines quality, security, and
documentation audits with smart baseline detection.

## Arguments

Parse `$ARGUMENTS`:

**Mode (mutually exclusive):**

- (default): Quick-Audit since last baseline
- `--quick`: Same as default, explicit quick mode
- `--full`: Complete audit of entire project

**Scope (can combine, default = all):**

- `--quality`: Code quality checks (PHPStan, ESLint, etc.)
- `--security`: Security vulnerability checks
- `--docs`: Documentation validation
- No scope flag: All three areas

**Examples:**

```bash
/audit                    # Quick-Audit, all areas
/audit --quick            # Same as above
/audit --full             # Full-Audit, all areas
/audit --quality          # Quick-Audit, quality only
/audit --full --security  # Full-Audit, security only
/audit --docs --quality   # Quick-Audit, docs + quality
```

## State File

Progress and baseline tracking in `.claude/state/audit-state.json`:

```json
{
  "last_full_audit": {
    "date": "2026-01-15T10:30:00Z",
    "git_commit": "abc1234def5678",
    "git_branch": "main",
    "areas": {
      "quality": {
        "status": "pass",
        "critical": 0,
        "warnings": 3,
        "checked_at": "2026-01-15T10:30:00Z"
      },
      "security": {
        "status": "pass",
        "critical": 0,
        "warnings": 1,
        "checked_at": "2026-01-15T10:35:00Z"
      },
      "docs": {
        "status": "warn",
        "critical": 0,
        "warnings": 5,
        "checked_at": "2026-01-15T10:40:00Z"
      }
    },
    "report": ".claude/reports/audit-2026-01-15-abc1234.md"
  },
  "last_quick_audit": {
    "date": "2026-01-18T14:20:00Z",
    "git_commit": "def5678abc1234",
    "baseline_commit": "abc1234def5678",
    "files_checked": 12,
    "areas_checked": ["quality", "security"],
    "findings": 1
  }
}
```

## Baseline Detection (Quick-Audit)

```text
1. Read state/audit-state.json
2. Determine baseline commit:

   IF last_quick_audit exists
      AND last_quick_audit.date > last_full_audit.date
   THEN
      baseline = last_quick_audit.git_commit
   ELSE
      baseline = last_full_audit.git_commit

   IF no baseline found (first run)
   THEN
      → Prompt: "No previous audit found. Run full audit first?"
      → Or: Use reasonable default (last 50 commits, or HEAD~50)

3. Get changed files:
   git diff <baseline>..HEAD --name-only

4. If no changes:
   → "No changes since last audit. Nothing to check."
```

## Quick-Audit Workflow

### Step 1: Detect Changes

```bash
git diff <baseline>..HEAD --name-only
```

### Step 2: Categorize Files

| Pattern                             | Area           | Checks             |
| ----------------------------------- | -------------- | ------------------ |
| `src/php/**`, `tests/php/**`        | Quality        | PHPStan, CS, PHPMD |
| `src/node/**`, `tests/node/**`      | Quality        | ESLint, TypeCheck  |
| `docker/**`, `compose*.yaml`        | Security       | Trivy, Hadolint    |
| `.env*`, `*secret*`, `*credential*` | Security       | Secret scan        |
| `.zappzarapp/docs/**`, `*.md`       | Docs           | Links, references  |
| `Makefile`                          | Quality + Docs | Target validation  |

### Step 3: Run Relevant Checks Only

For each affected area (filtered by `--quality`/`--security`/`--docs` if
specified):

**Quality checks (if PHP files changed):**

```bash
make analyse      # PHPStan
make cs-check     # PHP-CS-Fixer
make phpmd        # Mess Detector
```

**Quality checks (if Node files changed):**

```bash
make lint-node    # ESLint
make type-check   # TypeScript
```

**Security checks (if Docker/env files changed):**

```bash
make security-scan    # Trivy
make lint-docker      # Hadolint
# Grep for hardcoded secrets in changed files
```

**Docs checks (if markdown/docs changed):**

```bash
make lint-md          # Markdownlint
# Validate internal links in changed files
# Check file references still exist
```

### Step 4: Report & Update State

```text
Quick Audit Results
═══════════════════════════════════════════════
Baseline: abc1234 (2026-01-15, last quick-audit)
Changes:  12 files since baseline
Areas:    quality, security

Quality (8 files):
  ✓ PHPStan: 0 errors
  ✓ ESLint: 0 errors
  ! CS-Check: 2 warnings (auto-fixable)

Security (4 files):
  ✓ No hardcoded secrets
  ✓ Docker config OK

Result: PASS (2 warnings)
═══════════════════════════════════════════════
```

Update `last_quick_audit` in state file.

## Full-Audit Workflow

Complete project audit, regardless of changes.

### Phase 1: Quality

```bash
make validate          # Dependency integrity
make cs-check          # PHP coding style
make analyse           # PHPStan
make phpmd             # Mess Detector
make rector-check      # Refactoring suggestions
make lint-node         # ESLint
make type-check        # TypeScript
make prettier-check    # Formatting
```

Record findings with severity [CRIT], [WARN], [INFO].

### Phase 2: Security

**Dependency vulnerabilities:**

```bash
make security-deps        # Composer audit
make security-audit-node  # pnpm audit
```

**Container security:**

```bash
make security-scan        # Trivy
make security-config      # Dockerfile misconfig
make lint-docker          # Hadolint
```

**Code patterns:**

- Hardcoded credentials (grep patterns)
- SQL injection patterns
- XSS vulnerabilities
- Insecure deserialization

**Configuration:**

- `.env.example` secure defaults
- Docker security (USER, caps, secrets)
- Nginx headers and SSL config

### Phase 3: Documentation

**Structural checks:**

```bash
make lint-md              # Markdownlint
```

**Content validation:**

- Internal links resolve
- External links reachable (optional, slow)
- File references exist
- Code examples valid syntax
- Make targets documented and exist
- Environment variables documented

### Phase 4: Synthesis

Generate comprehensive report:

```text
Full Audit Report
═══════════════════════════════════════════════
Date:   2026-01-20
Commit: def5678
Branch: main

Summary:
┌──────────┬────────┬────────┬────────┬────────┐
│ Area     │ [CRIT] │ [WARN] │ [INFO] │ Status │
├──────────┼────────┼────────┼────────┼────────┤
│ Quality  │ 0      │ 3      │ 5      │ PASS   │
│ Security │ 0      │ 1      │ 2      │ PASS   │
│ Docs     │ 0      │ 5      │ 8      │ WARN   │
├──────────┼────────┼────────┼────────┼────────┤
│ Total    │ 0      │ 9      │ 15     │ PASS   │
└──────────┴────────┴────────┴────────┴────────┘

Critical Issues: None

Warnings requiring attention:
1. [Quality] src/php/App/Service/X.php - unused import
2. [Security] compose.yaml - container runs as root
3. [Docs] .zappzarapp/docs/API.md - broken link to removed file
...

Next quick-audit baseline: def5678
═══════════════════════════════════════════════
```

Save report to `.claude/reports/audit-YYYY-MM-DD-<commit>.md`

Update `last_full_audit` in state file.

## Report File Format

`.claude/reports/audit-YYYY-MM-DD-<short-commit>.md`:

```markdown
# Audit Report

**Date**: YYYY-MM-DD HH:MM **Mode**: Full / Quick **Commit**: <full commit hash>
**Branch**: <branch name> **Baseline**: <baseline commit> (Quick-Audit only)

## Summary

| Area     | Critical | Warnings | Info | Status |
| -------- | -------- | -------- | ---- | ------ |
| Quality  | X        | X        | X    | OK     |
| Security | X        | X        | X    | OK     |
| Docs     | X        | X        | X    | WARN   |

## Critical Issues

None / List...

## Warnings

### Quality

- [File:Line] Description

### Security

- [File:Line] Description

### Documentation

- [File:Line] Description

## Informational

<collapsed or brief list>

## Files Checked

- X PHP files
- X Node files
- X Docker files
- X Documentation files

## Next Steps

1. Fix critical issues immediately
2. Address warnings before next release
3. Next quick-audit will use baseline: <commit>
```

## Edge Cases

### No Previous Audit

```text
No previous audit found in state file.

Options:
○ Run full audit now (Recommended)
○ Set current commit as baseline (skip initial audit)
○ Cancel
```

### State File Corrupted

```text
State file invalid or corrupted.
Backing up to state/audit-state.json.bak
Starting fresh full audit.
```

### Baseline Commit Not Found

If baseline commit no longer exists (rebased, force-pushed):

```text
Baseline commit abc1234 not found in history.
Falling back to last full audit commit.
```

### No Changes Detected

```text
No changes since last audit (baseline: abc1234).

Options:
○ Run full audit anyway
○ Skip (nothing to check)
```

## Integration with Agent Workflow

This command can be used:

1. **Standalone**: Manual audit at any time
2. **As BACKLOG task**: "Run security audit" → Agent workflow
3. **Pre-release**: `/audit --full` before version bump
4. **CI/CD**: Quick-audit on every PR

## State File Location

- Path: `.claude/state/audit-state.json`
- Gitignored: Yes (personal/machine-specific)
- Survives: Context resets, session changes

## Notes

- Full audit can take several minutes
- Quick audit typically completes in seconds
- Some security checks require running containers
- External link checking is slow and optional
- State file should not be committed (machine-specific baselines)
