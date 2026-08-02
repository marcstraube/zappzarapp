#!/bin/sh
# shellcheck shell=sh
# Note: This script intentionally uses POSIX sh for Alpine compatibility
# SC2292 (prefer [[ ]]) doesn't apply to POSIX sh scripts
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
else
    # No SSL certificates found - behavior depends on environment
    ENV_MODE="${ZAPPZARAPP_ENV:-development}"

    # Diagnostic information about what was found
    if [ -d /tmp/certs ]; then
        echo "[entrypoint] WARNING: /tmp/certs exists but no valid certificates found"
        echo "[entrypoint] Expected: /tmp/certs/cert.{crt,key} or /tmp/certs/server.{crt,key}"
        echo "[entrypoint] Found files in /tmp/certs:"
        ls -la /tmp/certs/ 2>/dev/null || echo "  (directory is empty or not accessible)"
    else
        echo "[entrypoint] INFO: No /tmp/certs directory found (SSL certificates not mounted)"
    fi

    # Environment-specific behavior: Fail-Fast in production, warn in development
    if [ "$ENV_MODE" = "production" ]; then
        # PRODUCTION: SSL is MANDATORY for security compliance
        echo "[entrypoint] FATAL: SSL certificates are required in production mode"
        echo "[entrypoint] PostgreSQL REQUIRES encrypted connections in production environments"
        echo "[entrypoint] Security policy: Databases must not transmit data unencrypted"
        echo "[entrypoint]"
        echo "[entrypoint] Fix: Ensure SSL certificates are properly mounted:"
        echo "[entrypoint]   1. Run: make ssl-internal"
        echo "[entrypoint]   2. Verify compose.production.yaml mounts: ./docker/certs/internal:/tmp/certs:ro"
        echo "[entrypoint]   3. Ensure files exist: docker/certs/internal/cert.{crt,key}"
        echo "[entrypoint]"
        echo "[entrypoint] Container startup ABORTED to prevent security violation."
        exit 1  # Fail-Fast: Do not start database without SSL in production
    else
        # DEVELOPMENT: Warn but allow (local development flexibility)
        echo "[entrypoint] WARNING: PostgreSQL will start WITHOUT SSL support (development mode)"
        echo "[entrypoint] This is acceptable for local development but NOT for production"
        echo "[entrypoint] To test with SSL locally: run 'make ssl-internal'"
    fi
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

# For existing volumes: Run init script after PostgreSQL is ready
# (init scripts in /docker-entrypoint-initdb.d/ only run on first init)
PGDATA="${PGDATA:-/var/lib/postgresql/data/pgdata}"
if [ -d "$PGDATA" ] && [ -f "$PGDATA/PG_VERSION" ]; then
    echo "[entrypoint] Existing database detected, scheduling post-start initialization..."
    # Run init in background after postgres is ready
    (
        # Wait for PostgreSQL to be ready (max 60 seconds)
        for _ in $(seq 1 60); do
            if pg_isready -U "${POSTGRES_USER:-app}" -q 2>/dev/null; then
                echo "[entrypoint] PostgreSQL ready, running initialization..."
                # Run as postgres user using gosu
                gosu postgres /docker-entrypoint-initdb.d/10-init-db.sh || true
                break
            fi
            sleep 1
        done
    ) &
fi

# Execute the original entrypoint
exec docker-entrypoint.sh "$@"
