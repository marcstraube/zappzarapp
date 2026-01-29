# Task 06: Add Code Coverage Badges to README

## Priority

LOW - Post-Release v1.1

## Estimated Effort

30 minutes

## Context

During the Code Quality & Testing review, it was noted that while comprehensive
test coverage exists (80% thresholds), this isn't visible in the README.
Coverage badges provide immediate quality signaling and encourage maintaining
high coverage.

## Current State

- PHPUnit and Vitest generate coverage reports
- Coverage is uploaded to Codecov in CI
- No badges in README

## Target State

1. Add coverage badges to README
2. Add CI status badges
3. Add other relevant quality badges

## Implementation Steps

### Step 1: Get Badge URLs from Codecov

After coverage is uploaded to Codecov:

1. Go to https://app.codecov.io/gh/marcstraube/zappzarapp
2. Navigate to Settings → Badge
3. Copy the markdown badge URL

### Step 2: Update README.md

Add badges section at the top of `README.md`:

```markdown
# zappzarapp

[![CI](https://github.com/marcstraube/zappzarapp/actions/workflows/ci.yml/badge.svg)](https://github.com/marcstraube/zappzarapp/actions/workflows/ci.yml)
[![Security](https://github.com/marcstraube/zappzarapp/actions/workflows/security-scan.yml/badge.svg)](https://github.com/marcstraube/zappzarapp/actions/workflows/security-scan.yml)
[![codecov](https://codecov.io/gh/marcstraube/zappzarapp/graph/badge.svg)](https://codecov.io/gh/marcstraube/zappzarapp)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

A professional web development stack that gets you coding in minutes - zappzarapp!
```

### Step 3: Add Detailed Coverage Badges (Optional)

For separate PHP and Node.js coverage:

```markdown
## Test Coverage

| Component | Coverage |
|-----------|----------|
| PHP | [![codecov](https://codecov.io/gh/marcstraube/zappzarapp/graph/badge.svg?flag=php)](https://codecov.io/gh/marcstraube/zappzarapp) |
| Node.js | [![codecov](https://codecov.io/gh/marcstraube/zappzarapp/graph/badge.svg?flag=node)](https://codecov.io/gh/marcstraube/zappzarapp) |
```

This requires flag configuration in CI:

```yaml
# In ci.yml PHP coverage upload
- name: Upload PHP coverage
  uses: codecov/codecov-action@v4
  with:
    files: ./build/coverage/clover.xml
    flags: php

# In ci.yml Node coverage upload
- name: Upload Node coverage
  uses: codecov/codecov-action@v4
  with:
    files: ./build/coverage/lcov.info
    flags: node
```

### Step 4: Add Additional Quality Badges (Optional)

```markdown
[![PHPStan](https://img.shields.io/badge/PHPStan-Level%208-brightgreen.svg)](https://phpstan.org/)
[![TypeScript](https://img.shields.io/badge/TypeScript-Strict-blue.svg)](https://www.typescriptlang.org/)
[![Docker](https://img.shields.io/badge/Docker-Ready-blue.svg)](https://www.docker.com/)
[![Node](https://img.shields.io/badge/Node-24.x-green.svg)](https://nodejs.org/)
[![PHP](https://img.shields.io/badge/PHP-8.4-purple.svg)](https://www.php.net/)
```

### Step 5: Create codecov.yml (Optional)

**Create `codecov.yml` for coverage configuration:**

```yaml
codecov:
  require_ci_to_pass: yes

coverage:
  precision: 2
  round: down
  range: "70...100"
  status:
    project:
      default:
        target: 80%
        threshold: 5%
    patch:
      default:
        target: 80%

flags:
  php:
    paths:
      - src/php/
    carryforward: true
  node:
    paths:
      - src/node/
    carryforward: true

comment:
  layout: "reach,diff,flags,files"
  behavior: default
  require_changes: true
```

## Verification

1. **Badges display correctly:**
   - Push changes to a branch
   - View README in GitHub
   - Verify all badges load and show correct status

2. **Badge URLs work:**

   ```bash
   # Test badge URL (should return SVG)
   curl -I "https://github.com/marcstraube/zappzarapp/actions/workflows/ci.yml/badge.svg"
   ```

3. **Coverage badge updates:**
   - After CI runs on master
   - Codecov badge should reflect current coverage

## Files to Modify

1. `README.md` - Add badges section
2. `.github/workflows/ci.yml` - Add coverage flags (optional)
3. `codecov.yml` - Create for configuration (optional)

## Badge Reference

| Badge | Source | Purpose |
|-------|--------|---------|
| CI Status | GitHub Actions | Build status |
| Security | GitHub Actions | Security scan status |
| Coverage | Codecov | Test coverage percentage |
| License | shields.io | License type |
| PHP Version | shields.io | Required PHP version |
| Node Version | shields.io | Required Node version |
| PHPStan | shields.io | Static analysis level |

## Notes

- Badges may take a few minutes to update after CI runs
- Private repos need Codecov token for badge access
- Keep badge count reasonable (5-7 max)
- Badges should reflect actual project status
