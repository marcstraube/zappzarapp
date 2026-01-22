#!/bin/sh
# shellcheck shell=sh
# Docker PHP Entrypoint Script (Development)
# Validates dependencies (for php-fpm) and configures development environment

set -e

# Copy secrets to readable location (development only)
# Host files stay secure at 600, copies at /tmp/secrets are 444
# Application code checks /tmp/secrets first, falls back to /run/secrets
if [ -d "/run/secrets" ]; then
    mkdir -p /tmp/secrets
    chmod 755 /tmp/secrets
    for secret in /run/secrets/*; do
        if [ -f "$secret" ]; then
            name=$(basename "$secret")
            cp "$secret" "/tmp/secrets/$name" && chmod 444 "/tmp/secrets/$name"
        fi
    done
    echo "[entrypoint.development] Secrets copied to /tmp/secrets (readable)"
fi

# Configure Git safe directory for Dev Dashboard (system-wide, applies to all users)
git config --system --add safe.directory /var/www/html 2>/dev/null || true

# Fix vendor volume permissions (named volume created as root)
if [ -d "/var/www/html/vendor" ]; then
    chown -R www-data:www-data /var/www/html/vendor 2>/dev/null || true
fi

# Only validate dependencies when starting php-fpm service (not for composer/other commands)
# Skip dependency check if PHP_SKIP_DEPENDENCY_CHECK=1 (used in CI for docker compose exec)
if [ "$1" = "php-fpm" ]; then
    echo "[entrypoint.development] Starting PHP-FPM service..."
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
