#!/bin/sh
# Docker PHP Entrypoint Script (Development)
# Handles timezone setup, dependency installation and Git configuration

set -e

echo "[entrypoint] Starting PHP container..."
echo "[entrypoint] ENV: ${ENV:-production}"

# Set up timezone from TZ environment variable
# Creates /etc/localtime symlink required for PHP on Alpine Linux
if [ -n "$TZ" ] && [ -f "/usr/share/zoneinfo/$TZ" ]; then
    echo "[entrypoint] Setting timezone to $TZ"
    ln -sf "/usr/share/zoneinfo/$TZ" /etc/localtime
    echo "$TZ" > /etc/timezone
fi

# Configure Git safe directory for Dev Dashboard (system-wide, applies to all users)
git config --system --add safe.directory /var/www/html

# Fix vendor volume permissions (named volume created as root)
if [ -d "/var/www/html/vendor" ]; then
    echo "[entrypoint] Fixing vendor permissions..."
    chown -R www-data:www-data /var/www/html/vendor
fi

# Install dependencies if vendor doesn't exist, autoload missing, or composer.lock missing
if [ ! -d "/var/www/html/vendor" ] || [ ! -f "/var/www/html/vendor/autoload.php" ] || [ ! -f "/var/www/html/composer.lock" ]; then
    echo "[entrypoint] Installing dependencies (as www-data)..."
    # Switch to www-data user for composer install
    su www-data -s /bin/sh -c '
        # Check if composer.lock exists and is not empty
        if [ -f "/var/www/html/composer.lock" ] && [ -s "/var/www/html/composer.lock" ]; then
            echo "[entrypoint] Using existing composer.lock (frozen lockfile)"
            composer install --no-interaction --prefer-dist --optimize-autoloader
        else
            echo "[entrypoint] No valid lockfile found, generating new one..."
            composer install --no-interaction --prefer-dist --optimize-autoloader
        fi
    '
    echo "[entrypoint] Dependencies installed successfully"
else
    echo "[entrypoint] Dependencies already installed, skipping..."
fi

# Execute the main command (php-fpm)
exec "$@"
