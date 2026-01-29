# Review 11: Code Quality & Tool Parity

**Role**: Code Quality and Static Analysis Expert

**Weight**: 7% of final score

**Report Location**: `reports/review-11-code-quality.md`

---

## Verification Commands

```bash
# Run all linters
make check

# Compare tool configs
cat phpstan.neon
cat eslint.config.js
cat .php-cs-fixer.dist.php
```

---

## Analysis Checklist

### A. PHP Quality Tools

- [ ] PHPStan level 8 (strictest)
- [ ] PHPMD rules comprehensive
- [ ] PHP-CS-Fixer rules match project style
- [ ] All tools agree (no conflicting rules)

### B. Node Quality Tools

- [ ] ESLint with TypeScript support
- [ ] Prettier configured
- [ ] ESLint + Prettier integration (no conflicts)
- [ ] Strict rules enabled

### C. Other Linters

- [ ] ShellCheck for shell scripts
- [ ] Hadolint for Dockerfiles
- [ ] sqlfluff for SQL
- [ ] markdownlint for documentation

### D. Tool Parity

**CRITICAL**: All tools must agree on style:

- [ ] PHP: PHPStan + PHPMD + CS-Fixer aligned
- [ ] Node: ESLint + Prettier aligned
- [ ] IDE: PhpStorm settings match tool configs
- [ ] IDE: VSCode settings match tool configs
- [ ] Pre-commit: Same rules as CI

### E. No False Positives

- [ ] No rules that trigger on valid code
- [ ] Suppressions are justified with comments
- [ ] No blanket ignores of important rules

---

## Output Format

See `00-overview.md` for standard report format.
