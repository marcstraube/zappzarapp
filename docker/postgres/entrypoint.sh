#!/bin/sh
set -e

# Copy SSL certificates with correct permissions if they exist
if [ -f /tmp/certs/server.crt ] && [ -f /tmp/certs/server.key ]; then
    echo "Setting up SSL certificates..."
    cp /tmp/certs/server.crt /var/lib/postgresql/server.crt
    cp /tmp/certs/server.key /var/lib/postgresql/server.key
    chown postgres:postgres /var/lib/postgresql/server.crt /var/lib/postgresql/server.key
    chmod 644 /var/lib/postgresql/server.crt
    chmod 600 /var/lib/postgresql/server.key
    echo "SSL certificates configured successfully."
fi

# Execute the original entrypoint
exec docker-entrypoint.sh "$@"
