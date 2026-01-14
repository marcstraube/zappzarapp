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
ES_URL="http://localhost:9200"
REPO_NAME="backup_repo"

# Load .env if exists
if [[ -f "$PROJECT_ROOT/.env" ]]; then
    # shellcheck disable=SC1091
    source "$PROJECT_ROOT/.env"
fi

# Get encryption key
BACKUP_ENCRYPTION_KEY="${BACKUP_ENCRYPTION_KEY:-}"
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
read -p "Continue? (yes/no): " CONFIRM
if [[ "$CONFIRM" != "yes" ]]; then
    echo -e "${YELLOW}Restore cancelled.${NC}"
    exit 0
fi

# Wait for Elasticsearch to be ready
echo -e "${YELLOW}Waiting for Elasticsearch to be ready...${NC}"
for i in {1..30}; do
    if docker compose exec -T elasticsearch curl -s "$ES_URL/_cluster/health" >/dev/null 2>&1; then
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
    openssl enc -aes-256-cbc -d -pbkdf2 -pass pass:"$BACKUP_ENCRYPTION_KEY" -in "$BACKUP_FILE" \
        | tar -xzf - -C "$TEMP_DIR"
else
    tar -xzf "$BACKUP_FILE" -C "$TEMP_DIR"
fi

# Copy snapshot data to container
echo -e "${YELLOW}Copying snapshot to Elasticsearch...${NC}"
docker compose exec -T elasticsearch sh -c "mkdir -p /usr/share/elasticsearch/backup"
docker compose cp "$TEMP_DIR/es-snapshot/." elasticsearch:/usr/share/elasticsearch/backup/

# Register snapshot repository
echo -e "${YELLOW}Registering snapshot repository...${NC}"
docker compose exec -T elasticsearch sh -c "
    curl -s -X PUT '$ES_URL/_snapshot/$REPO_NAME' -H 'Content-Type: application/json' -d '{
        \"type\": \"fs\",
        \"settings\": {
            \"location\": \"/usr/share/elasticsearch/backup\",
            \"compress\": true
        }
    }'
" >/dev/null

# Get available snapshots
echo -e "${YELLOW}Finding snapshot to restore...${NC}"
SNAPSHOT_NAME=$(docker compose exec -T elasticsearch curl -s "$ES_URL/_snapshot/$REPO_NAME/_all" \
    | grep -o '"snapshot":"[^"]*"' | head -1 | cut -d'"' -f4)

if [[ -z "$SNAPSHOT_NAME" ]]; then
    echo -e "${RED}Error: No snapshot found in backup.${NC}"
    rm -rf "$TEMP_DIR"
    exit 1
fi

echo -e "Found snapshot: ${GREEN}$SNAPSHOT_NAME${NC}"

# Close all indices before restore
echo -e "${YELLOW}Closing indices for restore...${NC}"
docker compose exec -T elasticsearch curl -s -X POST "$ES_URL/_all/_close?wait_for_active_shards=0" >/dev/null 2>&1 || true

# Restore snapshot
echo -e "${YELLOW}Restoring snapshot...${NC}"
docker compose exec -T elasticsearch sh -c "
    curl -s -X POST '$ES_URL/_snapshot/$REPO_NAME/$SNAPSHOT_NAME/_restore?wait_for_completion=true' -H 'Content-Type: application/json' -d '{
        \"ignore_unavailable\": true,
        \"include_global_state\": false
    }'
"

# Cleanup
rm -rf "$TEMP_DIR"
docker compose exec -T elasticsearch sh -c "rm -rf /usr/share/elasticsearch/backup/*" 2>/dev/null || true

echo -e "${GREEN}=== Restore Complete ===${NC}"
