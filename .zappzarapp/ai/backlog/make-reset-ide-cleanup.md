# Make Reset: IDE-Generated Files Cleanup

**Status:** Planned **Size:** Small **Scope:** chore **Created:** 2026-01-21
**Planning:** Not required

## Context

Inconsistency between `make reset` and IDE credentials.

## Problem

`make reset` deletes `secrets/` but IDE-generated files with cached DB
credentials remain. This is inconsistent for a "factory reset".

## Files Affected (gitignored, locally generated)

| File                          | Content                     |
| ----------------------------- | --------------------------- |
| `.idea/dataSources.local.xml` | DB passwords/credentials    |
| `.idea/dataSources/`          | Schema cache, introspection |
| `.idea/sshConfigs.xml`        | SSH tunnel configurations   |

## Files Not Affected (committed templates)

- `.idea/dataSources.xml` — DB connection templates without passwords

## Implementation

1. Add IDE cleanup to `make reset`:

   ```bash
   rm -f .idea/dataSources.local.xml
   rm -rf .idea/dataSources/
   rm -f .idea/sshConfigs.xml
   ```

2. Optional: Create separate `make ide-clean` target for IDE-only reset

## Files

- `Makefile` (`reset` target, optional `ide-clean` target)
