---
description: Comprehensive quality audit with progress tracking and resume support
context: fork
allowed-tools: Read, Write, Grep, Glob, Bash(make:*), Bash(find:*), Bash(grep:*),
  Bash(diff:*), Bash(git:*), Bash(ls:*), Bash(cat:*), Bash(head:*), Bash(wc:*),
  Bash(docker:*)
---

# Quality Audit

Comprehensive quality audit with persistent progress tracking.

## Progress File

All findings are saved to `.claude/quality-audit-progress.json` for resumability.
This file persists across context resets and allows interruption/resume.

## Initialization

First, check if a previous audit exists:

1. Read `.claude/quality-audit-progress.json` if it exists
2. If exists:
   - Compare `git_commit` with current `git rev-parse HEAD`
   - If different: Warn user that codebase changed, ask whether to resume or restart
   - If same: Resume from last incomplete phase
3. If not exists: Create new progress file with this structure:

```json
{
  "audit_type": "quality",
  "started_at": "<ISO timestamp>",
  "last_updated": "<ISO timestamp>",
  "git_commit": "<commit hash from git rev-parse HEAD>",
  "git_branch": "<branch name from git branch --show-current>",
  "status": "in_progress",
  "phases": {
    "automated_checks": { "status": "pending", "findings": [] },
    "config_parity": { "status": "pending", "findings": [] },
    "industry_standards": { "status": "pending", "findings": [] },
    "code_quality": { "status": "pending", "findings": [] },
    "best_practices": { "status": "pending", "findings": [] },
    "platform_compat": { "status": "pending", "findings": [] },
    "zero_config": { "status": "pending", "findings": [] },
    "test_coverage": { "status": "pending", "findings": [] }
  },
  "summary": null
}
```

## Execution Phases

Execute phases sequentially. After each phase, update the progress file immediately.
Skip phases that are already marked as "complete".

### Phase 1: Automated Quality Checks

Run existing tooling to establish baseline:

```bash
make validate          # composer.json/lock + package.json/lock integrity
make lint-docker       # Hadolint for Dockerfiles
make lint-config       # YAML validation
make lint-md           # Markdown style
make cs-check          # PHP coding style (PSR-12)
make analyse           # PHPStan static analysis
make phpmd             # PHP Mess Detector
make rector-check      # Automated refactoring suggestions
make prettier-check    # Code formatting
make type-check        # TypeScript type checking
make lint-node         # ESLint
```

Any failures are [CRIT] findings.
Update progress file: set `phases.automated_checks.status = "complete"`.

### Phase 2: Configuration Parity

Check synchronization between related configuration files:

**Docker Compose Files:**

- `compose.yaml` ↔ `compose.override.yaml` ↔ `compose.production.yaml`
- Services defined in one but missing in others
- Environment variables inconsistent across files
- Volume mounts differing unexpectedly

**Dockerfile Targets:**

- `docker/php/Dockerfile`: development vs production targets
- `docker/nginx/Dockerfile`: development vs production targets
- `docker/node/Dockerfile`: development vs production targets

**Environment Files:**

- `.env.example`: All variables documented?
- `.env.example` ↔ actual `.env`: Any missing variables?

**PHP/Nginx Configuration:**

- `docker/php/php.ini` ↔ `docker/php/conf.d/development.ini`
- `docker/nginx/conf.d/ssl-development.conf.template` ↔
  `ssl-production.conf.template`

**IDE Configuration:**

- `.vscode/tasks.json`: All Makefile targets represented?
- `.idea/runConfigurations/`: Matches Makefile targets?

Update progress file: set `phases.config_parity.status = "complete"`.

### Phase 3: Industry Standards & Conventions

**Docker Standards:**

- OCI Compliance: Labels present? (org.opencontainers.image.\*)
- CIS Docker Benchmark: USER instruction, HEALTHCHECK, no latest tags
- Hadolint: Run `make lint-docker` and report issues

**PHP Standards:**

- PSR-4: Autoloading correct? Namespace matches directory?
- PSR-12: Run `make cs-check`
- Strict Types: `declare(strict_types=1)` in all files?

**12-Factor App Principles:**

1. Codebase: Single repo, multiple deploys?
2. Dependencies: Explicitly declared?
3. Config: Environment variables, not hardcoded?
4. Backing Services: Attached resources via URLs?
5. Build/Release/Run: Strictly separated?
6. Processes: Stateless, share-nothing?
7. Port Binding: Self-contained?
8. Concurrency: Scale via process model?
9. Disposability: Fast startup, graceful shutdown?
10. Dev/Prod Parity: Minimal gap?
11. Logs: Treat as event streams (stdout)?
12. Admin Processes: One-off tasks as processes?

**Git Standards:**

- `.gitignore`: Complete?
- `.gitattributes`: Line endings configured?
- Conventional Commits: Recent commits follow format?

Update progress file: set `phases.industry_standards.status = "complete"`.

### Phase 4: Code Quality

**Dead Code Detection:**

```bash
make knip              # Unused exports, dependencies, files
make depcheck          # Unused Node.js dependencies
make rector-check      # Suggested refactorings
```

**Unused Dependencies:**

- `composer.json`: Packages listed but not imported?
- `package.json`: Packages listed but not imported?

**Documentation Accuracy:**

