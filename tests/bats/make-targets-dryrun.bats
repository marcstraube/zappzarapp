#!/usr/bin/env bats
# BATS Tests: Comprehensive Dry-Run Validation
# Tests ALL Makefile targets with --dry-run to validate syntax

load 'helpers/setup'

# =============================================================================
# AI Sync Targets
# =============================================================================

@test "make ai-commands-sync --dry-run validates" {
    run make -n ai-commands-sync
    assert_success
}

@test "make ai-rules-sync --dry-run validates" {
    run make -n ai-rules-sync
    assert_success
}

@test "make ai-sync --dry-run validates" {
    run make -n ai-sync
    assert_success
}

# =============================================================================
# PHP Quality Targets
# =============================================================================

@test "make analyse --dry-run validates" {
    run make -n analyse
    assert_success
}

@test "make cs-check --dry-run validates" {
    run make -n cs-check
    assert_success
}

@test "make cs-fix --dry-run validates" {
    run make -n cs-fix
    assert_success
}

@test "make cs-fix-all --dry-run validates" {
    run make -n cs-fix-all
    assert_success
}

@test "make phpmd --dry-run validates" {
    run make -n phpmd
    assert_success
}

@test "make rector-check --dry-run validates" {
    run make -n rector-check
    assert_success
}

@test "make rector-fix --dry-run validates" {
    run make -n rector-fix
    assert_success
}

# =============================================================================
# Node.js Quality Targets
# =============================================================================

@test "make lint-node --dry-run validates" {
    run make -n lint-node
    assert_success
}

@test "make lint-node-fix --dry-run validates" {
    run make -n lint-node-fix
    assert_success
}

@test "make lint-md --dry-run validates" {
    run make -n lint-md
    assert_success
}

@test "make lint-md-fix --dry-run validates" {
    run make -n lint-md-fix
    assert_success
}

@test "make lint-sql --dry-run validates" {
    run make -n lint-sql
    assert_success
}

@test "make lint-sql-fix --dry-run validates" {
    run make -n lint-sql-fix
    assert_success
}

@test "make lint-docker --dry-run validates" {
    run make -n lint-docker
    assert_success
}

@test "make lint-config --dry-run validates" {
    run make -n lint-config
    assert_success
}

@test "make prettier-check --dry-run validates" {
    run make -n prettier-check
    assert_success
}

@test "make prettier-fix --dry-run validates" {
    run make -n prettier-fix
    assert_success
}

@test "make type-check --dry-run validates" {
    run make -n type-check
    assert_success
}

@test "make knip --dry-run validates" {
    run make -n knip
    assert_success
}

@test "make depcheck --dry-run validates" {
    run make -n depcheck
    assert_success
}

# =============================================================================
# Test Targets
# =============================================================================

@test "make test --dry-run validates" {
    run make -n test
    assert_success
}

@test "make test-php --dry-run validates" {
    run make -n test-php
    assert_success
}

@test "make test-php-debug --dry-run validates" {
    run make -n test-php-debug
    assert_success
}

@test "make test-node --dry-run validates" {
    run make -n test-node
    assert_success
}

@test "make test-node-watch --dry-run validates" {
    run make -n test-node-watch
    assert_success
}

@test "make test-sql --dry-run validates" {
    run make -n test-sql
    assert_success
}

@test "make test-coverage --dry-run validates" {
    run make -n test-coverage
    assert_success
}

@test "make test-coverage-php --dry-run validates" {
    run make -n test-coverage-php
    assert_success
}

@test "make test-coverage-node --dry-run validates" {
    run make -n test-coverage-node
    assert_success
}

# =============================================================================
# Check/Validation Targets
# =============================================================================

@test "make check --dry-run validates" {
    run make -n check
    assert_success
}

@test "make check-health --dry-run validates" {
    run make -n check-health
    assert_success
}

@test "make validate --dry-run validates" {
    run make -n validate
    assert_success
}

@test "make outdated --dry-run validates" {
    run make -n outdated
    assert_success
}

# =============================================================================
# Backup Targets
# =============================================================================

@test "make backup-all --dry-run validates" {
    run make -n backup-all
    assert_success
}

@test "make backup-db --dry-run validates" {
    run make -n backup-db
    assert_success
}

@test "make backup-db-list --dry-run validates" {
    run make -n backup-db-list
    assert_success
}

@test "make backup-db-restore --dry-run validates" {
    run make -n backup-db-restore
    assert_success
}

