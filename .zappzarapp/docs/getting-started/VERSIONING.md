# Versioning Your Application

How release versioning works in a project derived from zappzarapp: every release
tag in your repository belongs to your application — the boilerplate stays out
of the way.

## Your Tags Are Yours

Boilerplate release tags never mix with your project's tags, on either path of
getting zappzarapp:

- **Template path**: repositories created via GitHub's "Use this template" start
  with a single initial commit — no boilerplate history, no tags.
- **Clone path**: the first `make setup` on a fresh clone deletes every
  inherited boilerplate release tag and lists each removed tag.
- **Upstream syncs**: `make boilerplate-sync` and `make boilerplate-diff`
  configure the `zappzarapp` remote with `tagOpt --no-tags`, so fetching
  infrastructure updates never re-imports boilerplate tags.

Release tooling that derives versions from git tags (release-please, git-cliff,
semantic-release, …) therefore only ever sees your own releases.

## Recommended Scheme

Use [Semantic Versioning](https://semver.org/spec/v2.0.0.html) with `v`-prefixed
tags (`v1.0.0`, `v1.1.0`, `v2.0.0`):

- **MAJOR** — breaking changes to your application's public contract
- **MINOR** — new features, backwards compatible
- **PATCH** — bug fixes, backwards compatible

## Creating a Release

```bash
# Annotated tag (or -s for a signed tag)
git tag -a v1.0.0 -m "Release v1.0.0"
git push origin v1.0.0
```

On GitHub or GitLab, create a release from the pushed tag to publish release
notes and artifacts.

## Changelog

Your `CHANGELOG.md` starts as an empty
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) skeleton after
`make setup`. Collect changes under `[Unreleased]` while you work and move them
into a dated version section when you tag a release.

## Optional: Release Automation

zappzarapp does not prescribe a release workflow — pick what fits your team, or
tag manually. The commit-msg hook already enforces
[Conventional Commits](https://www.conventionalcommits.org/), so tools that
generate changelogs and version bumps from commit history plug in without
workflow changes, for example:

- [release-please](https://github.com/googleapis/release-please) — release PRs
  with generated changelog, tags on merge
- [git-cliff](https://git-cliff.org/) — changelog generation from commit history
