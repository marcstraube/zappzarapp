# License Compliance Check

**Status:** Planned **Size:** Small **Scope:** chore **Created:** 2026-01-20
**Planning:** Not required

## Context

Important for commercial projects using open-source dependencies.

## Goal

Add automated license compliance checking for PHP and Node.js dependencies.

## Why It Matters

- Detect GPL/AGPL licenses that may conflict with commercial use
- Identify unknown or problematic licenses
- Generate SBOM (Software Bill of Materials) for compliance audits

## Tools

| Tool                     | Language | Purpose                    |
| ------------------------ | -------- | -------------------------- |
| `license-checker`        | Node.js  | Scan pnpm dependencies     |
| `composer licenses`      | PHP      | Built-in Composer command  |
| `cyclonedx-php-composer` | PHP      | SBOM generation (optional) |

## Implementation

1. Add `make license-check` target
2. Configure allowed/blocked license lists
3. Fail CI on problematic licenses
4. Optional: Generate SBOM for audits

## Files

- `Makefile` (new `license-check` target)
