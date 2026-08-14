#!/bin/bash
# =============================================================================
# RABBITMQ RESTORE SCRIPT
# =============================================================================
# Restores RabbitMQ definitions from a backup file.
#
# Usage:
#   ./restore-rabbitmq.sh <backup-file>
#
# Arguments:
#   backup-file    Path to the backup file (.json or .json.enc)
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
# Exported so openssl can read it via -pass env: (keeps the key out of argv/ps)
export BACKUP_ENCRYPTION_KEY
if [[ -z "$BACKUP_ENCRYPTION_KEY" && -f "$PROJECT_ROOT/secrets/backup_encryption_key.txt" ]]; then
    BACKUP_ENCRYPTION_KEY=$(cat "$PROJECT_ROOT/secrets/backup_encryption_key.txt")
fi

# RabbitMQ credentials
RABBITMQ_USER="${RABBITMQ_USER:-}"
RABBITMQ_PASSWORD="${RABBITMQ_PASSWORD:-}"
if [[ -z "$RABBITMQ_USER" && -f "$PROJECT_ROOT/secrets/rabbitmq_user.txt" ]]; then
    RABBITMQ_USER=$(cat "$PROJECT_ROOT/secrets/rabbitmq_user.txt")
fi
if [[ -z "$RABBITMQ_PASSWORD" && -f "$PROJECT_ROOT/secrets/rabbitmq_password.txt" ]]; then
    RABBITMQ_PASSWORD=$(cat "$PROJECT_ROOT/secrets/rabbitmq_password.txt")
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

# Check if RabbitMQ is running
if ! docker compose ps rabbitmq 2>/dev/null | grep -q "Up"; then
    echo -e "${RED}Error: RabbitMQ container is not running.${NC}"
    echo -e "${YELLOW}Start with: ENABLE_RABBITMQ=true make up${NC}"
    exit 1
fi

# Check credentials
if [[ -z "$RABBITMQ_USER" || -z "$RABBITMQ_PASSWORD" ]]; then
    echo -e "${RED}Error: RabbitMQ credentials not found.${NC}"
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

echo -e "${BLUE}=== RabbitMQ Restore ===${NC}"
echo -e "Backup File: ${GREEN}$BACKUP_FILE${NC}"
echo -e "Encrypted:   ${GREEN}$IS_ENCRYPTED${NC}"
echo ""

# Confirmation
echo -e "${YELLOW}WARNING: This will import definitions (may overwrite existing exchanges/queues)!${NC}"
read -rp "Continue? (yes/no): " CONFIRM
if [[ "$CONFIRM" != "yes" ]]; then
    echo -e "${YELLOW}Restore cancelled.${NC}"
    exit 0
fi

# Decrypt if needed
echo -e "${YELLOW}Restoring RabbitMQ definitions...${NC}"
if [[ "$IS_ENCRYPTED" == true ]]; then
    DEFINITIONS=$(openssl enc -aes-256-cbc -d -pbkdf2 -pass env:BACKUP_ENCRYPTION_KEY -in "$BACKUP_FILE")
else
    DEFINITIONS=$(cat "$BACKUP_FILE")
fi

# Import definitions via Management API
echo "$DEFINITIONS" | docker compose exec -T rabbitmq sh -c "
    curl -s -X POST -u '$RABBITMQ_USER:$RABBITMQ_PASSWORD' \
        -H 'Content-Type: application/json' \
        http://localhost:15672/api/definitions \
        -d @-
"

echo -e "${GREEN}=== Restore Complete ===${NC}"
echo -e "${YELLOW}Note: Messages in queues are not restored (they are transient).${NC}"
