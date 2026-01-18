---
description: Review changes before committing
context: fork
allowed-tools: Read, Glob, Grep, Bash(git:*), Bash(make:*), Bash(docker:*),
  Bash(docker compose:*)
argument-hint: [--staged | --all | --file <path>]
---

# Code Review

Review code changes before committing to catch issues early.

## Arguments

Parse `$ARGUMENTS`:

- (default): Review staged changes only
- `--staged`: Same as default, review staged changes
- `--all`: Review all changes (staged + unstaged)
- `--file <path>`: Review specific file only

## Review Process

### Step 1: Gather Changes

```bash
# Staged changes (default)
git diff --cached --name-only
git diff --cached

# All changes
git diff --name-only
git diff

# Specific file
git diff <path>
git diff --cached <path>
```

### Step 2: Categorize Changes

Group files by type:

| Category | Files |
|----------|-------|
| PHP | src/php/*, tests/php/* |
| Node | src/node/*, tests/node/* |
| Config | compose.yaml, Dockerfile, Makefile |
| Docs | documentation/*, *.md |

### Step 3: Review Checklist

For each changed file, check:

#### Code Quality

- [ ] No debug code left (console.log, var_dump, dd())
- [ ] No commented-out code blocks
- [ ] No TODO/FIXME without ticket reference
- [ ] Consistent code style (PSR-12 for PHP, ESLint for Node)

#### Security

- [ ] No hardcoded secrets or credentials
- [ ] No SQL injection vulnerabilities
- [ ] No XSS vulnerabilities
- [ ] Input validation present where needed

#### Architecture

- [ ] Changes follow existing patterns
- [ ] No unnecessary dependencies added
- [ ] Proper error handling
- [ ] Types/interfaces used correctly

#### Tests

- [ ] New code has corresponding tests
- [ ] Existing tests still pass
- [ ] Edge cases covered

#### Config Changes

- [ ] .env.example updated if new env vars added
- [ ] compose.yaml changes require rebuild reminder
- [ ] Dockerfile changes tested

### Step 4: Run Automated Checks

```bash
# PHP checks
make phpstan
make cs-check

# Node checks
make eslint
make typecheck

# Tests
make test
```

### Step 5: Generate Report

```text
Review Summary
══════════════

Files Changed: 5
  - src/php/App/Service/UserService.php (modified)
  - src/php/App/Controller/AuthController.php (modified)
  - tests/php/App/Unit/UserServiceTest.php (added)
  - src/node/backend/routes/auth.ts (modified)
  - documentation/API.md (modified)

Issues Found: 2

[WARN] src/php/App/Service/UserService.php:45
  TODO without ticket reference: "TODO: add validation"

[WARN] src/node/backend/routes/auth.ts:23
  console.log left in code

Checks Passed:
  ✓ PHPStan
  ✓ PHP-CS-Fixer
  ✓ ESLint
  ✓ TypeScript
  ✓ Tests

Recommendation: Fix warnings before committing
```

## Quick Review Mode

For small changes, provide condensed output:

```text
Quick Review: 2 files, 15 lines changed
✓ No issues found
Ready to commit
```

## Integration with /commit

After review passes, suggest:

```text
Review passed. Run /commit to proceed with commit.
```

## Notes

- Run before every commit for best results
- Use --all to catch unstaged changes that might be forgotten
- Config file changes get extra scrutiny
