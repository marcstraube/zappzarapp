# 0010: Simplified Gitflow Branching Model

**Date:** 2026-08-14

**Status:** Accepted

**Context:** The repository needs a branching model that serves two audiences at
once. Template users start from the default branch (GitHub's "Use this template"
copies the default branch, not a release tag), so that branch must always hold
release-quality state. Maintainers need milestone-based releases and the ability
to ship a patch release even while unreleased features are already merged for
the next milestone. A single-trunk model (GitHub flow: `master` + feature
branches, squash merges) fails both needs: the default branch carries
half-finished milestone state, and a patch release would drag along every
feature merged since the last release. Full Gitflow adds `release/*`
stabilization branches, which duplicate what a release-please release PR already
provides.

**Decision:** Simplified Gitflow with two long-lived branches and no `release/*`
branches:

- `develop` — integration branch; every feature/fix/chore/docs PR targets it.
  Per-PR choice between squash and merge commit (squash noisy branches, keep a
  real merge when the individual commits carry value).
- `master` — default branch; releases and hotfixes only. Release tags live here,
  template users and badges read it.
- Milestone release: merge `develop` → `master` as a merge commit — never
  squash, so release-please can parse the individual Conventional Commits on
  `master` into the release changelog.
- Hotfix: branch from `master`, PR to `master` (becomes a patch release), then
  merge `master` back into `develop`.
- Branch protection with required checks on both long-lived branches;
  `gh pr merge --auto` is only used on protected branches (without required
  checks it merges immediately instead of waiting for CI).
- Renovate targets `develop` (`baseBranches`), so dependency updates flow
  through integration and reach `master` with the next release.

**Consequences:**

- (+) The default branch is release-quality by construction — template users
  never receive mid-milestone state
- (+) Patch releases are independent of milestone progress
- (+) Commit granularity is preserved where it matters (release changelog),
  disposable where it does not (squashed feature branches)
- (-) Two long-lived branches to protect and keep in sync (hotfix back-merges)
- (-) Milestone features reach users only at release time, not continuously

## Amendment (2026-08-16): release branch may be `master` or `main`

The model names the release/default branch `master`, but the name is not
hard-wired. CI workflows list both `master` and `main` in their triggers, gate
release-branch-only steps on `github.event.repository.default_branch` (not a
literal ref), and the hooks match `develop master main`. A project can therefore
use `main` as its release branch with no config changes — a fresh setup should
prefer `main` (the modern default for new repositories). `develop` keeps its
name.

A different _model_ also works but is not first-class: single-trunk (GitHub
flow, release branch only, no `develop`) degrades gracefully — the `develop`
triggers simply never fire. The branch-model-specific files (`.github/`
workflows, `docker/hooks/`) are boilerplate-synced, so running a non-default
model means owning those files yourself (`make boilerplate-sync` re-imposes them
otherwise).
