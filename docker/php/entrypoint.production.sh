#!/bin/sh
# shellcheck shell=sh
# PHP-FPM Production Entrypoint (Unprivileged)
# Runs as www-data user - no root, no capabilities needed
# Secrets are bind-mounted with mode 0644 (world-readable) in compose.production.yaml

set -e

# Set environment variables for secrets (direct access, no copy needed)
# Secrets are already readable via ./secrets:/run/secrets:ro mount
if [ -d "/run/secrets" ]; then
    if [ -f "/run/secrets/db_password" ]; then
        export DB_PASSWORD_FILE=/run/secrets/db_password
    fi
    if [ -f "/run/secrets/encryption_key" ]; then
        export ENCRYPTION_KEY_FILE=/run/secrets/encryption_key
    fi
    if [ -f "/run/secrets/backup_encryption_key" ]; then
        export BACKUP_ENCRYPTION_KEY_FILE=/run/secrets/backup_encryption_key
    fi
fi

# CORS Configuration Warning
if [ "$CORS_ORIGINS" = "*" ]; then
    echo "[entrypoint] CRITICAL: CORS_ORIGINS is set to wildcard (*) in production" >&2
    echo "[entrypoint] PHP allows cross-origin requests from ANY domain" >&2
    echo "[entrypoint] Security policy: Must restrict origins in production environments" >&2
    echo "[entrypoint]" >&2
    echo "[entrypoint] Fix: Update .env.production with specific origins:" >&2
    echo "[entrypoint]   CORS_ORIGINS=https://yourdomain.com,https://api.yourdomain.com" >&2
    echo "[entrypoint]" >&2
    echo "[entrypoint] Container will start WITH insecure CORS configuration (WARNING only)." >&2
fi

exec "$@"
