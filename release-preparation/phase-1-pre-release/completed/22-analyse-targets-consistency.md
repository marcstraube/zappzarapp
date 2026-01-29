# 22: Consistent Naming for Static Analysis Targets

## Goal

Rename and restructure `make analyse` to provide consistent naming for static
analysis across PHP and Node/TypeScript.

## Current State

```
make analyse     → PHPStan only
make type-check  → TypeScript tsc --noEmit
```

## Target State

```
make analyse           → Runs both analyse-php and analyse-node
make analyse-php       → PHPStan (formerly: make analyse)
make analyse-node      → TypeScript type-check (formerly: make type-check)
make type-check        → Keep as alias for backwards compatibility (optional)
```

## Implementation

```makefile
analyse: analyse-php analyse-node  ## Run static analysis (PHP + Node)

analyse-php: ## Run PHPStan static analysis
	@echo -e "\033[0;33mRunning PHPStan...\033[0m"
	@docker compose exec php composer analyse

analyse-node: ## Run TypeScript type checking (static analysis)
	@echo -e "\033[0;33mRunning TypeScript type check...\033[0m"
	@$(DC) run --rm -T dev-tools pnpm run type-check
	@echo -e "\033[0;32mTypeScript check completed!\033[0m"
```

## Tasks

1. **Rename targets in Makefile**
   - `analyse` → `analyse-php`
   - `type-check` → `analyse-node`
   - New `analyse` as umbrella target

2. **Update `make check`**
   - Replace `analyse` with `analyse-php` (or keep using umbrella)
   - Replace `type-check` with `analyse-node`

3. **Update documentation**
   - `.zappzarapp/standards/make-targets.md`
   - Any other references to old target names

4. **Consider backwards compatibility**
   - Keep `type-check` as alias? Or clean break?

## Files to Modify

- `Makefile` - Target definitions (~line 2486, 2610)
- `.zappzarapp/standards/make-targets.md` - Documentation
- Tests referencing these targets (if any)

## Priority

Low - Consistency improvement, no functional change.

## Benefits

- Consistent naming pattern: `analyse-{php,node}`
- Mirrors existing patterns: `test-{php,node}`, `lint-{php,node}`
- Single `make analyse` command for full static analysis
