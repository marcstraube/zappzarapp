# Task 14: Automatic Release Workflow via GitHub Action

## Priority

LOW - Future Backlog

## Estimated Effort

2-4 hours

## Context

Currently, releases are triggered manually via `make release` which runs
`standard-version` to bump version, generate changelog from conventional commits,
and create a git tag. This workflow is sufficient for now but could be automated
as the project matures.

## Current State

- `standard-version` configured in `package.json` and `.versionrc.json`
- Manual workflow: `make release` → `git push --follow-tags`
- Conventional commits mapped to changelog sections (feat→Added, fix→Fixed, etc.)
- Works well for current development pace

## Platform Support

As a boilerplate, zappzarapp must support both GitHub and GitLab. Any automatic
release workflow needs implementations for both platforms:

| Feature | GitHub | GitLab |
|---------|--------|--------|
| CI Config | `.github/workflows/*.yaml` | `.gitlab-ci.yml` |
| Release API | GitHub Releases | GitLab Releases |
| CLI Tool | `gh` | `glab` |
| Bot/Token | `GITHUB_TOKEN` | `CI_JOB_TOKEN` or `GITLAB_TOKEN` |

## Options to Evaluate

### Option 1: standard-version CI Job

- Trigger on push to `master`/`main`
- Automatically bump version, generate changelog, push tag
- Simple, mirrors current local workflow
- GitHub: Workflow with `actions/checkout` + `standard-version`
- GitLab: Job in `.gitlab-ci.yml` with `npx standard-version`
- Con: Every merge = release (less control)

### Option 2: Release-Please (Google) / GitLab Equivalent

- Creates/updates a "Release PR/MR" from accumulated commits
- Merge the PR/MR to trigger actual release
- Preview changelog before it goes live
- GitHub: `googleapis/release-please-action`
- GitLab: Custom job or `semantic-release` with GitLab plugin
- Con: More complex, additional PR/MR workflow

### Option 3: Manual with CI Validation

- Keep `make release` manual
- Add CI job to validate changelog was updated on release branches
- Minimal change, maximum control
- Works identically on both platforms
- Con: Still manual effort

### Option 4: Tag-triggered Release

- Developer creates tag locally or via UI
- CI detects tag, generates platform Release with notes
- GitHub: `softprops/action-gh-release`
- GitLab: `release:` keyword in `.gitlab-ci.yml`
- Changelog stays manual or semi-automated
- Con: Changelog and tag can drift

## Decision Criteria

Evaluate when revisiting:

- How often are releases made?
- Is the team comfortable with automated releases?
- Do we need release approval/review?
- Are there downstream consumers that need predictable release cadence?

## Implementation Considerations

### GitHub Actions

```yaml
# .github/workflows/release.yaml
on:
  push:
    branches: [master]
jobs:
  release:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
        with:
          fetch-depth: 0
      - run: npx standard-version
      - run: git push --follow-tags
```

### GitLab CI

```yaml
# .gitlab-ci.yml (addition)
release:
  stage: deploy
  rules:
    - if: $CI_COMMIT_BRANCH == "master"
  script:
    - npx standard-version
    - git push --follow-tags https://oauth2:${GITLAB_TOKEN}@${CI_SERVER_HOST}/${CI_PROJECT_PATH}.git
```

### Shared Makefile Target

Consider a `make release-ci` target that both platforms can call, keeping the
logic in one place and CI configs minimal.

## Notes

- Current `make release` workflow is working fine
- Revisit after v4.0 or when release frequency increases
- Consider project growth and contributor count when deciding
- Both platforms must be implemented and tested before shipping
