---
description: Documentation audit with validation of examples, links, and references
context: fork
allowed-tools: Read, Write, Edit, Grep, Glob, Bash(make:*), Bash(find:*), Bash(grep:*),
  Bash(git:*), Bash(ls:*), Bash(cat:*), Bash(head:*), Bash(test:*), Bash(docker compose run:*),
  AskUserQuestion
argument-hint: "[--full | --since <commit>] [--fix]  OR  --fix-only"
---

# Documentation Audit

Comprehensive documentation audit with validation of examples, links, and references.
Supports automatic and interactive fixes for common issues.

## Modes

This audit supports multiple modes via `$ARGUMENTS`:

### Audit Modes

- **Full audit** (`--full` or no argument): Check all documentation files
- **Incremental audit** (`--since <commit>`): Only check files changed since commit

### Fix Modes

- **Audit only** (default): Report findings without fixing
- **With fixes** (`--fix`): Audit and fix issues (safe fixes auto, others interactive)
- **Fix only** (`--fix-only`): Apply fixes to a completed audit without re-running

Examples:

```bash
# Audit only (report issues)
/docs-audit --full
/docs-audit --since main

# Audit with fixes
/docs-audit --fix
/docs-audit --full --fix
/docs-audit --since main --fix

# Apply fixes to previous audit (must have unchanged git state)
/docs-audit --fix-only
```

## Fix Behavior

### Safe Fixes (Auto-Applied with `--fix`)

These fixes are applied automatically without confirmation:

| Issue Type | Fix Action |
| ---------- | ---------- |
| Missing code block language | Add language based on context |
| Wrong relative link path | Correct path (e.g., `security/X.md` → `../security/X.md`) |
| Typo in make target name | Fix obvious typo (e.g., `make biuld` → `make build`) |
| Missing file in `_sidebar.md` | Add entry in correct section |
| Wrong env variable name | Fix to match `.env.example` |

### Interactive Fixes (Requires Confirmation)

These fixes require user confirmation via `AskUserQuestion`:

| Issue Type | Confirmation Needed |
| ---------- | ------------------- |
| Outdated code example | Show diff, ask to update |
| Deprecated make target | Ask how to handle (remove/update) |
| Missing documentation section | Propose content, ask to add |
| Config snippet mismatch | Show actual vs documented, ask to update |
| Major content change | Always ask before significant edits |

### Never Auto-Fixed

| Issue Type | Reason |
| ---------- | ------ |
| External broken links | May be temporary, needs manual verification |
| Security-related content | Requires expert review |
| Multiple valid options | User must choose |
| Unclear intent | Ask user for clarification |

## Progress File

All findings are saved to `.claude/docs-audit-progress.json` for resumability.
This file persists across context resets and allows interruption/resume.

## Initialization