@test "make backup-elasticsearch --dry-run validates" {
    run make -n backup-elasticsearch
    assert_success
}

@test "make backup-elasticsearch-list --dry-run validates" {
    run make -n backup-elasticsearch-list
    assert_success
}

@test "make backup-elasticsearch-restore --dry-run validates" {
    run make -n backup-elasticsearch-restore
    assert_success
}

@test "make backup-rabbitmq --dry-run validates" {
    run make -n backup-rabbitmq
    assert_success
}

@test "make backup-rabbitmq-list --dry-run validates" {
    run make -n backup-rabbitmq-list
    assert_success
}

@test "make backup-rabbitmq-restore --dry-run validates" {
    run make -n backup-rabbitmq-restore
    assert_success
}

@test "make backup-seaweedfs --dry-run validates" {
    run make -n backup-seaweedfs
    assert_success
}

@test "make backup-seaweedfs-list --dry-run validates" {
    run make -n backup-seaweedfs-list
    assert_success
}

@test "make backup-seaweedfs-restore --dry-run validates" {
    run make -n backup-seaweedfs-restore
    assert_success
}

# =============================================================================
# Database Targets
# =============================================================================

@test "make db-cleanup --dry-run validates" {
    run make -n db-cleanup
    assert_success
}

@test "make db-migrations --dry-run validates" {
    run make -n db-migrations
    assert_success
}

@test "make postgres-dump --dry-run validates" {
    run make -n postgres-dump
    assert_success
}

@test "make postgres-restore --dry-run validates" {
    run make -n postgres-restore
    assert_success
}

@test "make mariadb-cli --dry-run validates" {
    run make -n mariadb-cli
    assert_success
}

@test "make mariadb-dump --dry-run validates" {
    run make -n mariadb-dump
    assert_success
}

@test "make mariadb-restore --dry-run validates" {
    run make -n mariadb-restore
    assert_success
}

@test "make redis-flush --dry-run validates" {
    run make -n redis-flush
    assert_success
}

@test "make redis-monitor --dry-run validates" {
    run make -n redis-monitor
    assert_success
}

# =============================================================================
# Documentation Targets
# =============================================================================

@test "make docs --dry-run validates" {
    run make -n docs
    assert_success
}

@test "make docs-clean --dry-run validates" {
    run make -n docs-clean
    assert_success
}

@test "make docs-php --dry-run validates" {
    run make -n docs-php
    assert_success
}

@test "make docs-node --dry-run validates" {
    run make -n docs-node
    assert_success
}

@test "make docs-node-backend --dry-run validates" {
    run make -n docs-node-backend
    assert_success
}

@test "make docs-node-frontend --dry-run validates" {
    run make -n docs-node-frontend
    assert_success
}

# =============================================================================
# Security Targets
# =============================================================================

@test "make secrets --dry-run validates" {
    run make -n secrets
    assert_success
}

@test "make secrets-rotate --dry-run validates" {
    run make -n secrets-rotate
    assert_success
}

@test "make secrets-rotate-passwords --dry-run validates" {
    run make -n secrets-rotate-passwords
    assert_success
}

@test "make security-audit-node --dry-run validates" {
    run make -n security-audit-node
    assert_success
}

@test "make security-config --dry-run validates" {
    run make -n security-config
    assert_success
}

@test "make security-deps --dry-run validates" {
    run make -n security-deps
    assert_success
}

@test "make security-sbom --dry-run validates" {
    run make -n security-sbom
    assert_success
}

@test "make security-scan --dry-run validates" {
    run make -n security-scan
    assert_success
}

@test "make falco-run --dry-run validates" {
    run make -n falco-run
    assert_success
}

# =============================================================================
# SSL Targets
# =============================================================================

@test "make ssl-selfsigned --dry-run validates" {
    run make -n ssl-selfsigned
    assert_success
}

@test "make ssl-letsencrypt --dry-run validates" {
    run make -n ssl-letsencrypt
    assert_success
}

@test "make ssl-renew --dry-run validates" {
    run make -n ssl-renew
    assert_success
}

@test "make ssl-reload-services --dry-run validates" {
    run make -n ssl-reload-services
    assert_success
}

@test "make ssl-prod-enable --dry-run validates" {
    run make -n ssl-prod-enable
    assert_success
}

@test "make ssl-info --dry-run validates" {
    run make -n ssl-info
    assert_success
}

