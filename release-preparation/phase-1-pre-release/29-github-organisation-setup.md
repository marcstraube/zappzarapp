# 29: GitHub Organisation Setup for v1.0 Release

## Goal

Prepare the project for public v1.0 release by creating a professional GitHub organisation and ensuring the codebase is ready for open-source collaboration.

## Current State

- Repository: `marcstraube/zappzarapp` (private)
- No LICENSE file
- Potential secrets/credentials in commit history
- README/docs may contain company-specific references
- No CONTRIBUTING.md or community guidelines

## Target State

- Organisation: `zappzarapp` (professional branding)
- Repository: `zappzarapp/zappzarapp` (public)
- MIT License added
- All secrets/credentials removed from history
- Clean branding (no company references)
- Ready for community contributions

## Implementation Plan

### Step 1: Pre-Transfer Audit

**1.1 Secrets & Credentials Scan**

Scan entire git history for sensitive data:
```bash
# Check for common secret patterns
git log -p | grep -iE '(password|secret|key|token|api_key)' > audit-secrets.txt

# Use gitleaks or similar
docker run --rm -v "$(pwd):/path" zricethezav/gitleaks:latest detect --source="/path" --report-path=/path/audit-gitleaks.json
```

Check files that should never be committed:
- `.env` files (should only be in `.gitignore`)
- Certificates/keys in version control
- Database dumps with real data
- API tokens/credentials

**1.2 Company References Audit**

Search for company-specific content:
```bash
# Search for company name references
grep -r "COMPANY_NAME" . --exclude-dir={vendor,node_modules,.git}

# Check documentation
grep -r "firma\|company\|client" docs/ .claude/ .zappzarapp/ README.md
```

Files to review:
- `README.md`
- `.claude/CLAUDE.md`
- `.zappzarapp/docs/`
- Session files (`.claude/sessions/`)
- Docker configs (check for hardcoded domains/IPs)

**1.3 Clean Commit History (if needed)**

If secrets found in history:
- Use `git filter-repo` or `BFG Repo-Cleaner`
- Document cleaned commits in CHANGELOG.md
- Force push to new clean repo

### Step 2: License & Legal

**2.1 Add MIT License**

