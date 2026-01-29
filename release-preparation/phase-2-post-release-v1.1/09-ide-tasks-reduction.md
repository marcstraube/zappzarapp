# IDE Tasks Reduction (PhpStorm & VSCode)

**Status:** Open **Size:** Small **Scope:** chore **Created:** 2026-01-21
**Planning:** Not required

## Context

~140 VSCode tasks and ~130 PhpStorm runConfigurations are overwhelming.

## Goal

Reduce to ~28 core tasks, identical in both IDEs (parity principle).

## Tasks to KEEP

| Category      | Tasks                                                           |
| ------------- | --------------------------------------------------------------- |
| Docker        | up, down, restart, build, status, check-health                  |
| Tests         | test, test-php, test-node, test-coverage                        |
| Quality       | check, analyse, cs-fix, lint-node-fix, prettier-fix, type-check |
| Node Dev      | node-dev, node-dev-full, node-build                             |
| Logs          | logs, logs-php, logs-node, logs-nginx                           |
| Shells        | shell-php, shell-node                                           |
| Composer/pnpm | composer-update, pnpm-install, pnpm-update                      |

## Tasks to REMOVE

| Category                                         | Reason                                      |
| ------------------------------------------------ | ------------------------------------------- |
| Setup (init, setup, hooks-install)               | One-time at project start                   |
| Backup-\* (12 tasks)                             | Complex ops, terminal better                |
| SSL-\* (7 tasks)                                 | Rare, sensitive                             |
| Security-\* (9 tasks)                            | CI/CD or terminal                           |
| Docs-\*                                          | Rarely manual                               |
| Service-specific Logs                            | Too granular (mariadb, elasticsearch, etc.) |
| Service-specific Shells                          | Too granular (mercure, meilisearch, etc.)   |
| Dangerous Ops (fresh, clean, prune, redis-flush) | Better conscious in terminal                |
| Renovate                                         | CI/CD                                       |
| Database CLI/Dump/Restore                        | Terminal better                             |
| Node PM2-_, Frontend-_, Server-\*                | Too specific                                |
| Redis CLI/Monitor                                | Terminal better                             |

## Files

- `.vscode/tasks.json` (reduce from ~95 to ~28 tasks)
- `.idea/runConfigurations/*.xml` (delete ~100 files, keep ~28)
