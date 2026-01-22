# CI/CD Prettier TOML Pattern Fix

**Status:** Open
**Size:** Small
**Scope:** fix
**Created:** 2026-01-22
**Planning:** Not required

## Context

CI/CD tests fail because Prettier searches for `**/*.toml` files but none exist.
This causes exit code 2.

## Goal

`make check` and CI/CD pipeline run without Prettier errors.

## Problem

```text
[error] No files matching the pattern were found: "**/*.toml".
ELIFECYCLE  Command failed with exit code 2.
make: *** [Makefile:2216: prettier-check] Fehler 2
```

## Implementation

1. `**/*.toml` Pattern aus package.json Prettier-Scripts entfernen
2. Oder: Dummy .toml Datei anlegen (nicht empfohlen)
3. Oder: `--ignore-unknown` Flag zu Prettier hinzufügen

## Files

- `package.json` (format und format:check Scripts)

## Notes

The `.toml` pattern was likely intended for Gemini CLI commands
(`.gemini/commands/*.toml`), but these are generated and not in the repo.
