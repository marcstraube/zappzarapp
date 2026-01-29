# Review 03: CI/CD Pipeline

**Role**: CI/CD and DevOps Pipeline Expert

**Weight**: 7% of final score

**Report Location**: `reports/review-03-cicd-pipeline.md`

---

## Verification Commands

```bash
# List all workflows
ls -la .github/workflows/

# Check workflow syntax
for f in .github/workflows/*.yml; do
  echo "=== $f ==="
  python3 -c "import yaml; yaml.safe_load(open('$f'))" && echo "Valid YAML"
done

# Count services in security scan
grep -A 100 "matrix:" .github/workflows/security-scan.yml | grep "name:" | wc -l
```

---

## Analysis Checklist

### A. Workflow Completeness

- [ ] CI workflow exists and covers all quality checks
- [ ] Security scan workflow covers ALL 12 services
- [ ] All workflows have proper triggers (push, PR, schedule)
- [ ] Manual dispatch available where appropriate
- [ ] Caching implemented for dependencies
- [ ] Artifacts uploaded appropriately
- [ ] Timeout limits set

### B. Job Coverage

Verify CI runs:

- [ ] PHP linting (PHPStan, PHPMD, PHP-CS-Fixer)
- [ ] Node linting (ESLint, Prettier)
- [ ] Shell linting (ShellCheck)
- [ ] Docker linting (Hadolint)
- [ ] SQL linting (sqlfluff)
- [ ] Markdown linting (markdownlint)
- [ ] PHP unit tests
- [ ] Node unit tests
- [ ] GOSS container tests
- [ ] BATS integration tests
- [ ] Security scans (Trivy, dependency audit)

### C. BATS Integration Tests

**Special focus (known problem area):**

- [ ] Timeout handling for long-running tests
- [ ] Proper cleanup on failure
- [ ] Service dependency management
- [ ] Parallel execution considerations
- [ ] Retry logic for flaky tests
- [ ] Output capturing and reporting
- [ ] **File availability checks** (test files exist before operations)
- [ ] **File cleanup verification** (temp files removed after tests)
- [ ] No hardcoded paths that break on different environments
- [ ] Graceful handling of missing optional services

### D. Platform Compatibility

Workflows must work on both:

- [ ] **GitHub Actions**: Primary platform, full feature support
- [ ] **GitLab CI**: Secondary platform, compatible patterns

Check for:
- [ ] No GitHub-only features without alternatives
- [ ] Secrets handling works on both platforms
- [ ] Artifact upload/download patterns portable
- [ ] Matrix strategies compatible
- [ ] Reusable workflow patterns documented

### E. Efficiency

- [ ] Jobs parallelized where possible
- [ ] Unnecessary rebuilds avoided
- [ ] Cache hit rates optimized
- [ ] Total pipeline time reasonable

### F. Security

- [ ] No secrets in logs
- [ ] Minimal permissions (contents: read)
- [ ] Dependency pinning (@vX not @main)
- [ ] SARIF upload for security findings

---

## Output Format

See `00-overview.md` for standard report format.
