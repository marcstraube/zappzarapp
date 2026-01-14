#!/bin/sh
# Docker PHP Entrypoint Script (Development)
# Validates dependencies (for php-fpm), sets up timezone and Git configuration

set -e

# Set up timezone from TZ environment variable
# Creates /etc/localtime symlink required for PHP on Alpine Linux
if [ -n "$TZ" ] && [ -f "/usr/share/zoneinfo/$TZ" ]; then
    ln -sf "/usr/share/zoneinfo/$TZ" /etc/localtime
    echo "$TZ" > /etc/timezone
fi

# Configure Git safe directory for Dev Dashboard (system-wide, applies to all users)
git config --system --add safe.directory /var/www/html 2>/dev/null || true

# Fix vendor volume permissions (named volume created as root)
if [ -d "/var/www/html/vendor" ]; then
    chown -R www-data:www-data /var/www/html/vendor 2>/dev/null || true
fi

# Only validate dependencies when starting php-fpm service (not for composer/other commands)
if [ "$1" = "php-fpm" ]; then
    echo "[entrypoint] Starting PHP-FPM service..."
    if [ ! -d "/var/www/html/vendor" ] || [ ! -f "/var/www/html/vendor/autoload.php" ]; then
        echo "[entrypoint] ERROR: Composer dependencies not installed!"
        echo "[entrypoint] Run 'make composer-install' to install dependencies."
        exit 1
    fi
    echo "[entrypoint] Dependencies OK"
fi

# Execute the main command
# php-fpm handles user switching internally (configured in php-fpm.conf)
# All other commands (composer, phpunit, etc.) run as www-data
if [ "$1" = "php-fpm" ]; then
    exec "$@"
else
    exec su-exec www-data "$@"
fi
