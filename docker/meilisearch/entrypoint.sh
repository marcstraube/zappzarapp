#!/bin/sh
# Meilisearch entrypoint script
# Reads secrets from files and sets environment variables

# Load master key from file if specified
if [ -n "$MEILI_MASTER_KEY_FILE" ] && [ -f "$MEILI_MASTER_KEY_FILE" ]; then
    export MEILI_MASTER_KEY="$(cat "$MEILI_MASTER_KEY_FILE")"
fi

# Execute meilisearch
exec meilisearch "$@"
