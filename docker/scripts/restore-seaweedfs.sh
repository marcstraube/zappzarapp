#!/bin/bash
# =============================================================================
# SEAWEEDFS RESTORE SCRIPT
# =============================================================================
# Restores SeaweedFS data from an encrypted backup.
#
# Usage:
#   ./restore-seaweedfs.sh BACKUP_FILE
#
# Arguments:
#   BACKUP_FILE            Path to the backup file (.tar.gz or .tar.gz.enc)
#
# Options:
#   -f, --force            Skip confirmation prompt
#   -h, --help             Show this help message
#
# Environment Variables:
#   BACKUP_ENCRYPTION_KEY  Encryption key (required for .enc files)
#
# WARNING: This will overwrite existing SeaweedFS data!
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
FORCE=false
BACKUP_FILE=""

# Load .env if exists
if [[ -f "$PROJECT_ROOT/.env" ]]; then
    # shellcheck disable=SC1091
    source "$PROJECT_ROOT/.env"
fi

# Encryption key
BACKUP_ENCRYPTION_KEY="${BACKUP_ENCRYPTION_KEY:-}"

# Try to read from secrets if not set
if [[ -z "$BACKUP_ENCRYPTION_KEY" && -f "$PROJECT_ROOT/secrets/backup_encryption_key.txt" ]]; then
    BACKUP_ENCRYPTION_KEY=$(cat "$PROJECT_ROOT/secrets/backup_encryption_key.txt")
fi

# Help message
show_help() {
    head -19 "$0" | tail -14 | sed 's/^# //' | sed 's/^#//'
    exit 0
}

# Parse arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        -f|--force)
            FORCE=true
            shift
            ;;
        -h|--help)
            show_help
            ;;
        *)
            if [[ -z "$BACKUP_FILE" ]]; then
                BACKUP_FILE="$1"
            else
                echo -e "${RED}Unknown option: $1${NC}"
                echo "Use --help for usage information"
                exit 1
            fi
            shift
            ;;
    esac
done

# Check if backup file was provided
if [[ -z "$BACKUP_FILE" ]]; then
    echo -e "${RED}Error: No backup file specified.${NC}"
    echo "Usage: $0 BACKUP_FILE"
    echo "Use --help for more information"
    exit 1
fi

# Check if backup file exists
if [[ ! -f "$BACKUP_FILE" ]]; then
    echo -e "${RED}Error: Backup file not found: $BACKUP_FILE${NC}"
    exit 1
fi

# Check if encrypted and we have the key
if [[ "$BACKUP_FILE" == *.enc ]]; then
    if [[ -z "$BACKUP_ENCRYPTION_KEY" ]]; then
        echo -e "${RED}Error: BACKUP_ENCRYPTION_KEY is not set.${NC}"
        echo -e "${YELLOW}The backup file is encrypted. Set the key in .env or secrets/backup_encryption_key.txt.${NC}"
        exit 1
    fi
fi

# Check if SeaweedFS is running
if ! docker compose ps seaweedfs 2>/dev/null | grep -q "Up"; then
    echo -e "${RED}Error: SeaweedFS container is not running.${NC}"
    echo -e "${YELLOW}Start with: ENABLE_SEAWEEDFS=true make up${NC}"
    exit 1
fi

# Confirmation prompt
if [[ "$FORCE" != true ]]; then
    echo -e "${RED}WARNING: This will overwrite all existing SeaweedFS data!${NC}"
    echo -e "Backup file: ${BLUE}$BACKUP_FILE${NC}"
    echo ""
    read -rp "Are you sure you want to continue? Type 'YES' to confirm: " CONFIRM
    if [[ "$CONFIRM" != "YES" ]]; then
        echo -e "${YELLOW}Restore cancelled.${NC}"
        exit 0
    fi
fi

echo -e "${BLUE}=== SeaweedFS Restore ===${NC}"
echo -e "Backup file: ${GREEN}$BACKUP_FILE${NC}"
echo ""

# Create temp directory
TEMP_DIR=$(mktemp -d)

# Extract backup
echo -e "${YELLOW}Extracting backup...${NC}"
if [[ "$BACKUP_FILE" == *.enc ]]; then
    openssl enc -aes-256-cbc -d -pbkdf2 -pass pass:"$BACKUP_ENCRYPTION_KEY" -in "$BACKUP_FILE" \
        | tar -xzf - -C "$TEMP_DIR"
else
    tar -xzf "$BACKUP_FILE" -C "$TEMP_DIR"
fi

# Stop SeaweedFS to ensure clean restore
echo -e "${YELLOW}Stopping SeaweedFS...${NC}"
docker compose stop seaweedfs

# Clear existing data
echo -e "${YELLOW}Clearing existing data...${NC}"
docker compose run --rm --no-deps -v "$(docker volume ls -q | grep seaweedfs-data):/data" seaweedfs sh -c "rm -rf /data/*" 2>/dev/null || true

# Copy restored data
echo -e "${YELLOW}Restoring data...${NC}"
docker compose cp "$TEMP_DIR/seaweedfs-data/." seaweedfs:/data/ 2>/dev/null || {
    echo -e "${YELLOW}No data directory in backup, checking root...${NC}"
    docker compose cp "$TEMP_DIR/." seaweedfs:/data/ 2>/dev/null || true
}

# Cleanup temp directory
rm -rf "$TEMP_DIR"

# Start SeaweedFS
echo -e "${YELLOW}Starting SeaweedFS...${NC}"
docker compose start seaweedfs

# Wait for SeaweedFS to be healthy
echo -e "${YELLOW}Waiting for SeaweedFS to be ready...${NC}"
for _ in {1..30}; do
    if docker compose exec -T seaweedfs wget -q --spider http://127.0.0.1:8333/ 2>/dev/null; then
        echo -e "${GREEN}SeaweedFS is ready!${NC}"
        break
    fi
    sleep 1
done

# Log restore event
LOG_DIR="$PROJECT_ROOT/storage/logs"
mkdir -p "$LOG_DIR"
echo "{\"timestamp\":\"$(date -Iseconds)\",\"action\":\"backup_restored\",\"service\":\"seaweedfs\",\"file\":\"$(basename "$BACKUP_FILE")\"}" >> "$LOG_DIR/backup.log"

echo ""
echo -e "${GREEN}=== Restore Complete ===${NC}"
