# Shell Script Standards

## Linting

- Tool: ShellCheck (via Docker: `koalaman/shellcheck`)
- Target: `make lint-shell`
- Config: `.shellcheckrc`
- Severity: `warning` (info-level shown but won't fail build)

## Disabled Checks

| Check | Reason |
|-------|--------|
| SC1091 | Can't follow non-constant source (external files) |
| SC2002 | "Useless cat" — stylistic preference, `cat file \| cmd` is readable |
| SC2250 | Prefer `${var}` over `$var` — too noisy, `$var` is fine |
| SC2312 | Command substitution in pipes — too noisy, often intentional |

## Required Checks

| Check | Description | Why |
|-------|-------------|-----|
| SC2292 | Prefer `[[ ]]` over `[ ]` | Safer in Bash (no word splitting, better quoting) |
| SC2155 | Declare and assign separately | Prevents masking return values |
| SC2046 | Quote command substitution | Prevents word splitting |

## Best Practices

### Shebang Selection

- `#!/bin/bash` — For scripts using Bash features (`[[ ]]`, `local`, arrays)
- `#!/bin/sh` — For POSIX-compatible scripts (Alpine containers, portability)

When using `#!/bin/sh`, add `# shellcheck shell=sh` to prevent Bash-specific warnings.

### Variable Assignment with Export

```bash
# Bad: export VAR=$(cmd) — masks return value
export VAR=$(cmd)

# Good: separate assignment and export
VAR=$(cmd)
export VAR
```

### Test Brackets

```bash
# POSIX sh (use [ ])
if [ -f "$file" ]; then

# Bash (use [[ ]] — safer)
if [[ -f "$file" ]]; then
```

### Read User Input

```bash
# Bad: read without -r mangles backslashes
read -p "Input: " value

# Good: use -r flag
read -rp "Input: " value
```

### External Variables

For variables set by the environment or framework (e.g., BATS):

```bash
# shellcheck disable=SC2154  # VAR is set via environment
```

## Scripts Covered

- `docker/*/entrypoint*.sh` — Container entrypoints
- `docker/*/healthcheck.sh` — Health check scripts
- `docker/scripts/*.sh` — Utility scripts
- `docker/certs/*.sh` — Certificate management
- `docker/hooks/*.sh` — Git/build hooks
- `tests/bats/helpers/*.bash` — BATS test helpers
