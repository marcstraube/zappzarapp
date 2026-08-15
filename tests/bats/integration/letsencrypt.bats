#!/usr/bin/env bats
# Integration Tests: Let's Encrypt certificate installation (file logic)
#
# Covers install-letsencrypt.sh and the argument validation of
# setup-letsencrypt.sh without any ACME traffic: a certbot-style
# letsencrypt/ tree (archive/ + live/ symlinks) is rebuilt with
# self-signed certificates in a temporary --cert-dir.
#
# The full ACME flow against a local Pebble server lives in
# letsencrypt-e2e.bats (opt-in).

load 'setup'

setup() {
    load "${SCRIPT_DIR}/../helpers/setup.bash"

    INSTALLER="${PROJECT_ROOT}/docker/certs/install-letsencrypt.sh"
    SETUP_SCRIPT="${PROJECT_ROOT}/docker/certs/setup-letsencrypt.sh"
    CERT_DIR="${BATS_TEST_TMPDIR}/certs"
    mkdir -p "$CERT_DIR"
}

# Rebuild the certbot on-disk layout: versioned files under archive/<domain>/
# and live/<domain>/ symlinks pointing at the current version. A README file
# in live/ mirrors certbot, which places one there (the lineage auto-detect
# must not trip over it).
make_lineage() {
    local domain="$1" version="${2:-1}"
    local archive="$CERT_DIR/letsencrypt/archive/$domain"
    local live="$CERT_DIR/letsencrypt/live/$domain"
    mkdir -p "$archive" "$live"
    openssl req -x509 -newkey rsa:2048 -nodes \
        -keyout "$archive/privkey$version.pem" \
        -out "$archive/fullchain$version.pem" \
        -days 1 -subj "/CN=$domain" 2>/dev/null
    chmod 600 "$archive/privkey$version.pem"
    ln -sf "../../archive/$domain/fullchain$version.pem" "$live/fullchain.pem"
    ln -sf "../../archive/$domain/privkey$version.pem" "$live/privkey.pem"
    touch "$CERT_DIR/letsencrypt/live/README"
}

cert_fingerprint() {
    openssl x509 -in "$1" -noout -fingerprint -sha256
}

# =============================================================================
# install-letsencrypt.sh
# =============================================================================

@test "install-letsencrypt installs certificate and key for nginx" {
    make_lineage example.test

    run bash "$INSTALLER" example.test --cert-dir "$CERT_DIR"
    assert_success
    assert_output "installed"

    assert [ -f "$CERT_DIR/nginx/cert.crt" ]
    assert [ -f "$CERT_DIR/nginx/cert.key" ]
    # The copies must resolve the live/ symlinks (plain files, same content)
    assert [ ! -L "$CERT_DIR/nginx/cert.crt" ]
    run cmp -s "$CERT_DIR/letsencrypt/archive/example.test/fullchain1.pem" "$CERT_DIR/nginx/cert.crt"
    assert_success
}

@test "install-letsencrypt sets permissions and nginx read ACL" {
    make_lineage example.test
    bash "$INSTALLER" example.test --cert-dir "$CERT_DIR"

    run stat -c '%a' "$CERT_DIR/nginx/cert.crt"
    assert_output "644"
    # The key carries an ACL, so the group bits show the ACL mask (r--)
    # instead of a plain 600 - assert on the ACL entries directly
    run getfacl --omit-header "$CERT_DIR/nginx/cert.key"
    assert_output --partial "user::rw-"
    assert_output --partial "user:100:r--"
    assert_output --partial "group::---"
    assert_output --partial "other::---"
}

@test "install-letsencrypt is idempotent" {
    make_lineage example.test
    bash "$INSTALLER" example.test --cert-dir "$CERT_DIR"

    run bash "$INSTALLER" example.test --cert-dir "$CERT_DIR"
    assert_success
    assert_output "unchanged"
}

@test "install-letsencrypt re-enforces ACLs on unchanged certificates" {
    make_lineage example.test
    bash "$INSTALLER" example.test --cert-dir "$CERT_DIR"
    setfacl -b "$CERT_DIR/nginx/cert.key"

    run bash "$INSTALLER" example.test --cert-dir "$CERT_DIR"
    assert_success
    assert_output "unchanged"
    run getfacl --omit-header "$CERT_DIR/nginx/cert.key"
    assert_output --partial "user:100:r--"
}

@test "install-letsencrypt detects a rotated certificate" {
    make_lineage example.test 1
    bash "$INSTALLER" example.test --cert-dir "$CERT_DIR"
    local before
    before=$(cert_fingerprint "$CERT_DIR/nginx/cert.crt")

    # A renewal adds a new version to archive/ and repoints the live/ symlinks
    make_lineage example.test 2

    run bash "$INSTALLER" example.test --cert-dir "$CERT_DIR"
    assert_success
    assert_output "installed"

    local after
    after=$(cert_fingerprint "$CERT_DIR/nginx/cert.crt")
    assert [ "$before" != "$after" ]
    # The installed copy matches the renewed lineage - this fingerprint
    # comparison is exactly what make ssl-renew uses to trigger the reload
    run cmp -s "$CERT_DIR/letsencrypt/archive/example.test/fullchain2.pem" "$CERT_DIR/nginx/cert.crt"
    assert_success
}

@test "install-letsencrypt auto-detects a single lineage" {
    make_lineage example.test

    run bash "$INSTALLER" --cert-dir "$CERT_DIR"
    assert_success
    assert_output "installed"
}

@test "install-letsencrypt requires an explicit domain with multiple lineages" {
    make_lineage one.test
    make_lineage two.test

    run bash "$INSTALLER" --cert-dir "$CERT_DIR"
    assert_failure
    assert_output --partial "Multiple lineages"

    run bash "$INSTALLER" two.test --cert-dir "$CERT_DIR"
    assert_success
    assert_output "installed"
}

@test "install-letsencrypt fails without a certificate lineage" {
    run bash "$INSTALLER" --cert-dir "$CERT_DIR"
    assert_failure
    assert_output --partial "No certificate lineage"
}

@test "install-letsencrypt fails when the private key is missing" {
    make_lineage example.test
    rm "$CERT_DIR/letsencrypt/archive/example.test/privkey1.pem"

    run bash "$INSTALLER" example.test --cert-dir "$CERT_DIR"
    assert_failure
    assert_output --partial "privkey.pem not found"
}

# =============================================================================
# setup-letsencrypt.sh argument validation (no ACME traffic)
# =============================================================================

@test "setup-letsencrypt requires domain and email" {
    run bash "$SETUP_SCRIPT"
    assert_failure
    assert_output --partial "Usage:"
}

@test "setup-letsencrypt rejects --staging combined with --server" {
    run bash "$SETUP_SCRIPT" example.test admin@example.test \
        --staging --server https://localhost:14000/dir
    assert_failure
    assert_output --partial "mutually exclusive"
}

@test "setup-letsencrypt rejects unknown options" {
    run bash "$SETUP_SCRIPT" example.test admin@example.test --bogus
    assert_failure
    assert_output --partial "Unknown option"
}
