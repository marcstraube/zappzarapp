#!/bin/sh
set -e

# Make secrets readable by postgres user
# Docker Compose file-based secrets are mounted as root:root with 600 permissions
# PostgreSQL needs to read them as the postgres user (uid 70)
if [ -d /run/secrets ]; then
    for secret in /run/secrets/*; do
        if [ -f "$secret" ]; then
            chmod 640 "$secret" 2>/dev/null || true
            chown root:postgres "$secret" 2>/dev/null || true
        fi
    done
fi

# Copy SSL certificates with correct permissions if they exist
# PostgreSQL requires the key file to be owned by postgres user (uid 70) or root
# Bind-mounted files keep host ownership, so we copy them to set correct permissions
#
# Priority order:
# 1. /tmp/certs/cert.crt + cert.key (Compose Production bind mount)
# 2. /tmp/certs/server.crt + server.key (Development bind mount)
SSL_CERT=""
SSL_KEY=""

if [ -f /tmp/certs/cert.crt ] && [ -f /tmp/certs/cert.key ]; then
    SSL_CERT="/tmp/certs/cert.crt"
    SSL_KEY="/tmp/certs/cert.key"
    echo "[entrypoint] Found SSL certificates at /tmp/certs/cert.{crt,key}"
elif [ -f /tmp/certs/server.crt ] && [ -f /tmp/certs/server.key ]; then
    SSL_CERT="/tmp/certs/server.crt"
    SSL_KEY="/tmp/certs/server.key"
    echo "[entrypoint] Found SSL certificates at /tmp/certs/server.{crt,key}"
fi

if [ -n "$SSL_CERT" ] && [ -n "$SSL_KEY" ]; then
    echo "[entrypoint] Setting up SSL certificates with correct ownership..."
    cat "$SSL_CERT" > /var/lib/postgresql/server.crt
    cat "$SSL_KEY" > /var/lib/postgresql/server.key
    chown postgres:postgres /var/lib/postgresql/server.crt /var/lib/postgresql/server.key
    chmod 644 /var/lib/postgresql/server.crt
    chmod 600 /var/lib/postgresql/server.key
    echo "[entrypoint] SSL certificates configured successfully."
fi

# Execute the original entrypoint
exec docker-entrypoint.sh "$@"
