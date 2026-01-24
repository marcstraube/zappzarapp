#!/usr/bin/env bats
# BATS Tests: Goss Integration
# Tests BATS + Goss combined validation

load 'helpers/setup'
load 'helpers/goss-integration'

# =============================================================================
# Goss Infrastructure
# =============================================================================

@test "goss directory exists" {
    run test -d tests/goss
    assert_success
}

@test "goss services directory exists" {
    run test -d tests/goss/services
    assert_success
}

@test "goss presets directory exists" {
    run test -d tests/goss/presets
    assert_success
}

@test "runtime-tests.sh exists and is executable" {
    run test -x tests/goss/runtime-tests.sh
    assert_success
}

@test "preset-runner.sh exists and is executable" {
    run test -x tests/goss/preset-runner.sh
    assert_success
}

# =============================================================================
# Goss Service Specs
# =============================================================================

@test "nginx.yaml spec exists" {
    run test -f tests/goss/services/nginx.yaml
    assert_success
}

@test "php.yaml spec exists" {
    run test -f tests/goss/services/php.yaml
    assert_success
}

@test "node-backend.yaml spec exists" {
    run test -f tests/goss/services/node-backend.yaml
    assert_success
}

@test "postgres.yaml spec exists" {
    run test -f tests/goss/services/postgres.yaml
    assert_success
}

@test "mariadb.yaml spec exists" {
    run test -f tests/goss/services/mariadb.yaml
    assert_success
}

@test "redis.yaml spec exists" {
    run test -f tests/goss/services/redis.yaml
    assert_success
}

@test "mercure.yaml spec exists" {
    run test -f tests/goss/services/mercure.yaml
    assert_success
}

@test "meilisearch.yaml spec exists" {
    run test -f tests/goss/services/meilisearch.yaml
    assert_success
}

@test "elasticsearch.yaml spec exists" {
    run test -f tests/goss/services/elasticsearch.yaml
    assert_success
}

@test "mailpit.yaml spec exists" {
    run test -f tests/goss/services/mailpit.yaml
    assert_success
}

@test "rabbitmq.yaml spec exists" {
    run test -f tests/goss/services/rabbitmq.yaml
    assert_success
}

@test "seaweedfs.yaml spec exists" {
    run test -f tests/goss/services/seaweedfs.yaml
    assert_success
}

# =============================================================================
# Goss Makefile Targets
# =============================================================================

@test "goss-test target exists" {
    run target_exists "goss-test"
    assert_success
}

@test "goss-test-build target exists" {
    run target_exists "goss-test-build"
    assert_success
}

@test "goss-test-all target exists" {
    run target_exists "goss-test-all"
    assert_success
}

@test "goss-cleanup target exists" {
    run target_exists "goss-cleanup"
    assert_success
}

@test "goss-build target exists" {
    run target_exists "goss-build"
    assert_success
}

@test "goss-test-preset target exists" {
    run target_exists "goss-test-preset"
    assert_success
}

@test "goss-test-matrix target exists" {
    run target_exists "goss-test-matrix"
    assert_success
}

# =============================================================================
# Goss Service-Specific Targets
# =============================================================================

@test "goss-test-nginx target exists" {
    run target_exists "goss-test-nginx"
    assert_success
}

@test "goss-test-php target exists" {
    run target_exists "goss-test-php"
    assert_success
}

@test "goss-test-node-backend target exists" {
    run target_exists "goss-test-node-backend"
    assert_success
}

@test "goss-test-postgres target exists" {
    run target_exists "goss-test-postgres"
    assert_success
}

@test "goss-test-redis target exists" {
    run target_exists "goss-test-redis"
    assert_success
}

# =============================================================================
# Goss Preset Targets
# =============================================================================

@test "goss-test-dev-fullstack target exists" {
    run target_exists "goss-test-dev-fullstack"
    assert_success
}

@test "goss-test-dev-php-only target exists" {
    run target_exists "goss-test-dev-php-only"
    assert_success
}

@test "goss-test-dev-node-only target exists" {
    run target_exists "goss-test-dev-node-only"
    assert_success
}

@test "goss-test-dev-minimal target exists" {
    run target_exists "goss-test-dev-minimal"
    assert_success
}

@test "goss-test-prod-fullstack target exists" {
    run target_exists "goss-test-prod-fullstack"
    assert_success
}

# =============================================================================
# Goss Dockerfile
# =============================================================================

@test "goss Dockerfile exists" {
    run test -f docker/goss/Dockerfile
    assert_success
}

@test "goss Dockerfile is valid" {
    run docker build --check -f docker/goss/Dockerfile .
    # --check may not be supported in all Docker versions, so we allow failure
    # but the build syntax check should pass
    [[ $status -eq 0 ]] || [[ "$output" =~ "unknown flag" ]]
}

# =============================================================================
# Goss Integration Helpers
# =============================================================================

@test "get_goss_presets returns preset list" {
    run get_goss_presets
    assert_success
    assert_output --partial "dev-fullstack"
}

@test "preset_exists returns true for valid preset" {
    run preset_exists "dev-fullstack"
    assert_success
}

@test "preset_exists returns false for invalid preset" {
    run preset_exists "nonexistent-preset-12345"
    assert_failure
}

# =============================================================================
# Dry Run Tests
# =============================================================================

@test "make goss-test --dry-run validates" {
    run make -n goss-test
    assert_success
}

@test "make goss-test-build --dry-run validates" {
    run make -n goss-test-build
    assert_success
}

@test "make goss-cleanup --dry-run validates" {
    run make -n goss-cleanup
    assert_success
}

@test "make goss-build --dry-run validates" {
    run make -n goss-build
    assert_success
}

# =============================================================================
# BATS Targets
# =============================================================================

@test "bats-test target exists" {
    run target_exists "bats-test"
    assert_success
}

@test "bats-build target exists" {
    run target_exists "bats-build"
    assert_success
}

@test "bats-test-file target exists" {
    run target_exists "bats-test-file"
    assert_success
}

@test "bats-test-verbose target exists" {
    run target_exists "bats-test-verbose"
    assert_success
}

@test "bats-test-local target exists" {
    run target_exists "bats-test-local"
    assert_success
}

# =============================================================================
# BATS Dockerfile
# =============================================================================

@test "bats Dockerfile exists" {
    run test -f docker/bats/Dockerfile
    assert_success
}
