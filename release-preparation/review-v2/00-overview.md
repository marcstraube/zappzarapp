# Comprehensive Pre-Release Review v2.0

## Critical Review Guidelines

**THIS IS AN EXTREMELY STRICT REVIEW.** Every reviewer must:

- Find EVERY issue, no matter how small
- Verify EVERY claim in documentation
- Test EVERY path and command
- Question EVERY default value
- Challenge EVERY architectural decision
- Report EVERY inconsistency

**Target Score: 99/100 minimum for release approval.**

The project claims: "Zero-Configuration", "Security-by-Design-and-Default", "Clean Code"

These claims MUST be verified. If ANY claim is false or partially true, it's a finding.

---

## Project Context

- **Type**: Docker-based web development boilerplate
- **Services**: elasticsearch, mailpit, mariadb, meilisearch, mercure, nginx, node,
  php, postgres, rabbitmq, redis, seaweedfs (12 runtime services)
- **Tech Stack**: PHP 8.4 backend, Node.js 24 frontend, Alpine Linux containers
- **Key Claims**:
  - Zero-configuration setup (`make setup && make up` must work)
  - Security by design and default (no insecure defaults anywhere)
  - Clean Code (consistent patterns, no tech debt)
  - Full linter/test coverage
  - IDE integration (PhpStorm primary, VSCode secondary)
