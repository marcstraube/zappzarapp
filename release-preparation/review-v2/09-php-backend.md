# Review 09: PHP Backend

**Role**: PHP Backend Development Expert

**Weight**: 6% of final score

**Report Location**: `reports/review-09-php-backend.md`

---

## Verification Commands

```bash
# Check PHP version
docker compose exec php php --version

# Check installed extensions
docker compose exec php php -m

# Run static analysis
make lint-php

# Check opcache (production)
docker compose exec php php -i | grep opcache
```

---

## Analysis Checklist

### A. PHP Configuration

- [ ] Version 8.4 with latest features
- [ ] Required extensions installed
- [ ] php.ini optimized (dev vs prod)
- [ ] OPcache configured for production
- [ ] Error reporting appropriate per environment
- [ ] Memory limits sensible

### B. Composer Setup

- [ ] PSR-4 autoloading correct
- [ ] Dev dependencies separated
- [ ] Scripts defined
- [ ] Platform requirements specified
- [ ] No abandoned packages

### C. Code Architecture

- [ ] PSR-12 coding standard
- [ ] Dependency injection used
- [ ] Service layer pattern
- [ ] Repository pattern (if applicable)
- [ ] DTOs for data transfer
- [ ] Proper exception handling

### D. PHP 8.4 Features

- [ ] Typed properties used
- [ ] Constructor promotion
- [ ] Named arguments where appropriate
- [ ] Attributes used (not annotations)
- [ ] Readonly classes/properties
- [ ] Enums for fixed values

### E. Static Analysis

- [ ] PHPStan level 8
- [ ] No ignored errors without justification
- [ ] PHPMD rules enforced
- [ ] PHP-CS-Fixer rules enforced

---

## Output Format

See `00-overview.md` for standard report format.
