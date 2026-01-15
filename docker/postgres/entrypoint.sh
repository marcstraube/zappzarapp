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
# Priority: Docker secrets (production) > /tmp/certs (development bind-mount)
if [ -f /run/secrets/ssl_cert ] && [ -f /run/secrets/ssl_key ]; then
    echo "[entrypoint] Setting up SSL certificates from Docker secrets..."
    cat /run/secrets/ssl_cert > /var/lib/postgresql/server.crt
    cat /run/secrets/ssl_key > /var/lib/postgresql/server.key
    chown postgres:postgres /var/lib/postgresql/server.crt /var/lib/postgresql/server.key
    chmod 644 /var/lib/postgresql/server.crt
    chmod 600 /var/lib/postgresql/server.key
    echo "[entrypoint] SSL certificates configured successfully."
elif [ -f /tmp/certs/server.crt ] && [ -f /tmp/certs/server.key ]; then
    echo "[entrypoint] Setting up SSL certificates from /tmp/certs..."
    cat /tmp/certs/server.crt > /var/lib/postgresql/server.crt
    cat /tmp/certs/server.key > /var/lib/postgresql/server.key
    chown postgres:postgres /var/lib/postgresql/server.crt /var/lib/postgresql/server.key
    chmod 644 /var/lib/postgresql/server.crt
    chmod 600 /var/lib/postgresql/server.key
    echo "[entrypoint] SSL certificates configured successfully."
fi

# Execute the original entrypoint
exec docker-entrypoint.sh "$@"
