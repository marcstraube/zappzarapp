# Semgrep Integration (Multi-Language SAST)

**Status:** Planned **Size:** Medium **Scope:** chore **Created:** 2026-01-20
**Planning:** Not required

## Context

Industry-standard SAST tool, covers both PHP and Node.js.

## Goal

Add Semgrep for comprehensive security scanning across all code.

## What It Finds

- SQL Injection (string concatenation in queries)
- XSS (unescaped output)
- Command Injection
- Path Traversal
- Hardcoded Secrets
- Insecure Deserialization
- OWASP Top 10 patterns

## Implementation

1. Add `.semgrep.yml` with rule configuration
2. Create `make security-scan` target (runs via Docker or pip)
3. Configure rulesets: `p/security-audit`, `p/owasp-top-ten`
4. Add to CI pipeline (optional, can be slow)
5. Document suppression syntax for false positives

## Files

Create:

- `.semgrep.yml` (rule configuration)

Modify:

- `Makefile` (new `security-scan` target)
- `.gitlab-ci.yml` / `.github/workflows/` (optional CI integration)

## Resources

- <https://semgrep.dev/docs/>
- <https://semgrep.dev/r> (rule registry)
