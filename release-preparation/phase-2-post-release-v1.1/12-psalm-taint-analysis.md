# Psalm Taint Analysis (PHP)

**Status:** Planned **Size:** Medium **Scope:** chore **Created:** 2026-01-20
**Planning:** Not required

## Context

Deep PHP-specific dataflow analysis, complements Semgrep.

## Goal

Enable Psalm's taint analysis for tracking untrusted data through PHP code.

## What It Finds

- SQL Injection via tainted variables
- XSS via unescaped user input
- Command Injection via shell_exec/exec
- File inclusion vulnerabilities
- LDAP Injection
- Custom taint sources/sinks

## How It Works

Tracks data flow from "sources" (user input) to "sinks" (dangerous functions).
More precise than pattern matching, fewer false positives.

## Implementation

1. `make composer CMD="require --dev vimeo/psalm"`
2. Create `psalm.xml` with taint analysis enabled
3. Add taint annotations to existing code (`@psalm-taint-source`,
   `@psalm-taint-sink`)
4. Create `make security-scan-php` target
5. Integrate with existing `make check` or separate `make security-check`

## Files

Modify:

- `composer.json` (new dev dependency)

Create:

- `psalm.xml` (Psalm configuration with taint analysis)

Modify:

- `Makefile` (new `security-scan-php` target)

## Resources

- <https://psalm.dev/docs/security_analysis/>
- <https://psalm.dev/docs/security_analysis/custom_taint_sources/>
