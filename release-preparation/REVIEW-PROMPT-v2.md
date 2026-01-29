# Comprehensive Pre-Release Review v2.0

## Overview

This is an extremely strict pre-release review for the zappzarapp Docker boilerplate.

**Target Score**: 99/100 minimum for release approval.

## Quick Start

1. Read `review-v2/00-overview.md` for instructions, scoring, and execution requirements
2. Execute reviews sequentially (see overview for order)
3. Save each report to `review-v2/reports/review-{NN}-{name}.md`
4. Run synthesis last (`review-v2/18-synthesis.md`)

---

## Review Structure

The review is split into 18 specialized reviewer prompts:

### Core Infrastructure (22% weight)

| File | Review | Weight |
|------|--------|--------|
| [00-overview.md](review-v2/00-overview.md) | Instructions, Scoring, Execution | - |
| [01-docker-infrastructure.md](review-v2/01-docker-infrastructure.md) | Docker & Container Infrastructure | 8% |
| [02-makefile-build.md](review-v2/02-makefile-build.md) | Makefile & Build System | 7% |
| [03-cicd-pipeline.md](review-v2/03-cicd-pipeline.md) | CI/CD Pipeline | 7% |

### Security (16% weight)

| File | Review | Weight |
|------|--------|--------|
| [04-container-security.md](review-v2/04-container-security.md) | Container Security | 8% |
| [05-application-security.md](review-v2/05-application-security.md) | Application Security | 8% |

### Backend Services (12% weight)

| File | Review | Weight |
|------|--------|--------|
| [06-database-cache.md](review-v2/06-database-cache.md) | Database & Cache Services | 6% |
| [07-messaging-search.md](review-v2/07-messaging-search.md) | Messaging & Search Services | 6% |

### Application Code (12% weight)

| File | Review | Weight |
|------|--------|--------|
| [08-nodejs-frontend.md](review-v2/08-nodejs-frontend.md) | Node.js & Frontend | 6% |
| [09-php-backend.md](review-v2/09-php-backend.md) | PHP Backend | 6% |

### Quality & Testing (14% weight)

| File | Review | Weight |
|------|--------|--------|
| [10-testing.md](review-v2/10-testing.md) | Testing Infrastructure | 7% |
| [11-code-quality.md](review-v2/11-code-quality.md) | Code Quality & Tool Parity | 7% |

### Documentation & DX (13% weight)

| File | Review | Weight |
|------|--------|--------|
| [12-documentation.md](review-v2/12-documentation.md) | Documentation Completeness | 7% |
| [13-developer-experience.md](review-v2/13-developer-experience.md) | Developer Experience | 6% |

### Specialized (14% weight)

| File | Review | Weight |
|------|--------|--------|
| [14-ide-integration.md](review-v2/14-ide-integration.md) | IDE & Tool Integration | 5% |
| [15-nginx-assets.md](review-v2/15-nginx-assets.md) | Nginx & Static Assets | 5% |
| [16-kubernetes.md](review-v2/16-kubernetes.md) | Kubernetes Configuration | 5% |
| [17-claude-ai.md](review-v2/17-claude-ai.md) | Claude/AI Integration | 4% |

### Synthesis

| File | Review | Weight |
|------|--------|--------|
| [18-synthesis.md](review-v2/18-synthesis.md) | Integration & Final Synthesis | - |

---

## Key Principles

### Zero-Configuration
- `make setup && make up` must work without ANY manual steps
- Document ANY required intervention

### Security-by-Design-and-Default
- No insecure defaults anywhere
- All services require authentication
- TLS everywhere

### Documentation Completeness
- Every make target documented
- Every example reproducible 1:1
- Every path verified

### Tool Parity
- IDE settings match linter configs
- All tools agree on style
- Docker-based tool execution

---

## Reports Location

All reports are saved to:

```
release-preparation/review-v2/reports/
├── review-01-docker-infrastructure.md
├── review-02-makefile-build.md
├── ...
├── review-17-claude-ai.md
└── review-18-synthesis.md
```

---

## Execution Order

### Sequential (make commands)
1. 01 → 02 → 03 → 06 → 07 → 08 → 09 → 10 → 13 → 15

### Parallel (code analysis only)
- 04, 05, 11, 12, 14, 16, 17

### Last
- 18 (Synthesis - after all others complete)

---

## Version History

- **v2.0** (2026-01-24): Complete rewrite with 17 reviewers, documentation completeness, tool parity, report storage
- **v1.0** (2025-01-24): Original 7-reviewer version (see PROMPT.md)
