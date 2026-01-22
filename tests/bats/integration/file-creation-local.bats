#!/usr/bin/env bats
# Integration Tests: File Creation Verification (Local Tools)
#
# Tests that local Makefile targets correctly create expected files.
# These tests require locally installed tools (composer, pnpm).
# Tests are SKIPPED if tools are not available.
#
# Run: make bats-test-integration-file FILE=file-creation-local.bats

load 'setup'

# =============================================================================
# Helper Functions
# =============================================================================

# Check if composer is installed locally
require_local_composer() {
    if ! command -v composer &>/dev/null; then
        skip "Local composer not installed"
    fi
}

# Check if pnpm is installed locally
require_local_pnpm() {
    if ! command -v pnpm &>/dev/null; then
        skip "Local pnpm not installed"
    fi
}

# =============================================================================
# Composer Local Installation
# =============================================================================

@test "Local composer is available" {
    if ! command -v composer &>/dev/null; then
        skip "Local composer not installed"
    fi
    run composer --version
    assert_success
    assert_output --partial "Composer"
}

@test "make composer-install-local creates vendor/" {
    require_local_composer

    # Clean first if exists
    rm -rf vendor 2>/dev/null || true

    run timeout 300 make composer-install-local
    assert_success

    [[ -d "vendor" ]]
}

@test "make composer-install-local creates vendor/autoload.php" {
    require_local_composer
    [[ -f "vendor/autoload.php" ]]
}

@test "make composer-install-local creates vendor/composer/" {
    require_local_composer
    [[ -d "vendor/composer" ]]
}

@test "make composer-install-local creates vendor/bin/" {
    require_local_composer
    [[ -d "vendor/bin" ]]
}

# =============================================================================
# pnpm Local Installation
# =============================================================================

@test "Local pnpm is available" {
    if ! command -v pnpm &>/dev/null; then
        skip "Local pnpm not installed"
    fi
    run pnpm --version
    assert_success
}

@test "make pnpm-install-local creates node_modules/" {
    require_local_pnpm

    # Clean first if exists
    rm -rf node_modules 2>/dev/null || true

    run timeout 300 make pnpm-install-local
    assert_success

    [[ -d "node_modules" ]]
}

@test "make pnpm-install-local creates node_modules/.pnpm/" {
    require_local_pnpm
    [[ -d "node_modules/.pnpm" ]]
}

@test "make pnpm-install-local creates node_modules/.modules.yaml" {
    require_local_pnpm
    [[ -f "node_modules/.modules.yaml" ]]
}

@test "make pnpm-install-local creates pnpm-lock.yaml" {
    require_local_pnpm
    [[ -f "pnpm-lock.yaml" ]]
}

# =============================================================================
# Lockfile Verification
# =============================================================================

@test "composer.lock is created after composer-install-local" {
    require_local_composer
    [[ -f "composer.lock" ]]
}

@test "pnpm-lock.yaml is created after pnpm-install-local" {
    require_local_pnpm
    [[ -f "pnpm-lock.yaml" ]]
}
