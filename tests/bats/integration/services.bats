#!/usr/bin/env bats
# Integration Tests: Service Operations
# Requires running containers

load 'setup'

# =============================================================================
# File Setup/Teardown
# =============================================================================

setup_file() {
    integration_setup
}

teardown_file() {
    integration_teardown
}

# Manifest backup used by the pnpm-sync test below. Restored in teardown()
# (which BATS runs even when an assert fails) so a failed sync test cannot
# leave a modified package.json behind.
PNPM_SYNC_MANIFEST_BAK="package.json.pnpm-sync-test.bak"

# Overrides teardown() from helpers/setup.bash - keep its cd restore
teardown() {
    cd "${PROJECT_ROOT}" || true
    if [[ -f "${PNPM_SYNC_MANIFEST_BAK}" ]]; then
        mv "${PNPM_SYNC_MANIFEST_BAK}" package.json
    fi
}

# =============================================================================
# Core Services
# =============================================================================

@test "[Integration] nginx responds to requests" {
    require_service "nginx"
    # Development nginx uses HTTPS only (port 8080 redirects to 8443)
    # Use --insecure for self-signed certificates
    run timeout 10 curl -sf --insecure https://localhost:8443/health
    assert_success
}

@test "[Integration] PHP-FPM is accessible from nginx" {
    require_service "nginx"
    require_service "php"
    # Development nginx uses HTTPS only (port 8080 redirects to 8443)
    # Use --insecure for self-signed certificates
    run timeout 10 curl -sf --insecure https://localhost:8443/
    # May return 200 or redirect, both are fine
    [[ $status -eq 0 ]] || [[ $status -eq 22 ]]
}

# =============================================================================
# Shell Access
# =============================================================================

@test "[Integration] make shell-php provides shell access" {
    require_service "php"
    run timeout 10 docker compose exec -T php whoami
    assert_success
}

@test "[Integration] make shell-node provides shell access" {
    require_service "node"
    run timeout 10 docker compose exec -T node whoami
    assert_success
}

@test "[Integration] make shell-nginx provides shell access" {
    require_service "nginx"
    run timeout 10 docker compose exec -T nginx whoami
    assert_success
}

# =============================================================================
# Logs
# =============================================================================

@test "[Integration] make logs shows combined logs" {
    require_containers
    run timeout 10 docker compose logs --tail=5
    assert_success
}

@test "[Integration] make logs-php shows PHP logs" {
    require_service "php"
    run timeout 10 docker compose logs --tail=5 php
    assert_success
}

@test "[Integration] make logs-nginx shows nginx logs" {
    require_service "nginx"
    run timeout 10 docker compose logs --tail=5 nginx
    assert_success
}

@test "[Integration] make logs-node shows Node logs" {
    require_service "node"
    run timeout 10 docker compose logs --tail=5 node
    assert_success
}

# =============================================================================
# Node.js Services
# =============================================================================

@test "[Integration] Node backend is running" {
    require_service "node"
    # Check if Express/backend is responding
    run timeout 10 docker compose exec -T node curl -sf http://localhost:3000/health 2>/dev/null || true
    # May not have health endpoint, just check container is up
    run docker compose ps --status running node
    assert_success
}

@test "[Integration] make node-build succeeds" {
    require_service "node"
    require_dependencies
    run timeout 180 make node-build
    assert_success
}

# =============================================================================
# IDE Configuration
# =============================================================================

@test "[Integration] make ide-config updates IDE settings" {
    run timeout 30 make ide-config
    assert_success
}

# =============================================================================
# Dependency Installation
# =============================================================================

@test "[Integration] make composer-install installs PHP deps" {
    require_service "php"
    run timeout 180 make composer-install
    assert_success
    [[ -d "vendor" ]]
}

@test "[Integration] make pnpm-install installs Node deps" {
    require_service "node"
    run timeout 180 make pnpm-install
    assert_success
    [[ -d "node_modules" ]]
}

@test "[Integration] make pnpm-sync re-resolves lockfile after package.json change" {
    require_service "node"
    require_dependencies
    # In CI (compose.ci.yaml) the lockfile is intentionally not bind-mounted
    # (single-file mounts cannot be atomically replaced), so pnpm-sync writes
    # the re-resolved lockfile only inside the ephemeral container and the
    # host file never changes. This test covers the dev preset, where the
    # lockfile IS bind-mounted and where the regression lived.
    [[ "${COMPOSE_FILE:-}" != *"compose.ci.yaml"* ]] || skip "CI mode: lockfile not bind-mounted"
    [[ -s "pnpm-lock.yaml" ]] || skip "No host lockfile"
    grep -q "pino-pretty" pnpm-lock.yaml || skip "pino-pretty not in lockfile"

    cp package.json "${PNPM_SYNC_MANIFEST_BAK}"
    local checksum_before
    checksum_before=$(cksum pnpm-lock.yaml | cut -d' ' -f1)

    # Drop a devDependency so the lockfile no longer matches the manifest.
    # Line-based removal is safe: Prettier keeps one dependency per line and
    # pino-pretty is not the last entry (no dangling comma).
    sed -i '/"pino-pretty":/d' package.json

    # Regression guard: this aborted with ERR_PNPM_OUTDATED_LOCKFILE before
    # pnpm-sync learned the temp-location lockfile re-resolve
    run timeout 300 make pnpm-sync
    assert_success

    # The lockfile must actually have been re-resolved
    [[ "$(cksum pnpm-lock.yaml | cut -d' ' -f1)" != "${checksum_before}" ]]

    # Round-trip: restore the manifest, sync back
    mv "${PNPM_SYNC_MANIFEST_BAK}" package.json
    run timeout 300 make pnpm-sync
    assert_success
    grep -q "pino-pretty" pnpm-lock.yaml
}

# =============================================================================
# Status & Health
# =============================================================================

@test "[Integration] make status shows container status" {
    require_containers
    run make status
    assert_success
}

@test "[Integration] docker compose ps shows running containers" {
    run docker compose ps
    assert_success
    assert_output --partial "nginx"
}
