---
description: Documentation audit with validation of examples, links, and references
context: fork
allowed-tools: Read, Write, Grep, Glob, Bash(make:*), Bash(find:*), Bash(grep:*),
  Bash(git:*), Bash(ls:*), Bash(cat:*), Bash(head:*), Bash(test:*)
argument-hint: [--full | --since <commit>]
---

# Documentation Audit

Comprehensive documentation audit with validation of examples, links, and references.

## Modes

This audit supports two modes via `$ARGUMENTS`:

- **Full audit** (`--full` or no argument): Check all documentation files
- **Incremental audit** (`--since <commit>`): Only check files changed since commit

Examples:

```bash
/docs-audit --full
/docs-audit --since main
/docs-audit --since abc1234
/docs-audit --since HEAD~5
```

## Progress File

All findings are saved to `.claude/docs-audit-progress.json` for resumability.
This file persists across context resets and allows interruption/resume.

## Initialization

1. Parse `$ARGUMENTS` to determine mode:
   - If empty or `--full`: Set `mode = "full"`, `scope = "all files"`
   - If `--since <ref>`: Set `mode = "incremental"`, get changed files via
     `git diff --name-only <ref> -- documentation/`

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
  "started_at": "<ISO timestamp>",
  "last_updated": "<ISO timestamp>",
  "git_commit": "<commit hash from git rev-parse HEAD>",
  "git_branch": "<branch name from git branch --show-current>",
  "status": "in_progress",
  "files_to_check": ["documentation/file1.md", "documentation/file2.md"],
  "phases": {
    "markdown_lint": { "status": "pending", "findings": [] },
    "internal_links": { "status": "pending", "findings": [] },
    "external_links": { "status": "pending", "findings": [] },
    "file_references": { "status": "pending", "findings": [] },
    "code_examples": { "status": "pending", "findings": [] },
    "make_targets": { "status": "pending", "findings": [] },
    "env_variables": { "status": "pending", "findings": [] },
    "config_snippets": { "status": "pending", "findings": [] },
    "structure_consistency": { "status": "pending", "findings": [] }
  },
  "summary": null
}
```

## Execution Phases

Execute phases sequentially. After each phase, update the progress file immediately.
Skip phases that are already marked as "complete".

For incremental mode: Only check files in `files_to_check` list.

### Phase 1: Markdown Lint

Run markdownlint on documentation files:

```bash
pnpm exec markdownlint-cli2 'documentation/**/*.md'
```

Check for:

- MD001-MD050 rule violations
- Inconsistent heading levels
- Missing language specifiers on code blocks
- Trailing whitespace, line length issues

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

Severity: Broken link = [CRIT], Orphaned file = [MED]

Update progress file: set `phases.internal_links.status = "complete"`.

### Phase 3: External Links

Validate external URLs (optional - can be slow):

- HTTP/HTTPS links reachable?
- No 404 errors?
- No redirect loops?

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

Severity: Invalid syntax = [CRIT], Outdated example = [MED]

Update progress file: set `phases.code_examples.status = "complete"`.

### Phase 6: Make Targets

Find all `make <target>` references and validate:

```bash
grep -ohE 'make [a-z][-a-z0-9]*' documentation/**/*.md | sort -u
```

For each target: Does it exist in Makefile?

Cross-reference with `documentation/development/MAKEFILE-REFERENCE.md`:

- All Makefile targets documented?
- Any documented targets that no longer exist?

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

Severity: Missing from sidebar = [MED], Inconsistent structure = [LOW]

Update progress file: set `phases.structure_consistency.status = "complete"`.

## Synthesis & Report

After all phases complete:

1. Read all findings from progress file
2. Count by severity: [CRIT], [MED], [LOW]
3. Generate summary table
4. Update progress file: set `status = "complete"` and populate `summary`
5. Write final report to `.claude/reports/docs-audit-{date}-{commit}.md`

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
**Files checked**: X of Y total
**Duration**: <started_at> to <last_updated>

## Summary

| Category              | [CRIT] | [MED] | [LOW] | Status  |
| --------------------- | ------ | ----- | ----- | ------- |
| Markdown Lint         | X      | X     | X     | OK/FAIL |
| Internal Links        | X      | X     | X     | OK/FAIL |
| External Links        | X      | X     | X     | OK/FAIL |
| File References       | X      | X     | X     | OK/FAIL |
| Code Examples         | X      | X     | X     | OK/FAIL |
| Make Targets          | X      | X     | X     | OK/FAIL |
| Environment Variables | X      | X     | X     | OK/FAIL |
| Config Snippets       | X      | X     | X     | OK/FAIL |
| Structure/Consistency | X      | X     | X     | OK/FAIL |
| **Total**             | X      | X     | X     | --      |

## Critical Issues (Fix Immediately)

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

1. Fix all [CRIT] broken links and invalid references immediately
2. Update [MED] outdated examples and missing documentation
3. Address [LOW] style and consistency issues
```

## Resume Instructions

If this audit was interrupted:

1. The progress file `.claude/docs-audit-progress.json` contains all work done
2. Run `/docs-audit` again with same arguments to resume
3. To start fresh, delete the progress file first

## Notes

- External link checking can be slow; skip if needed
- Some checks require network access
- Progress file is gitignored and won't be committed
- For large documentation changes, consider `--full` mode
- For small changes, `--since main` is more efficient
