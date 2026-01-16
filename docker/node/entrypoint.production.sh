#!/bin/sh
# Node.js Production Entrypoint
# Runs as root, copies secrets to readable location, then drops to node user
#
# WHY THIS IS NEEDED:
# In Docker Compose, secrets are mounted with host UID and mode 0600.
# The node user (UID 50000) cannot read them directly.
# This entrypoint copies secrets to /tmp/secrets/ with mode 0444 before
# dropping privileges to the node user.
#
# In Docker Swarm, secrets have proper uid/gid/mode, so this is a harmless no-op.

set -e

# Copy secrets to readable location (as root, for node user)
if [ -d "/run/secrets" ]; then
    mkdir -p /tmp/secrets
    chmod 755 /tmp/secrets
    for secret in /run/secrets/*; do
        if [ -f "$secret" ]; then
            name=$(basename "$secret")
            cp "$secret" "/tmp/secrets/$name" && chmod 444 "/tmp/secrets/$name"
        fi
    done

    # Update environment variables to point to readable location
    if [ -f "/tmp/secrets/db_password" ]; then
        export DB_PASSWORD_FILE=/tmp/secrets/db_password
    fi
    if [ -f "/tmp/secrets/encryption_key" ]; then
        export ENCRYPTION_KEY_FILE=/tmp/secrets/encryption_key
    fi
    if [ -f "/tmp/secrets/backup_encryption_key" ]; then
        export BACKUP_ENCRYPTION_KEY_FILE=/tmp/secrets/backup_encryption_key
    fi
fi

# Drop privileges and execute command as node user
exec su-exec node "$@"
