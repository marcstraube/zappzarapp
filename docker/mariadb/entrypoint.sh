#!/bin/bash
set -e

# Create certs directory if it doesn't exist
mkdir -p /etc/mysql/certs

# Copy SSL certificates with correct permissions if they exist
if [ -f /tmp/certs/server.crt ] && [ -f /tmp/certs/server.key ]; then
    echo "Setting up SSL certificates for MariaDB..."
    cp /tmp/certs/server.crt /etc/mysql/certs/server.crt
    cp /tmp/certs/server.key /etc/mysql/certs/server.key
    chown mysql:mysql /etc/mysql/certs/server.crt /etc/mysql/certs/server.key
    chmod 644 /etc/mysql/certs/server.crt
    chmod 600 /etc/mysql/certs/server.key
    echo "SSL certificates configured successfully for MariaDB."
fi

# Execute the original entrypoint
exec docker-entrypoint.sh "$@"
