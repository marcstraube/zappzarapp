# Dependency Management

This document explains how dependencies are managed in this Docker-based
development environment.

---

## Overview

Dependencies are installed and managed **inside Docker containers** to ensure
consistency across all platforms (Linux, macOS, Windows). Lock files are
synchronized back to the host for version control.

| Tool     | Lock File        | Container Path         |
| -------- | ---------------- | ---------------------- |
| Composer | `composer.lock`  | `/var/www/html/vendor` |
| pnpm     | `pnpm-lock.yaml` | `/app/node_modules`    |

---

## File Synchronization Architecture

This project uses **Docker Compose Watch** for cross-platform file
synchronization:

```text
Host                          Container
├── composer.json    ──Watch──►  /var/www/html/composer.json
├── composer.lock    ──Watch──►  /var/www/html/composer.lock
├── package.json     ──Watch──►  /app/package.json
└── pnpm-lock.yaml   ──Watch──►  /app/pnpm-lock.yaml
```

### Watch Mode Behavior

- **Direction**: Host → Container (one-way sync)
- **Action**: `rebuild` - Container rebuilds when dependency files change
- **Cross-Platform**: Works identically on Linux, macOS, and Windows

### Why Watch Instead of Bind Mounts?

| Aspect         | Watch Mode       | Bind Mounts                        |
| -------------- | ---------------- | ---------------------------------- |
| Windows/macOS  | ✅ Fast          | ⚠️ Slower (filesystem translation) |
| Linux          | ✅ Fast          | ✅ Fast                            |
| Sync Direction | Host → Container | Bidirectional                      |

Watch mode is the
[recommended approach](https://docs.docker.com/compose/how-tos/file-watch/) for
dependency files because changes to `package.json` or `composer.json` typically
require a full reinstall anyway.

---

## Installing Dependencies

### Docker (Recommended)

Always use Docker commands for consistency:

```bash
# PHP (Composer)
make composer-install    # Install dependencies + sync lock file

# Node.js (pnpm)
make pnpm-install        # Install dependencies + sync lock file
```

Both commands automatically sync lock files back to the host after installation.

### Local (IDE Code Completion Only)

For IDE autocompletion (not recommended for running code):

```bash
# PHP (Composer)
make composer-install-local    # Uses --ignore-platform-reqs

# Node.js (pnpm)
make pnpm-install-local        # Uses local pnpm
```

Both commands automatically sync lock files from the container first (if
running), ensuring the local installation uses the same versions as Docker.

⚠️ **Warning**: Local installations may differ from Docker due to different
PHP/Node.js versions.

---

## Updating Dependencies

### Adding New Packages

```bash
# PHP: Add a package
make composer CMD="require vendor/package"

# Node.js: Add a package
make pnpm CMD="add package-name"

# Sync lock files after adding
make sync-lockfiles
```

### Updating All Dependencies

```bash
# PHP: Update all packages
make composer-update    # Updates + syncs composer.lock

# Node.js: Update all packages
make pnpm-update        # Updates + syncs pnpm-lock.yaml
```

---

## Lock File Synchronization

Since Watch mode is one-way (Host → Container), lock files generated in the
container must be synced back to the host for Git commits.

### Automatic Sync

Lock files are automatically synced after:

- `make composer-install`
- `make pnpm-install`
- `make composer-update`
- `make pnpm-update`
- `make setup`

### Manual Sync

If you run commands directly in the container:

```bash
make sync-lockfiles
```

This copies:

- `composer.lock` from PHP container to host
- `pnpm-lock.yaml` from Node container to host

---

## Workflow Examples

### Starting a New Feature

```bash
# 1. Start containers
make up

# 2. Add new dependencies
make composer CMD="require new/package"
make pnpm CMD="add new-package"

# 3. Sync lock files
make sync-lockfiles

# 4. Commit changes
git add composer.json composer.lock package.json pnpm-lock.yaml
git commit -m "feat: add new dependencies"
```

### After Pulling Changes

```bash
# 1. Pull latest code
git pull

# 2. Rebuild containers (Watch mode detects changes)
make restart

# 3. Or manually reinstall if needed
make composer-install
make pnpm-install
```

### Fresh Install (New Developer)

```bash
# 1. Clone repository
git clone <repo>
cd <repo>

# 2. Initialize project
make init       # Creates .env from template

# 3. Full setup (builds + installs dependencies)
make setup      # Includes automatic lock file sync
```

---

## Troubleshooting

### Lock File Out of Sync

**Symptom**: `Warning: The lock file is not up to date`

**Solution**:

```bash
make sync-lockfiles
```

### Container Has Old Dependencies

**Symptom**: Package not found despite being in lock file

**Solution**:

```bash
# Force reinstall
make composer-install    # or make pnpm-install
```

### Permission Issues (node_modules)

**Symptom**: `EACCES: permission denied`

**Solution**:

```bash
make pnpm-install    # Automatically fixes permissions
```

### Dependencies Differ Between Host and Container

**Symptom**: Code works in container but not locally (or vice versa)

**Solution**: Always use container versions. Local install is only for IDE
support:

```bash
# Run tests/code in container, not locally
make test           # Correct
npm test            # Incorrect (uses local node)
```

---

## Related Make Targets

| Command                   | Description                                 |
| ------------------------- | ------------------------------------------- |
| `make composer-install`   | Install PHP deps (Docker) + sync lock file  |
| `make pnpm-install`       | Install Node deps (Docker) + sync lock file |
| `make composer-update`    | Update PHP deps + sync lock file            |
| `make pnpm-update`        | Update Node deps + sync lock file           |
| `make sync-lockfiles`     | Manually sync lock files from containers    |
| `make composer CMD="..."` | Run arbitrary Composer command              |
| `make pnpm CMD="..."`     | Run arbitrary pnpm command                  |
| `make validate`           | Validate composer.json and package.json     |
| `make outdated`           | Check for outdated PHP dependencies         |

---

## See Also

- [WINDOWS.md](../setup/WINDOWS.md) - Windows-specific setup (WSL2)
- [RENOVATE.md](RENOVATE.md) - Automated dependency updates
- [Docker Compose Watch](https://docs.docker.com/compose/how-tos/file-watch/) -
  Official documentation
