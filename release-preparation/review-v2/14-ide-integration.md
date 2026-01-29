# Review 14: IDE & Tool Integration

**Role**: IDE Integration and Developer Tooling Expert

**Weight**: 5% of final score

**Report Location**: `reports/review-14-ide-integration.md`

---

## Verification Commands

```bash
# List IDE configs
ls -la .idea/
ls -la .vscode/

# Compare settings
cat .idea/inspectionProfiles/Project_Default.xml
cat .vscode/settings.json
```

---

## Analysis Checklist

### A. PhpStorm Configuration

**CRITICAL**: IDE must use Docker containers for linters/tools, not local installations.

- [ ] Inspection profile configured
- [ ] Code style matches PHP-CS-Fixer
- [ ] PHPStan plugin configured
- [ ] **PHP interpreter uses Docker container** (not local PHP)
- [ ] **PHPStan runs via Docker** (Settings > PHP > Quality Tools)
- [ ] **PHP-CS-Fixer runs via Docker**
- [ ] **ESLint runs via Docker/node container**
- [ ] Docker integration configured
- [ ] Database connections configured (using container ports)
- [ ] Run configurations present
- [ ] Xdebug configuration present (remote debugging to container)
- [ ] Scopes defined (Tests, etc.)
- [ ] File watchers use container tools where applicable

### B. VSCode Configuration

- [ ] settings.json mirrors PhpStorm where applicable
- [ ] Recommended extensions in extensions.json
- [ ] Launch configurations for debugging
- [ ] Tasks defined for common operations
- [ ] Workspace settings appropriate
- [ ] **Remote Containers / Dev Containers support** (if applicable)
- [ ] ESLint configured to use container Node
- [ ] PHP tools configured for container execution

### C. Parity Matrix

| Feature | PhpStorm | VSCode | Matched |
|---------|----------|--------|---------|

- [ ] PHP inspection level
- [ ] TypeScript strictness
- [ ] Code formatting rules
- [ ] Linter integration (both use Docker)
- [ ] Debugger setup
- [ ] Test runner
- [ ] Git integration
- [ ] Docker integration
- [ ] Database tools

### D. EditorConfig

- [ ] .editorconfig present
- [ ] Indent style consistent
- [ ] Line endings configured
- [ ] Charset specified
- [ ] Matches IDE settings and linter configs

### E. Tool Configuration Files Used by IDE

Verify IDE respects these project configs:

- [ ] `.php-cs-fixer.dist.php` used by PhpStorm
- [ ] `phpstan.neon` used by PHPStan plugin
- [ ] `eslint.config.js` used by ESLint integration
- [ ] `prettier.config.js` used by Prettier
- [ ] `tsconfig.json` used by TypeScript service

---

## Output Format

See `00-overview.md` for standard report format.
