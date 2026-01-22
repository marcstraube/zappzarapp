# Shell Tests (BATS)

This document describes the BATS (Bash Automated Testing System) tests for
validating Makefile targets, environment configurations, and Docker Compose
setups.

## Overview

BATS tests validate the infrastructure layer:

- **Makefile targets**: All documented targets exist and execute correctly
- **Environment variables**: DB_TYPE, NODE_MODE, ENABLE\_\* flags work as
  expected
- **Docker Compose**: Configuration validates, services start correctly
- **Goss integration**: BATS + Goss combined validation

## Directory Structure

```text
tests/bats/
├── helpers/
│   ├── setup.bash              # Common setup, load helpers
│   └── goss-integration.bash   # Goss validation wrappers
├── integration/
│   ├── setup.bash              # Integration test setup/teardown
│   ├── lint.bats               # Lint & code quality (~20 tests)
│   ├── test.bats               # PHPUnit & Vitest (~10 tests)
│   ├── docs.bats               # Documentation generation (~6 tests)
│   ├── database.bats           # Database operations (~15 tests)
│   ├── security.bats           # Security scanning (~12 tests)
│   ├── services.bats           # Service operations (~15 tests)
│   └── destructive.bats        # ⚠️ Destructive tests (~10 tests)
├── make-help.bats              # Help system tests (~27 tests)
├── make-env.bats               # Environment loading tests (~27 tests)
├── make-docker.bats            # Docker command tests (~33 tests)
├── make-environment.bats       # Environment matrix tests (~42 tests)
├── make-goss.bats              # BATS + Goss integration (~49 tests)
└── make-targets-dryrun.bats    # All targets dry-run (~154 tests)

docker/bats/
└── Dockerfile                  # BATS testing container
```

## Running Tests

### Quick Start

```bash
# Build BATS container (first time only)
make bats-build

# Run all BATS tests (dry-run validation)
make bats-test

# Run specific test file
make bats-test-file FILE=make-help.bats

# Verbose output (TAP format)
make bats-test-verbose

# JUnit output (for CI/CD)
make bats-test-junit
```

### Integration Tests

Integration tests require running containers:

```bash
# Start containers first
make up
make composer-install
make pnpm-install

# Run integration tests
make bats-test-integration

# Run specific integration test
make bats-test-integration-file FILE=lint.bats

# Run all tests (dry-run + integration)
make bats-test-all
```

### Destructive Tests

Destructive tests modify/delete data. Use with caution:

```bash
# Run destructive tests (with confirmation prompt)
make bats-test-destructive
```

### Local Development

If you have BATS installed locally:

```bash
# Install BATS (macOS)
brew install bats-core

# Run tests locally
make bats-test-local
```

## Test Suites

### make-help.bats

Tests the Makefile help system:

- `make help` produces expected output
- Category filtering works (`FILTER=setup`, `FILTER=docker`)
- All key targets have help text
- Target existence validation

```bash
@test "make help shows all main categories" {
    run make help
    assert_success
    assert_output --partial "Setup"
    assert_output --partial "Docker"
    assert_output --partial "Quality Assurance"
}
```

### make-env.bats

Tests environment variable handling:

- `.env` file loading
- `.env.local` override behavior
- Default values (DB_TYPE, NODE_MODE, ENV)
- ENABLE\_\* flags
- Port configuration
- Environment isolation

```bash
@test "DB_TYPE defaults to postgres" {
    clean_test_env
    load_env_file ".env"
    [[ "${DB_TYPE:-postgres}" == "postgres" ]]
}
```

### make-docker.bats

Tests Docker commands (read-only and dry-run):

- `make status` works
- Compose configuration validates
- Build/up/down dry-run tests
- Logs/shell commands exist
- Profile detection

```bash
@test "docker compose config validates" {
    run docker compose config --quiet
    assert_success
}
```

### make-environment.bats

Tests environment variable combinations:

- DB_TYPE: postgres | mariadb
- NODE_MODE: assets | api | assets-api | framework | framework-api | idle
- ENABLE_REDIS, ENABLE_MERCURE, ENABLE_MEILISEARCH, etc.
- Combined configurations
- Preset file validation

```bash
@test "DB_TYPE=postgres + NODE_MODE=framework + ENABLE_REDIS=true: compose validates" {
    export DB_TYPE=postgres
    export NODE_MODE=framework
    export ENABLE_REDIS=true
    run docker compose config --quiet
    assert_success
}
```

### make-goss.bats

Tests Goss integration:

- Goss directory structure
- Service spec files exist
- Makefile targets exist
- Preset helpers work
- Dry-run validation

```bash
@test "goss-test target exists" {
    run target_exists "goss-test"
    assert_success
}
```

## Helper Functions

### setup.bash

Common test helpers available in all test files:

| Function                               | Description                          |
| -------------------------------------- | ------------------------------------ |
| `is_container_running <name>`          | Check if container is running        |
| `wait_for_healthy <service> <timeout>` | Wait for service health              |
| `clean_test_env`                       | Clear all test environment variables |
| `load_env_file <file>`                 | Source an env file safely            |
| `target_exists <target>`               | Check if Makefile target exists      |
| `get_target_help <target>`             | Get help text for a target           |

