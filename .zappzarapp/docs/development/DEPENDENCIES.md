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

This project uses **bind mounts** for bidirectional file synchronization.
Dependency directories use **named volumes** for cross-platform performance:

```text
Host                          Container
├── composer.json    ◄──Bind──►  /var/www/html/composer.json
├── composer.lock    ◄──Bind──►  /var/www/html/composer.lock
├── package.json     ◄──Bind──►  /app/package.json
├── pnpm-lock.yaml   ◄──Bind──►  /app/pnpm-lock.yaml
│
│   Named Volumes (Container-only)
├── (php_vendor)     ────────►   /var/www/html/vendor
└── (node_modules)   ────────►   /app/node_modules
```

### Architecture Benefits

| Component      | Type         | Benefit                                |
| -------------- | ------------ | -------------------------------------- |
| Source files   | Bind Mount   | Instant sync, HMR works                |
| Lock files     | Bind Mount   | Bidirectional, version control         |
| `node_modules` | Named Volume | Fast installs, no Windows/macOS issues |
| `vendor`       | Named Volume | Fast installs, no permission issues    |

### Why Named Volumes for Dependencies?

Named volumes provide consistent performance across all platforms:

- **Windows/macOS**: Bind-mounted `node_modules` is extremely slow due to
  filesystem translation. Named volumes stay inside Docker and are fast.
- **Linux**: Named volumes avoid permission conflicts between host and container
  user IDs.
- **All platforms**: Package managers (pnpm, Composer) work without EBUSY/lock
  file issues.

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
make lockfiles-sync
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

Lock files are automatically synchronized via bind mounts. Changes made inside
the container are immediately visible on the host and vice versa.

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
make lockfiles-sync
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
make lockfiles-sync

# 4. Commit changes
git add composer.json composer.lock package.json pnpm-lock.yaml
git commit -m "feat: add new dependencies"
```

### After Pulling Changes

```bash
# 1. Pull latest code
git pull

# 2. Reinstall dependencies (if lock files changed)
make composer-install
make pnpm-install

# 3. Restart containers
make restart
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
make lockfiles-sync
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
| `make lockfiles-sync`     | Manually sync lock files from containers    |
| `make composer CMD="..."` | Run arbitrary Composer command              |
| `make pnpm CMD="..."`     | Run arbitrary pnpm command                  |
| `make validate`           | Validate composer.json and package.json     |
| `make outdated`           | Check for outdated PHP dependencies         |

---

## See Also

- [WINDOWS.md](../setup/WINDOWS.md) - Windows-specific setup (WSL2)
- [RENOVATE.md](RENOVATE.md) - Automated dependency updates
- [Docker Volumes](https://docs.docker.com/engine/storage/volumes/) - Official
  documentation
