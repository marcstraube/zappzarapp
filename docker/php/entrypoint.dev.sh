#!/bin/sh
set -e

# Configure Git safe directory for Dev Dashboard
# This allows the dashboard to read git status even though .git is owned by a different user
git config --global --add safe.directory /var/www/html

# Execute the main command (php-fpm)
exec "$@"
