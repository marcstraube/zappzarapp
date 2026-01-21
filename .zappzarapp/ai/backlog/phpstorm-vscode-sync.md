# PhpStorm/VSCode Settings Sync

**Status:** Open **Size:** Small **Scope:** chore **Created:** 2026-01-21
**Planning:** Not required

## Context

Final sync between PhpStorm and VSCode settings. PhpStorm is the leading IDE for
configuration.

## Goal

Ensure VSCode has matching settings where applicable.

## Implementation

1. Compare `.idea/` settings with `.vscode/` equivalents
2. Identify missing VSCode settings
3. Add missing configurations to VSCode

## Files

Check:

- `.idea/*.xml` (source of truth)
- `.vscode/settings.json`
- `.vscode/extensions.json`
