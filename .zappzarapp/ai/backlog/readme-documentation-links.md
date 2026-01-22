# README.md Documentation Links Fix

**Status:** Open
**Size:** Small
**Scope:** docs
**Created:** 2026-01-22
**Planning:** Not required

## Context

The README.md contains links to documentation files in `.zappzarapp/docs/`. These
links don't work by default because the documentation folder only exists after
running `make setup`, which moves files to the project root.

## Goal

Fix documentation links in README.md so they work both:
1. In the boilerplate repo itself (before setup)
2. After `make setup` in user projects

## Implementation

1. Review current README.md link structure
2. Determine link targets (relative paths in `.zappzarapp/docs/`)
3. Either:
   - Use relative links that work pre-setup
   - Add note explaining docs are available after `make setup`
   - Or restructure to use working links

## Files

- `README.md`
- `.zappzarapp/docs/` (link targets)

## Notes

User reported: "Links zu Dokumentation korrigieren, funktionieren derzeit nicht
per Default, erst wenn verschoben nach make setup"
