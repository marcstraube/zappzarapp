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
    echo "⚠️⚠️⚠️  CRITICAL SECURITY WARNING  ⚠️⚠️⚠️" >&2
    echo "CORS_ORIGINS is set to wildcard (*) in production!" >&2
    echo "This is a severe security risk. Set specific origins immediately." >&2
    echo "⚠️⚠️⚠️⚠️⚠️⚠️⚠️⚠️⚠️⚠️⚠️⚠️⚠️⚠️⚠️⚠️⚠️⚠️⚠️⚠️⚠️" >&2
fi

exec "$@"
