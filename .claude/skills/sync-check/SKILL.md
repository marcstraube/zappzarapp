---
name: sync-check
description: Check synchronization between related configuration files
model: haiku
context: fork
allowed-tools:
  - Read
  - Grep
  - Glob
  - Bash(make:*)
  - Bash(git:*)
  - Bash(diff:*)
  - Bash(ls:*)
  - AskUserQuestion
argument-hint: '[--fix] [--category <docker|env|k8s|versions|all>]'
---

# Sync Check

Verify that related configuration files stay in sync.

## Purpose

This project ships several **dev/prod configuration pairs and overlays** —
compose files, nginx SSL templates, entrypoints, env files, Helm values. When
you change one side (add a service, a path, an env var), its counterpart usually
has to change too, or an environment silently drifts.

It helps both when developing zappzarapp and when building a project **on top
of** it: after customizing the config, run it to catch a service you renamed in
`compose.yaml` that a CI/prod overlay still references, or a variable you added
to `.env` but not `.env.production`.

## Arguments

Parse `$ARGUMENTS`:

- `--fix`: Offer to fix simple, safe gaps (with confirmation)
- `--category <name>`: Only check one category (`docker`, `env`, `k8s`,
  `versions`, `all`)

```bash
/sync-check                    # Check everything
/sync-check --category docker  # Only Docker configs
/sync-check --fix              # Check and offer fixes
```

## Category: docker

### Compose overlays (subset check)

`compose.override.yaml` (dev), `compose.production.yaml` (prod) and
`compose.ci.yaml` (CI) are all **overlays** merged on top of `compose.yaml`
(`docker compose -f compose.yaml -f <overlay> …`). They are subsets by design —
each redeclares only the services it needs to tune.

- **Check:** every service an overlay declares **must exist in `compose.yaml`**.
  An overlay entry for a renamed or removed base service silently does nothing —
  this is the drift to catch.
- **Do NOT flag:** base services an overlay omits. They still run in that
  environment with base config unchanged (`compose.ci.yaml` is the most
  minimal).
- Where an overlay explicitly copies base config (e.g. `compose.ci.yaml` marks
  `# === FROM compose.yaml (base config, must be included) ===` mounts), that
  copy should still match the base — flag it if the base path changed.

### Standalone dev/prod pairs

| Pair                                                                                                     | Expectation                                                          |
| -------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------- |
| `docker/nginx/conf.d/ssl-development.conf.template` ↔ `docker/nginx/conf.d/ssl-production.conf.template` | Same location blocks / proxy targets; SSL hardening may differ       |
| `docker/php/entrypoint.development.sh` ↔ `docker/php/entrypoint.production.sh`                           | Prod is dev minus dev-only steps (permission fixes, secrets copying) |
| `docker/node/entrypoint.development.sh` ↔ `docker/node/entrypoint.production.sh`                         | Same as above                                                        |

Diff each pair and flag: location blocks / proxy targets present on one side but
missing from the other; entrypoint steps in prod that are absent from dev (prod
should be a subset of dev, not the reverse).

## Category: env

| Pair                       | Expectation                                   |
| -------------------------- | --------------------------------------------- |
| `.env` ↔ `.env.production` | Same variable **keys**; values differ per env |

`.env` and `.env.production` are alternative full env files (not overlays), so
both should define the same set of keys. `.env.local.example` documents optional
local overrides — it is not a parity target.

Flag: keys in `.env` missing from `.env.production` (or vice versa). Values are
expected to differ.

## Category: k8s

`.env` (Compose world) and `kubernetes/values.yaml` (Helm world) both decide
which services run — deliberately maintained twice, since each world needs its
own config depth. Divergence **may be intentional** (e.g. no frontend locally,
frontend in the cluster): the chart is authoritative for k8s (`make k8s-build`
derives everything from the render), so drift cannot build wrong images — this
check only makes it **visible**. Report as `[INFO]`, never auto-fix.

Compare the toggles (production overlay `values.production.yaml` may override
`values.yaml` — check the overlay first, fall back to the default):

| `.env`                  | `values.yaml` counterpart                                                       |
| ----------------------- | ------------------------------------------------------------------------------- |
| `ENABLE_PHP`            | `php.enabled`                                                                   |
| `ENABLE_DATABASE=true`  | exactly the `DB_TYPE` engine enabled (`postgres.enabled` XOR `mariadb.enabled`) |
| `ENABLE_DATABASE=false` | both database engines disabled                                                  |
| `ENABLE_REDIS`          | `redis.enabled`                                                                 |
| `ENABLE_MERCURE`        | `mercure.enabled`                                                               |
| `ENABLE_MEILISEARCH`    | `meilisearch.enabled`                                                           |
| `ENABLE_ELASTICSEARCH`  | `elasticsearch.enabled`                                                         |
| `ENABLE_SEAWEEDFS`      | `seaweedfs.enabled`                                                             |
| `ENABLE_RABBITMQ`       | `rabbitmq.enabled`                                                              |
| `ENABLE_MAILPIT`        | `mailpit.enabled`                                                               |

`NODE_MODE` maps to the two Node deployments (with `ENABLE_NODE=false` or
`idle`, both are false):

