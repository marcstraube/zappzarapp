---
description: Comprehensive security audit with progress tracking and resume support
context: fork
allowed-tools: Read, Write, Grep, Glob, Bash(make:*), Bash(find:*), Bash(grep:*),
  Bash(ls:*), Bash(cat:*), Bash(head:*), Bash(docker:*)
---

# Security Audit

Comprehensive security audit with persistent progress tracking.

## Progress File

All findings are saved to `.claude/security-audit-progress.json` for resumability.
This file persists across context resets and allows interruption/resume.

## Initialization

First, check if a previous audit exists:

1. Read `.claude/security-audit-progress.json` if it exists
2. If exists:
   - Compare `git_commit` with current `git rev-parse HEAD`
   - If different: Warn user that codebase changed, ask whether to resume or restart
   - If same: Resume from last incomplete phase
3. If not exists: Create new progress file with this structure:

```json
{
  "audit_type": "security",
  "started_at": "<ISO timestamp>",
  "last_updated": "<ISO timestamp>",
  "git_commit": "<commit hash from git rev-parse HEAD>",
  "git_branch": "<branch name from git branch --show-current>",
  "status": "in_progress",
  "phases": {
    "dependencies": { "status": "pending", "findings": [] },
    "docker_images": { "status": "pending", "findings": [] },
    "hardcoded_secrets": { "status": "pending", "findings": [] },
    "environment_config": { "status": "pending", "findings": [] },
    "container_security": { "status": "pending", "findings": [] },
    "nginx_security": { "status": "pending", "findings": [] },
    "file_permissions": { "status": "pending", "findings": [] },
    "owasp_patterns": { "status": "pending", "findings": [] },
    "auth_sessions": { "status": "pending", "findings": [] }
  },
  "summary": null
}
```

## Execution Phases

Execute phases sequentially. After each phase, update the progress file immediately.
Skip phases that are already marked as "complete".

### Phase 1: Dependency Vulnerabilities

Run security scan tools:

```bash
make security-deps        # PHP/Composer vulnerabilities
make security-audit-node  # Node.js/pnpm vulnerabilities
```

Record findings with severity [CRIT], [MED], or [LOW].
Update progress file: set `phases.dependencies.status = "complete"`.

### Phase 2: Docker Image Vulnerabilities

```bash
make security-scan        # Trivy scan for HIGH/CRITICAL CVEs
make security-config      # Dockerfile misconfiguration check
```

Update progress file: set `phases.docker_images.status = "complete"`.

### Phase 3: Hardcoded Credentials & Secrets

Search codebase (exclude vendor/, node_modules/, .git/, build/):

Patterns to search:

- `password\s*=\s*['"][^'"]+['"]`
- `api[_-]?key\s*=\s*['"][^'"]+['"]`
- `secret\s*=\s*['"][^'"]+['"]`
- `BEGIN (RSA |EC |OPENSSH )?PRIVATE KEY`
- `AKIA[0-9A-Z]{16}` (AWS Access Key)

Update progress file: set `phases.hardcoded_secrets.status = "complete"`.

### Phase 4: Environment & Configuration Security

Check .env.example and configuration files for:

- Insecure default values (e.g., `password=secret`, `debug=true`)
- Missing security-relevant variables
- Exposed debug settings for production
- Weak encryption settings

Update progress file: set `phases.environment_config.status = "complete"`.

### Phase 5: Docker & Container Security

Analyze Docker configuration for:

- Containers running as root unnecessarily
- Privileged mode usage
- Exposed sensitive ports
- Missing health checks
- Secrets mounted insecurely
- Missing resource limits (memory, CPU)

Files to check:

- compose.yaml, compose.override.yaml, compose.production.yaml
- docker/\*/Dockerfile
- docker/\*/entrypoint\*.sh

Update progress file: set `phases.container_security.status = "complete"`.

### Phase 6: Nginx Security

