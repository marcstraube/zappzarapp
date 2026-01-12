#!/bin/sh
# Docker PHP Entrypoint Script (Development)
# Handles dependency installation and Git configuration

set -e

echo "[entrypoint] Starting PHP container..."
echo "[entrypoint] ENV: ${ENV:-production}"

# Configure Git safe directory for Dev Dashboard (system-wide, applies to all users)
git config --system --add safe.directory /var/www/html

# Fix composer files: Docker image layers are read-only, we need to move them
# to the writable overlay layer to allow composer to update them.
# This is a workaround for Docker's copy-on-write limitation with file_put_contents.
for file in /var/www/html/composer.json /var/www/html/composer.lock; do
    if [ -f "$file" ]; then
        cp "$file" "${file}.tmp"
        rm "$file"
        mv "${file}.tmp" "$file"
        chown www-data:www-data "$file"
    fi
done

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
