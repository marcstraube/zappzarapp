#!/bin/bash
# =============================================================================
# ELASTICSEARCH BACKUP SCRIPT
# =============================================================================
# Creates snapshots of Elasticsearch indices using the Snapshot API.
#
# Usage:
#   ./backup-elasticsearch.sh [options]
#
# Options:
#   -o, --output DIR       Output directory (default: ./backups/elasticsearch)
#   -r, --retention DAYS   Keep backups for N days (default: 30, 0 = keep all)
#   -n, --no-encrypt       Skip encryption (not recommended for production)
#   -i, --indices PATTERN  Indices to backup (default: * = all)
#   -h, --help             Show this help message
#
# Environment Variables:
#   BACKUP_ENCRYPTION_KEY  Encryption key for backups (required unless --no-encrypt)
#
# Output:
#   Encrypted:   elasticsearch_{TIMESTAMP}.tar.gz.enc
#   Unencrypted: elasticsearch_{TIMESTAMP}.tar.gz
#
# Notes:
#   - Uses Elasticsearch Snapshot API
#   - Creates a filesystem repository in the container
#   - Exports snapshot to local backup directory
# =============================================================================

set -euo pipefail

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Default values
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
OUTPUT_DIR="$PROJECT_ROOT/backups/elasticsearch"
ENCRYPT=true
INDICES="*"
ES_URL="http://localhost:9200"
REPO_NAME="backup_repo"

# Load .env if exists
if [[ -f "$PROJECT_ROOT/.env" ]]; then
    # shellcheck disable=SC1091
    source "$PROJECT_ROOT/.env"
fi

# Retention from env or default
RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-30}"
BACKUP_ENCRYPTION_KEY="${BACKUP_ENCRYPTION_KEY:-}"
# Exported so openssl can read it via -pass env: (keeps the key out of argv/ps)
export BACKUP_ENCRYPTION_KEY

# Try to read from secrets if not set
if [[ -z "$BACKUP_ENCRYPTION_KEY" && -f "$PROJECT_ROOT/secrets/backup_encryption_key.txt" ]]; then
    BACKUP_ENCRYPTION_KEY=$(cat "$PROJECT_ROOT/secrets/backup_encryption_key.txt")
fi

# Help message
show_help() {
    head -30 "$0" | tail -25 | sed 's/^# //' | sed 's/^#//'
    exit 0
}

# Parse arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        -o|--output)
            OUTPUT_DIR="$2"
            shift 2
            ;;
        -r|--retention)
            RETENTION_DAYS="$2"
            shift 2
            ;;
        -n|--no-encrypt)
            ENCRYPT=false
            shift
            ;;
        -i|--indices)
            INDICES="$2"
            shift 2
            ;;
        -h|--help)
            show_help
            ;;
        *)
            echo -e "${RED}Unknown option: $1${NC}"
            echo "Use --help for usage information"
            exit 1
            ;;
    esac
done

# Check if Elasticsearch is running
if ! docker compose ps elasticsearch 2>/dev/null | grep -q "Up"; then
    echo -e "${RED}Error: Elasticsearch container is not running.${NC}"
    echo -e "${YELLOW}Start with: ENABLE_ELASTICSEARCH=true make up${NC}"
    exit 1
fi

# Check encryption key if encryption is enabled
if [[ "$ENCRYPT" == true && -z "$BACKUP_ENCRYPTION_KEY" ]]; then
    echo -e "${RED}Error: BACKUP_ENCRYPTION_KEY is not set.${NC}"
    echo -e "${YELLOW}Either set it in .env, create secrets/backup_encryption_key.txt, or use --no-encrypt.${NC}"
    exit 1
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

# Create output directory
mkdir -p "$OUTPUT_DIR"

# Timestamp for filename
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_NAME="elasticsearch_${TIMESTAMP}"
SNAPSHOT_NAME="snapshot_${TIMESTAMP}"

echo -e "${BLUE}=== Elasticsearch Backup ===${NC}"
echo -e "Output Dir:    ${GREEN}$OUTPUT_DIR${NC}"
echo -e "Indices:       ${GREEN}$INDICES${NC}"
echo -e "Encryption:    ${GREEN}$([[ "$ENCRYPT" == true ]] && echo "enabled" || echo "disabled")${NC}"
echo ""

# Create snapshot repository (if not exists)
echo -e "${YELLOW}Setting up snapshot repository...${NC}"
docker compose exec -T elasticsearch sh -c "
    mkdir -p /usr/share/elasticsearch/backup
    curl -s -X PUT '$ES_URL/_snapshot/$REPO_NAME' -H 'Content-Type: application/json' -d '{
        \"type\": \"fs\",
        \"settings\": {
            \"location\": \"/usr/share/elasticsearch/backup\",
            \"compress\": true
        }
    }' || true
