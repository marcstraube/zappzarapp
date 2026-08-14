#!/bin/sh
# shellcheck shell=sh
# Redis TLS Health Check and TLS-aware redis-cli wrapper
# Handles both development and production cert paths (bind mounts).
# Extra arguments are passed to redis-cli in place of the default `ping`,
# so tooling can run commands against the TLS-only port without duplicating
# the certificate detection (e.g. `healthcheck.sh FLUSHALL`).

# Production paths (bind-mounted to /etc/redis/certs/)
PROD_CERT="/etc/redis/certs/cert.crt"
PROD_KEY="/etc/redis/certs/cert.key"
PROD_CA="/etc/redis/certs/ca.crt"

# Development paths (compose.override.yaml bind-mounts)
DEV_CERT="/etc/ssl/certs/redis.crt"
DEV_KEY="/etc/ssl/private/redis.key"
DEV_CA="/etc/ssl/certs/internal-ca.crt"

# Determine which paths to use
if [ -f "$PROD_CERT" ] && [ -f "$PROD_KEY" ]; then
    CERT="$PROD_CERT"
    KEY="$PROD_KEY"
    # Use CA if available, otherwise use cert itself (self-signed)
    if [ -f "$PROD_CA" ]; then
        CA="$PROD_CA"
    else
        CA="$PROD_CERT"
    fi
elif [ -f "$DEV_CERT" ] && [ -f "$DEV_KEY" ]; then
    CERT="$DEV_CERT"
    KEY="$DEV_KEY"
    # Use CA if available, otherwise use cert itself (self-signed)
    if [ -f "$DEV_CA" ]; then
        CA="$DEV_CA"
    else
        CA="$DEV_CERT"
    fi
else
    # Fallback: try without TLS (for non-TLS setups)
    [ $# -eq 0 ] && set -- ping
    exec redis-cli "$@"
fi

[ $# -eq 0 ] && set -- ping
exec redis-cli --tls --cert "$CERT" --key "$KEY" --cacert "$CA" "$@"
