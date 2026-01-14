#!/bin/bash
# =============================================================================
# MINIO RESTORE SCRIPT
# =============================================================================
# Restores MinIO data from a backup archive.
#
# Usage:
#   ./restore-minio.sh <backup-file>
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

# MinIO credentials
MINIO_ROOT_USER="${MINIO_ROOT_USER:-}"
MINIO_ROOT_PASSWORD="${MINIO_ROOT_PASSWORD:-}"
if [[ -z "$MINIO_ROOT_USER" && -f "$PROJECT_ROOT/secrets/minio_root_user.txt" ]]; then
    MINIO_ROOT_USER=$(cat "$PROJECT_ROOT/secrets/minio_root_user.txt")
fi
if [[ -z "$MINIO_ROOT_PASSWORD" && -f "$PROJECT_ROOT/secrets/minio_root_password.txt" ]]; then
    MINIO_ROOT_PASSWORD=$(cat "$PROJECT_ROOT/secrets/minio_root_password.txt")
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

# Check if MinIO is running
if ! docker compose ps minio 2>/dev/null | grep -q "Up"; then
    echo -e "${RED}Error: MinIO container is not running.${NC}"
    echo -e "${YELLOW}Start with: ENABLE_MINIO=true make up${NC}"
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

echo -e "${BLUE}=== MinIO Restore ===${NC}"
echo -e "Backup File: ${GREEN}$BACKUP_FILE${NC}"
echo -e "Encrypted:   ${GREEN}$IS_ENCRYPTED${NC}"
echo ""

# Confirmation
echo -e "${YELLOW}WARNING: This will overwrite existing MinIO data!${NC}"
read -p "Continue? (yes/no): " CONFIRM
if [[ "$CONFIRM" != "yes" ]]; then
    echo -e "${YELLOW}Restore cancelled.${NC}"
    exit 0
fi

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

# Copy data to container
echo -e "${YELLOW}Restoring data to MinIO...${NC}"
if [[ -d "$TEMP_DIR/minio-data" ]]; then
    docker compose cp "$TEMP_DIR/minio-data/." minio:/tmp/restore-data

    # Use mc to restore data
    docker compose exec -T minio sh -c "
        mc alias set restore http://localhost:9000 '$MINIO_ROOT_USER' '$MINIO_ROOT_PASSWORD' --api S3v4 2>/dev/null
        mc mirror /tmp/restore-data restore/ --overwrite 2>/dev/null || true
        rm -rf /tmp/restore-data
    "
fi

# Cleanup
rm -rf "$TEMP_DIR"

echo -e "${GREEN}=== Restore Complete ===${NC}"
