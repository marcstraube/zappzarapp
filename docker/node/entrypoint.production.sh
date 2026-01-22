#!/bin/sh
# shellcheck shell=sh
# Node.js Production Entrypoint
#
# This entrypoint runs as the node user (UID 50000) directly.
# Secrets are bind-mounted from ./secrets with mode 0644 (readable by all).
#
# For Kubernetes: Secrets use native K8s Secrets with securityContext.fsGroup.
# For Compose: Secrets are bind-mounted as volumes.

set -e

# Set environment variables pointing to secret files
if [ -d "/run/secrets" ]; then
    if [ -f "/run/secrets/db_password.txt" ]; then
        export DB_PASSWORD_FILE=/run/secrets/db_password.txt
    fi
    if [ -f "/run/secrets/encryption_key.txt" ]; then
        export ENCRYPTION_KEY_FILE=/run/secrets/encryption_key.txt
    fi
    if [ -f "/run/secrets/backup_encryption_key.txt" ]; then
        export BACKUP_ENCRYPTION_KEY_FILE=/run/secrets/backup_encryption_key.txt
    fi
fi

# Execute the command
exec "$@"
