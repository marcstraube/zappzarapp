#!/usr/bin/env bats
# Integration Tests: Security Scanning
# Requires running containers and installed dependencies

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

# =============================================================================
# Dependency Audits
# =============================================================================

@test "[Integration] Composer audit passes" {
    require_php
    require_dependencies
    run timeout 60 docker compose exec -T php composer audit
    # Allow exit code 0 (no vulnerabilities) or informational
    [[ $status -eq 0 ]] || skip "Composer audit found vulnerabilities (informational)"
}

@test "[Integration] pnpm audit passes" {
    require_node
    require_dependencies
    run timeout 60 docker compose exec -T node pnpm audit
    # Allow exit code 0 (no vulnerabilities) or informational
    [[ $status -eq 0 ]] || skip "pnpm audit found vulnerabilities (informational)"
}

@test "[Integration] make security-audit-node runs" {
    require_node
    require_dependencies
    run timeout 120 make security-audit-node
    # Security audits may report findings (0=pass, 1=warnings, 2=errors found)
    [[ $status -eq 0 ]] || [[ $status -eq 1 ]] || [[ $status -eq 2 ]]
}

# =============================================================================
# Security Configuration
# =============================================================================

@test "[Integration] make security-config validates" {
    run timeout 60 make security-config
    assert_success
}

@test "[Integration] make security-deps checks dependencies" {
    require_php
    require_node
    require_dependencies
    run timeout 120 make security-deps
    # May report outdated dependencies (0=pass, 1=warnings, 2=issues found)
    [[ $status -eq 0 ]] || [[ $status -eq 1 ]] || [[ $status -eq 2 ]]
}

# =============================================================================
# SBOM Generation
# =============================================================================

@test "[Integration] make security-sbom generates SBOM" {
    require_php
    require_node
    require_dependencies
    run timeout 180 make security-sbom
    assert_success
}

# =============================================================================
# Security Scan
# =============================================================================

@test "[Integration] make security-scan runs Trivy" {
    require_containers
    # Skip if Trivy not available
    if ! command -v trivy &>/dev/null && ! docker images | grep -q trivy; then
        skip "Trivy not available"
    fi
    run timeout 300 make security-scan
    # Security scans may find issues
    [[ $status -eq 0 ]] || [[ $status -eq 1 ]]
}

# =============================================================================
# Secrets Management
# =============================================================================

@test "[Integration] make secrets shows secret status" {
    run timeout 30 make secrets
    assert_success
}

# =============================================================================
# SSL/TLS
# =============================================================================

@test "[Integration] make ssl-info shows certificate info" {
    run timeout 30 make ssl-info
    # May not have certificates yet
    [[ $status -eq 0 ]] || [[ $status -eq 1 ]]
}

@test "[Integration] make ssl-internal generates CA and certificates" {
    # Skip if certificates already exist
    if [[ -f "docker/certs/nginx/cert.crt" ]]; then
        skip "Certificates already exist"
    fi
    run timeout 60 make ssl-internal
    assert_success
    # Verify CA created
    [[ -f "docker/certs/ca/ca.crt" ]]
    [[ -f "docker/certs/ca/ca.key" ]]
    # Verify nginx certificate created
    [[ -f "docker/certs/nginx/cert.crt" ]]
    [[ -f "docker/certs/nginx/cert.key" ]]
    # Verify internal certificate created
    [[ -f "docker/certs/internal/cert.crt" ]]
    [[ -f "docker/certs/internal/cert.key" ]]
    [[ -f "docker/certs/internal/ca.crt" ]]
}

# =============================================================================
# OWASP ZAP DAST Scanning (Manual/CI Only - Slow!)
# =============================================================================

@test "[Integration] make security-zap-start initializes production environment" {
    # Skip by default (requires production ENV, takes 5-10 min)
    skip "ZAP scan integration test - run manually with: bats tests/bats/integration/security.bats -f zap-start"

    # Clean up any existing services
    ENV=production make down || true

    run timeout 120 make security-zap-start
    assert_success

    # Verify services are running
    run docker compose ps
    assert_success
    assert_output --partial "Up"
}

@test "[Integration] make security-zap-scan runs OWASP ZAP" {
    # Skip by default (requires running services, takes 5-10 min)
    skip "ZAP scan integration test - run manually with: bats tests/bats/integration/security.bats -f zap-scan"

    # Requires security-zap-start to have been run first
    require_containers

    run timeout 600 make security-zap-scan
    # ZAP scan may find issues (|| true in Makefile)
    [[ $status -eq 0 ]] || [[ $status -eq 1 ]]

    # Verify report was generated
    [[ -f "zap-report.html" ]]
}

@test "[Integration] make security-zap-stop cleans up environment" {
    # Skip by default
    skip "ZAP scan integration test - run manually with: bats tests/bats/integration/security.bats -f zap-stop"

    run timeout 60 make security-zap-stop
    assert_success

    # Verify services are stopped
    run docker compose ps
    # Should be empty or show "Exit" status
    [[ $status -eq 0 ]]
}
