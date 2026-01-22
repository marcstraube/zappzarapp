#!/bin/sh
# shellcheck shell=sh
# Meilisearch entrypoint script
# Reads secrets from files and sets environment variables

# Load master key from file if specified
if [ -n "$MEILI_MASTER_KEY_FILE" ] && [ -f "$MEILI_MASTER_KEY_FILE" ]; then
    MEILI_MASTER_KEY="$(cat "$MEILI_MASTER_KEY_FILE")"
    export MEILI_MASTER_KEY
fi

# Execute meilisearch
exec meilisearch "$@"
