# Mutation Testing Integration

**Status:** Planned **Size:** Medium **Scope:** chore **Created:** 2026-01-19
**Planning:** Not required

## Context

Current tests pass, but do they actually catch bugs?

## Goal

Add mutation testing to verify test effectiveness.

## Tools

- **PHP:** Infection (<https://infection.github.io/>)
- **Node:** Stryker (<https://stryker-mutator.io/>)

## How It Works

- Modifies code (mutations) and re-runs tests
- If tests still pass → "mutant survived" = test gap
- Mutation Score Indicator (MSI) shows test quality

## Implementation

1. Add Infection to composer dev dependencies
2. Add Stryker to package.json dev dependencies
3. Create Makefile targets: `mutation-test-php`, `mutation-test-node`
4. Configure for CI (optional, slow but valuable)

## Expected Findings

- Tests that don't assert correctly
- Dead code paths
- Missing edge case coverage

## Files

Create:

- `infection.json5` (PHP config)
- `stryker.conf.json` (Node config)

Modify:

- `Makefile` (new targets)