| `NODE_MODE`   | `node.enabled` | `nodeBackend.enabled` |
| ------------- | -------------- | --------------------- |
| assets        | false          | false                 |
| api           | false          | true                  |
| assets-api    | false          | true                  |
| framework     | true           | false                 |
| framework-api | true           | true                  |

**Do NOT flag:** dev-only toggles without a chart counterpart
(`ENABLE_DEV_TOOLBAR`, `ENABLE_ADMINER`, `ENABLE_PGADMIN`) and `nginx` (always
on in Compose, `nginx.enabled` in the chart).

## Category: versions

Versions live in **one** Renovate-managed place per tool — the Dockerfile `ARG`s
(plus the dind tag in `.gitlab-ci.yml`). Everything else is a _reference_ that
drifts silently when a major lands. Docs state at most **major.minor** and never
patch versions (a patch version in prose is itself a finding — link the ARG
instead).

Extract the major(.minor) from each source and flag references that disagree:

| Source of truth (ARG)                        | References to check                                                                                                                                                         |
| -------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `docker/php/Dockerfile` `PHP_VERSION`        | `composer.json` `config.platform.php` · `.vscode/settings.json` `intelephense…phpVersion` · `.idea/php.xml` `php_language_level` · `PHP X.Y` in `*.md` + `.claude/context/` |
| `docker/node/Dockerfile` `NODE_VERSION`      | `Node.js NN` / `Node NN` in `*.md` + `.claude/context/`                                                                                                                     |
| `docker/nginx/Dockerfile` `NGINX_VERSION`    | `Nginx X.Y` in `*.md`                                                                                                                                                       |
| `.gitlab-ci.yml` `docker:NN-dind`            | `docker:NN-…` snippets in `*.md`                                                                                                                                            |
| other `docker/*/Dockerfile` `*_VERSION` ARGs | major mentions in `*.md` (redis, postgres, elasticsearch, …)                                                                                                                |

**Skip:** `CHANGELOG.md`, `.zappzarapp/ai/LEARNINGS.md`, `.zappzarapp/docs/adr/`
(history), and PHP-feature provenance notes like "PHP 8.4 asymmetric visibility"
in `standards/php.md` (the feature's origin version is a fact, not a reference).
Report drift as `[WARN]` — version drift is never intentional.

## Output

```text
Sync Check Results
==================

| Category | Checks | OK  | Issues |
| -------- | ------ | --- | ------ |
| docker   | 5      | 4   | 1      |
| env      | 1      | 0   | 1      |
| k8s      | 11     | 10  | 1      |
| versions | 5      | 4   | 1      |

[WARN] docker: compose.production.yaml declares service 'mercury' — not in compose.yaml (renamed to 'mercure'?)
  -> fix the overlay service name to match the base
[WARN] env: 'NEW_FEATURE_FLAG' in .env missing from .env.production
  -> add NEW_FEATURE_FLAG to .env.production
[INFO] k8s: ENABLE_REDIS=true but redis.enabled=false in kubernetes/values.yaml
  -> intentional divergence is fine (the chart leads for k8s); align if not
[WARN] versions: ARCHITECTURE.md says "PHP 8.4" but docker/php/Dockerfile pins 8.5
  -> update the doc reference (docs state major.minor only)
```

Severity: `[CRIT]` breaks the environment · `[WARN]` should be fixed · `[INFO]`
minor.

## Auto-Fix (`--fix`)

Offer to auto-fix only safe, mechanical gaps, each with confirmation:

- Add a missing env key to the other file with a placeholder value
- Bump the three mechanical PHP-version references (`composer.json`
  `config.platform.php`, `.vscode/settings.json` `phpVersion`, `.idea/php.xml`
  `php_language_level`) to the Dockerfile ARG's major.minor

Never auto-fix: differing values, an overlay service name (could be an
intentional rename either way), a `.env` ↔ chart toggle divergence (may be
intentional — the chart leads for k8s), structural differences,
security-relevant config — report those for manual review.

## Extraction Helpers

```bash
# Service names declared in a compose overlay vs the base
grep -E '^  [a-z][-a-z0-9_]*:' compose.production.yaml | tr -d ' :' | sort
grep -E '^  [a-z][-a-z0-9_]*:' compose.yaml            | tr -d ' :' | sort

# Variable keys per env file
grep -E '^[A-Z_]+=' .env            | cut -d= -f1 | sort
grep -E '^[A-Z_]+=' .env.production | cut -d= -f1 | sort

# Service toggles: .env side and chart side (top-level key + its enabled flag)
grep -E '^(ENABLE_[A-Z_]+|NODE_MODE|DB_TYPE)=' .env
awk '/^[a-z]/{svc=$1} /^  enabled:/{print svc, $2}' kubernetes/values.yaml

# Version ARGs (source of truth) and their references
grep -h '^ARG [A-Z_]*VERSION=' docker/*/Dockerfile
grep -oE '"php": "[0-9.]+"' composer.json                 # config.platform
grep -oE 'phpVersion": "[0-9.]+"' .vscode/settings.json
grep -oE 'php_language_level="[0-9.]+"' .idea/php.xml
git grep -nE '(PHP|Node\.js|Nginx) [0-9]+\.?[0-9]*' -- '*.md' .claude/context/
```

Run this after changing any paired/overlaid config, before opening a PR.