Create `LICENSE` file:
```
MIT License

Copyright (c) 2025 Marc Straube

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

**2.2 Update Package Manifests**

Add license to:
- `src/php/composer.json` → `"license": "MIT"`
- `src/node/backend/package.json` → `"license": "MIT"`
- `src/node/frontend/package.json` → `"license": "MIT"`
- All future `packages/*/composer.json|package.json`

### Step 3: GitHub Organisation

**3.0 Request Dormant Username (Optional)**

If the desired username `zappzarapp` is taken by a dormant account:

1. **Check account status:**
   - Visit https://github.com/zappzarapp
   - Verify: Only forks, no original repos, no recent activity

2. **Submit request via GitHub Support Form:**
   - URL: https://support.github.com/contact/name-squatting
   - **Alternative:** Settings → Contact GitHub Support → Account and Profile
   - **Do NOT use:** Abuse button (not appropriate for name squatting requests)

3. **Request template:**

```markdown
Subject: Dormant Account Username Request - "zappzarapp"

Hello GitHub Support Team,

I would like to request the username "zappzarapp" under GitHub's Name
Squatting Policy for dormant accounts.

**Current Situation:**
- The account @zappzarapp has only 2 forked repositories
- No original repositories or contributions
- No activity for several years
- Account appears to be inactive/abandoned

**My Use Case:**
I am the maintainer of the zappzarapp framework
(https://github.com/marcstraube/zappzarapp), an open-source PHP
full-stack boilerplate project. I am preparing for the first public
release and plan to:

1. Create a GitHub organization for the project
2. Split the monorepo into multiple repositories (core, infrastructure,
   devtoolbar, devdashboard)
3. Publish Composer packages under this namespace
4. Open the project for community contributions

The username "zappzarapp" would be the natural and expected namespace
for this project and its ecosystem, making it easier for contributors
and users to find and identify official packages.

I understand if this is not possible, but I wanted to formally request
this under your dormant account policy.

Thank you for considering this request.

Best regards,
Marc Straube
```

4. **Wait for response** (typically 1-7 days)

5. **Fallback if denied:** Use `zappzarapp-framework` as organisation name

**3.1 Create Organisation**

1. GitHub → Settings → Organizations → New organization
2. Name: `zappzarapp` (or `zappzarapp-framework` if username request denied)
3. Contact email: (your public email)
4. Plan: Free (upgrade later if needed)

**3.2 Organisation Settings**

- Profile:
  - Display name: Zappzarapp
  - Description: "Developer Platform for modern, secure web applications"
  - Website: (later: zappzarapp.dev or similar)
  - Twitter/Social: (optional)

- Member privileges:
  - Base permissions: None (contributors need explicit access)
  - Two-factor authentication: Not required (yet)

### Step 4: Repository Transfer & Setup

**4.1 Transfer Repository**

1. `marcstraube/zappzarapp` → Settings → Transfer ownership
2. New owner: `zappzarapp`
3. Confirm transfer

**4.2 Create Private Backup Repository**

Purpose: Private backup for WIP branches during development.

1. GitHub → `marcstraube` account → New repository
2. Name: `zappzarapp`
3. Visibility: **Private**
4. Description: "Private backup for zappzarapp development"
5. Do NOT initialize (empty repo)

**4.3 Configure Git Remotes**

Setup dual-remote workflow:

```bash
# Update origin to official public repo
git remote set-url origin git@github.com:zappzarapp/zappzarapp.git

# Add private backup as secondary remote
git remote add backup git@github.com:marcstraube/zappzarapp.git

# Verify remotes
git remote -v
# origin    git@github.com:zappzarapp/zappzarapp.git (fetch)
# origin    git@github.com:zappzarapp/zappzarapp.git (push)
# backup    git@github.com:marcstraube/zappzarapp.git (fetch)
# backup    git@github.com:marcstraube/zappzarapp.git (push)

# Initial push to backup
git push backup --all
git push backup --tags
```

**Usage workflow:**
```bash
# During development (WIP backup)
git push backup feature/wip-experimental

# When ready for public
git push origin feature/completed-feature

# Sync backup with public
git push backup main
```

**4.4 Set Repository Visibility**

- Keep `zappzarapp/zappzarapp` **private** until v1.0 is ready
- Change to **public** only after:
  - All pre-release tasks completed (phase-1-pre-release/)
  - README updated (Task 24)
  - Secrets audit passed
  - CONTRIBUTING.md added

- Keep `marcstraube/zappzarapp` **private** (always)

### Step 5: Update Tool Configurations

**5.1 Tasks Skill Configuration**

Update `.claude/skills/tasks/config.json` (if exists) or task management configs:

```json
{
  "repositories": {
    "origin": "zappzarapp/zappzarapp",
    "upstream": null,
    "zappzarapp": "zappzarapp/zappzarapp"
  }
}
```

**5.2 GitHub Actions Workflows**

Check `.github/workflows/*.yml` for hardcoded repo references:
- Update any `uses: marcstraube/zappzarapp@*` → `zappzarapp/zappzarapp@*`
- Update badge URLs in README
- Update clone URLs in documentation

**5.3 Documentation References**

Update repository URLs in:
- `README.md` - Clone instructions
- `.zappzarapp/docs/` - Any repo references
- `.claude/CLAUDE.md` - If repo mentioned
- `CONTRIBUTING.md` - Fork/clone instructions
- Issue/PR templates - Reference links

**5.4 Package Manifests**

Update repository field in:
```json
// src/php/composer.json
{
  "homepage": "https://github.com/zappzarapp/zappzarapp",
  "support": {
    "issues": "https://github.com/zappzarapp/zappzarapp/issues",
    "source": "https://github.com/zappzarapp/zappzarapp"
  }
}

// src/node/*/package.json
{
  "repository": {
    "type": "git",
    "url": "https://github.com/zappzarapp/zappzarapp.git"
  },
  "bugs": {
    "url": "https://github.com/zappzarapp/zappzarapp/issues"
  }
}
```

### Step 6: Community Preparation

**5.1 Create CONTRIBUTING.md**

Basic structure:
```markdown
# Contributing to Zappzarapp

**Status:** This project is in active development (pre-v1.0). We're not yet accepting external contributions until the first stable release.

## Planned for v1.0+

- Contribution guidelines
- Code of conduct
- Development setup guide
- PR process

## Questions?

Open an issue with the "question" label.
```

**5.2 Issue Templates**

Create `.github/ISSUE_TEMPLATE/`:
- `bug_report.md` - Bug reports
- `feature_request.md` - Feature requests
- `question.md` - General questions

**5.3 Pull Request Template**

Create `.github/PULL_REQUEST_TEMPLATE.md`:
```markdown
## Description
<!-- Brief description of changes -->

## Type of Change
- [ ] Bug fix
- [ ] New feature
- [ ] Breaking change
- [ ] Documentation update