@test "make ssl-clean --dry-run validates" {
    run make -n ssl-clean
    assert_success
}

# =============================================================================
# Node.js Service Targets
# =============================================================================

@test "make node-up --dry-run validates" {
    run make -n node-up
    assert_success
}

@test "make node-api-up --dry-run validates" {
    run make -n node-api-up
    assert_success
}

@test "make node-framework-up --dry-run validates" {
    run make -n node-framework-up
    assert_success
}

@test "make node-dev-full --dry-run validates" {
    run make -n node-dev-full
    assert_success
}

@test "make node-dev-backend --dry-run validates" {
    run make -n node-dev-backend
    assert_success
}

@test "make node-dev-vite --dry-run validates" {
    run make -n node-dev-vite
    assert_success
}

@test "make node-server-dev --dry-run validates" {
    run make -n node-server-dev
    assert_success
}

@test "make node-server-build --dry-run validates" {
    run make -n node-server-build
    assert_success
}

# =============================================================================
# Node.js Frontend Targets
# =============================================================================

@test "make node-frontend-dev --dry-run validates" {
    run make -n node-frontend-dev
    assert_success
}

@test "make node-frontend-build --dry-run validates" {
    run make -n node-frontend-build
    assert_success
}

@test "make node-frontend-start --dry-run validates" {
    run make -n node-frontend-start
    assert_success
}

@test "make node-frontend-clean --dry-run validates" {
    run make -n node-frontend-clean
    assert_success
}

@test "make node-frontend-nuxt --dry-run validates" {
    run make -n node-frontend-nuxt
    assert_success
}

@test "make node-frontend-next --dry-run validates" {
    run make -n node-frontend-next
    assert_success
}

@test "make node-frontend-remix --dry-run validates" {
    run make -n node-frontend-remix
    assert_success
}

@test "make node-frontend-sveltekit --dry-run validates" {
    run make -n node-frontend-sveltekit
    assert_success
}

# =============================================================================
# IDE Config Targets
# =============================================================================

@test "make ide-config --dry-run validates" {
    run make -n ide-config
    assert_success
}

@test "make ide-config-full --dry-run validates" {
    run make -n ide-config-full
    assert_success
}

@test "make ide-config-phpstorm --dry-run validates" {
    run make -n ide-config-phpstorm
    assert_success
}

@test "make ide-config-phpstorm-full --dry-run validates" {
    run make -n ide-config-phpstorm-full
    assert_success
}

@test "make ide-config-vscode --dry-run validates" {
    run make -n ide-config-vscode
    assert_success
}

@test "make ide-config-vscode-full --dry-run validates" {
    run make -n ide-config-vscode-full
    assert_success
}

# =============================================================================
# Shell Targets
# =============================================================================

@test "make shell-elasticsearch --dry-run validates" {
    run make -n shell-elasticsearch
    assert_success
}

@test "make shell-mailpit --dry-run validates" {
    run make -n shell-mailpit
    assert_success
}

@test "make shell-mariadb --dry-run validates" {
    run make -n shell-mariadb
    assert_success
}

@test "make shell-meilisearch --dry-run validates" {
    run make -n shell-meilisearch
    assert_success
}

@test "make shell-mercure --dry-run validates" {
    run make -n shell-mercure
    assert_success
}

@test "make shell-postgres --dry-run validates" {
    run make -n shell-postgres
    assert_success
}

@test "make shell-rabbitmq --dry-run validates" {
    run make -n shell-rabbitmq
    assert_success
}

@test "make shell-redis --dry-run validates" {
    run make -n shell-redis
    assert_success
}

@test "make shell-seaweedfs --dry-run validates" {
    run make -n shell-seaweedfs
    assert_success
}

# =============================================================================
# Logs Targets
# =============================================================================

@test "make logs-elasticsearch --dry-run validates" {
    run make -n logs-elasticsearch
    assert_success
}

@test "make logs-mailpit --dry-run validates" {
    run make -n logs-mailpit
    assert_success
}

@test "make logs-mariadb --dry-run validates" {
    run make -n logs-mariadb
    assert_success
}

@test "make logs-meilisearch --dry-run validates" {
    run make -n logs-meilisearch
    assert_success
}

@test "make logs-mercure --dry-run validates" {
    run make -n logs-mercure
    assert_success
}

@test "make logs-postgres --dry-run validates" {
    run make -n logs-postgres
    assert_success
}

@test "make logs-rabbitmq --dry-run validates" {
    run make -n logs-rabbitmq
    assert_success
}

