#!/bin/bash
# =============================================================================
# SEAWEEDFS BACKUP SCRIPT
# =============================================================================
# Creates encrypted, compressed backups of all SeaweedFS data.
#
# Usage:
#   ./backup-seaweedfs.sh [options]
#
# Options:
#   -o, --output DIR       Output directory (default: ./backups/seaweedfs)
#   -r, --retention DAYS   Keep backups for N days (default: 30, 0 = keep all)
#   -n, --no-encrypt       Skip encryption (not recommended for production)
#   -h, --help             Show this help message
#
# Environment Variables (from .env or secrets):
#   BACKUP_ENCRYPTION_KEY  Encryption key for backups (required unless --no-encrypt)
#
# Output:
#   Encrypted:   seaweedfs_{TIMESTAMP}.tar.gz.enc
#   Unencrypted: seaweedfs_{TIMESTAMP}.tar.gz
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
OUTPUT_DIR="$PROJECT_ROOT/backups/seaweedfs"
ENCRYPT=true

# Load .env if exists
if [[ -f "$PROJECT_ROOT/.env" ]]; then
    # shellcheck disable=SC1091
    source "$PROJECT_ROOT/.env"
fi

# Retention from env or default
RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-30}"

# Encryption key
BACKUP_ENCRYPTION_KEY="${BACKUP_ENCRYPTION_KEY:-}"

# Try to read from secrets if not set
if [[ -z "$BACKUP_ENCRYPTION_KEY" && -f "$PROJECT_ROOT/secrets/backup_encryption_key.txt" ]]; then
    BACKUP_ENCRYPTION_KEY=$(cat "$PROJECT_ROOT/secrets/backup_encryption_key.txt")
fi

# Help message
show_help() {
    head -22 "$0" | tail -17 | sed 's/^# //' | sed 's/^#//'
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

# Check if SeaweedFS is running
if ! docker compose ps seaweedfs 2>/dev/null | grep -q "Up"; then
    echo -e "${RED}Error: SeaweedFS container is not running.${NC}"
    echo -e "${YELLOW}Start with: ENABLE_SEAWEEDFS=true make up${NC}"
    exit 1
fi

# Check encryption key if encryption is enabled
if [[ "$ENCRYPT" == true && -z "$BACKUP_ENCRYPTION_KEY" ]]; then
    echo -e "${RED}Error: BACKUP_ENCRYPTION_KEY is not set.${NC}"
    echo -e "${YELLOW}Either set it in .env, create secrets/backup_encryption_key.txt, or use --no-encrypt.${NC}"
    exit 1
fi

# Create output directory
mkdir -p "$OUTPUT_DIR"

# Timestamp for filename
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_NAME="seaweedfs_${TIMESTAMP}"
TEMP_DIR=$(mktemp -d)

echo -e "${BLUE}=== SeaweedFS Backup ===${NC}"
echo -e "Output Dir:    ${GREEN}$OUTPUT_DIR${NC}"
echo -e "Encryption:    ${GREEN}$([[ "$ENCRYPT" == true ]] && echo "enabled" || echo "disabled")${NC}"
echo ""

# Export SeaweedFS data
# SeaweedFS stores data in /data directory
echo -e "${YELLOW}Exporting SeaweedFS data...${NC}"

# Create a snapshot of the data directory
docker compose exec -T seaweedfs sh -c "
    # Create export directory
    mkdir -p /tmp/backup-export

    # Copy all data (master, volume, and filer data)
    cp -r /data/* /tmp/backup-export/ 2>/dev/null || true

    # Copy configuration if exists
    cp -r /etc/seaweedfs/config/*.json /tmp/backup-export/ 2>/dev/null || true
"

# Copy exported data from container
docker compose cp seaweedfs:/tmp/backup-export "$TEMP_DIR/seaweedfs-data" 2>/dev/null || {
    # If no data, create empty dir
    mkdir -p "$TEMP_DIR/seaweedfs-data"
}

# Clean up temp files in container
docker compose exec -T seaweedfs sh -c "rm -rf /tmp/backup-export" 2>/dev/null || true

# Create tarball
echo -e "${YELLOW}Creating compressed archive...${NC}"
if [[ "$ENCRYPT" == true ]]; then
    tar -czf - -C "$TEMP_DIR" . \
        | openssl enc -aes-256-cbc -salt -pbkdf2 -pass pass:"$BACKUP_ENCRYPTION_KEY" \
        > "$OUTPUT_DIR/${BACKUP_NAME}.tar.gz.enc"
    BACKUP_FILE="${BACKUP_NAME}.tar.gz.enc"
else
    tar -czf "$OUTPUT_DIR/${BACKUP_NAME}.tar.gz" -C "$TEMP_DIR" .
    BACKUP_FILE="${BACKUP_NAME}.tar.gz"
fi

# Cleanup temp directory
rm -rf "$TEMP_DIR"

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
        ((DELETED_COUNT++))
        echo -e "  Deleted: $(basename "$old_backup")"
    done < <(find "$OUTPUT_DIR" -name "seaweedfs_*.tar.gz*" -type f -mtime +"$RETENTION_DAYS" -print0 2>/dev/null)

    if [[ "$DELETED_COUNT" -eq 0 ]]; then
        echo -e "  ${GREEN}No old backups to delete.${NC}"
    else
        echo -e "  ${GREEN}Deleted $DELETED_COUNT old backup(s).${NC}"
    fi
fi

# Log backup event
LOG_DIR="$PROJECT_ROOT/storage/logs"
mkdir -p "$LOG_DIR"
echo "{\"timestamp\":\"$(date -Iseconds)\",\"action\":\"backup_created\",\"service\":\"seaweedfs\",\"file\":\"$BACKUP_FILE\",\"size\":\"$BACKUP_SIZE\",\"encrypted\":$ENCRYPT}" >> "$LOG_DIR/backup.log"

echo ""
echo -e "${GREEN}=== Backup Complete ===${NC}"
