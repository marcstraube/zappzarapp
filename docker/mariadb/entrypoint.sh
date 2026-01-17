#!/bin/sh
set -e

# Create certs directory if it doesn't exist
mkdir -p /etc/mysql/certs

# Copy SSL certificates with correct permissions if they exist
# MariaDB requires the key file to be owned by mysql user or root
# Bind-mounted files keep host ownership, so we copy them to set correct permissions
#
# Priority order:
# 1. /tmp/certs/cert.crt + cert.key (Compose Production bind mount)
# 2. /tmp/certs/server.crt + server.key (Development bind mount)
SSL_CERT=""
SSL_KEY=""

if [ -f /tmp/certs/cert.crt ] && [ -f /tmp/certs/cert.key ]; then
    SSL_CERT="/tmp/certs/cert.crt"
    SSL_KEY="/tmp/certs/cert.key"
    echo "[entrypoint] Found SSL certificates at /tmp/certs/cert.{crt,key}"
elif [ -f /tmp/certs/server.crt ] && [ -f /tmp/certs/server.key ]; then
    SSL_CERT="/tmp/certs/server.crt"
    SSL_KEY="/tmp/certs/server.key"
    echo "[entrypoint] Found SSL certificates at /tmp/certs/server.{crt,key}"
fi

if [ -n "$SSL_CERT" ] && [ -n "$SSL_KEY" ]; then
    echo "[entrypoint] Setting up SSL certificates with correct ownership..."
    cp "$SSL_CERT" /etc/mysql/certs/server.crt
    cp "$SSL_KEY" /etc/mysql/certs/server.key
    chown mysql:mysql /etc/mysql/certs/server.crt /etc/mysql/certs/server.key
    chmod 644 /etc/mysql/certs/server.crt
    chmod 600 /etc/mysql/certs/server.key
    echo "[entrypoint] SSL certificates configured successfully."
fi

# Execute the original entrypoint
exec docker-entrypoint.sh "$@"
