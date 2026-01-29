# DevToolbar ESLint Cleanup

**Status:** Pending
**Priority:** Medium
**Estimated Effort:** 1-2h

## Context

After the DevToolbar code review and cleanup, there are still ESLint violations remaining. The coder agent that performed the initial fixes did not complete all linting tasks.

## Current State

- Debug logger implemented (conditional logging based on DEV flag)
- Bug fixes applied (QuotaExceededError, cookie validation, parseTimeAgo)
- Legacy code removed (HistoryCollector, RequestStore, migration code)
- Reflection replaced with public API
- 80 UI tests written and passing

**However:** ESLint still reports violations in DevToolbar TypeScript files.

## Tasks

### 1. Run ESLint Fix on DevToolbar

```bash
cd src/node
npx eslint backend/DevToolbar/**/*.ts --fix
```

### 2. Manually Fix Remaining Issues

Check for issues that can't be auto-fixed:

- `@typescript-eslint/no-explicit-any` violations
- Unused variables/imports
- Missing return types
- Incorrect type assertions

### 3. Verify Clean State

```bash
make lint-node ARGS="backend/DevToolbar/**/*.ts"
```

Expected: **0 errors, 0 warnings**

## Files to Check

Priority files (most likely to have issues):

1. `backend/DevToolbar/ui/DevToolbarUI.ts`
2. `backend/DevToolbar/ui/RequestSwitcher.ts`
3. `backend/DevToolbar/ui/HistoryTabManager.ts`
4. `backend/DevToolbar/ui/TabManager.ts`
5. `backend/DevToolbar/storage/StorageManager.ts`

## Acceptance Criteria

- [ ] All DevToolbar TypeScript files pass ESLint without errors
- [ ] All DevToolbar TypeScript files pass ESLint without warnings
- [ ] No `any` types remain (use proper types or `unknown`)
- [ ] No unused variables/imports
- [ ] All functions have explicit return types

## Notes

- Use `make lint-node-fix` with ARGS for faster iteration
- Some errors may require manual fixes (not auto-fixable)
- Check `tsconfig.json` for strict mode settings
