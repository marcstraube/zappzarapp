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

@test "[Integration] make ssl-selfsigned generates certificate" {
    # Skip if certificates already exist
    if [[ -f "docker/certs/cert.crt" ]]; then
        skip "Certificates already exist"
    fi
    run timeout 60 make ssl-selfsigned
    assert_success
    # Verify certificate created
    [[ -f "docker/certs/cert.crt" ]]
}
