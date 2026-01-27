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

# Production Environment Validation
# Ensure NODE_ENV is explicitly set to "production"
if [ "${NODE_ENV:-}" != "production" ]; then
    echo "[entrypoint] SECURITY WARNING: NODE_ENV is not set to 'production'" >&2
    echo "[entrypoint] Current value: ${NODE_ENV:-<not set>}" >&2
    echo "[entrypoint] This may enable debug features or insecure defaults" >&2
    echo "[entrypoint]" >&2
fi

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

# CORS Configuration Security Check
# CRITICAL: Wildcard CORS origins are a security risk in production
# They allow ANY domain to make authenticated cross-origin requests
if [ "${CORS_ORIGINS:-}" = "*" ]; then
    echo "[entrypoint] SECURITY ERROR: CORS_ORIGINS wildcard (*) forbidden in production" >&2
    echo "[entrypoint]" >&2
    echo "[entrypoint] Wildcard origins allow ANY domain to:" >&2
    echo "[entrypoint]   - Make authenticated cross-origin requests" >&2
    echo "[entrypoint]   - Access user sessions and sensitive data" >&2
    echo "[entrypoint]   - Perform CSRF attacks" >&2
    echo "[entrypoint]" >&2
    echo "[entrypoint] Fix: Set specific origins in .env.production:" >&2
    echo "[entrypoint]   CORS_ORIGINS=https://yourdomain.com,https://api.yourdomain.com" >&2
    echo "[entrypoint]" >&2
    echo "[entrypoint] Container startup aborted for security." >&2
    exit 1
fi

# Validate CORS_ORIGINS format if set
if [ -n "${CORS_ORIGINS:-}" ] && [ "${CORS_ORIGINS}" != "null" ]; then
    # Check for common misconfigurations
    case "${CORS_ORIGINS}" in
        *"://*."*|*"://*:*")
            echo "[entrypoint] WARNING: CORS_ORIGINS contains wildcard subdomain patterns" >&2
            echo "[entrypoint] This may expose the application to subdomain attacks" >&2
            ;;
    esac
fi

# Execute the command
exec "$@"
