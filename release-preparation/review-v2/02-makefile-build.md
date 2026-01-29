# Review 02: Makefile & Build System

**Role**: Build System and Automation Expert

**Weight**: 7% of final score

**Report Location**: `reports/review-02-makefile-build.md`

---

## Verification Commands

```bash
# List all targets
make help

# Count targets with help text
grep -E "^[a-zA-Z_-]+:.*##" Makefile | wc -l

# Test safe targets
make lint-check
```

---

## Analysis Checklist

### A. Target Completeness

For EVERY target in Makefile:

- [ ] Has `## description` for help text
- [ ] Description accurately describes behavior
- [ ] Dependencies (.PHONY, prerequisites) correct
- [ ] Error handling appropriate
- [ ] Works on Linux, macOS, WSL

### B. Target Categories

Verify presence and correctness of:

- [ ] Setup targets (setup, init, secrets, certs)
- [ ] Container targets (up, down, restart, logs, status)
- [ ] Build targets (build, build-*, rebuild, fresh)
- [ ] Test targets (test, test-php, test-node, test-bats, test-goss)
- [ ] Lint targets (lint, lint-*, fix, fix-*)
- [ ] Dependency targets (composer, pnpm, *-install, *-sync)
- [ ] Documentation targets (docs, docs-*)
- [ ] Utility targets (shell-*, logs-*, exec-*)
- [ ] Node targets (node-build, node-dev, node-frontend-*)
- [ ] Reset targets (reset, reset-full, fresh, clean)

### C. Consistency

- [ ] Naming convention consistent (verb-noun, kebab-case)
- [ ] Similar operations have similar patterns
- [ ] No duplicate functionality
- [ ] Logical grouping in Makefile sections
- [ ] Category comments (##@) present

### D. Cross-Platform

- [ ] Works with bash (not bash-specific where avoidable)
- [ ] Path separators handled
- [ ] No hardcoded paths
- [ ] Environment detection works

### E. Documentation Match

- [ ] `make help` output complete
- [ ] Category headers present
- [ ] All targets in MAKEFILE-REFERENCE.md
- [ ] Examples in docs use correct target names

---

## Output Format

See `00-overview.md` for standard report format.
