#!/bin/sh
# pgAdmin entrypoint wrapper
# Loads default password from Docker secrets before starting pgAdmin

set -e

# Load pgAdmin password from secrets file if exists
if [ -f "/run/secrets/pgadmin_password.txt" ]; then
    export PGADMIN_DEFAULT_PASSWORD="$(cat /run/secrets/pgadmin_password.txt)"
fi

# Execute the original entrypoint
exec /entrypoint.sh "$@"
