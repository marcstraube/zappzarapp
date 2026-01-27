#!/bin/sh
# shellcheck shell=sh
# Node.js Production Entrypoint
#
# This entrypoint runs as the node user (UID 50000) directly.
# Secrets are bind-mounted from ./secrets with mode 0644 (readable by all).
#
# For Kubernetes: Secrets use native K8s Secrets with securityContext.fsGroup.
# For Compose: Secrets are bind-mounted as volumes.

set -e

# Set environment variables pointing to secret files
if [ -d "/run/secrets" ]; then
    if [ -f "/run/secrets/db_password.txt" ]; then
        export DB_PASSWORD_FILE=/run/secrets/db_password.txt
    fi
    if [ -f "/run/secrets/encryption_key.txt" ]; then
        export ENCRYPTION_KEY_FILE=/run/secrets/encryption_key.txt
    fi
    if [ -f "/run/secrets/backup_encryption_key.txt" ]; then
        export BACKUP_ENCRYPTION_KEY_FILE=/run/secrets/backup_encryption_key.txt
    fi
fi

# CORS Configuration Warning
if [ "$CORS_ORIGINS" = "*" ]; then
    echo "[entrypoint] CRITICAL: CORS_ORIGINS is set to wildcard (*) in production" >&2
    echo "[entrypoint] Node.js allows cross-origin requests from ANY domain" >&2
    echo "[entrypoint] Security policy: Must restrict origins in production environments" >&2
    echo "[entrypoint]" >&2
    echo "[entrypoint] Fix: Update .env.production with specific origins:" >&2
    echo "[entrypoint]   CORS_ORIGINS=https://yourdomain.com,https://api.yourdomain.com" >&2
    echo "[entrypoint]" >&2
    echo "[entrypoint] Container will start WITH insecure CORS configuration (WARNING only)." >&2
fi

# Execute the command
exec "$@"
