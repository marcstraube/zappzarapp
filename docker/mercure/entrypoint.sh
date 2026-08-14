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
# Every entry is validated first: the value ends up as raw Caddyfile config
# (via MERCURE_EXTRA_DIRECTIVES), so anything that is not a well-formed
# origin - whitespace, newlines, stray directives - would be config
# injection and must abort the start instead.
if [ -n "${CORS_ORIGINS:-}" ]; then
    validated=""
    # set -f: the unquoted split below must not glob (a "*" entry would
    # otherwise expand to the files in the working directory)
    set -f
    IFS=','
    for origin in $CORS_ORIGINS; do
        [ -z "$origin" ] && continue
        case "$origin" in
            *[!A-Za-z0-9:/.*-]*)
                echo "[entrypoint] FATAL: CORS_ORIGINS entry contains characters invalid in an origin: '$origin'" >&2
                exit 1
                ;;
            \*|http://?*|https://?*)
                validated="${validated:+$validated }$origin"
                ;;
            *)
                echo "[entrypoint] FATAL: CORS_ORIGINS entry is not an origin (expected http(s)://host[:port] or *): '$origin'" >&2
                exit 1
                ;;
        esac
    done
    unset IFS
    set +f
    if [ -n "$validated" ]; then
        cors_directive="cors_origins $validated"
        if [ -n "${MERCURE_EXTRA_DIRECTIVES:-}" ]; then
            MERCURE_EXTRA_DIRECTIVES="$MERCURE_EXTRA_DIRECTIVES
$cors_directive"
        else
            MERCURE_EXTRA_DIRECTIVES="$cors_directive"
        fi
        export MERCURE_EXTRA_DIRECTIVES
    fi
fi

# Execute the original Mercure entrypoint
exec /usr/bin/caddy run --config /etc/caddy/Caddyfile --adapter caddyfile
