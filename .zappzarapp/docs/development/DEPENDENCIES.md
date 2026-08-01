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

### Updating the Package Managers

**pnpm** is version-pinned in `package.json` via
`"packageManager": "pnpm@x.x.x"`; Corepack enforces this version inside the
container.

- To update pnpm, use `make pnpm-upgrade` — it fetches the latest version via
  the container and updates the pin.
- The container is the source of truth: local Node/pnpm versions may differ.
- Rebuilding the container (even with `--no-cache --pull`) does NOT update pnpm
  — the `packageManager` field takes precedence.

**Composer** is baked into the PHP image at build time via `FROM composer:2`.

- To update Composer, run `make build-php` — it pulls the latest `composer:2`
  image.
- There is no runtime pinning (unlike pnpm/Corepack) and no dedicated
  `composer-upgrade` target: the container rebuild handles it automatically.

### pnpm Override Floors (Security Fixes)

When forcing a patched version of a vulnerable transitive dependency via
`pnpm.overrides`, never let an override selector span a semver major:

- A floor like `"js-yaml@<4.3.0": ">=4.3.0"` silently rewrites js-yaml **3.x**
  consumers onto 4.x. js-yaml 4 removed `safeLoad`, so every 3.x consumer
  crashes at require time — a runtime break that no lint or audit check catches,
  only actually executing the tool.
- Write one floor per major line, targeting that line's patched release:
  `"js-yaml@<3.15.0": ">=3.15.0 <4.0.0"` and
  `"js-yaml@>=4.0.0 <4.3.0": ">=4.3.0 <5.0.0"`.
- Check the OSV/GHSA "affected ranges" for per-major fix versions before writing
  a floor — advisories often ship backports (here, 3.15.0 fixes the same GHSAs
  as 4.3.0).
- An override selector that spans a semver major is a compatibility rewrite, not
  a security floor. After adding overrides, smoke-run the CLIs that consume the
  affected transitive.

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

| Command                   | Description                                   |
| ------------------------- | --------------------------------------------- |
| `make composer-install`   | Install PHP deps (Docker) + sync lock file    |
| `make pnpm-install`       | Install Node deps (Docker) + sync lock file   |
| `make composer-update`    | Update PHP deps + sync lock file              |
| `make pnpm-update`        | Update Node deps + sync lock file             |
| `make lockfiles-sync`     | Manually sync lock files from containers      |
| `make composer CMD="..."` | Run arbitrary Composer command                |
| `make pnpm CMD="..."`     | Run arbitrary pnpm command                    |
| `make validate`           | Validate composer.json and package.json       |
| `make outdated`           | Check for outdated PHP + Node.js dependencies |

---

## See Also

- [WINDOWS.md](../setup/WINDOWS.md) - Windows-specific setup (WSL2)
- [RENOVATE.md](RENOVATE.md) - Automated dependency updates
- [Docker Volumes](https://docs.docker.com/engine/storage/volumes/) - Official
  documentation
