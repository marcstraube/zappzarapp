#!/bin/sh
# Redis TLS Health Check
# Handles both development and production cert paths (bind mounts)

# Production paths (bind-mounted to /etc/redis/certs/)
PROD_CERT="/etc/redis/certs/cert.crt"
PROD_KEY="/etc/redis/certs/cert.key"

# Development paths (compose.override.yaml bind-mounts)
DEV_CERT="/etc/ssl/certs/redis.crt"
DEV_KEY="/etc/ssl/private/redis.key"

# Determine which paths to use
if [ -f "$PROD_CERT" ] && [ -f "$PROD_KEY" ]; then
    CERT="$PROD_CERT"
    KEY="$PROD_KEY"
    CA="$PROD_CERT"
elif [ -f "$DEV_CERT" ] && [ -f "$DEV_KEY" ]; then
    CERT="$DEV_CERT"
    KEY="$DEV_KEY"
    CA="$DEV_CERT"
else
    # Fallback: try without TLS (for non-TLS setups)
    exec redis-cli ping
fi

exec redis-cli --tls --cert "$CERT" --key "$KEY" --cacert "$CA" ping
