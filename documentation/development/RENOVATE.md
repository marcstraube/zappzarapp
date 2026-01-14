# Dependency Management with Renovate

This project uses **Renovate** to automatically manage dependency updates across
PHP (Composer), Node.js (pnpm), and Docker ecosystems.

## Overview

Renovate is configured in `renovate.json` and provides:

- **Grouping:** Multiple small updates are grouped into a single PR to reduce
  noise
- **Automerge:** Patch-level updates are automatically merged after CI passes
- **Exclusions:** Critical components (major frameworks) are excluded for
  individual review

For detailed options, see the
[official Renovate documentation](https://docs.renovatebot.com/).

---

## Running the Scanner

The `make renovate` command runs the Renovate scanner via Docker and
automatically detects the mode based on environment variables in your `.env`
file.

### Platform Mode (CI/CD Automation)

This mode connects to your Git host (GitHub, GitLab) and creates Pull Requests
for updates. Intended for automated CI/CD pipelines.

**Configuration in `.env`:**

| Variable             | Example                 | Description                                  |
| -------------------- | ----------------------- | -------------------------------------------- |
| `RENOVATE_PLATFORM`  | `github`                | Git platform (`github`, `gitlab`, etc.)      |
| `RENOVATE_REPO_SLUG` | `your-org/your-project` | Full repository name                         |
| `GITHUB_COM_TOKEN`   | `ghp_...`               | Personal Access Token with repo write access |

**Execution:**

```bash
make renovate
```

When these variables are set, Renovate authenticates and creates PRs
automatically.

---

### Local Filesystem Mode (Manual Updates)

This is the **default mode** when `RENOVATE_PLATFORM` and `RENOVATE_REPO_SLUG`
are empty in `.env`.

- Scanner runs against the local code
- Modifies files directly (`composer.json`, `package.json`, etc.)
- No Pull Requests are created

**Workflow:**

```bash
# 1. Create a new branch
git checkout -b renovate-updates

# 2. Run Renovate locally
make renovate

# 3. Review changes
git diff

# 4. Commit and push manually
git add -A && git commit -m "chore(deps): update dependencies"
```

---

## Configuration

### renovate.json

The main configuration file at the project root:

```json
{
  "$schema": "https://docs.renovatebot.com/renovate-schema.json",
  "extends": ["config:base"],
  "packageRules": [
    {
      "matchUpdateTypes": ["patch"],
      "automerge": true
    },
    {
      "matchPackagePatterns": ["*"],
      "groupName": "all dependencies",
      "groupSlug": "all"
    }
  ]
}
```

### Common Customizations

**Disable automerge:**

```json
{
  "automerge": false
}
```

**Exclude specific packages:**

```json
{
  "packageRules": [
    {
      "matchPackageNames": ["php", "node"],
      "enabled": false
    }
  ]
}
```

**Schedule updates:**

```json
{
  "schedule": ["after 10pm every weekday", "before 5am every weekday"]
}
```

---

## Supported Ecosystems

Renovate automatically detects and updates:

| Ecosystem          | Files                            | Description           |
| ------------------ | -------------------------------- | --------------------- |
| **Composer**       | `composer.json`, `composer.lock` | PHP dependencies      |
| **npm/pnpm**       | `package.json`, `pnpm-lock.yaml` | Node.js dependencies  |
| **Docker**         | `Dockerfile`, `compose.yaml`     | Docker image versions |
| **GitHub Actions** | `.github/workflows/*.yml`        | Action versions       |

---

## Best Practices

1. **Always review major updates** - Don't automerge major version bumps
2. **Run tests before merging** - Ensure CI passes on all update PRs
3. **Group related updates** - Keep dependencies from the same ecosystem
   together
4. **Schedule during off-hours** - Avoid disruption during active development
5. **Pin base images** - Use specific versions for Docker images

---

## Troubleshooting

### Renovate Not Detecting Updates

**Check configuration:**

```bash
# Validate renovate.json
npx renovate-config-validator
```

**Check logs:**

```bash
make renovate 2>&1 | tee renovate.log
```

### Authentication Errors

**GitHub:**

- Ensure `GITHUB_COM_TOKEN` has `repo` scope
- Token must not be expired

**GitLab:**

- Use `GITLAB_TOKEN` instead
- Token needs `api` and `write_repository` scopes

### Local Mode Not Working

**Check Docker:**

```bash
docker run --rm renovate/renovate --version
```

**Check file permissions:**

```bash
ls -la composer.json package.json
```

---

## References

- [Renovate Documentation](https://docs.renovatebot.com/)
- [Renovate Configuration Options](https://docs.renovatebot.com/configuration-options/)
- [GitHub: Renovate](https://github.com/renovatebot/renovate)
