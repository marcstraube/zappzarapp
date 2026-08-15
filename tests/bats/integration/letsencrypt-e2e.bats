#!/usr/bin/env bats
# Integration Tests: ACME end-to-end flow against Pebble
#
# Issues and renews a certificate through the real certbot/ACME protocol
# using Pebble (the Let's Encrypt project's ACME test server) plus
# pebble-challtestsrv as its DNS backend - no public domain or real ACME
# infrastructure required.
#
# These tests are SKIPPED by default unless explicitly enabled:
#   BATS_ENABLE_ACME_E2E=true make bats-test-integration-file FILE=letsencrypt-e2e.bats
#
# Environment assumptions:
# - Docker with host networking available (the standard bats-test setup)
# - Host ports 14000/15000 (Pebble), 8053/8055 (challtestsrv) and 5002
#   (HTTP-01 challenge) are free
#
# Topology (everything on the host network):
# - challtestsrv answers Pebble's DNS queries with 127.0.0.1
# - Pebble (ACME directory at https://localhost:14000/dir) validates
#   HTTP-01 against 127.0.0.1:5002
# - certbot (Docker standalone mode) serves the challenge on port 5002

load 'setup'

PEBBLE_IMAGE="${PEBBLE_IMAGE:-ghcr.io/letsencrypt/pebble:latest}"
CHALLTESTSRV_IMAGE="${CHALLTESTSRV_IMAGE:-ghcr.io/letsencrypt/pebble-challtestsrv:latest}"
PEBBLE_CONTAINER="zappzarapp-acme-pebble"
CHALLTESTSRV_CONTAINER="zappzarapp-acme-challtestsrv"
E2E_DOMAIN="acme-e2e.test"

acme_e2e_enabled() {
    [[ "${BATS_ENABLE_ACME_E2E:-false}" == "true" ]]
}

setup_file() {
    acme_e2e_enabled || return 0

    # Host-visible path: the certbot container bind mounts it through the
    # Docker socket, so it must exist identically on host and in the bats
    # container ($(PWD) is mounted at the same path by make bats-test)
    E2E_DIR="${PROJECT_ROOT}/build/tmp/acme-e2e"
    export E2E_DIR
    rm -rf "$E2E_DIR"
    mkdir -p "$E2E_DIR"

    docker rm -f "$PEBBLE_CONTAINER" "$CHALLTESTSRV_CONTAINER" >/dev/null 2>&1 || true

    # The images ship their binary as the entrypoint - pass flags only.
    # challtestsrv serves DNS only: its built-in challenge responders are
    # disabled because certbot serves the HTTP-01 challenge itself (and
    # they would occupy port 5002, where Pebble validates)
    echo "# Starting pebble-challtestsrv..." >&3
    docker run -d --name "$CHALLTESTSRV_CONTAINER" --network host \
        "$CHALLTESTSRV_IMAGE" -defaultIPv4 127.0.0.1 \
        -http01 "" -https01 "" -tlsalpn01 "" >/dev/null

    # PEBBLE_WFE_NONCEREJECT=0: Pebble rejects a percentage of valid nonces
    # by default to exercise client retry logic - deterministic tests need
    # that off
    echo "# Starting Pebble..." >&3
    docker run -d --name "$PEBBLE_CONTAINER" --network host \
        -e PEBBLE_VA_NOSLEEP=1 \
        -e PEBBLE_WFE_NONCEREJECT=0 \
        "$PEBBLE_IMAGE" -config test/config/pebble-config.json \
        -dnsserver 127.0.0.1:8053 >/dev/null

    echo "# Waiting for the Pebble directory endpoint..." >&3
    local elapsed=0
    until curl -ks https://localhost:14000/dir >/dev/null 2>&1; do
        if [[ $elapsed -ge 30 ]]; then
            echo "# Pebble did not become ready:" >&3
            docker logs "$PEBBLE_CONTAINER" 2>&1 | tail -20 | sed 's/^/# /' >&3 || true
            return 1
        fi
        sleep 1
        elapsed=$((elapsed + 1))
    done

    # Pebble's ACME endpoint serves TLS signed by the minica CA shipped in
    # the image; certbot needs that bundle to verify the connection
    docker cp "$PEBBLE_CONTAINER:/test/certs/pebble.minica.pem" \
        "$E2E_DIR/pebble.minica.pem"
}

teardown_file() {
    acme_e2e_enabled || return 0
    docker rm -f "$PEBBLE_CONTAINER" "$CHALLTESTSRV_CONTAINER" >/dev/null 2>&1 || true
    rm -rf "${PROJECT_ROOT}/build/tmp/acme-e2e"
}

setup() {
    load "${SCRIPT_DIR}/../helpers/setup.bash"

    if ! acme_e2e_enabled; then
        skip "ACME E2E tests disabled (set BATS_ENABLE_ACME_E2E=true to enable)"
    fi

    E2E_DIR="${PROJECT_ROOT}/build/tmp/acme-e2e"
    CERT_DIR="$E2E_DIR/certs"
}

cert_fingerprint() {
    openssl x509 -in "$1" -noout -fingerprint -sha256
}

@test "issues a certificate from Pebble and installs it for nginx" {
    run bash "${PROJECT_ROOT}/docker/certs/setup-letsencrypt.sh" \
        "$E2E_DOMAIN" "admin@${E2E_DOMAIN}" \
        --server https://localhost:14000/dir \
        --ca-bundle "$E2E_DIR/pebble.minica.pem" \
        --http-port 5002 \
        --cert-dir "$CERT_DIR" \
        --yes
    assert_success

    assert [ -f "$CERT_DIR/letsencrypt/live/$E2E_DOMAIN/fullchain.pem" ]
    assert [ -f "$CERT_DIR/nginx/cert.crt" ]
    assert [ -f "$CERT_DIR/nginx/cert.key" ]

    run openssl x509 -in "$CERT_DIR/nginx/cert.crt" -noout -issuer
    assert_output --partial "Pebble"

    # The key carries an ACL, so the group bits show the ACL mask - assert
    # on the ACL entries directly
    run getfacl --omit-header "$CERT_DIR/nginx/cert.key"
    assert_output --partial "user:100:r--"
    assert_output --partial "other::---"
}

@test "renewal replaces the installed certificate" {
    assert [ -f "$CERT_DIR/nginx/cert.crt" ]
    local before
    before=$(cert_fingerprint "$CERT_DIR/nginx/cert.crt")

    # --force-renewal: the certificate just issued above is nowhere near
    # expiry, and the test asserts on the replacement actually happening.
    # Same container setup as the Docker branch of setup-letsencrypt.sh:
    # invoking user, work/logs under the mounted config dir
    docker run --rm --network host \
        --user "$(id -u):$(id -g)" \
        -v "$CERT_DIR/letsencrypt:/etc/letsencrypt" \
        -v "$E2E_DIR/pebble.minica.pem:/acme-ca.pem:ro" \
        -e REQUESTS_CA_BUNDLE=/acme-ca.pem \
        certbot/certbot renew --force-renewal --http-01-port 5002 \
        --work-dir /etc/letsencrypt/work \
        --logs-dir /etc/letsencrypt/logs

    run bash "${PROJECT_ROOT}/docker/certs/install-letsencrypt.sh" --cert-dir "$CERT_DIR"
    assert_success
    assert_output "installed"

    local after
    after=$(cert_fingerprint "$CERT_DIR/nginx/cert.crt")
    assert [ "$before" != "$after" ]
}
