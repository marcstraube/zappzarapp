# ESLint Security Plugin (Node.js)

**Status:** Planned **Size:** Small **Scope:** chore **Created:** 2026-01-20
**Planning:** Not required

## Context

Quick win - minimal setup, immediate value.

## Goal

Add security-focused linting rules for Node.js code.

## What It Finds

- `eval()` and `Function()` usage
- `child_process` with dynamic input
- Non-literal `require()` calls
- Regular Expression DoS (ReDoS)
- Unsafe object property access

## Implementation

1. `make pnpm CMD="add -D eslint-plugin-security"`
2. Add plugin to `eslint.config.js`
3. Run initial scan, fix or suppress findings
4. Add to `make check` pipeline

## Files

- `package.json` (new dev dependency)
- `eslint.config.js` (add security plugin config)

## Resources

- <https://www.npmjs.com/package/eslint-plugin-security>
