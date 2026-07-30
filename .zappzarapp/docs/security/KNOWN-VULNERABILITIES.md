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

- Ignored via `auditConfig.ignoreGhsas` in `pnpm-workspace.yaml` (pnpm 11 no
  longer reads the `pnpm` field from `package.json`)
- depcheck runs only in trusted development and CI environments

**Monitoring:**

- Advisory page: <https://github.com/advisories/GHSA-mh99-v99m-4gvg>
- When per-major backports appear: remove `GHSA-mh99-v99m-4gvg` from
  `ignoreGhsas` (now in `pnpm-workspace.yaml`) and add per-major override floors
  instead

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
| Node.js runtime + bundled npm (`undici`, `tar`) and `pnpm`                                                                          | `trivy-node`                      | Node release; pnpm bumped to 11.18.0 (re-scan pending)      |

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
