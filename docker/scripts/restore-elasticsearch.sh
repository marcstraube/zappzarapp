#!/bin/bash
# =============================================================================
# ELASTICSEARCH RESTORE SCRIPT
# =============================================================================
# Restores Elasticsearch indices from a snapshot backup.
#
# Usage:
#   ./restore-elasticsearch.sh <backup-file>
#
# Arguments:
#   backup-file    Path to the backup file (.tar.gz or .tar.gz.enc)
#
# Environment Variables:
#   BACKUP_ENCRYPTION_KEY  Required for encrypted backups
# =============================================================================

set -euo pipefail

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Script paths
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
ES_URL="https://localhost:9200"
REPO_NAME="backup_repo"

# Authenticated, TLS-aware curl inside the elasticsearch container: the
# bootstrap password is read from the container-mounted secret and travels
# as a curl config on stdin (-K -), never in argv (visible to other
# processes). -k: the internal certificate is self-signed.
es_curl() {
    docker compose exec -T elasticsearch sh -c \
        'printf "user = \"elastic:%s\"\n" "$(cat /run/secrets/elasticsearch_bootstrap_password.txt)" | curl -sk -K - "$@"' \
        sh "$@"
}

# Load .env if exists
if [[ -f "$PROJECT_ROOT/.env" ]]; then
    # shellcheck disable=SC1091
    source "$PROJECT_ROOT/.env"
fi

# Get encryption key
BACKUP_ENCRYPTION_KEY="${BACKUP_ENCRYPTION_KEY:-}"
# Exported so openssl can read it via -pass env: (keeps the key out of argv/ps)
export BACKUP_ENCRYPTION_KEY
if [[ -z "$BACKUP_ENCRYPTION_KEY" && -f "$PROJECT_ROOT/secrets/backup_encryption_key.txt" ]]; then
    BACKUP_ENCRYPTION_KEY=$(cat "$PROJECT_ROOT/secrets/backup_encryption_key.txt")
fi

