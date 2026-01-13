#!/bin/sh
# PHP-FPM Entrypoint
# Sets up timezone from TZ environment variable before starting PHP-FPM

# Create /etc/localtime symlink if TZ is set
# This is required for PHP to correctly read the timezone on Alpine Linux
if [ -n "$TZ" ] && [ -f "/usr/share/zoneinfo/$TZ" ]; then
    ln -sf "/usr/share/zoneinfo/$TZ" /etc/localtime
    echo "$TZ" > /etc/timezone
fi

# Execute the main command (php-fpm)
exec "$@"