" >/dev/null 2>&1

# Create snapshot
echo -e "${YELLOW}Creating snapshot of indices: $INDICES${NC}"
docker compose exec -T elasticsearch sh -c "
    # Delete old snapshot if exists
    curl -s -X DELETE '$ES_URL/_snapshot/$REPO_NAME/$SNAPSHOT_NAME' 2>/dev/null || true

    # Create new snapshot
    curl -s -X PUT '$ES_URL/_snapshot/$REPO_NAME/$SNAPSHOT_NAME?wait_for_completion=true' -H 'Content-Type: application/json' -d '{
        \"indices\": \"$INDICES\",
        \"ignore_unavailable\": true,
        \"include_global_state\": false
    }'
"

# Check snapshot status
SNAPSHOT_STATUS=$(docker compose exec -T elasticsearch curl -s "$ES_URL/_snapshot/$REPO_NAME/$SNAPSHOT_NAME" | grep -o '"state":"[^"]*"' | head -1 || echo "")
if [[ "$SNAPSHOT_STATUS" != *"SUCCESS"* ]]; then
    echo -e "${YELLOW}Warning: Snapshot may not have completed successfully.${NC}"
fi

# Export snapshot to local directory
echo -e "${YELLOW}Exporting snapshot...${NC}"
TEMP_DIR=$(mktemp -d)

# Copy snapshot data from container
docker compose cp elasticsearch:/usr/share/elasticsearch/backup "$TEMP_DIR/es-snapshot"

# Create tarball
echo -e "${YELLOW}Creating compressed archive...${NC}"
if [[ "$ENCRYPT" == true ]]; then
    tar -czf - -C "$TEMP_DIR" . \
        | openssl enc -aes-256-cbc -salt -pbkdf2 -pass env:BACKUP_ENCRYPTION_KEY \
        > "$OUTPUT_DIR/${BACKUP_NAME}.tar.gz.enc"
    BACKUP_FILE="${BACKUP_NAME}.tar.gz.enc"
else
    tar -czf "$OUTPUT_DIR/${BACKUP_NAME}.tar.gz" -C "$TEMP_DIR" .
    BACKUP_FILE="${BACKUP_NAME}.tar.gz"
fi

# Cleanup temp directory and container snapshot
rm -rf "$TEMP_DIR"
docker compose exec -T elasticsearch sh -c "rm -rf /usr/share/elasticsearch/backup/*" 2>/dev/null || true

# Calculate backup size
BACKUP_SIZE=$(du -h "$OUTPUT_DIR/$BACKUP_FILE" | cut -f1)

echo -e "${GREEN}Backup created successfully!${NC}"
echo -e "  File: ${BLUE}$OUTPUT_DIR/$BACKUP_FILE${NC}"
echo -e "  Size: ${BLUE}$BACKUP_SIZE${NC}"

# Apply retention policy
if [[ "$RETENTION_DAYS" -gt 0 ]]; then
    echo ""
    echo -e "${YELLOW}Applying retention policy ($RETENTION_DAYS days)...${NC}"

    DELETED_COUNT=0
    while IFS= read -r -d '' old_backup; do
        rm -f "$old_backup"
        DELETED_COUNT=$((DELETED_COUNT + 1))
        echo -e "  Deleted: $(basename "$old_backup")"
    done < <(find "$OUTPUT_DIR" -name "elasticsearch_*.tar.gz*" -type f -mtime +"$RETENTION_DAYS" -print0 2>/dev/null)

    if [[ "$DELETED_COUNT" -eq 0 ]]; then
        echo -e "  ${GREEN}No old backups to delete.${NC}"
    else
        echo -e "  ${GREEN}Deleted $DELETED_COUNT old backup(s).${NC}"
    fi
fi

# Log backup event
LOG_DIR="$PROJECT_ROOT/storage/logs"
mkdir -p "$LOG_DIR"
echo "{\"timestamp\":\"$(date -Iseconds)\",\"action\":\"backup_created\",\"service\":\"elasticsearch\",\"indices\":\"$INDICES\",\"file\":\"$BACKUP_FILE\",\"size\":\"$BACKUP_SIZE\",\"encrypted\":$ENCRYPT}" >> "$LOG_DIR/backup.log"

echo ""
echo -e "${GREEN}=== Backup Complete ===${NC}"
