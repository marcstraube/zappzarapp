# 23: Optimization of IDE Integration for Makefile Targets

## Goal

Analyze existing Makefile targets, reduce redundancy, and determine which
targets belong in the Makefile vs. IDE-only actions (PHPStorm/VSCode).

## Analysis Tasks

### 1. Audit Current Targets

- Count total number of targets
- Categorize by usage frequency (daily, weekly, rarely, never)
- Identify redundant or overlapping targets
- Find targets that are only useful in IDE context

### 2. Targets to Consider Removing/Moving to IDE-Only

**Candidates for IDE-only:**
- Database web tool links (Adminer, phpMyAdmin) → IDE has built-in DB tools
- Individual service URLs that are rarely accessed directly
- Highly specialized debugging commands

**Questions to answer:**
- Which targets are only useful during interactive development?
- Which targets are essential for CI/CD and must stay in Makefile?
- Which targets are duplicated by IDE functionality?

### 3. Possible Targets to Add

**Browser shortcuts:**
- `make open-homepage` → Open project homepage in default browser
- `make open-dashboard` → Open Dev Dashboard in browser
- `make open-docs` → Open generated documentation in browser
- `make open-coverage` → Open coverage report in browser

**Implementation example:**
```makefile
open-homepage: ## Open homepage in browser
	@$(OPEN_CMD) "https://$(PROJECT_DOMAIN)"

open-dashboard: ## Open Dev Dashboard in browser
	@$(OPEN_CMD) "https://$(PROJECT_DOMAIN)/dev-dashboard"
```

Where `OPEN_CMD` is `xdg-open` (Linux), `open` (macOS), or `start` (Windows).

### 4. IDE Integration Review

**PHPStorm (.idea/runConfigurations/):**
- Which make targets should have run configurations?
- Keyboard shortcut recommendations?

**VSCode (.vscode/tasks.json):**
- Which tasks should be in tasks.json?
- Which should be in launch.json for debugging?

## Evaluation Criteria

| Keep in Makefile | Move to IDE-only |
|------------------|------------------|
| CI/CD required | Interactive-only |
| Cross-platform needed | IDE-specific features |
| Scriptable/composable | Visual feedback needed |
| Documentation value | Rarely used |

## Targets to Review

Categories to audit:
- `##@ Docker` - Essential, keep
- `##@ Development` - Review each
- `##@ Quality Assurance` - Keep for CI
- `##@ Database` - Keep commands, remove web UI links?
- `##@ Documentation` - Keep generation, add open-in-browser
- `##@ Utilities` - Review for relevance

## Expected Outcomes

1. **Reduced Makefile** - Fewer, more focused targets
2. **Better IDE integration** - Frequently-used actions as IDE shortcuts
3. **New convenience targets** - Browser openers for common URLs
4. **Documentation** - Clear guidance on when to use make vs. IDE

## Priority

Medium - Developer experience improvement.

## Notes

- Database web tools (Adminer, etc.) not needed as targets since JetBrains and
  VSCode have excellent built-in database tooling
- Focus on targets that provide value beyond what IDEs already offer
- Consider `make help` output length - too many targets reduces discoverability
