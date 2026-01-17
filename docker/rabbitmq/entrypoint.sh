#!/bin/bash
# RabbitMQ Entrypoint Wrapper
# Loads credentials from Docker Secrets for RabbitMQ 4.x
# (RABBITMQ_DEFAULT_USER_FILE and RABBITMQ_DEFAULT_PASS_FILE are deprecated in 4.0)

set -e

# Load user from secret file if available (support both with and without .txt)
if [ -f /run/secrets/rabbitmq_user.txt ]; then
    export RABBITMQ_DEFAULT_USER=$(cat /run/secrets/rabbitmq_user.txt)
elif [ -f /run/secrets/rabbitmq_user ]; then
    export RABBITMQ_DEFAULT_USER=$(cat /run/secrets/rabbitmq_user)
fi

# Load password from secret file if available (support both with and without .txt)
if [ -f /run/secrets/rabbitmq_password.txt ]; then
    export RABBITMQ_DEFAULT_PASS=$(cat /run/secrets/rabbitmq_password.txt)
elif [ -f /run/secrets/rabbitmq_password ]; then
    export RABBITMQ_DEFAULT_PASS=$(cat /run/secrets/rabbitmq_password)
fi

# Execute the original entrypoint
exec docker-entrypoint.sh "$@"
