# GitLab Mirror Setup

**Status:** Planned **Size:** Small **Scope:** chore **Created:** 2026-01-22
**Planning:** Not required

## Context

The boilerplate provides CI configurations for both GitHub Actions and GitLab
CI. To properly test and demonstrate both platforms, we need the project
available on both GitHub and GitLab.

## Goal

Set up GitLab as a mirror of the GitHub repository so that:

- GitHub remains the primary source (Issues, PRs, Contributions)
- GitLab automatically mirrors from GitHub (read-only)
- Both CI pipelines run on every push
- Users can see that the boilerplate works on both platforms

## Implementation

1. Create GitLab project (same name: `zappzarapp`)
2. Configure repository mirroring:
   - GitLab Settings → Repository → Mirroring repositories
   - Add GitHub as pull source
   - Set up authentication (Deploy Key or Token)
3. Verify automatic sync works
4. Test GitLab CI pipeline runs on mirrored commits
5. Update documentation to mention both platforms

## Files

- `.gitlab-ci.yml` (already exists)
- `README.md` (add GitLab badge/link)
- `.zappzarapp/docs/` (document dual-platform setup)

## Notes

- GitLab offers free repository mirroring
- Mirror can be public (demonstrates platform compatibility)
- PRs/Issues remain GitHub-only (single source of truth)
- Alternative: GitHub → GitLab push mirror (requires GitLab token in GitHub
  secrets)
