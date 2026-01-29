# 15: Integration Tests Failing - Missing Dependencies

## Problem

`make bats-test-all` fails at integration tests with:

```
ok 187 [Integration] make test-php runs PHPUnit tests # skip Dependencies not installed
ok 188 [Integration] make test-coverage-php generates coverage # skip Dependencies not installed
...
make[1]: *** [Makefile:2980: bats-test-integration] Fehler 1
```

The integration tests skip because dependencies are not installed, but the test
suite still returns exit code 1.

## Root Cause

Integration tests check for installed dependencies and skip if missing. However,
the test file likely returns a non-zero exit code even when tests are skipped.

## Expected Behavior

- If dependencies are not installed, tests should skip gracefully (exit 0)
- OR the test runner should differentiate between "skipped" and "failed"

## Files to Investigate

- `tests/bats/integration/lint.bats` - Check skip logic
- `tests/bats/integration/test.bats` - Check dependency detection
- `Makefile` - `bats-test-integration` target (line ~2980)

## Priority

Medium - Blocks CI/CD pipeline if dependencies aren't pre-installed.

## Workaround

Run `make composer-install && make pnpm-install` before `make bats-test-all`.