- **Target Audience**: Beginners (just works) to Experts (don't reinvent the wheel)
- **Project Size**: ~850-900 files
- **Goal**: Identify EVERY issue before 1.0 release

---

## Exclusions

The following paths are NOT part of the review (work-in-progress/meta files):

```text
release-preparation/
.zappzarapp/ai/backlog/
.zappzarapp/ai/BACKLOG.md
```

---

## Report Storage

**MANDATORY**: Each reviewer MUST save their report to:

```text
release-preparation/review-v2/reports/
```

### File Naming Convention

```text
review-{NN}-{reviewer-name}.md
```

Examples:
- `review-01-docker-infrastructure.md`
- `review-02-makefile-build.md`
- `review-18-synthesis.md`

### Report Template

Each report must follow this structure:

```markdown
# Review {NN}: {Reviewer Name}

**Reviewer**: Claude AI
**Date**: {YYYY-MM-DD}
**Prompt Version**: v2.0

## Score: {X}/100

## Executive Summary
[2-3 sentences summarizing findings]

## Critical Issues (Score Impact: -10 each)
[List with file:line references]

## High Issues (Score Impact: -5 each)
[List with file:line references]

## Medium Issues (Score Impact: -2 each)
[List with file:line references]

## Low Issues (Score Impact: -1 each)
[List with file:line references]

## Strengths
[What's done exceptionally well]

## Verification Results
[Output of executed commands]

## Recommendations
[Prioritized list of fixes]
```

---

## Execution Requirements

### Sequential Execution (MANDATORY)

**ALL reviewers that execute `make` commands MUST run sequentially.**

Docker operations are resource-intensive and can interfere with each other.
Only reviewers doing pure code analysis (no command execution) may run in parallel.

Sequential reviewers (in order):
1. Review 01 (Docker builds)
2. Review 02 (Makefile - executes targets)
3. Review 03 (CI/CD - runs tests)
4. Review 06 (Database - starts services)
5. Review 07 (Messaging/Search - starts optional services)
6. Review 08 (Node.js - builds/runs)
7. Review 09 (PHP - runs linters)
8. Review 10 (Testing - executes all tests)
9. Review 13 (DX - runs setup)
10. Review 15 (Nginx - tests configuration)

Parallel-safe reviewers (code analysis only):
- Review 04 (Container Security - file analysis)
- Review 05 (Application Security - code analysis)
- Review 11 (Code Quality - config analysis)
- Review 12 (Documentation - content verification)
- Review 14 (IDE - config analysis)
- Review 16 (Kubernetes - manifest analysis)
- Review 17 (Claude/AI - config analysis)

### Required Commands

Before starting reviews, execute a full factory reset:

```bash
# Full factory reset (removes EVERYTHING)
make reset-full

# Fresh setup (this is the zero-conf test!)
make setup
make up

# Verify health
make status
```

**IMPORTANT**: Always use `make up` to start containers, never `docker compose up` directly.

Document ANY manual intervention required. Zero-conf means ZERO manual steps.

### Cross-Platform Requirements

The boilerplate targets cross-platform compatibility:

| Priority | Platform | Requirement |
|----------|----------|-------------|
| 1 (Primary) | Linux | Must work flawlessly |
| 2 | macOS | Must work flawlessly |
| 3 | WSL2 | Must work with documented setup |
| 4 | Windows native | Document limitations, provide workarounds |

**UNIX compatibility has priority** (99.99% of production environments).

For Windows-specific issues:
- Identify the issue clearly
- Explain why it fails on Windows
- Propose a fix or workaround
- Document in WINDOWS.md if not fixable

### CI/CD Platform Requirements

Workflows must work on:

- **GitHub Actions** (primary)
- **GitLab CI** (secondary - via compatibility)

Verify:
- No GitHub-specific features without GitLab alternative
- Reusable workflow patterns
- Secrets handling works on both platforms

---

## Scoring System

Each reviewer assigns points (0-100) in their domain. Final score is weighted average.

| Rating | Score Range | Meaning |
|--------|-------------|---------|
| Perfect | 95-100 | Production-ready, exemplary |
| Excellent | 85-94 | Minor issues only |
| Good | 70-84 | Some issues need attention |
| Acceptable | 50-69 | Significant issues |
| Poor | 25-49 | Major rework needed |
| Failing | 0-24 | Fundamental problems |

**Release threshold: Minimum 95 average, no individual score below 85.**

### Score Calculation

```
Final Score = Weighted Average of all review scores

Weights:
- 01 Docker Infrastructure: 8%
- 02 Makefile: 7%
- 03 CI/CD: 7%
- 04 Container Security: 8%
- 05 Application Security: 8%
- 06 Database/Cache: 6%
- 07 Messaging/Search: 6%
- 08 Node.js: 6%
- 09 PHP: 6%
- 10 Testing: 7%
- 11 Code Quality: 7%
- 12 Documentation: 7%
- 13 Developer Experience: 6%
- 14 IDE Integration: 5%
- 15 Nginx: 5%
- 16 Kubernetes: 5%
- 17 Claude/AI: 4%

Total: 100%
```

### Release Decision Matrix

| Score | Decision |
|-------|----------|
| 99-100 | Ship immediately |
| 95-98 | Ship after minor fixes |
| 90-94 | Ship after addressing high-priority issues |
| 85-89 | Delay release, address issues |
| <85 | Major rework required |

---

## Review Files

The reviews are split into individual files:

| File | Review | Weight |
|------|--------|--------|
| `01-docker-infrastructure.md` | Docker & Container Infrastructure | 8% |
| `02-makefile-build.md` | Makefile & Build System | 7% |
| `03-cicd-pipeline.md` | CI/CD Pipeline | 7% |
| `04-container-security.md` | Container Security | 8% |
| `05-application-security.md` | Application Security | 8% |
| `06-database-cache.md` | Database & Cache Services | 6% |
| `07-messaging-search.md` | Messaging & Search Services | 6% |
| `08-nodejs-frontend.md` | Node.js & Frontend | 6% |
| `09-php-backend.md` | PHP Backend | 6% |
| `10-testing.md` | Testing Infrastructure | 7% |
| `11-code-quality.md` | Code Quality & Tool Parity | 7% |
| `12-documentation.md` | Documentation Completeness | 7% |
| `13-developer-experience.md` | Developer Experience | 6% |
| `14-ide-integration.md` | IDE & Tool Integration | 5% |
| `15-nginx-assets.md` | Nginx & Static Assets | 5% |
| `16-kubernetes.md` | Kubernetes Configuration | 5% |
| `17-claude-ai.md` | Claude/AI Integration | 4% |
| `18-synthesis.md` | Integration & Synthesis | - |

---

## Execution Instructions

1. **Read this overview first** - understand scoring and requirements
2. **Run `make reset-full`** - start from clean slate
3. **Execute reviews sequentially** for make-executing reviewers
4. **Save each report** to `reports/review-{NN}-{name}.md`
5. **Be EXTREMELY strict** - target is 99/100
6. **Document everything** - findings must be reproducible
7. **Provide specific fixes** - not just "this is wrong"
8. **Consider all audiences** - beginner to expert
9. **Verify security-by-default** - no insecure defaults anywhere
10. **Check tool parity** - all tools must agree
11. **Run synthesis last** - after all 17 reviews are complete

**Remember: The goal is a flawless 1.0 release. Find EVERY issue.**