1. Parse `$ARGUMENTS` to determine mode:
   - If `--fix-only`: Jump to [Fix Application](#fix-application-fix-only-mode)
   - If empty or `--full`: Set `mode = "full"`, `scope = "all files"`
   - If `--since <ref>`: Set `mode = "incremental"`, get changed files via
     `git diff --name-only <ref> -- documentation/`
   - If `--fix` present: Set `fix_mode = "enabled"`

2. Read `.claude/docs-audit-progress.json` if it exists:
   - Compare `git_commit` with current HEAD
   - Compare `mode` and `scope` with current run
   - If different: Warn user, ask whether to resume or restart
   - If same: Resume from last incomplete phase

3. If not exists or restart: Create new progress file:

```json
{
  "audit_type": "docs",
  "mode": "full | incremental",
  "scope": "all files | list of changed files",
  "since_ref": "<commit ref if incremental>",
  "fix_mode": "disabled | enabled",
  "started_at": "<ISO timestamp>",
  "last_updated": "<ISO timestamp>",
  "git_commit": "<commit hash from git rev-parse HEAD>",
  "git_branch": "<branch name from git branch --show-current>",
  "status": "in_progress | complete",
  "files_to_check": ["documentation/file1.md", "documentation/file2.md"],
  "phases": {
    "markdown_lint": {
      "status": "pending | complete",
      "findings": [],
      "fixes_applied": [],
      "fixes_pending": []
    }
  },
  "summary": null
}
```

## Execution Phases

Execute phases sequentially. After each phase, update the progress file immediately.
Skip phases that are already marked as "complete".

For incremental mode: Only check files in `files_to_check` list.

### Phase 1: Markdown Lint

Run markdownlint on documentation files using the project's `.markdownlint-cli2.jsonc` config:

```bash
# Via Make target (recommended - uses project config automatically)
make lint-md

# Or directly via Docker (also uses project config)
docker compose run --rm -T dev-tools pnpm exec markdownlint-cli2 'documentation/**/*.md'
```

**Important:** Always use the project's markdownlint config (`.markdownlint-cli2.jsonc`) to ensure
consistent formatting. The config disables certain rules (MD013 line length, MD033 inline HTML,
MD041 first heading, MD055/MD060 table formatting) that are handled by Prettier or impractical
for documentation.

Check for:

- MD001-MD050 rule violations (respecting project config)
- Inconsistent heading levels
- Missing language specifiers on code blocks (MD040)
- Code block style violations (MD046)

**Fixable Issues:**

| Issue | Auto-Fix | Action |
| ----- | -------- | ------ |
| MD040: Missing code block language | ✅ Safe | Infer from content or use `text` |
| MD009: Trailing spaces | ✅ Safe | Remove trailing whitespace |
| MD010: Hard tabs | ✅ Safe | Convert to spaces |
| MD047: Missing newline at EOF | ✅ Safe | Add newline |

If `--fix` enabled and safe fixes exist:

```bash
docker compose run --rm -T dev-tools pnpm exec markdownlint-cli2 --fix 'documentation/**/*.md'
```

Severity: Lint errors = [MED], Lint warnings = [LOW]

Update progress file: set `phases.markdown_lint.status = "complete"`.

### Phase 2: Internal Links

Validate all internal markdown links:

- `[text](./other-file.md)` - Target file exists?
- `[text](#section-heading)` - Anchor exists in same file?
- `[text](./file.md#anchor)` - File and anchor exist?
- `[text](../other-dir/file.md)` - Relative paths resolve correctly?

Check `_sidebar.md`:

- All documentation files listed?
- Any orphaned files (not linked anywhere)?

**Fixable Issues:**

| Issue | Auto-Fix | Action |
| ----- | -------- | ------ |
| Wrong relative path | ✅ Safe | Correct path (e.g., `security/X.md` → `../security/X.md`) |
| Missing `./` prefix | ✅ Safe | Add prefix for same-directory links |
| File not in sidebar | ✅ Safe | Add to `_sidebar.md` in correct section |
| Broken anchor link | ❌ Interactive | Show available anchors, ask user to choose |

Severity: Broken link = [CRIT], Orphaned file = [MED]

Update progress file: set `phases.internal_links.status = "complete"`.

### Phase 3: External Links

Validate external URLs (optional - can be slow):

- HTTP/HTTPS links reachable?
- No 404 errors?
- No redirect loops?

**Fix Policy:** External links are NEVER auto-fixed. Report only.

Note: Skip if network unavailable. Mark findings with [LOW] since external
links can break independently of our code.

Update progress file: set `phases.external_links.status = "complete"`.

### Phase 4: File References

Check that referenced files/paths exist in the codebase:

Patterns to find and validate:

- `` `path/to/file.php` `` or `path/to/file.ts`
- `docker/nginx/conf.d/...`
- `src/php/...`, `src/node/...`
- `tests/...`
- `.env`, `.env.example`
- `compose.yaml`, `Makefile`

For each path mentioned: Does it exist?

**Fixable Issues:**

| Issue | Auto-Fix | Action |
| ----- | -------- | ------ |
| Simple rename (same dir) | ✅ Safe | Update to new filename |
| Moved file (findable) | ✅ Safe | Update to new path |
| Deleted file | ❌ Interactive | Ask: remove reference or update content? |
| Ambiguous match | ❌ Interactive | Show options, ask user to choose |

Severity: Missing file = [CRIT], Renamed/moved file = [MED]

Update progress file: set `phases.file_references.status = "complete"`.

### Phase 5: Code Examples

Validate code examples in documentation:

**Bash examples:**

- Commands start with valid binaries?
- `make <target>` - target exists?
- `docker compose <cmd>` - valid syntax?

**PHP examples:**

- Syntax valid? (basic check for matching braces, semicolons)
- Class/function names match codebase?

**JavaScript/TypeScript examples:**

- Syntax plausible?
- Import paths would resolve?

**YAML/JSON examples:**

- Valid syntax?
- If config example: Keys match actual config structure?

**Fixable Issues:**

| Issue | Auto-Fix | Action |
| ----- | -------- | ------ |
| Typo in make target | ✅ Safe | Fix obvious typo (Levenshtein distance ≤ 2) |
| Wrong import path | ✅ Safe | Update to correct path |
| Outdated class/function name | ❌ Interactive | Show old vs new, ask to update |
| Invalid syntax | ❌ Interactive | Show error, propose fix |
| Outdated example output | ❌ Interactive | Show actual output, ask to replace |

Severity: Invalid syntax = [CRIT], Outdated example = [MED]

Update progress file: set `phases.code_examples.status = "complete"`.

### Phase 6: Make Targets

Find all `make <target>` references and validate:

```bash
grep -rohE 'make [a-z][-a-z0-9]*' documentation/ | sort -u
```

For each target: Does it exist in Makefile?

Cross-reference with `documentation/development/MAKEFILE-REFERENCE.md`:

- All Makefile targets documented?
- Any documented targets that no longer exist?

**Fixable Issues:**

| Issue | Auto-Fix | Action |
| ----- | -------- | ------ |
| Typo in target name | ✅ Safe | Fix if Levenshtein distance ≤ 2 |
| Renamed target | ✅ Safe | Update to new name (if obvious mapping) |
| Removed target | ❌ Interactive | Ask: remove docs or was removal a mistake? |
| Undocumented target | ❌ Interactive | Propose documentation, ask to add |

Severity: Missing target = [CRIT], Undocumented target = [MED]

Update progress file: set `phases.make_targets.status = "complete"`.

### Phase 7: Environment Variables

Find all environment variable references:

- `${VAR_NAME}` or `$VAR_NAME`
- `VAR_NAME=value` examples
- References in `.env` context

Validate against `.env.example`:

- Variable exists?
- Default value mentioned matches?
- Description accurate?

**Fixable Issues:**

| Issue | Auto-Fix | Action |
| ----- | -------- | ------ |
| Renamed variable | ✅ Safe | Update to new name |
| Wrong default value | ✅ Safe | Update to match `.env.example` |
| Removed variable | ❌ Interactive | Ask: update docs or was removal a mistake? |
| Missing from docs | ❌ Interactive | Propose documentation, ask to add |

Severity: Missing var = [MED], Wrong default = [LOW]

Update progress file: set `phases.env_variables.status = "complete"`.

### Phase 8: Config Snippets

Validate configuration examples match actual files:

- Nginx config snippets vs `docker/nginx/...`
- PHP config snippets vs `docker/php/...`
- Docker Compose snippets vs `compose.yaml`
- Package.json snippets vs actual `package.json`

Check for:

- Outdated syntax
- Missing/renamed keys
- Different default values

**Fixable Issues:**

| Issue | Auto-Fix | Action |
| ----- | -------- | ------ |
| Wrong key name | ✅ Safe | Update key name |
| Wrong default value | ✅ Safe | Update value |
| Outdated structure | ❌ Interactive | Show diff, ask to update |
| Missing required key | ❌ Interactive | Propose addition, ask to confirm |

Severity: Misleading config = [CRIT], Outdated = [MED]

Update progress file: set `phases.config_snippets.status = "complete"`.

### Phase 9: Structure & Consistency

Check documentation structure:

**Sidebar completeness:**

- All .md files in `documentation/` listed in `_sidebar.md`?
- Sidebar structure matches directory structure?

**Heading consistency:**

- All docs have H1 title?
- Consistent heading hierarchy (no skipped levels)?

**Required sections:**

- Do similar docs have similar structure?
- Security docs all have "Prerequisites" section?
- Setup docs all have "Verification" section?

**Metadata:**

- TODOs or FIXMEs that need attention?
- Outdated dates or version numbers?
- "Coming soon" or placeholder sections?

**Fixable Issues:**

| Issue | Auto-Fix | Action |
| ----- | -------- | ------ |
| Missing from sidebar | ✅ Safe | Add entry to `_sidebar.md` |
| Skipped heading level | ✅ Safe | Adjust heading level |
| Missing H1 title | ❌ Interactive | Propose title based on filename |
| Missing required section | ❌ Interactive | Propose template, ask to add |

Severity: Missing from sidebar = [MED], Inconsistent structure = [LOW]

Update progress file: set `phases.structure_consistency.status = "complete"`.

## Fix Application (fix-only Mode)

When running with `--fix-only`:

1. **Check progress file exists:**
   - If not: Error "No previous audit found. Run `/docs-audit` first."

2. **Verify git state unchanged:**
   ```bash
   git rev-parse HEAD
   ```
   - Compare with `git_commit` in progress file
   - If different: Error "Codebase has changed since audit. Run `/docs-audit --fix` instead."
   - Also check: `git status --porcelain` for uncommitted changes to documentation/
   - If dirty: Warn "Uncommitted changes detected. Proceed anyway?" (ask user)

3. **Check audit status:**
   - If `status != "complete"`: Error "Audit incomplete. Run `/docs-audit` to finish first."

4. **Apply pending fixes:**
   - Read `fixes_pending` from each phase
   - Apply safe fixes automatically
   - For interactive fixes: Ask user one by one using `AskUserQuestion`
   - After each fix, move from `fixes_pending` to `fixes_applied`
   - Update progress file after each fix

5. **Summary:**
   - Report: X safe fixes applied, Y interactive fixes applied, Z skipped

## Synthesis & Report

After all phases complete:

1. Read all findings from progress file
2. Count by severity: [CRIT], [MED], [LOW]
3. Count fixes: applied (safe), applied (interactive), pending, skipped
4. Generate summary table
5. Update progress file: set `status = "complete"` and populate `summary`
6. Write final report to `.claude/reports/docs-audit-{date}-{commit}.md`

### Report File

Save the final report to:

```text
.claude/reports/docs-audit-YYYY-MM-DD-{short-commit}.md
```

Example: `.claude/reports/docs-audit-2026-01-15-7cc7668.md`

Create the `.claude/reports/` directory if it doesn't exist.

### Report Format

```markdown
# Documentation Audit Report

**Date**: YYYY-MM-DD
**Branch**: <git_branch>
**Commit**: <git_commit>
**Mode**: full | incremental (since <ref>)
**Fix Mode**: disabled | enabled
**Files checked**: X of Y total
**Duration**: <started_at> to <last_updated>

## Summary

| Category              | [CRIT] | [MED] | [LOW] | Fixed | Status  |
| --------------------- | ------ | ----- | ----- | ----- | ------- |
| Markdown Lint         | X      | X     | X     | X     | OK/FAIL |
| Internal Links        | X      | X     | X     | X     | OK/FAIL |
| External Links        | X      | X     | X     | -     | OK/FAIL |
| File References       | X      | X     | X     | X     | OK/FAIL |
| Code Examples         | X      | X     | X     | X     | OK/FAIL |
| Make Targets          | X      | X     | X     | X     | OK/FAIL |
| Environment Variables | X      | X     | X     | X     | OK/FAIL |
| Config Snippets       | X      | X     | X     | X     | OK/FAIL |
| Structure/Consistency | X      | X     | X     | X     | OK/FAIL |
| **Total**             | X      | X     | X     | X     | --      |

## Fixes Applied

### Auto-Fixed (Safe)
| File | Line | Issue | Fix |
| ---- | ---- | ----- | --- |
| ... | ... | ... | ... |

### Interactively Fixed
| File | Line | Issue | Fix |
| ---- | ---- | ----- | --- |
| ... | ... | ... | ... |

### Pending Fixes (Not Applied)
| File | Line | Issue | Reason |
| ---- | ---- | ----- | ------ |
| ... | ... | ... | User skipped / Requires manual review |

## Critical Issues (Remaining)

| Severity | File                    | Issue       | Recommendation |
| -------- | ----------------------- | ----------- | -------------- |
| [CRIT]   | documentation/file.md:L | Description | Concrete fix   |

## All Findings by Phase

### Phase 1: Markdown Lint
[Findings...]

### Phase 2: Internal Links
[Findings...]

[...continue for all phases...]

## Files Not Checked (Incremental Mode)

[List if incremental mode]

## Next Steps

1. Fix all remaining [CRIT] issues immediately
2. Run `/docs-audit --fix-only` to apply pending interactive fixes
3. Address [LOW] style and consistency issues
```

## Resume Instructions

If this audit was interrupted:

1. The progress file `.claude/docs-audit-progress.json` contains all work done
2. Run `/docs-audit` again with same arguments to resume
3. To apply fixes to completed audit: `/docs-audit --fix-only`
4. To start fresh, delete the progress file first

## Notes

- External link checking can be slow; skip if needed
- Some checks require network access
- Progress file is gitignored and won't be committed
- For large documentation changes, consider `--full` mode
- For small changes, `--since main` is more efficient
- Safe fixes are reversible via git; interactive fixes require user judgment
- `--fix-only` protects against applying fixes to changed code
