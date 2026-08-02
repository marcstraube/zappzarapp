# Dependency Management with Renovate

This project ships a **Renovate** configuration (`renovate.json`) that manages
dependency updates across PHP (Composer), Node.js (pnpm), Docker, and GitHub
Actions.

Renovate is activated via the self-hosted [workflow](#activation) shipped in
`.github/workflows/renovate.yml`. Use [`make renovate`](#local-dry-run) to
preview what it would do without opening PRs.

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

## Activation

Two ways to run Renovate. **Pick one — running both opens duplicate PRs.**

### Self-hosted workflow (shipped, cross-platform)

`.github/workflows/renovate.yml` runs Renovate in CI (the same
`renovate/renovate` used locally and drivable on GitLab). No third-party app
gets repo access; you keep full control of the version and schedule. Setup:

1. Create a **fine-grained PAT** scoped to this repository with permissions:
   Contents **RW**, Pull requests **RW**, Issues **RW** (Dependency Dashboard),
   Commit statuses **RW** (the `minimumReleaseAge` stability check — without it
   Renovate aborts before opening PRs), Workflows **RW** (Renovate updates
   `.github/workflows/*`).
2. Store it as a repository secret:

   ```bash
   gh secret set RENOVATE_TOKEN --body '<your-fine-grained-pat>'
   ```

3. Trigger the first run manually (Actions → Renovate → _Run workflow_, or
   `gh workflow run renovate.yml`). Manual runs default to **automerge off** so
   you can review the initial PRs; the weekly schedule then lets `renovate.json`
   govern (automerge on). Renovate posts a **Dependency Dashboard** issue
   summarising everything it sees.

The workflow costs CI minutes and needs the PAT — that is the trade for not
granting a hosted SaaS write access.

### Mend Renovate GitHub App (zero-setup, GitHub only)

For a GitHub-only project the fastest path is the hosted
[Mend Renovate App](https://github.com/apps/renovate): install it on the repo
and it reads `renovate.json` — no PAT, no CI minutes, native app auth. It does
**not** cover GitLab. If you use the App, delete `renovate.yml` to avoid
duplicate runs.

### GitLab

The App is GitHub-only; on GitLab run the same `renovate/renovate` image from a
scheduled `.gitlab-ci.yml` job with a project access token — the mirror of the
workflow above.

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
  1. Docker images — grouped **per image across managers**
     (`groupName: {{depName}}`), so an image's Dockerfile `ARG` and its
     `kubernetes/values.yaml` tag always bump in one PR and can't drift apart;
     automerge.
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

| Ecosystem          | Files                     | Notes                                                          |
| ------------------ | ------------------------- | -------------------------------------------------------------- |
| **Composer**       | `composer.json`           | PHP dependencies                                               |
| **npm/pnpm**       | `package.json`            | Node.js dependencies (+ workspace)                             |
| **Docker**         | `docker/**/Dockerfile`    | Base image tags/digests                                        |
| **Kubernetes**     | `kubernetes/values.yaml`  | Helm-values image tags (grouped with the Dockerfile per image) |
| **GitHub Actions** | `.github/workflows/*.yml` | Action versions                                                |

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
