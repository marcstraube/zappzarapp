#!/bin/sh
# shellcheck shell=sh
# docker/mercure/entrypoint.sh
# Entrypoint wrapper to load JWT secret from Docker Secret file

# Load JWT secret from file if available
# Support both with and without .txt extension for flexibility
if [ -f /run/secrets/mercure_jwt_secret.txt ]; then
    MERCURE_PUBLISHER_JWT_KEY="$(cat /run/secrets/mercure_jwt_secret.txt)"
    MERCURE_SUBSCRIBER_JWT_KEY="$MERCURE_PUBLISHER_JWT_KEY"
    export MERCURE_PUBLISHER_JWT_KEY MERCURE_SUBSCRIBER_JWT_KEY
elif [ -f /run/secrets/mercure_jwt_secret ]; then
    MERCURE_PUBLISHER_JWT_KEY="$(cat /run/secrets/mercure_jwt_secret)"
    MERCURE_SUBSCRIBER_JWT_KEY="$MERCURE_PUBLISHER_JWT_KEY"
    export MERCURE_PUBLISHER_JWT_KEY MERCURE_SUBSCRIBER_JWT_KEY
fi

# Translate the canonical comma-separated CORS_ORIGINS list (shared with the
# PHP/Node services) into Mercure's cors_origins directive, which expects
# space-separated origins ("*" passes through unchanged).
if [ -n "${CORS_ORIGINS:-}" ]; then
    cors_directive="cors_origins $(printf '%s' "$CORS_ORIGINS" | tr ',' ' ')"
    if [ -n "${MERCURE_EXTRA_DIRECTIVES:-}" ]; then
        MERCURE_EXTRA_DIRECTIVES="$MERCURE_EXTRA_DIRECTIVES
$cors_directive"
    else
        MERCURE_EXTRA_DIRECTIVES="$cors_directive"
    fi
    export MERCURE_EXTRA_DIRECTIVES
fi

# Execute the original Mercure entrypoint
exec /usr/bin/caddy run --config /etc/caddy/Caddyfile --adapter caddyfile
