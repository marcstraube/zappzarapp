#!/usr/bin/env bats
# BATS Tests: Makefile Help System
# Tests the help target and target discovery

load 'helpers/setup'

# =============================================================================
# Basic Help Output
# =============================================================================

@test "make help exits successfully" {
    run make help
    assert_success
}

@test "make help produces output" {
    run make help
    assert_success
    assert_output --partial "Available commands:"
}

@test "make help shows all main categories" {
    run make help
    assert_success
    assert_output --partial "Setup"
    assert_output --partial "Docker"
    assert_output --partial "Quality Assurance"
}

@test "make help shows GOSS category" {
    run make help
    assert_success
    assert_output --partial "GOSS Container Tests"
}

@test "make help lists the customize target" {
    run make help
    assert_success
    assert_output --partial "customize"
}

@test "make help shows BATS category" {
    run make help
    assert_success
    assert_output --partial "BATS Makefile Tests"
}

# =============================================================================
# Help Filtering
# =============================================================================

@test "make help FILTER=setup shows only setup targets" {
    run make help FILTER=setup
    assert_success
    assert_output --partial "setup"
    assert_output --partial "init"
}

@test "make help FILTER=docker shows docker targets" {
    run make help FILTER=docker
    assert_success
    assert_output --partial "up"
    assert_output --partial "down"
    assert_output --partial "build"
}

@test "make help FILTER=test shows test targets" {
    run make help FILTER=test
    assert_success
    assert_output --partial "test-php"
    assert_output --partial "test-node"
}

@test "make help FILTER=goss shows goss targets" {
    run make help FILTER=goss
    assert_success
    assert_output --partial "goss-test"
    assert_output --partial "goss-test-build"
}

@test "make help FILTER=bats shows bats targets" {
    run make help FILTER=bats
    assert_success
    assert_output --partial "bats-test"
    assert_output --partial "bats-build"
}

@test "make help FILTER=nonexistent shows no targets" {
    run make help FILTER=nonexistent
    assert_success
    # Should not contain any target lines (just header or empty)
    refute_output --partial "##"
}

# =============================================================================
# Key Target Documentation
# =============================================================================

@test "setup target has help text" {
    run get_target_help "setup"
    assert_success
    assert_output --partial "setup"
}

@test "up target has help text" {
    run get_target_help "up"
    assert_success
    assert_output --partial "up"
}

@test "down target has help text" {
    run get_target_help "down"
    assert_success
    assert_output --partial "down"
}

@test "test target has help text" {
    run get_target_help "test"
    assert_success
    assert_output --partial "test"
}

@test "check target has help text" {
    run get_target_help "check"
    assert_success
    assert_output --partial "check"
}

# =============================================================================
# Target Existence
# =============================================================================

@test "setup target exists" {
    run target_exists "setup"
    assert_success
}

@test "init target exists" {
    run target_exists "init"
    assert_success
}

@test "up target exists" {
    run target_exists "up"
    assert_success
}

@test "down target exists" {
    run target_exists "down"
    assert_success
}

@test "build target exists" {
    run target_exists "build"
    assert_success
}

@test "test target exists" {
    run target_exists "test"
    assert_success
}

@test "check target exists" {
    run target_exists "check"
    assert_success
}

@test "goss-test target exists" {
    run target_exists "goss-test"
    assert_success
}

@test "bats-test target exists" {
    run target_exists "bats-test"
    assert_success
}

# =============================================================================
# Help Format Validation
# =============================================================================

@test "help output contains proper formatting" {
    run make help
    assert_success
    # Check for category headers
    assert_output --partial "Setup"
    assert_output --partial "Docker"
}

@test "help shows available commands" {
    run make help
    assert_success
    assert_output --partial "Available commands:"
}
