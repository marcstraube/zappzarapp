#!/bin/bash
# RabbitMQ Entrypoint Wrapper
# Loads credentials from Docker Secrets for RabbitMQ 4.x
# (RABBITMQ_DEFAULT_USER_FILE and RABBITMQ_DEFAULT_PASS_FILE are deprecated in 4.0)

set -e

# Load user from secret file if available (support both with and without .txt)
if [[ -f /run/secrets/rabbitmq_user.txt ]]; then
    RABBITMQ_DEFAULT_USER=$(cat /run/secrets/rabbitmq_user.txt)
    export RABBITMQ_DEFAULT_USER
elif [[ -f /run/secrets/rabbitmq_user ]]; then
    RABBITMQ_DEFAULT_USER=$(cat /run/secrets/rabbitmq_user)
    export RABBITMQ_DEFAULT_USER
fi

# Load password from secret file if available (support both with and without .txt)
if [[ -f /run/secrets/rabbitmq_password.txt ]]; then
    RABBITMQ_DEFAULT_PASS=$(cat /run/secrets/rabbitmq_password.txt)
    export RABBITMQ_DEFAULT_PASS
elif [[ -f /run/secrets/rabbitmq_password ]]; then
    RABBITMQ_DEFAULT_PASS=$(cat /run/secrets/rabbitmq_password)
    export RABBITMQ_DEFAULT_PASS
fi

# Ensure .erlang.cookie has correct permissions for healthcheck
# The cookie is created by the original entrypoint, but we ensure permissions here
# so that rabbitmq-diagnostics (healthcheck) can read it without DAC_READ_SEARCH capability
COOKIE_FILE="/var/lib/rabbitmq/.erlang.cookie"
if [[ -f "$COOKIE_FILE" ]]; then
    chown rabbitmq:rabbitmq "$COOKIE_FILE"
    chmod 400 "$COOKIE_FILE"
elif [[ ! -f "$COOKIE_FILE" ]] && [[ -n "$RABBITMQ_ERLANG_COOKIE" ]]; then
    # Create cookie with correct permissions if RABBITMQ_ERLANG_COOKIE is set
    echo "$RABBITMQ_ERLANG_COOKIE" > "$COOKIE_FILE"
    chown rabbitmq:rabbitmq "$COOKIE_FILE"
    chmod 400 "$COOKIE_FILE"
fi

# Execute the original entrypoint
exec docker-entrypoint.sh "$@"
