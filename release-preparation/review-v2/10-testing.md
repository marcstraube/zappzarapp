# Review 10: Testing Infrastructure

**Role**: Testing and Quality Assurance Expert

**Weight**: 7% of final score

**Report Location**: `reports/review-10-testing.md`

---

## Verification Commands

```bash
# Run all tests
make test

# Run specific test suites
make test-php
make test-node
make goss-test
make bats-test

# Check coverage
make test-coverage-php
make test-coverage-node
```

---

## Analysis Checklist

### A. PHP Testing (PHPUnit)

- [ ] phpunit.xml.dist configured correctly
- [ ] Test directory structure logical
- [ ] Unit tests present
- [ ] Integration tests present
- [ ] Fixtures/factories for test data
- [ ] Mocking strategy consistent
- [ ] Coverage thresholds set
- [ ] CI integration working

### B. Node Testing (Vitest)

- [ ] vitest.config.ts configured
- [ ] Test file naming consistent
- [ ] Unit tests present
- [ ] Mocking utilities used
- [ ] TypeScript support
- [ ] Coverage configured

### C. Container Testing (GOSS)

For EACH service:

- [ ] goss.yaml exists
- [ ] Process checks present
- [ ] Port checks present
- [ ] File checks present
- [ ] Command checks where relevant
- [ ] HTTP checks with proper auth

### D. Integration Testing (BATS)

- [ ] Test organization logical
- [ ] Setup/teardown proper
- [ ] Timeout handling
- [ ] Dependency management
- [ ] CI stability (no flaky tests)
- [ ] Error reporting clear
- [ ] File cleanup after tests

### E. Test Quality

- [ ] Tests actually test behavior (not implementation)
- [ ] Edge cases covered
- [ ] Error paths tested
- [ ] No skipped tests without reason
- [ ] Test names descriptive

---

## Output Format

See `00-overview.md` for standard report format.
