#!/bin/sh
set -e

echo "Starting PHP-FPM container..."

# Create Vendor Directory if not present
if [ ! -d "/var/www/html/vendor" ]; then
    mkdir -p /var/www/html/vendor
fi

# Set correct permissions for vendor
chown -R www-data:www-data /var/www/html/vendor 2>/dev/null || true

# Install Composer Dependencies if composer.json exists
if [ -f "/var/www/html/composer.json" ]; then
    echo "Installing Composer dependencies..."

    # Set Composer configuration based on Environment
    if [ "$ENV" = "development" ]; then
        composer install --working-dir=/var/www/html --prefer-dist --no-interaction
    else
        composer install --working-dir=/var/www/html --prefer-dist --no-interaction --no-dev --optimize-autoloader
    fi

    echo "Composer dependencies installed."
else
    echo "No composer.json found, skipping Composer installation."
fi

# Execute original Command
exec "$@"
