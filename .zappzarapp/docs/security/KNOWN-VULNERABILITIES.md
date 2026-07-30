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

## Container Image Vulnerabilities (Upstream-Only)

**Policy: "secure by default" extends as far as we can influence.** Every
container image is scanned by Trivy
(`--severity HIGH,CRITICAL --ignore-unfixed`, so only _fixable_ CVEs are
reported). Everything we can fix ourselves, we do:

- **Image tags** are bumped to the newest patch/minor within the pinned major
  line.
- **OS packages** are upgraded at build time — `apk upgrade` on the self-built
  Alpine images (php/node/nginx/redis/postgres) and the Alpine-based mercure
  image, `apt-get upgrade` on the Debian/Ubuntu images (mariadb, elasticsearch).

After those in-scope fixes, the Trivy findings that remain are **not fixable by
us** — the vulnerable component is a compiled binary, a bundled library, or a
language runtime baked into an upstream image, and only an upstream release
changes it:

| Source                                                                                                                              | Category                          | Fixable by                                                  |
| ----------------------------------------------------------------------------------------------------------------------------------- | --------------------------------- | ----------------------------------------------------------- |
| Bundled Java JARs in the Elasticsearch distribution (netty, jackson-databind, jakarta.mail, commons-lang3, reactor-netty, lz4-java) | `trivy-elasticsearch`             | Elastic release (already on newest maintained 8.x, 8.19.19) |
| Go stdlib / modules compiled into upstream `gosu` / Caddy binaries                                                                  | `trivy-postgres`, `trivy-mercure` | Upstream image rebuild                                      |
| Node.js runtime + bundled npm (`undici`, `tar`) and `pnpm`                                                                          | `trivy-node`                      | Node release / pnpm 11 migration (tracked separately)       |

### Decision: ACCEPTED (dismissed as "won't fix")

These findings are dismissed in GitHub code scanning with the reason **"won't
fix"** and a per-category justification. Dismissal keeps them **fully
auditable** (visible under the _Closed / Dismissed_ filter with their rationale)
while the default _Open_ view reflects only what we can act on. Crucially, a
**genuinely new** upstream CVE still appears as **Open** — dismissal is
per-alert, not a blanket rule-level mute — so real new signal is never hidden.

A static `.trivyignore` CVE-ID allowlist was deliberately **not** used: Trivy's
vulnerability database updates continuously, so the residual CVE-ID set drifts
(the same image scanned hours apart yields different IDs). Per-alert dismissal
is stable across that drift; an ID list would be a constant-maintenance
treadmill that also risks over-suppressing IDs that later become fixable by us.

**Monitoring / review trigger:**

- Re-evaluate when the relevant upstream ships a release (Elasticsearch, the
  base images, Node.js, pnpm 11).
- New **Open** container alerts = a CVE not covered by an existing dismissal →
  triage it (fix if it is ours, dismiss with rationale if upstream-only).

| Review Date | Reviewer     | Status                                            |
| ----------- | ------------ | ------------------------------------------------- |
| 2026-07-30  | Claude Agent | Accepted - upstream-only after all in-scope fixes |

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
