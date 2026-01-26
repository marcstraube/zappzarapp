---
name: audit
description: Project audit (quality, security, docs) with incremental tracking
model: sonnet
context: fork
allowed-tools:
  - Read
  - Write
  - Edit
  - Grep
  - Glob
  - Bash(make:*)
  - Bash(find:*)
  - Bash(grep:*)
  - Bash(diff:*)
  - Bash(git:*)
  - Bash(ls:*)
  - Bash(cat:*)
  - Bash(head:*)
  - Bash(wc:*)
  - Bash(docker:*)
  - Bash(docker compose:*)
  - Bash(test:*)
  - AskUserQuestion
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
      -> Prompt: "No previous audit found. Run full audit first?"
      -> Or: Use reasonable default (last 50 commits, or HEAD~50)

3. Get changed files:
   git diff <baseline>..HEAD --name-only

4. If no changes:
   -> "No changes since last audit. Nothing to check."
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
===============================================
Baseline: abc1234 (2026-01-15, last quick-audit)
Changes:  12 files since baseline
Areas:    quality, security

Quality (8 files):
  OK PHPStan: 0 errors
  OK ESLint: 0 errors
  !  CS-Check: 2 warnings (auto-fixable)

Security (4 files):
  OK No hardcoded secrets
  OK Docker config OK

Result: PASS (2 warnings)
===============================================
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

Generate comprehensive report and save to
`.claude/reports/audit-YYYY-MM-DD-<commit>.md`

Update `last_full_audit` in state file.

## Notes

- Full audit can take several minutes
- Quick audit typically completes in seconds
- Some security checks require running containers
- External link checking is slow and optional
- State file should not be committed (machine-specific baselines)