### goss-integration.bash

Goss-specific helpers:

| Function                     | Description                    |
| ---------------------------- | ------------------------------ |
| `run_goss_test <service>`    | Run Goss test for a service    |
| `run_goss_preset <preset>`   | Run Goss preset                |
| `goss_test_passed <service>` | Check if last Goss test passed |
| `get_goss_presets`           | List available presets         |
| `preset_exists <preset>`     | Check if preset exists         |

## Test Tiers

BATS tests are organized in tiers for different use cases:

| Tier                | Tests | Duration | When to Run      |
| ------------------- | ----- | -------- | ---------------- |
| **Quick (Dry-Run)** | ~332  | ~2 min   | Every push       |
| **Integration**     | ~88   | ~15 min  | PRs, main branch |
| **Destructive**     | ~10   | ~5 min   | Manual only      |

## CI/CD Integration

### GitHub Actions

BATS tests run in a matrix configuration:

```yaml
# Quick tests (always run)
bats-quick:
  name: BATS Quick Tests (Dry-Run)
  runs-on: ubuntu-latest
  timeout-minutes: 10
  steps:
    - uses: actions/checkout@v4
    - run: make bats-build
    - run: make bats-test

# Integration tests (on PR/main)
bats-integration:
  name: BATS Integration Tests
  runs-on: ubuntu-latest
  timeout-minutes: 30
  needs: [bats-quick]
  if: github.event_name == 'pull_request'
  steps:
    - uses: actions/checkout@v4
    - run: make bats-build
    - run: make build && make up
    - run: make composer-install && make pnpm-install
    - run: make bats-test-integration
```

### GitLab CI

```yaml
bats:quick:
  stage: test
  script:
    - make bats-build
    - make bats-test-junit
  artifacts:
    reports:
      junit: build/bats-report.xml

bats:integration:
  stage: test
  needs: [bats:quick]
  only: [master, develop, merge_requests]
  script:
    - make bats-build
    - make build && make up
    - make bats-test-integration
  timeout: 30m
```

## Writing New Tests

### Basic Test Structure

```bash
#!/usr/bin/env bats
# Test description

load 'helpers/setup'

@test "descriptive test name" {
    run some_command
    assert_success
    assert_output --partial "expected output"
}

@test "test with environment" {
    export MY_VAR=value
    run make my-target
    assert_success
}

@test "test failure case" {
    run invalid_command
    assert_failure
}
```

### Assertions

Available from bats-assert:

| Assertion                          | Description             |
| ---------------------------------- | ----------------------- |
| `assert_success`                   | Exit code 0             |
| `assert_failure`                   | Exit code non-zero      |
| `assert_output <expected>`         | Exact output match      |
| `assert_output --partial <text>`   | Output contains text    |
| `assert_output --regexp <pattern>` | Output matches regex    |
| `refute_output --partial <text>`   | Output does NOT contain |
| `assert_line <line>`               | Line exists in output   |

### Test Categories

Group tests with section comments:

```bash
# =============================================================================
# Section Name
# =============================================================================

@test "test in this section" {
    # ...
}
```

## Docker Container

The BATS container (`docker/bats/Dockerfile`) includes:

- Alpine Linux (minimal footprint)
- BATS 1.11.1
- bats-support, bats-assert, bats-file helper libraries
- Docker CLI (for testing compose commands)
- docker-compose
- Common utilities (bash, curl, jq, git)

Multi-stage build:

1. **bats-builder**: Installs BATS and helpers
2. **runtime**: Base image with dependencies
3. **test**: Copies test files
4. **production**: Final optimized image

## Best Practices

1. **Use dry-run for destructive commands**: `make -n target` validates without
   executing
2. **Clean environment**: Always use `clean_test_env` before testing defaults
3. **Descriptive names**: Test names should describe what's being tested
4. **One assertion per concept**: Keep tests focused
5. **Skip conditionally**: Use `skip` for optional features

```bash
@test ".env.local overrides values" {
    if [[ ! -f .env.local ]]; then
        skip ".env.local not present"
    fi
    # test continues...
}
```

## Troubleshooting

### Tests fail with "docker: command not found"

The BATS container needs access to Docker socket:

```bash
docker run --rm \
    -v /var/run/docker.sock:/var/run/docker.sock \
    -v "$(PWD):/app" \
    --network host \
    zappzarapp-bats tests/bats/
```

### Tests timeout

Increase timeout for slow operations:

```bash
@test "slow operation" {
    run timeout 60 make slow-target
    assert_success
}
```

### Environment pollution

Tests may pollute environment. Use setup/teardown:

```bash
setup() {
    load 'helpers/setup'
    clean_test_env
}

teardown() {
    clean_test_env
}
```

## Related Documentation

- [TESTING-PHP.md](TESTING-PHP.md) - PHP unit/feature tests (PHPUnit)
- [TESTING-NODE.md](TESTING-NODE.md) - Node.js tests (Vitest)
- [TESTING.md](../../ai/TESTING.md) - Goss container tests
