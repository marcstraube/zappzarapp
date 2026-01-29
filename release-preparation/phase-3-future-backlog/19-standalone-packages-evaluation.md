# Standalone Packages Evaluation (v2.0)

**Status:** Future
**Priority:** Low
**Complexity:** Very High
**Target Version:** 2.0+
**Estimated Effort:** 3-6 months

## Problem

By v2.0, the monorepo will have established several reusable packages (tls-config, docker-secrets, db-connection, audit-logger, possibly encryption). At this point, we need to decide:

**Stay Monorepo or Go Standalone?**

This decision impacts:
- Maintenance overhead
- Community adoption
- Development velocity
- Release flexibility
- Brand visibility

## Current State (v1.x Monorepo)

### Advantages

✅ **Low Overhead**
- Single repository, single CI/CD
- Atomic commits across packages
- Unified issue tracking
- One PR for cross-package changes

✅ **Fast Development**
- No version synchronization
- No dependency hell
- Easy to refactor across boundaries
- Quick iteration cycles

✅ **Simple Setup**
- One `git clone`
- One dependency install
- Packages always compatible
- No version matrix to manage

✅ **Boilerplate Integration**
- Packages developed alongside boilerplate
- Real-world testing built-in
- User feedback immediate
- Examples in same repo

### Disadvantages

❌ **Limited Discoverability**
- Not on Packagist/npm individually
- Harder to find via search
- Must know about boilerplate to find packages

❌ **No Independent Versions**
- Can't bump package version without boilerplate release
- Breaking changes affect all users
- No semantic versioning per package

❌ **Perceived Coupling**
- Looks like "part of boilerplate" not "standalone library"
- Users may hesitate to depend on it
- Marketing challenge

❌ **All-or-Nothing Downloads**
- Must clone entire repo
- Can't `composer require zappzarapp/tls-config` from Packagist
- Barrier to adoption in non-boilerplate projects

## Standalone Repositories Scenario

### Structure

```
GitHub Organization: zappzarapp
├── zappzarapp (main boilerplate repo)
├── php-tls-config
├── node-tls-config
├── php-docker-secrets
├── node-docker-secrets
├── php-db-connection
├── node-db-connection
├── php-audit-logger
├── node-audit-logger
└── php-encryption (if extracted)
```

### Advantages

✅ **Maximum Discoverability**
- Published on Packagist and npm
- Searchable independently
- Clear standalone identity
- SEO benefits

✅ **Independent Versioning**
- Each package follows SemVer
- Breaking changes don't affect others
- Faster iteration on individual packages
- Users pin to specific versions

✅ **Community Contributions**
- Focused repositories attract contributors
- Clear scope per repo
- Easier to maintain contribution guidelines
- Package-specific issues and discussions

✅ **Portfolio Effect**
- 10 repos > 1 repo for visibility
- Demonstrates expertise per domain
- More GitHub stars/activity
- Better for personal branding

✅ **Flexible Licensing**
- Different licenses per package if needed
- Easier to transfer ownership
- Corporate adoption easier (fewer dependencies)

### Disadvantages

❌ **Massive Maintenance Overhead**
- 10+ repositories to manage
- 10+ CI/CD pipelines
- 10+ issue trackers
- 10+ sets of GitHub settings

❌ **Synchronization Hell**
- Breaking changes across packages require coordination
- Version matrix complexity (which db-connection works with which tls-config?)
- Release choreography (must release in dependency order)
- Testing combinations exponentially harder

❌ **Slower Development**
- Cross-package changes = multiple PRs
- Can't test changes atomically
- Dependency update lag
- Risk of incompatibilities

❌ **Documentation Fragmentation**
- Must repeat common patterns across repos
- Hard to keep docs in sync
- Users must read multiple READMEs
- Integration examples scattered

❌ **Boilerplate Update Complexity**
- Must update multiple `composer.json` / `package.json`
- Version conflicts possible
- Testing all combinations
- Rollback becomes complicated

## Hybrid Approach: Monorepo with Published Subtree Splits

### Concept

**Best of both worlds:**
- Keep monorepo for development
- Auto-publish subtrees as standalone repos
- Packages available on Packagist/npm
- Maintain single source of truth

### How It Works

```
Main Repo: github.com/marcstraube/zappzarapp
├── packages/php/tls-config/       → github.com/zappzarapp/php-tls-config (auto)
├── packages/node/tls-config/      → github.com/zappzarapp/node-tls-config (auto)
└── ... (same for all packages)

On git push to main:
1. GitHub Action detects changed packages
2. Splits subtree to separate repo
3. Tags version if CHANGELOG indicates release
4. Publishes to Packagist/npm if new version
```

### Tools

**PHP:**
- `splitsh/lite` - Fast git subtree splitting
- GitHub Action: `symplify/monorepo-split-github-action`

**Node:**
- Already supported by pnpm workspaces
- `pnpm publish` with filters

