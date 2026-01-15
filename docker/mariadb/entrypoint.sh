#!/bin/sh
set -e

# Create certs directory if it doesn't exist
mkdir -p /etc/mysql/certs

# Copy SSL certificates with correct permissions if they exist
# Priority: Docker secrets (production) > /tmp/certs (development bind-mount)
if [ -f /run/secrets/ssl_cert ] && [ -f /run/secrets/ssl_key ]; then
    echo "[entrypoint] Setting up SSL certificates from Docker secrets..."
    cp /run/secrets/ssl_cert /etc/mysql/certs/server.crt
    cp /run/secrets/ssl_key /etc/mysql/certs/server.key
    chown mysql:mysql /etc/mysql/certs/server.crt /etc/mysql/certs/server.key
    chmod 644 /etc/mysql/certs/server.crt
    chmod 600 /etc/mysql/certs/server.key
    echo "[entrypoint] SSL certificates configured successfully."
elif [ -f /tmp/certs/server.crt ] && [ -f /tmp/certs/server.key ]; then
    echo "[entrypoint] Setting up SSL certificates from /tmp/certs..."
    cp /tmp/certs/server.crt /etc/mysql/certs/server.crt
    cp /tmp/certs/server.key /etc/mysql/certs/server.key
    chown mysql:mysql /etc/mysql/certs/server.crt /etc/mysql/certs/server.key
    chmod 644 /etc/mysql/certs/server.crt
    chmod 600 /etc/mysql/certs/server.key
    echo "[entrypoint] SSL certificates configured successfully."
fi

# Execute the original entrypoint
exec docker-entrypoint.sh "$@"