# Check arguments
if [[ $# -lt 1 ]]; then
    echo -e "${RED}Usage: $0 <backup-file>${NC}"
    exit 1
fi

BACKUP_FILE="$1"

# Verify backup file exists
if [[ ! -f "$BACKUP_FILE" ]]; then
    echo -e "${RED}Error: Backup file not found: $BACKUP_FILE${NC}"
    exit 1
fi

# Check if Elasticsearch is running
if ! docker compose ps elasticsearch 2>/dev/null | grep -q "Up"; then
    echo -e "${RED}Error: Elasticsearch container is not running.${NC}"
    echo -e "${YELLOW}Start with: ENABLE_ELASTICSEARCH=true make up${NC}"
    exit 1
fi

# Determine if encrypted
IS_ENCRYPTED=false
if [[ "$BACKUP_FILE" == *.enc ]]; then
    IS_ENCRYPTED=true
    if [[ -z "$BACKUP_ENCRYPTION_KEY" ]]; then
        echo -e "${RED}Error: Backup is encrypted but BACKUP_ENCRYPTION_KEY is not set.${NC}"
        exit 1
    fi
fi

echo -e "${BLUE}=== Elasticsearch Restore ===${NC}"
echo -e "Backup File: ${GREEN}$BACKUP_FILE${NC}"
echo -e "Encrypted:   ${GREEN}$IS_ENCRYPTED${NC}"
echo ""

# Confirmation
echo -e "${YELLOW}WARNING: This will close existing indices and restore from snapshot!${NC}"
read -rp "Continue? (yes/no): " CONFIRM
if [[ "$CONFIRM" != "yes" ]]; then
    echo -e "${YELLOW}Restore cancelled.${NC}"
    exit 0
fi

# Wait for Elasticsearch to be ready
echo -e "${YELLOW}Waiting for Elasticsearch to be ready...${NC}"
for i in {1..30}; do
    if es_curl -f "$ES_URL/_cluster/health" >/dev/null 2>&1; then
        break
    fi
    if [[ $i -eq 30 ]]; then
        echo -e "${RED}Error: Elasticsearch is not responding.${NC}"
        exit 1
    fi
    sleep 2
done

# Create temp directory
TEMP_DIR=$(mktemp -d)

# Extract backup
echo -e "${YELLOW}Extracting backup...${NC}"
if [[ "$IS_ENCRYPTED" == true ]]; then
    openssl enc -aes-256-cbc -d -pbkdf2 -pass env:BACKUP_ENCRYPTION_KEY -in "$BACKUP_FILE" \
        | tar -xzf - -C "$TEMP_DIR"
else
    tar -xzf "$BACKUP_FILE" -C "$TEMP_DIR"
fi

# Copy snapshot data to container
echo -e "${YELLOW}Copying snapshot to Elasticsearch...${NC}"
# Repository mountpoint preflight: the named volume must be writable by
# the server user (uid 1000). Volumes created before the image carried the
# owned mountpoint are root-owned - heal that via a root exec (works in
# development; the production preset drops CAP_CHOWN, there recreate the
# volume so it inherits the image ownership).
docker compose exec -T -u root elasticsearch chown 1000:0 /usr/share/elasticsearch/backup 2>/dev/null || true
if ! docker compose exec -T elasticsearch sh -c 'touch /usr/share/elasticsearch/backup/.write-probe && rm -f /usr/share/elasticsearch/backup/.write-probe'; then
    echo -e "${RED}Error: /usr/share/elasticsearch/backup is not writable by the server user.${NC}"
    echo -e "${YELLOW}Recreate the elasticsearch-backup volume (docker volume rm after 'make down') so it inherits the image ownership.${NC}"
    exit 1
fi
docker compose cp "$TEMP_DIR/es-snapshot/." elasticsearch:/usr/share/elasticsearch/backup/

# Register snapshot repository
echo -e "${YELLOW}Registering snapshot repository...${NC}"
REPO_RESPONSE=$(es_curl -X PUT "$ES_URL/_snapshot/$REPO_NAME" -H 'Content-Type: application/json' -d '{
    "type": "fs",
    "settings": {
        "location": "/usr/share/elasticsearch/backup",
        "compress": true
    }
}')
if [[ "$REPO_RESPONSE" == *'"error"'* ]]; then
    echo -e "${RED}Error: could not register the snapshot repository:${NC}"
    echo "$REPO_RESPONSE"
    rm -rf "$TEMP_DIR"
    exit 1
fi

# Get available snapshots
echo -e "${YELLOW}Finding snapshot to restore...${NC}"
SNAPSHOT_NAME=$(es_curl "$ES_URL/_snapshot/$REPO_NAME/_all" \
    | grep -o '"snapshot":"[^"]*"' | head -1 | cut -d'"' -f4)

if [[ -z "$SNAPSHOT_NAME" ]]; then
    echo -e "${RED}Error: No snapshot found in backup.${NC}"
    rm -rf "$TEMP_DIR"
    exit 1
fi

echo -e "Found snapshot: ${GREEN}$SNAPSHOT_NAME${NC}"

# Close only the indices contained in the snapshot: a blanket _all/_close
# fails on data streams and would leave unrelated indices closed
echo -e "${YELLOW}Closing indices contained in the snapshot...${NC}"
SNAPSHOT_INDICES=$(es_curl "$ES_URL/_snapshot/$REPO_NAME/$SNAPSHOT_NAME" \
    | jq -r '.snapshots[0].indices | join(",")' 2>/dev/null || echo "")
if [[ -n "$SNAPSHOT_INDICES" ]]; then
    es_curl -X POST "$ES_URL/$SNAPSHOT_INDICES/_close?wait_for_active_shards=0" >/dev/null 2>&1 || true
fi

# Restore snapshot (system/dot indices stay excluded even for snapshots
# taken with an unfiltered pattern - restoring them collides with the
# live cluster state)
echo -e "${YELLOW}Restoring snapshot...${NC}"
RESTORE_RESPONSE=$(es_curl -X POST "$ES_URL/_snapshot/$REPO_NAME/$SNAPSHOT_NAME/_restore?wait_for_completion=true" -H 'Content-Type: application/json' -d '{
    "indices": "*,-.*",
    "ignore_unavailable": true,
    "include_global_state": false
}')
if [[ "$RESTORE_RESPONSE" == *'"error"'* ]]; then
    echo -e "${RED}Error: restore failed:${NC}"
    echo "$RESTORE_RESPONSE"
    rm -rf "$TEMP_DIR"
    exit 1
fi

# Cleanup
rm -rf "$TEMP_DIR"
docker compose exec -T elasticsearch sh -c "rm -rf /usr/share/elasticsearch/backup/*" 2>/dev/null || true

echo -e "${GREEN}=== Restore Complete ===${NC}"
