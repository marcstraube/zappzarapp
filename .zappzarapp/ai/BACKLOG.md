# Project Backlog Index

Compact task index. Details in `backlog/<slug>.md` files.

## Task Size Guide

| Size   | Time        | Files | Context            |
| ------ | ----------- | ----- | ------------------ |
| Small  | <30 min     | 1-3   | Same context OK    |
| Medium | 30 min - 2h | 3-10  | Flexible           |
| Large  | >2h         | Many  | Fresh context rec. |

---

## High Priority

| Slug                     | Title                     | Size  | Status  |
| ------------------------ | ------------------------- | ----- | ------- |
| node-backend-eisdir      | Node Backend EISDIR Error | Small | Open    |
| v1.0-release-preparation | v1.0 Release Preparation  | Large | Planned |
| bats-setup-reset-tests   | BATS Setup/Reset Tests    | Small | Planned |

## Medium Priority

### Quick Wins

| Slug                       | Title                      | Size  | Status |
| -------------------------- | -------------------------- | ----- | ------ |
| ide-tasks-reduction        | IDE Tasks Reduction        | Small | Open   |
| hadolint-sc2086-fix        | Hadolint SC2086 Fix        | Small | Open   |
| shell-script-linting       | Shell Script Linting       | Small | Open   |
| readme-documentation-links | README Documentation Links | Small | Open   |

### Code Quality

| Slug                  | Title                      | Size   | Status  |
| --------------------- | -------------------------- | ------ | ------- |
| php-suppresswarnings  | PHP SuppressWarnings Audit | Medium | Planned |
| mutation-testing      | Mutation Testing           | Medium | Planned |
| markdown-code-linting | Markdown Code Linting      | Medium | Planned |

### Security

| Slug                   | Title                    | Size   | Status  |
| ---------------------- | ------------------------ | ------ | ------- |
| eslint-security-plugin | ESLint Security Plugin   | Small  | Planned |
| semgrep-integration    | Semgrep SAST Integration | Medium | Planned |
| psalm-taint-analysis   | Psalm Taint Analysis     | Medium | Planned |
| unified-security-scan  | Unified Security Target  | Small  | Planned |

### Infrastructure

| Slug                         | Title                     | Size   | Status  |
| ---------------------------- | ------------------------- | ------ | ------- |
| gitlab-mirror-setup          | GitLab Mirror Setup       | Small  | Planned |
| email-service-implementation | Email Service (Mailpit)   | Medium | Open    |
| boilerplate-update           | Boilerplate Update Mech.  | Medium | Planned |
| tls-certificate-arch         | TLS Certificate Arch.     | Medium | Planned |
| docker-compose-validation    | Docker Compose Validation | Small  | Planned |
| helm-chart-linting           | Helm Chart Linting        | Small  | Planned |
| docker-labels-standard       | Docker Labels Standard    | Small  | Planned |

### UI/UX

| Slug                       | Title                      | Size   | Status  |
| -------------------------- | -------------------------- | ------ | ------- |
| page-design-customization  | Page Design Customization  | Medium | Planned |
| frontend-testing-framework | Frontend Testing Framework | Large  | Planned |

### Future Ideas

| Slug                  | Title                  | Size       | Status  |
| --------------------- | ---------------------- | ---------- | ------- |
| debug-command         | Debug Command          | Medium     | Planned |
| devdashboard-features | DevDashboard Features  | Brainstorm | Idea    |
| livelogs-websocket    | LiveLogs via WebSocket | Large      | Planned |

## Low Priority

| Slug                       | Title                      | Size   | Status  |
| -------------------------- | -------------------------- | ------ | ------- |
| refactor-databaseconfig    | Refactor DatabaseConfig    | Small  | Planned |
| devdashboard-code-examples | DevDashboard Code Examples | Medium | Planned |
| missing-api-docs-warnings  | Missing API Docs Warnings  | Small  | Planned |
| make-reset-ide-cleanup     | Make Reset IDE Cleanup     | Small  | Planned |
| frontend-quality-research  | Frontend Quality Research  | Small  | Planned |
| make-help-autocompletion   | Make Help Autocompletion   | Small  | Planned |
| database-schema-diff       | Database Schema Diff       | Large  | Planned |
| waf-integration            | WAF Integration            | Large  | Planned |
| license-compliance-check   | License Compliance Check   | Small  | Planned |

---

## Completed

| Slug                         | Title                        | Completed  |
| ---------------------------- | ---------------------------- | ---------- |
| makefile-target-testing      | BATS + Goss Make Testing     | 2026-01-22 |
| make-integrations-setup      | Make Setup: db-migrations    | 2026-01-22 |
| make-setup-api-docs          | Make Setup: API Docs         | 2026-01-22 |
| phpstorm-vscode-sync         | PhpStorm/VSCode Sync         | 2026-01-22 |
| service-integration-examples | Service Integration Examples | 2026-01-21 |

---

## File Format

Task detail files follow this structure:

```markdown
# <Title>

**Status:** Open | In Progress | Blocked | Complete **Size:** Small | Medium |
Large **Scope:** feature | fix | critical | refactor | docs | chore **Created:**
YYYY-MM-DD **Planning:** Required | Not required

## Context

Why this task exists.

## Goal

What success looks like.

## Implementation

1. Step one
2. Step two

## Files

- `path/to/file.ext`

## Notes

Additional context, references, decisions.
```