## Checklist
- [ ] Tests added/updated
- [ ] Documentation updated
- [ ] Quality checks pass (`make check`)
- [ ] Follows code standards
```

**5.4 Code of Conduct**

Add `CODE_OF_CONDUCT.md` (use Contributor Covenant template).

### Step 7: Branding & Marketing

**6.1 Repository Settings**

- Description: "Developer Platform for modern, secure web applications (PHP + Node.js)"
- Topics: `php`, `nodejs`, `docker`, `kubernetes`, `developer-platform`, `boilerplate`, `security`, `gdpr`
- Social preview image: (create later)

**6.2 README Badge Section**

Add badges for professional look:
```markdown
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/PHP-8.4-blue.svg)](https://php.net)
[![Node Version](https://img.shields.io/badge/Node-24-green.svg)](https://nodejs.org)
```

### Step 8: Final Checks Before Public

**Checklist before making repository public:**

- [ ] No secrets in commit history (gitleaks scan clean)
- [ ] No company-specific references in docs
- [ ] LICENSE file added
- [ ] Git remotes configured (origin + backup)
- [ ] Backup repository created and synced
- [ ] Repository URLs updated in all configs:
  - [ ] Tasks skill configuration
  - [ ] GitHub Actions workflows
  - [ ] Package manifests (composer.json, package.json)
  - [ ] Documentation (README, CONTRIBUTING, docs/)
- [ ] README.md updated (Task 24 completed)
- [ ] CONTRIBUTING.md added
- [ ] CODE_OF_CONDUCT.md added
- [ ] Issue/PR templates configured
- [ ] `.gitignore` comprehensive
- [ ] All phase-1-pre-release tasks completed
- [ ] Fresh `make check` passes
- [ ] Fresh `make test` passes
- [ ] Docker images build cleanly
- [ ] CHANGELOG.md up to date

## Files to Create/Modify

**Create:**
- `LICENSE` - MIT License
- `CONTRIBUTING.md` - Contribution guidelines (v1.0+ only)
- `CODE_OF_CONDUCT.md` - Code of conduct
- `.github/ISSUE_TEMPLATE/bug_report.md`
- `.github/ISSUE_TEMPLATE/feature_request.md`
- `.github/ISSUE_TEMPLATE/question.md`
- `.github/PULL_REQUEST_TEMPLATE.md`

**Modify:**
- `README.md` - Add badges, update repository URLs
- `src/php/composer.json` - Add license, update repository URLs
- `src/node/*/package.json` - Add license, update repository URLs
- `.gitignore` - Ensure comprehensive
- `.claude/skills/tasks/config.json` - Update repository references (if exists)
- `.github/workflows/*.yml` - Update any hardcoded repo references
- `.zappzarapp/docs/` - Update clone/fork instructions

**Review & Clean:**
- All `.md` files in docs/
- `.claude/` - Remove session files with company refs
- `.env.example` - Ensure no real secrets

## Priority

**Critical** - Must be completed before v1.0 public release. However, organisation setup can happen anytime; repository should stay private until all phase-1-pre-release tasks are completed.

## Timeline

- **Audit & Cleanup:** 1-2 days (thorough secret scan + company ref removal)
- **Organisation Setup:** 1 hour
- **License & Legal:** 1 hour
- **Community Files:** 2-3 hours
- **Final Review:** 1 day

**Total:** ~1 week (can run parallel with other pre-release tasks)

## Dependencies

- Must complete Task 24 (README repositioning) before going public
- Should complete all phase-1-pre-release tasks before public release
- Secrets audit should be done early (blocks public release)

## Success Criteria

- [ ] GitHub organisation `zappzarapp` created
- [ ] Repository transferred to `zappzarapp/zappzarapp`
- [ ] Private backup repository `marcstraube/zappzarapp` created
- [ ] Git remotes configured (origin + backup)
- [ ] MIT License added
- [ ] No secrets in git history
- [ ] No company references in documentation
- [ ] Tool configurations updated (tasks skill, workflows, docs)
- [ ] Package manifests updated with new repository URLs
- [ ] Community files in place (CONTRIBUTING, CoC, templates)
- [ ] Repository ready to go public (but stays private until v1.0)

## Notes

- **Organisation name:**
  - **Preferred:** `zappzarapp` (requires dormant username request via GitHub Support)
  - **Fallback:** `zappzarapp-framework` (if username request denied)
  - Chosen for:
    - Professional branding
    - Shorter than `zappzarapp/platform`
    - Allows future sub-repos (zappzarapp/php-foundation, etc.)

- **Dual-remote workflow:**
  - `origin` (zappzarapp/zappzarapp): Official public repo, primary development
  - `backup` (marcstraube/zappzarapp): Private backup for WIP branches
  - No fork relationship needed - independent repos
  - Main development happens against origin
  - Backup is optional safety net for experimental work

- **Keep repositories private/public:**
  - `zappzarapp/zappzarapp`: Private until v1.0 ready, then public
  - `marcstraube/zappzarapp`: Always private (personal backup)

- **Before going public:**
  - All phase-1-pre-release tasks done
  - v1.0 quality bar met
  - User confirms readiness
  - No company references remain in final public version

- **Tool updates required:**
  - Tasks skill configuration (repository URLs)
  - GitHub Actions workflows (any hardcoded refs)
  - Documentation (clone/fork instructions)
  - Package manifests (repository fields)

## References

- MIT License: https://opensource.org/licenses/MIT
- Contributor Covenant: https://www.contributor-covenant.org/
- GitHub Issue Templates: https://docs.github.com/en/communities/using-templates-to-encourage-useful-issues-and-pull-requests/configuring-issue-templates-for-your-repository
- BFG Repo-Cleaner: https://rtyley.github.io/bfg-repo-cleaner/
- Gitleaks: https://github.com/gitleaks/gitleaks
