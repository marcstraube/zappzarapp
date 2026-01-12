#!/bin/sh
set -e

# Default values if not set
export NGINX_SSL_PORT=${NGINX_SSL_PORT:-8443}

# Process SSL config template if it exists
if [ -f /etc/nginx/conf.d/ssl-development.conf.template ]; then
    echo "Processing SSL configuration template..."
    envsubst '${NGINX_SSL_PORT}' < /etc/nginx/conf.d/ssl-development.conf.template > /etc/nginx/conf.d/ssl.conf
    chown nginx:nginx /etc/nginx/conf.d/ssl.conf
    echo "SSL configuration generated with NGINX_SSL_PORT=${NGINX_SSL_PORT}"
fi

# Start nginx as nginx user (not root)
exec "$@"
