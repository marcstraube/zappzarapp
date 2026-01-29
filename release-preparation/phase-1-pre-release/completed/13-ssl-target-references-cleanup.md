# SSL Target References Cleanup

**Status:** Open **Size:** Small **Scope:** docs **Created:** 2026-01-25
**Planning:** Not required

## Context

After migrating to Internal CA architecture (`ssl-ca` + `ssl-internal`), old
references to `ssl-selfsigned` and `ssl-generate` remain in documentation and
configuration files.

## Goal

All SSL-related documentation and configs reference the correct Make targets
and link to the SSL documentation for full options.

## Current SSL Targets

| Target | Purpose | Use Case |
|--------|---------|----------|
| `ssl-internal` | Generate certs signed by internal CA | Development (default) |
| `ssl-letsencrypt` | Setup Let's Encrypt certificate | Production |
| `ssl-prod-enable` | Enable production SSL config | Production |

**Full documentation:** `.zappzarapp/docs/security/INTERNAL-TLS.md`

## Files to Update

All are **development context** → replace with `ssl-internal`:

| File | Context |
|------|---------|
| `templates/dev-dashboard/health.php:168` | Dev Dashboard hint |
| `.gitlab-ci.yml:34,341` | CI pipeline setup |
| `.zappzarapp/docs/security/INTERNAL-TLS.md:155,158` | Error message example |
| `.zappzarapp/docs/TROUBLESHOOTING.md:510,543` | Troubleshooting steps |
| `.zappzarapp/docs/infrastructure/NODE-SSL.md:42` | Node SSL setup |
| `.zappzarapp/docs/development/MAKEFILE-REFERENCE.md:662,674` | Target reference |
| `docker/nginx/conf.d/ssl-development.conf.template:14` | Config comment |
| `.zappzarapp/ai/LEARNINGS.md:35` | Learning example |
| `.idea/runConfigurations/SSL__SelfSigned.xml:3` | IDE run config |
| `.vscode/tasks.json:944` | VSCode task |

**Skip (historical):**
- `CHANGELOG.md` - Historical entries, don't change
- `release-preparation/.../04-certificate-architecture.md` - Completed task

## Implementation

1. Replace `ssl-selfsigned` → `ssl-internal` in all active files
2. Replace `ssl-generate` → `ssl-internal`
3. Add reference to SSL docs where appropriate (e.g., "See INTERNAL-TLS.md for production setup")
4. Rename IDE configs: `SSL__SelfSigned.xml` → `SSL__Internal.xml`
5. Update VSCode task label

## Verification

```bash
grep -rE "ssl-selfsigned|ssl-generate" \
  --include="*.md" --include="*.yml" --include="*.json" \
  --include="*.xml" --include="*.php" --include="*.conf*" \
  . | grep -v CHANGELOG | grep -v "phase-1-pre-release/completed"
```

Should return no results.
