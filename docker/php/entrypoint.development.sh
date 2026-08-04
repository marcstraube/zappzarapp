#!/bin/sh
# shellcheck shell=sh
# Docker PHP Entrypoint Script (Development)
# Validates dependencies (for php-fpm) and configures development environment

set -e

# Secrets are read directly from the /run/secrets bind mount
# (mode 0644 via 'make setup', readable by www-data)

# Configure PHP timezone from TZ environment variable
# Note: PHP does NOT automatically use the TZ env var for date.timezone
# We must explicitly configure it via INI file
if [ -n "${TZ:-}" ]; then
    echo "date.timezone = ${TZ}" > /usr/local/etc/php/conf.d/99-timezone.ini
    echo "[entrypoint.development] Configured PHP timezone: ${TZ}"
fi

# Configure Git safe directory for Dev Dashboard (system-wide, applies to all users)
git config --system --add safe.directory /var/www/html 2>/dev/null || true

# Fix vendor volume permissions (named volume created as root)
if [ -d "/var/www/html/vendor" ]; then
    chown -R www-data:www-data /var/www/html/vendor 2>/dev/null || true
fi

# Fix build directory permissions (for test coverage generation from dashboard)
if [ -d "/var/www/html/build" ]; then
    chown -R www-data:www-data /var/www/html/build 2>/dev/null || true
fi

# Only validate dependencies when starting php-fpm service (not for composer/other commands)
# Skip dependency check if PHP_SKIP_DEPENDENCY_CHECK=1 (used in CI for docker compose exec)
if [ "$1" = "php-fpm" ]; then
    echo "[entrypoint.development] Starting PHP-FPM service..."

    # CORS Configuration Warning
    if [ "${CORS_ORIGINS:-}" = "*" ]; then
        echo "[entrypoint.development] WARNING: CORS_ORIGINS is set to wildcard (*)" >&2
        echo "[entrypoint.development] Wildcard origin allows requests from ANY domain (development mode)" >&2
        echo "[entrypoint.development] Credentials header disabled for browser compatibility" >&2
        if [ "${ZAPPZARAPP_ENV:-development}" = "production" ]; then
            echo "[entrypoint.development] CRITICAL: Wildcard CORS in production environment detected!" >&2
            echo "[entrypoint.development] Fix: Set specific origins in .env.production" >&2
        fi
    fi

    if [ "${PHP_SKIP_DEPENDENCY_CHECK:-0}" != "1" ]; then
        if [ ! -d "/var/www/html/vendor" ] || [ ! -f "/var/www/html/vendor/autoload.php" ]; then
            echo "[entrypoint.development] ERROR: Composer dependencies not installed!"
            echo "[entrypoint.development] Run 'make composer-install' to install dependencies."
            exit 1
        fi
        echo "[entrypoint.development] Dependencies OK"
    else
        echo "[entrypoint.development] Dependency check skipped (PHP_SKIP_DEPENDENCY_CHECK=1)"
    fi
fi

# Execute the main command
# php-fpm handles user switching internally (configured in php-fpm.conf)
# All other commands (composer, phpunit, etc.) run as www-data
if [ "$1" = "php-fpm" ]; then
    exec "$@"
else
    exec su-exec www-data "$@"
fi
