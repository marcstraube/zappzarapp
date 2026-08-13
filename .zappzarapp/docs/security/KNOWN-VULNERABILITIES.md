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

### brace-expansion - CVE-2026-14257 (DoS)

| Field    | Value                                                               |
| -------- | ------------------------------------------------------------------- |
| Package  | `brace-expansion` (transitive via `depcheck > minimatch`)           |
| Version  | 1.x (resolved through minimatch 3.x)                                |
| CVE      | [CVE-2026-14257](https://github.com/advisories/GHSA-mh99-v99m-4gvg) |
| Type     | Denial of Service (unbounded expansion length, out-of-memory crash) |
| Severity | **HIGH** (CVSS 7.5)                                                 |
| Affected | All versions ≤5.0.7 (every major line)                              |
| Patched  | 1.1.17 (1.x) / 2.1.3 (2.x) / 3.0.3 (3.x) / 5.0.8 (5.x)              |

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

#### Decision: RESOLVED

Per-major backports were released (2026-08-10). The temporary audit exception
has been replaced with per-major override floors in `pnpm-workspace.yaml`:

```yaml
'brace-expansion@<1.1.17': '>=1.1.17 <2.0.0'
'brace-expansion@>=2.0.0 <2.1.3': '>=2.1.3 <3.0.0'
'brace-expansion@>=3.0.0 <3.0.3': '>=3.0.3 <4.0.0'
'brace-expansion@>=5.0.0 <5.0.8': '>=5.0.8'
```

Note: no 4.x backport was released; the advisory skips from 3.0.3 to 5.0.8.

**Mitigation:**

- Override floors enforce the patched minimum within each major line
- `GHSA-mh99-v99m-4gvg` removed from `auditConfig.ignoreGhsas`
- depcheck runs only in trusted development and CI environments

| Review Date | Reviewer     | Status                                    |
| ----------- | ------------ | ----------------------------------------- |
| 2026-07-25  | Claude Agent | Accepted (temporary) - awaiting backports |
| 2026-08-10  | Claude Agent | Resolved - backports landed, floors added |

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

| Source                                                                                                                                                                   | Category                          | Fixable by                                             |
| ------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | --------------------------------- | ------------------------------------------------------ |
| Bundled Java JARs in the Elasticsearch distribution (netty, jackson-databind, jakarta.mail, commons-lang3, reactor-netty, lz4-java, httpcore5, jsoup, opentelemetry-api) | `trivy-elasticsearch`             | Elastic release (image tracks the current major)       |
| Go stdlib / modules compiled into upstream `gosu` / Caddy binaries                                                                                                       | `trivy-postgres`, `trivy-mercure` | Upstream image rebuild                                 |
| Node.js runtime + bundled npm (`undici`, `tar`, `ip-address`) and `pnpm`                                                                                                 | `trivy-node`                      | Node release; pnpm bumped to 11.18.0 (re-scan pending) |

### Decision: ACCEPTED (filtered by package at scan time)

These findings are filtered out of the Trivy scans by a **package-level ignore
policy** — [`.trivy/ignore-policy.rego`](../../../.trivy/ignore-policy.rego),
wired via `ignore-policy:` (GitHub) / `--ignore-policy` (GitLab). The policy
matches the upstream-only **packages** (Go `stdlib` and modules, the
`io.netty:*` family, `jackson-databind`, `undici`, `tar`, ...), so the alerts
are never created in the first place.

Why filter by **package**, not CVE ID or per-alert dismissal: Trivy's
vulnerability database updates continuously, so the residual CVE-ID set drifts
(the same image scanned hours apart yields different IDs), and every image
rebuild re-fingerprints the SARIF results into fresh code-scanning alerts. A
`.trivyignore` CVE-ID allowlist or per-alert dismissal therefore needs constant
maintenance. The vulnerable **packages** are stable, so a package filter holds
without a treadmill.

Trade-off (accepted): the filter is package-granular — it also suppresses any
_future_ CVE in those packages. That is acceptable here because we cannot patch
these packages regardless of the CVE; the review trigger below re-checks the
whole set. `pnpm` is intentionally **excluded** from the policy so its CVEs stay
visible: the pnpm 11 migration has now bumped the bundled pnpm to 11.18.0, so
those residual CVEs are expected to clear on the next image rebuild + Trivy
re-scan — keeping them un-filtered lets that reduction show. A VEX document
(`--vex`) is the standards-track upgrade path if per-CVE exploitability
statements are wanted later.

**Monitoring / review trigger:**

- Re-evaluate the package list when the relevant upstream ships a release
  (Elasticsearch, the base images, Node.js) or quarterly.
- A container CVE in a package **not** on the list appears as a normal Open
  alert → triage it (fix if it is ours, add to the policy if upstream-only).

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