@test "make logs-redis --dry-run validates" {
    run make -n logs-redis
    assert_success
}

@test "make logs-seaweedfs --dry-run validates" {
    run make -n logs-seaweedfs
    assert_success
}

@test "make logs-save --dry-run validates" {
    run make -n logs-save
    assert_success
}

# =============================================================================
# Goss Targets (Additional)
# =============================================================================

@test "make goss-test-dev-assets --dry-run validates" {
    run make -n goss-test-dev-assets
    assert_success
}

@test "make goss-test-dev-framework --dry-run validates" {
    run make -n goss-test-dev-framework
    assert_success
}

@test "make goss-test-dev-idle --dry-run validates" {
    run make -n goss-test-dev-idle
    assert_success
}

@test "make goss-test-dev-fullstack-mariadb --dry-run validates" {
    run make -n goss-test-dev-fullstack-mariadb
    assert_success
}

@test "make goss-test-dev-fullstack-optional --dry-run validates" {
    run make -n goss-test-dev-fullstack-optional
    assert_success
}

@test "make goss-test-prod-fullstack-mariadb --dry-run validates" {
    run make -n goss-test-prod-fullstack-mariadb
    assert_success
}

@test "make goss-test-prod-fullstack-optional --dry-run validates" {
    run make -n goss-test-prod-fullstack-optional
    assert_success
}

@test "make goss-test-prod-minimal --dry-run validates" {
    run make -n goss-test-prod-minimal
    assert_success
}

@test "make goss-test-prod-node-only --dry-run validates" {
    run make -n goss-test-prod-node-only
    assert_success
}

@test "make goss-test-prod-php-only --dry-run validates" {
    run make -n goss-test-prod-php-only
    assert_success
}

@test "make goss-test-elasticsearch --dry-run validates" {
    run make -n goss-test-elasticsearch
    assert_success
}

@test "make goss-test-mailpit --dry-run validates" {
    run make -n goss-test-mailpit
    assert_success
}

@test "make goss-test-mariadb --dry-run validates" {
    run make -n goss-test-mariadb
    assert_success
}

@test "make goss-test-meilisearch --dry-run validates" {
    run make -n goss-test-meilisearch
    assert_success
}

@test "make goss-test-mercure --dry-run validates" {
    run make -n goss-test-mercure
    assert_success
}

@test "make goss-test-rabbitmq --dry-run validates" {
    run make -n goss-test-rabbitmq
    assert_success
}

@test "make goss-test-seaweedfs --dry-run validates" {
    run make -n goss-test-seaweedfs
    assert_success
}

@test "make goss-test-node-frontend --dry-run validates" {
    run make -n goss-test-node-frontend
    assert_success
}

@test "make goss-test-matrix-dev --dry-run validates" {
    run make -n goss-test-matrix-dev
    assert_success
}

@test "make goss-test-matrix-prod --dry-run validates" {
    run make -n goss-test-matrix-prod
    assert_success
}

# =============================================================================
# Dependency Management Targets
# =============================================================================

@test "make composer --dry-run validates" {
    run make -n composer
    assert_success
}

@test "make composer-sync --dry-run validates" {
    run make -n composer-sync
    assert_success
}

@test "make composer-update --dry-run validates" {
    run make -n composer-update
    assert_success
}

@test "make pnpm --dry-run validates" {
    run make -n pnpm
    assert_success
}

@test "make pnpm-sync --dry-run validates" {
    run make -n pnpm-sync
    assert_success
}

@test "make pnpm-update --dry-run validates" {
    run make -n pnpm-update
    assert_success
}

@test "make pnpm-upgrade --dry-run validates" {
    run make -n pnpm-upgrade
    assert_success
}

@test "make lockfiles-sync --dry-run validates" {
    run make -n lockfiles-sync
    assert_success
}

@test "make hooks-install --dry-run validates" {
    run make -n hooks-install
    assert_success
}

# =============================================================================
# Utility Targets
# =============================================================================

@test "make dive --dry-run validates" {
    run make -n dive
    assert_success
}

@test "make renovate --dry-run validates" {
    run make -n renovate
    assert_success
}

# =============================================================================
# Reset/Cleanup Targets (Dry-Run Only - Destructive!)
# =============================================================================

@test "make reset --dry-run validates" {
    run make -n reset
    assert_success
}

@test "make reset-full --dry-run validates" {
    run make -n reset-full
    assert_success
}
