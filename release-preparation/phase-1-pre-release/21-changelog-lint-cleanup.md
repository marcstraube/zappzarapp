# Task 21: Enable CHANGELOG.md Linting for v1.0

## Overview

Remove `CHANGELOG.md` from markdownlint ignore list after cleaning up the legacy
changelog entries for the v1.0 release.

## Background

The current `CHANGELOG.md` contains legacy entries with markdown formatting
issues. It was added to `.markdownlint-cli2.jsonc` ignores to prevent CI
failures. Once the changelog is cleaned up for v1.0, linting should be enabled.

## Requirements

### Pre-requisites

- [ ] CHANGELOG.md has been cleaned up and reformatted for v1.0 release
- [ ] All legacy entries follow proper markdown formatting

### Changes

1. **Remove from ignore list** in `.markdownlint-cli2.jsonc`:

   ```jsonc
   "ignores": [
     // ... other entries ...
     "CHANGELOG.md"  // <-- REMOVE THIS LINE
   ]
   ```

2. **Verify** markdown linting passes:
   ```bash
   make lint-md
   ```

3. **Fix** any remaining issues in CHANGELOG.md

## Acceptance Criteria

- [ ] `CHANGELOG.md` is no longer in `.markdownlint-cli2.jsonc` ignores
- [ ] `make lint-md` passes without errors
- [ ] CHANGELOG.md follows Keep a Changelog format with proper markdown

## Priority

Low - Do during v1.0 release preparation

## Dependencies

- Depends on: v1.0 release changelog cleanup