- `README.md`: Does it match current project state?
- `CHANGELOG.md`: Up to date with recent changes?
- `documentation/`: Files current or stale?
- Inline comments: Outdated TODOs, FIXMEs?

**Code Smells:**

- Functions/methods too long (>50 lines)?
- Classes too large (>500 lines)?
- Deep nesting (>4 levels)?
- Magic numbers without constants?

Update progress file: set `phases.code_quality.status = "complete"`.

### Phase 5: Best Practices

**Docker Best Practices:**

- Multi-Stage Builds: Used to minimize image size?
- Layer Caching: Optimal instruction order?
- .dockerignore: Present and comprehensive?
- Image Size: Run `make dive` to analyze layers

**Makefile Quality:**

- Help Texts: All targets have `## Description`?
- Consistency: Similar targets follow same patterns?
- Error Handling: Failures handled gracefully?
- Idempotency: Safe to run multiple times?

**CI/CD Configuration:**

- `.github/workflows/`: All quality checks included? Caching configured?
- `.gitlab-ci.yml`: Mirrors GitHub Actions functionality?

Update progress file: set `phases.best_practices.status = "complete"`.

### Phase 6: Platform Compatibility

**Cross-Platform Issues:**

- Path Separators: Hardcoded `/` vs `\`?
- Line Endings: CRLF vs LF issues?
- Case Sensitivity: Filename case mismatches?
- Shell Scripts: Bash-specific syntax in POSIX scripts?

**File Permissions:**

- UID/GID Handling: Docker volumes respect host user?
- Executable Bits: Scripts have +x?

**WSL/Docker Desktop:**

- Volume mount performance?
- File watching works (inotify limits)?

Update progress file: set `phases.platform_compat.status = "complete"`.

### Phase 7: Zero-Config Experience

**Fresh Clone Test:**

Would `git clone && make up` work without manual steps?

- `.env.example` provides working defaults?
- `make init` creates all necessary files?
- `make up` starts without errors?
- Default ports don't conflict with common services?
- Health checks pass on first start?

**Default Values:**

- Database credentials: Secure but functional defaults?
- API keys: Placeholder or development values?
- URLs: localhost with correct ports?

**Error Messages:**

- Clear guidance when prerequisites missing?
- Helpful error messages, not cryptic failures?

Update progress file: set `phases.zero_config.status = "complete"`.

### Phase 8: Test Coverage & Quality

**Test Structure:**

- Mirror source structure (`src/` → `tests/`)?
- Unit vs Integration vs Feature tests separated?
- Test naming conventions followed?

**Coverage Metrics:**

```bash
make test-coverage-php
make test-coverage-node
```

- Line coverage acceptable (>80%)?
- Critical paths covered?
- Edge cases tested?

**Test Quality:**

- Tests actually assert behavior?
- Mocks used appropriately?
- Flaky tests identified?

Update progress file: set `phases.test_coverage.status = "complete"`.

## Synthesis & Report

After all phases complete:

1. Read all findings from progress file
2. Count by severity: [CRIT], [MED], [LOW]
3. Generate summary table
4. Update progress file: set `status = "complete"` and populate `summary`
5. Write final report to `.claude/reports/quality-audit-{date}-{commit}.md`

### Report File

Save the final report to:

```text
.claude/reports/quality-audit-YYYY-MM-DD-{short-commit}.md
```

Example: `.claude/reports/quality-audit-2026-01-15-7cc7668.md`

Create the `.claude/reports/` directory if it doesn't exist.

### Report Format

```markdown
# Quality Audit Report

**Date**: YYYY-MM-DD
**Branch**: <git_branch>
**Commit**: <git_commit>
**Duration**: <started_at> to <last_updated>

## Summary

| Category           | [CRIT] | [MED] | [LOW] | Status  |
| ------------------ | ------ | ----- | ----- | ------- |
| Automated Checks   | X      | X     | X     | OK/FAIL |
| Config Parity      | X      | X     | X     | OK/FAIL |
| Industry Standards | X      | X     | X     | OK/FAIL |
| Code Quality       | X      | X     | X     | OK/FAIL |
| Best Practices     | X      | X     | X     | OK/FAIL |
| Platform Compat    | X      | X     | X     | OK/FAIL |
| Zero-Config        | X      | X     | X     | OK/FAIL |
| Test Coverage      | X      | X     | X     | OK/FAIL |
| **Total**          | X      | X     | X     | --      |

## Critical Issues (Fix Immediately)

| Severity | File         | Issue       | Recommendation |
| -------- | ------------ | ----------- | -------------- |
| [CRIT]   | path/to/file | Description | Concrete fix   |

## All Findings by Phase

### Phase 1: Automated Quality Checks
[Findings...]

### Phase 2: Configuration Parity
[Findings...]

[...continue for all phases...]

## Quick Wins (Low Effort, High Impact)

1. ...
2. ...

## Technical Debt Backlog

1. ...
2. ...

## Next Steps

1. Fix all [CRIT] Critical issues first
2. Address [MED] Medium issues in next sprint
3. Schedule [LOW] Low issues for continuous improvement
```

## Resume Instructions

If this audit was interrupted:

1. The progress file `.claude/quality-audit-progress.json` contains all work done
2. Run `/quality-audit` again to resume from last incomplete phase
3. To start fresh, delete the progress file first

## Notes

- Some checks require running containers (`make up` first)
- If containers not running, note skipped checks in findings
- Progress file is gitignored and won't be committed
