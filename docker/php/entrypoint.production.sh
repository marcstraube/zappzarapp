#!/bin/sh
# shellcheck shell=sh
# PHP-FPM Production Entrypoint (Unprivileged)
# Runs as www-data user - no root, no capabilities needed
# Secrets are bind-mounted with mode 0644 (world-readable) in compose.production.yaml

set -e

# Configure PHP timezone from TZ environment variable
# Note: PHP does NOT automatically use the TZ env var for date.timezone
# We must explicitly configure it via INI file
if [ -n "${TZ:-}" ]; then
    echo "date.timezone = ${TZ}" > /usr/local/etc/php/conf.d/99-timezone.ini
    echo "[entrypoint.production] Configured PHP timezone: ${TZ}"
fi

# Production Environment Validation
# Ensure APP_ENV is explicitly set to "production"
if [ "${APP_ENV:-}" != "production" ]; then
    echo "[entrypoint] SECURITY WARNING: APP_ENV is not set to 'production'" >&2
    echo "[entrypoint] Current value: ${APP_ENV:-<not set>}" >&2
    echo "[entrypoint] This may enable debug features or insecure defaults" >&2
    echo "[entrypoint]" >&2
fi

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

exec "$@"
