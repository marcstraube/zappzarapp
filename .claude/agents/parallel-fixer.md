# Agent: Parallel Fixer

## Role

Coordinates parallel fixing of files across multiple languages.

## When to Use

Main Agent invokes when:

- Ad-hoc fix request (not via `/backlog`)
- Files belong to ≥2 different languages
- Files are independent (no cross-language dependencies)

## Language Detection

Scan requested files against configured standards in `.zappzarapp/standards/`:

| Pattern                       | Language | Standards |
| ----------------------------- | -------- | --------- |
| `*.php`, `src/php/**`         | PHP      | `php.md`  |
| `*.ts`, `*.js`, `src/node/**` | Node/TS  | `node.md` |
| `*.sql`, `migrations/**`      | SQL      | `sql.md`  |

**Note:** Docker and Markdown files are typically config/docs and handled by
Reviewer (C5) or Documenter (D), not parallelized as code fixes.

## Workflow

```text
Main Agent receives fix request
    ↓
Group files by language
    ↓
Validate: ≥2 languages? Independent?
    ↓
    No ──→ Handle sequentially (single agent)
    ↓
   Yes
    ↓
Spawn parallel Task agents (one per language)
    ↓
Collect results
    ↓
Run combined lint check (make check-quick)
    ↓
Report to user (Main Agent handles CHANGELOG at /commit)
```

## Sub-Agent Prompt Template

```text
Fix {language} files:

Files: {file_list}
Issue: {description}

Standards: Read .zappzarapp/standards/{language}.md

Requirements:
- Apply fixes according to standards
- Run language-specific lint after changes
- Report: files changed, issues resolved/remaining

DO NOT update: session files, BACKLOG, CHANGELOG (Main Agent handles)
```

## Spawning Example

```text
Main Agent spawns (parallel):
├── Task("Fix PHP: src/php/App/Service/FooService.php - PHPStan errors",
│        subagent_type="general-purpose")
└── Task("Fix Node: src/node/backend/services/BarService.ts - ESLint errors",
         subagent_type="general-purpose")
```

## Result Collection

After all agents complete, Main Agent collects:

```text
Parallel Fix Results
════════════════════════════════════════════════
✅ PHP (Agent B1)
   Files: src/php/App/Service/FooService.php
   Issues: 3 PHPStan errors → resolved

✅ Node/TS (Agent B2)
   Files: src/node/backend/services/BarService.ts
   Issues: 5 ESLint errors → resolved
════════════════════════════════════════════════
Summary: All fixes applied
Lint: make check-quick passed
```

## Error Handling

| Scenario               | Action                                |
| ---------------------- | ------------------------------------- |
| Agent fails            | Mark language as incomplete, continue |
| Lint fails after fixes | Show errors, ask user to review       |
| Dependency detected    | Abort parallel, switch to sequential  |

## Independence Check

Before parallelizing, verify no cross-language dependencies:

- PHP calling Node API → Sequential (Node first)
- Node importing PHP types → Sequential (PHP first)
- Both reading same config → Usually OK (read-only)
- Both modifying same file → Abort, handle manually
