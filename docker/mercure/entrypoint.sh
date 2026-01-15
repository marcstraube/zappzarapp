#!/bin/sh
# docker/mercure/entrypoint.sh
# Entrypoint wrapper to load JWT secret from Docker Secret file

# Load JWT secret from file if available
if [ -f /run/secrets/mercure_jwt_secret ]; then
    export MERCURE_PUBLISHER_JWT_KEY=$(cat /run/secrets/mercure_jwt_secret)
    export MERCURE_SUBSCRIBER_JWT_KEY=$(cat /run/secrets/mercure_jwt_secret)
fi

# Execute the original Mercure entrypoint
exec /usr/bin/caddy run --config /etc/caddy/Caddyfile --adapter caddyfile