### Example Workflow

```yaml
# .github/workflows/split-monorepo.yml
name: Split Monorepo
on:
  push:
    branches: [main, develop]
    tags: ['*']

jobs:
  split:
    runs-on: ubuntu-latest
    strategy:
      matrix:
        package:
          - tls-config
          - docker-secrets
          - db-connection
          - audit-logger
    steps:
      - uses: actions/checkout@v4
      - uses: symplify/monorepo-split-github-action@v2
        with:
          package_directory: 'packages/php/${{ matrix.package }}'
          split_repository_organization: 'zappzarapp'
          split_repository_name: 'php-${{ matrix.package }}'
          user_name: "github-actions[bot]"
          user_email: "github-actions[bot]@users.noreply.github.com"
```

### Advantages of Hybrid

✅ All benefits of monorepo (for maintainers)
✅ All benefits of standalone (for users)
✅ Single source of truth
✅ Automatic publishing
✅ Independent discoverability

### Disadvantages of Hybrid

❌ Complex CI/CD setup
❌ Subtree split can have edge cases
❌ Must manage dual repo structure
❌ Contributors may get confused (which repo to contribute to?)

## Decision Framework

### Stay Monorepo If:

1. **Low External Usage**: < 10 projects outside boilerplate use packages
2. **High Change Rate**: Breaking changes > 2 per year per package
3. **Limited Resources**: < 1 maintainer available for package support
4. **Tight Integration**: Packages highly coupled to boilerplate structure

### Go Standalone If:

1. **High External Usage**: 50+ projects use packages independently
2. **Stable APIs**: No breaking changes for 12+ months
3. **Multiple Maintainers**: 3+ people can support package issues
4. **Clear Value**: Packages demonstrably better than alternatives

### Go Hybrid If:

1. **Moderate External Usage**: 10-50 projects interested
2. **Stabilizing APIs**: 1-2 breaking changes per year
3. **Growing Community**: Contributors showing interest
4. **Portfolio Goals**: Want visibility but can't handle overhead

## Data to Collect Before Decision

### Usage Metrics (by v2.0)

- [ ] GitHub stars on main repo
- [ ] GitHub clones/week
- [ ] External projects using boilerplate
- [ ] npm downloads (if packages published)
- [ ] Packagist downloads (if packages published)
- [ ] Community issues/PRs about packages
- [ ] Conference talks/blog posts mentioning packages

### Community Feedback

- [ ] Survey boilerplate users: Would you use packages standalone?
- [ ] GitHub Discussions: "Standalone packages?"
- [ ] Community call: Gauge interest
- [ ] Corporate users: Adoption blockers?

### Cost-Benefit Analysis

**Standalone Costs:**
- Maintainer time: +20-40 hours/month
- CI/CD costs: +$50-100/month
- Documentation burden: +10 hours/month

**Standalone Benefits:**
- Increased adoption: +X users (estimate)
- Community contributions: +Y PRs (estimate)
- Brand visibility: +Z stars (estimate)

## Recommendation Timeline

### v1.1-1.3 (2026)
**Stay Monorepo, Collect Data**
- Establish packages in monorepo
- Gather usage metrics
- Build community
- Stabilize APIs

### v2.0 (Early 2027)
**Decision Point**
- Review collected data
- Evaluate community size
- Assess maintainer capacity
- Choose: Monorepo / Standalone / Hybrid

### Post-v2.0 (2027+)
**If Standalone/Hybrid:**
- Set up subtree splits or separate repos
- Publish to Packagist/npm
- Marketing push
- Community management ramp-up

**If Stay Monorepo:**
- Improve package documentation
- Add "Usage outside boilerplate" guides
- Consider Composer/npm metadata for discoverability
- Accept limited external adoption

## Exit Strategy

**Important:** Design v1.x packages to be **extraction-ready**
- Clean APIs with minimal boilerplate dependencies
- Comprehensive tests (no boilerplate mocking)
- Documentation written for standalone usage
- Namespace isolation (`Zappzarapp\*` not `App\*`)

This ensures we can go standalone later without rewrites.

## Success Criteria

**For v2.0 Decision:**
- [ ] 12+ months of package stability data
- [ ] Clear usage metrics (internal + external)
- [ ] Community size assessment
- [ ] Cost-benefit analysis complete
- [ ] Maintainer commitment confirmed

**If Go Standalone:**
- [ ] Subtree split automated (if hybrid)
- [ ] All packages published to Packagist/npm
- [ ] Documentation updated for standalone use
- [ ] Marketing plan executed
- [ ] Community support process established

## References

- Monorepo examples: Laravel (components), Symfony (components)
- Hybrid examples: Symplify, Yii2
- Subtree split tools: https://github.com/splitsh/lite
- Discussion: "Monorepos in Open Source" (blog post ideas)
- Current packages: 26-monorepo-packages-foundation.md
- Extraction strategy: 18-packages-extraction-strategy.md
