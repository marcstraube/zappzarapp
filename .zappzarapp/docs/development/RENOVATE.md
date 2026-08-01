# Dependency Management with Renovate

This project ships a **Renovate** configuration (`renovate.json`) that manages
dependency updates across PHP (Composer), Node.js (pnpm), Docker, and GitHub
Actions.

> **Status:** the config is present but the Renovate bot is **not yet active**
> on this repository (no update PRs are created automatically). Activation is
> planned for the v1.0 release. Until then, use `make renovate` to preview what
> Renovate _would_ do (see [Local dry-run](#local-dry-run)).

## Overview

The shipped `renovate.json` provides:

- **`config:recommended`** as the base preset (successor to the deprecated
  `config:base`); enables the Dependency Dashboard and sensible defaults.
- **Grouping:** updates are grouped per ecosystem (Docker / Composer / Node) to
  reduce PR noise.
- **Scoped automerge:** patch and minor updates automerge **after CI passes**
  (`platformAutomerge`). **Major updates never automerge** — they always require
  manual review (last package rule wins).
- **`minimumReleaseAge: "3 days"`:** a freshly published version is not proposed
  until it has aged 3 days — a supply-chain safeguard that matches the pnpm
  `minimumReleaseAge` policy (1 day, strict) with an extra margin before
  automerge.
- **Exclusions:** major application frameworks (`laravel/framework`,
  `symfony/framework-bundle`) are excluded from the grouped Composer PR for
  individual review.

For detailed options, see the
[official Renovate documentation](https://docs.renovatebot.com/).

---

## Local dry-run

`make renovate` runs Renovate against the local checkout using the **`local`
platform** in **full dry-run** mode:

```bash
make renovate                 # LOG_LEVEL=info
make renovate LOG_LEVEL=debug # verbose (per-dependency lookups)
```

What it does — and does not do:

- ✅ Validates `renovate.json` (reports any configuration errors).
- ✅ Extracts dependencies from all managers and logs the updates it _would_
  propose.
- ❌ Does **not** create branches or Pull Requests.
- ❌ Does **not** modify `composer.json`, `package.json`, Dockerfiles, or any
  other file.

Run it before enabling the bot (or after editing `renovate.json`) to confirm the
config is valid and see the pending update set. The Renovate image is pinned via
`RENOVATE_VERSION` in the `Makefile` (bump manually).

### GitHub token (better local fidelity)

`minimumReleaseAge` needs a release timestamp per version. Locally, without a
GitHub token, GitHub-datasource lookups (many Docker base images, tool releases,
changelogs) return **without** timestamps and get rate-limited, so Renovate
holds those updates as _pending_
(`minimumReleaseAgeBehaviour=timestamp-required`, the safe default). Provide a
**read-only** `GITHUB_COM_TOKEN` to fix most of this — `make renovate` picks it
up from the environment or `.env.local`:

```bash
# in .env.local (gitignored — NEVER put tokens in the tracked .env)
GITHUB_COM_TOKEN=ghp_your_readonly_token
```

Even with a token, a few update types (Docker digests, package pinning,
replacements) still lack timestamps upstream and stay pending — that is a
Renovate limitation, not a config problem. Do **not** work around it by setting
`minimumReleaseAgeBehaviour: "timestamp-optional"` in `renovate.json`: that
would let versions with no verifiable age bypass the supply-chain wait entirely.

---

## Configuration

The config lives in `renovate.json` at the project root. Its shape:

- Top-level: `extends: ["config:recommended"]`, `automerge: true`,
  `platformAutomerge: true`, `minimumReleaseAge: "3 days"`, plus `labels` and a
  `schedule`.
- `packageRules` (order matters — later rules override earlier ones):
  1. Docker base images — grouped, automerge.
  2. Composer — grouped, automerge, framework majors excluded.
  3. Node (pnpm) — grouped, automerge; dev dependencies grouped separately.
  4. Patch updates — automerge at any time.
  5. **Major updates — `automerge: false`** (final rule, applies across all
     managers).

### Common customizations

**Turn off automerge entirely:**

```json
{ "automerge": false }
```

**Disable updates for a package:**

```json
{
  "packageRules": [{ "matchPackageNames": ["php"], "enabled": false }]
}
```

**Restrict to off-hours:**

```json
{ "schedule": ["after 10pm every weekday", "before 5am every weekday"] }
```

---

## Supported ecosystems

Renovate detects and updates:

| Ecosystem          | Files                     | Notes                              |
| ------------------ | ------------------------- | ---------------------------------- |
| **Composer**       | `composer.json`           | PHP dependencies                   |
| **npm/pnpm**       | `package.json`            | Node.js dependencies (+ workspace) |
| **Docker**         | `docker/**/Dockerfile`    | Base image tags/digests            |
| **GitHub Actions** | `.github/workflows/*.yml` | Action versions                    |

> Lock files (`composer.lock`, `pnpm-lock.yaml`) are **not** tracked in this
> repo — CI re-resolves them fresh each run, so in-range (`^`/`~`) patch/minor
> updates are already picked up automatically. Renovate's value here is bumping
> the version _floors_ in the manifests plus Docker/Actions pins.

---

## Best practices

1. **Always review major updates** — they never automerge by design.
2. **Ensure CI passes** — automerge waits for green checks via
   `platformAutomerge`.
3. **Keep floors, not exact pins** in manifests so in-range updates flow through
   CI without a PR.
4. **Pin Docker base images** to specific versions so Renovate can track them.

---

## Troubleshooting

**Validate the config** without a full run:

```bash
docker run --rm -v "$(pwd):/usr/src/app" -w /usr/src/app \
  renovate/renovate renovate-config-validator
```

(or simply `make renovate` — it reports config errors at the top of its output).

**Capture a full log:**

```bash
make renovate LOG_LEVEL=debug 2>&1 | tee build/tmp/renovate.log
```

**Image won't run:** confirm Docker works and the pinned tag exists —
`docker run --rm renovate/renovate:$(grep RENOVATE_VERSION Makefile | head -1 | awk '{print $3}') --version`.

---

## References

- [Renovate Documentation](https://docs.renovatebot.com/)
- [Configuration Options](https://docs.renovatebot.com/configuration-options/)
- [renovatebot/renovate](https://github.com/renovatebot/renovate)