Check Nginx configuration for:

- Missing security headers (X-Frame-Options, CSP, HSTS, etc.)
- Exposed server version
- Missing rate limiting
- Insecure SSL/TLS settings
- Directory listing enabled

Files: docker/nginx/conf.d/\*.conf, docker/nginx/snippets/\*

Update progress file: set `phases.nginx_security.status = "complete"`.

### Phase 7: File Permissions

Check for overly permissive files:

- Scripts with 777 permissions
- Sensitive files readable by others (.env, secrets/, private keys)
- Executable files that shouldn't be

Update progress file: set `phases.file_permissions.status = "complete"`.

### Phase 8: OWASP Top 10 Code Patterns

Search for common vulnerability patterns:

- **SQL Injection**: Raw SQL queries without parameterization
- **XSS**: Unescaped output, innerHTML usage
- **CSRF**: Missing token validation
- **Insecure Deserialization**: unserialize() with user input
- **Command Injection**: exec(), shell_exec(), system() with user input
- **Path Traversal**: File operations with unsanitized paths

Update progress file: set `phases.owasp_patterns.status = "complete"`.

### Phase 9: Authentication & Session Security

Check for:

- Weak password policies
- Missing brute-force protection
- Insecure session configuration
- Token expiration settings

Update progress file: set `phases.auth_sessions.status = "complete"`.

## Synthesis & Report

After all phases complete:

1. Read all findings from progress file
2. Count by severity: [CRIT], [MED], [LOW]
3. Generate summary table
4. Update progress file: set `status = "complete"` and populate `summary`
5. Write final report to `.claude/reports/security-audit-{date}-{commit}.md`

### Report File

Save the final report to:

```text
.claude/reports/security-audit-YYYY-MM-DD-{short-commit}.md
```

Example: `.claude/reports/security-audit-2026-01-15-7cc7668.md`

Create the `.claude/reports/` directory if it doesn't exist.

### Report Format

```markdown
# Security Audit Report

**Date**: YYYY-MM-DD
**Branch**: <git_branch>
**Commit**: <git_commit>
**Duration**: <started_at> to <last_updated>

## Summary

| Category                   | [CRIT] | [MED] | [LOW] | Status  |
| -------------------------- | ------ | ----- | ----- | ------- |
| Dependency Vulnerabilities | X      | X     | X     | OK/FAIL |
| Docker Images              | X      | X     | X     | OK/FAIL |
| Hardcoded Credentials      | X      | X     | X     | OK/FAIL |
| Environment Config         | X      | X     | X     | OK/FAIL |
| Container Security         | X      | X     | X     | OK/FAIL |
| Nginx Security             | X      | X     | X     | OK/FAIL |
| File Permissions           | X      | X     | X     | OK/FAIL |
| OWASP Top 10               | X      | X     | X     | OK/FAIL |
| Auth & Sessions            | X      | X     | X     | OK/FAIL |
| **Total**                  | X      | X     | X     | --      |

## Critical Issues (Fix Immediately)

| Severity | File         | Issue       | Recommendation |
| -------- | ------------ | ----------- | -------------- |
| [CRIT]   | path/to/file | Description | Concrete fix   |

## All Findings by Phase

### Phase 1: Dependency Vulnerabilities
[Findings...]

### Phase 2: Docker Images
[Findings...]

[...continue for all phases...]

## Next Steps

1. Fix all [CRIT] Critical issues immediately
2. Address [MED] Medium issues before next release
3. Schedule [LOW] Low issues for hardening sprint
```

## Resume Instructions

If this audit was interrupted:

1. The progress file `.claude/security-audit-progress.json` contains all work done
2. Run `/security-audit` again to resume from last incomplete phase
3. To start fresh, delete the progress file first

## Notes

- Some checks require running containers (`make up` first)
- If containers not running, note skipped checks in findings
- Progress file is gitignored and won't be committed
