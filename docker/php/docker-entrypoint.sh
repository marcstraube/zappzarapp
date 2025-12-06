#!/bin/sh
set -e

cd /var/www/html

# Install Composer Dependencies if composer.json exists
if [ -f "composer.json" ] && [ ! -f "vendor/autoload.php" ]; then
    echo "Development mode: Installing Composer dependencies..."
    composer install --prefer-dist --no-interaction
    echo "Composer dependencies installed."
fi

# Execute original Command
exec "$@"
