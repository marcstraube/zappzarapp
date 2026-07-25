# Known Vulnerabilities

> Documented security vulnerabilities with accepted risk status.

---

## Overview

This document tracks known vulnerabilities in project dependencies that have
been reviewed and accepted as low risk. Each entry includes:

- Vulnerability details and severity
- Risk assessment and justification
- Mitigation measures
- Review schedule

---

## Accepted Vulnerabilities

### pm2 - CVE-2025-5891 (ReDoS)

| Field    | Value                                                              |
| -------- | ------------------------------------------------------------------ |
| Package  | `pm2`                                                              |
| Version  | 6.0.14                                                             |
| CVE      | [CVE-2025-5891](https://github.com/advisories/GHSA-x5gf-qvw8-r2rm) |
| Type     | Regular Expression Denial of Service (ReDoS)                       |
| Severity | **LOW** (CVSS 2.1)                                                 |
| Affected | All versions ≤6.0.14                                               |
| Patched  | None available                                                     |

**Vulnerability Details:**

Inefficient regular expression in `/lib/tools/Config.js` can cause excessive CPU
consumption through specially crafted input strings.

**Risk Assessment:**

| Factor         | Assessment                               |
| -------------- | ---------------------------------------- |
| Scope          | Development only (`devDependency`)       |
| Impact         | Availability (DoS), no data breach       |
| Exploitability | Requires local access to dev environment |
| CVSS Score     | 2.1 (Low)                                |

#### Decision: ACCEPTED

Justification:

1. **Development-only**: pm2 is not deployed to production
2. **No fix available**: Upstream has not released a patched version
3. **Essential tool**: Required for development process management
4. **Low impact**: DoS only, no confidentiality/integrity impact

**Mitigation:**

- Limit pm2 usage to trusted development environments
- Do not expose pm2 management interfaces to untrusted networks
- Monitor for upstream fix release

**Monitoring:**

- GitHub Releases: <https://github.com/Unitech/pm2/releases>
- Update when version >6.0.14 is released

| Review Date | Reviewer     | Status                      |
| ----------- | ------------ | --------------------------- |
| 2026-01-22  | Claude Agent | Accepted - no fix available |

### brace-expansion - CVE-2026-14257 (DoS)

| Field    | Value                                                               |
| -------- | ------------------------------------------------------------------- |
| Package  | `brace-expansion` (transitive via `depcheck > minimatch`)           |
| Version  | 1.x (resolved through minimatch 3.x)                                |
| CVE      | [CVE-2026-14257](https://github.com/advisories/GHSA-mh99-v99m-4gvg) |
| Type     | Denial of Service (unbounded expansion length, out-of-memory crash) |
| Severity | **HIGH** (CVSS 7.5)                                                 |
| Affected | All versions ≤5.0.7 (every major line)                              |
| Patched  | 5.0.8 only - no per-major backports available yet                   |

**Vulnerability Details:**

A crafted brace pattern can expand to an unbounded number of results, causing
excessive memory allocation and an out-of-memory process crash.

**Risk Assessment:**

| Factor         | Assessment                                                |
| -------------- | --------------------------------------------------------- |
| Scope          | Development only (transitive of `devDependency` depcheck) |
| Impact         | Availability (DoS), no data breach                        |
| Exploitability | Requires attacker-controlled glob patterns; depcheck only |
|                | processes local repository configuration                  |
| CVSS Score     | 7.5 (High) - context-adjusted to low                      |

#### Decision: ACCEPTED (temporary)

Justification:

1. **Development-only**: brace-expansion is reached only through depcheck, a
   `devDependency` that never ships to production
2. **No safe fix**: the only patched release is 5.0.8; forcing 1.x consumers
   onto 5.x via a pnpm override would cross semver majors - such an override is
   a compatibility rewrite, not a security floor (one floor per major line is
   the project rule)
3. **No untrusted input**: depcheck expands globs from local project
   configuration, not from external sources
4. **Backports expected**: the previous brace-expansion advisory
   (GHSA-v6h2-p8h4-qcjw) received per-major backports (1.1.12, 2.0.2, 3.0.1,
   4.0.1) after initial publication

**Mitigation:**

- Ignored via `pnpm.auditConfig.ignoreGhsas` in the root `package.json`
- depcheck runs only in trusted development and CI environments

**Monitoring:**

- Advisory page: <https://github.com/advisories/GHSA-mh99-v99m-4gvg>
- When per-major backports appear: remove `GHSA-mh99-v99m-4gvg` from
  `ignoreGhsas` and add per-major override floors instead

| Review Date | Reviewer     | Status                                    |
| ----------- | ------------ | ----------------------------------------- |
| 2026-07-25  | Claude Agent | Accepted (temporary) - awaiting backports |

---

## Review Process

1. **Quarterly Review**: Check all accepted vulnerabilities for available
   patches
2. **On Alert**: Re-evaluate when new CVEs affect accepted packages
3. **Update Immediately**: When patches become available

---

## References

- [OWASP Dependency-Check](https://owasp.org/www-project-dependency-check/)
- [GitHub Security Advisories](https://github.com/advisories)
- [NVD - National Vulnerability Database](https://nvd.nist.gov/)
