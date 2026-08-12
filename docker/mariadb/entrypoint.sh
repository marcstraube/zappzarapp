#!/bin/sh
# shellcheck shell=sh
set -e

# Create certs directory if it doesn't exist
mkdir -p /etc/mysql/certs

# Copy SSL certificates with correct permissions if they exist
# MariaDB requires the key file to be owned by mysql user or root
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
        echo "[entrypoint] MariaDB REQUIRES encrypted connections in production environments"
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
        echo "[entrypoint] WARNING: MariaDB will start WITHOUT SSL support (development mode)"
        echo "[entrypoint] This is acceptable for local development but NOT for production"
        echo "[entrypoint] To test with SSL locally: run 'make ssl-internal'"
    fi
fi

if [ -n "$SSL_CERT" ] && [ -n "$SSL_KEY" ]; then
    echo "[entrypoint] Setting up SSL certificates with correct ownership..."
    # MariaDB requires the key to be owned by the mysql user or root; the
    # copy gives it the correct owner on both startup paths (root here in
    # development, uid 999 writing into a uid-999 tmpfs in the production
    # preset - only the root path can and needs to chown).
    cp "$SSL_CERT" /etc/mysql/certs/server.crt
    cp "$SSL_KEY" /etc/mysql/certs/server.key
    if [ "$(id -u)" = "0" ]; then
        chown mysql:mysql /etc/mysql/certs/server.crt /etc/mysql/certs/server.key
    fi
    chmod 644 /etc/mysql/certs/server.crt
    chmod 600 /etc/mysql/certs/server.key
    echo "[entrypoint] SSL certificates configured successfully."
fi

# For existing volumes: Run init script after MariaDB is ready
# (init scripts in /docker-entrypoint-initdb.d/ only run on first init)
DATADIR="/var/lib/mysql"
if [ -d "$DATADIR/mysql" ]; then
    echo "[entrypoint] Existing database detected, scheduling post-start initialization..."
    # Run init in background after mariadb is ready
    (
        # Wait for MariaDB to be ready (max 60 seconds)
        for _ in $(seq 1 60); do
            if healthcheck.sh --connect 2>/dev/null; then
                echo "[entrypoint] MariaDB ready, running initialization..."
                /docker-entrypoint-initdb.d/10-init-db.sh || true
                break
            fi
            sleep 1
        done
    ) &
fi

# Execute the original entrypoint
exec docker-entrypoint.sh "$@"
