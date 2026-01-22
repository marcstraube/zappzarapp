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
