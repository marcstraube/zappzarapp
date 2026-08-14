#!/bin/bash
# =============================================================================
# RABBITMQ BACKUP SCRIPT
# =============================================================================
# Exports RabbitMQ definitions (exchanges, queues, bindings, users, policies).
# Note: Messages in queues are NOT backed up (they are transient by design).
#
# Usage:
#   ./backup-rabbitmq.sh [options]
#
# Options:
#   -o, --output DIR       Output directory (default: ./backups/rabbitmq)
#   -r, --retention DAYS   Keep backups for N days (default: 30, 0 = keep all)
#   -n, --no-encrypt       Skip encryption (not recommended for production)
#   -h, --help             Show this help message
#
# Environment Variables (from .env or secrets):
#   RABBITMQ_USER          RabbitMQ admin user (or from /run/secrets/rabbitmq_user)
#   RABBITMQ_PASSWORD      RabbitMQ admin password (or from /run/secrets/rabbitmq_password)
#   BACKUP_ENCRYPTION_KEY  Encryption key for backups (required unless --no-encrypt)
#
# Output:
#   Encrypted:   rabbitmq_{TIMESTAMP}.json.enc
#   Unencrypted: rabbitmq_{TIMESTAMP}.json
#
# What's included:
#   - Users and permissions
#   - Virtual hosts
#   - Exchanges
#   - Queues (definitions, not messages)
#   - Bindings
#   - Policies
#   - Parameters
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
OUTPUT_DIR="$PROJECT_ROOT/backups/rabbitmq"
ENCRYPT=true

# Load .env if exists
if [[ -f "$PROJECT_ROOT/.env" ]]; then
    # shellcheck disable=SC1091
    source "$PROJECT_ROOT/.env"
fi

# Retention from env or default
RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-30}"

# RabbitMQ credentials from environment or secrets
RABBITMQ_USER="${RABBITMQ_USER:-}"
RABBITMQ_PASSWORD="${RABBITMQ_PASSWORD:-}"
BACKUP_ENCRYPTION_KEY="${BACKUP_ENCRYPTION_KEY:-}"
# Exported so openssl can read it via -pass env: (keeps the key out of argv/ps)
export BACKUP_ENCRYPTION_KEY

# Try to read from secrets if not set
if [[ -z "$RABBITMQ_USER" && -f "$PROJECT_ROOT/secrets/rabbitmq_user.txt" ]]; then
    RABBITMQ_USER=$(cat "$PROJECT_ROOT/secrets/rabbitmq_user.txt")
fi
if [[ -z "$RABBITMQ_PASSWORD" && -f "$PROJECT_ROOT/secrets/rabbitmq_password.txt" ]]; then
    RABBITMQ_PASSWORD=$(cat "$PROJECT_ROOT/secrets/rabbitmq_password.txt")
fi
if [[ -z "$BACKUP_ENCRYPTION_KEY" && -f "$PROJECT_ROOT/secrets/backup_encryption_key.txt" ]]; then
    BACKUP_ENCRYPTION_KEY=$(cat "$PROJECT_ROOT/secrets/backup_encryption_key.txt")
fi

# Help message
show_help() {
    head -35 "$0" | tail -30 | sed 's/^# //' | sed 's/^#//'
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

# Check if RabbitMQ is running
if ! docker compose ps rabbitmq 2>/dev/null | grep -q "Up"; then
    echo -e "${RED}Error: RabbitMQ container is not running.${NC}"
    echo -e "${YELLOW}Start with: ENABLE_RABBITMQ=true make up${NC}"
    exit 1
fi

# Check encryption key if encryption is enabled
if [[ "$ENCRYPT" == true && -z "$BACKUP_ENCRYPTION_KEY" ]]; then
    echo -e "${RED}Error: BACKUP_ENCRYPTION_KEY is not set.${NC}"
    echo -e "${YELLOW}Either set it in .env, create secrets/backup_encryption_key.txt, or use --no-encrypt.${NC}"
    exit 1
fi

# Check RabbitMQ credentials
if [[ -z "$RABBITMQ_USER" || -z "$RABBITMQ_PASSWORD" ]]; then
    echo -e "${RED}Error: RabbitMQ credentials not found.${NC}"
    echo -e "${YELLOW}Set RABBITMQ_USER/RABBITMQ_PASSWORD or create secrets files.${NC}"
    exit 1
fi

# Create output directory
mkdir -p "$OUTPUT_DIR"

# Timestamp for filename
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_NAME="rabbitmq_${TIMESTAMP}"

echo -e "${BLUE}=== RabbitMQ Backup ===${NC}"
echo -e "Output Dir:    ${GREEN}$OUTPUT_DIR${NC}"
echo -e "Encryption:    ${GREEN}$([[ "$ENCRYPT" == true ]] && echo "enabled" || echo "disabled")${NC}"
echo ""

# Export definitions via Management API
echo -e "${YELLOW}Exporting RabbitMQ definitions...${NC}"

# Use curl inside container to access management API
# Credentials travel as a curl config on stdin (-K -), never in argv
# (command lines are visible to other processes on host and container)
if [[ "$ENCRYPT" == true ]]; then
    printf 'user = "%s:%s"\n' "$RABBITMQ_USER" "$RABBITMQ_PASSWORD" | \
    docker compose exec -T rabbitmq curl -s -K - http://localhost:15672/api/definitions \
    | openssl enc -aes-256-cbc -salt -pbkdf2 -pass env:BACKUP_ENCRYPTION_KEY \
      > "$OUTPUT_DIR/${BACKUP_NAME}.json.enc"
    BACKUP_FILE="${BACKUP_NAME}.json.enc"
else
    printf 'user = "%s:%s"\n' "$RABBITMQ_USER" "$RABBITMQ_PASSWORD" | \
    docker compose exec -T rabbitmq curl -s -K - http://localhost:15672/api/definitions \
    > "$OUTPUT_DIR/${BACKUP_NAME}.json"
    BACKUP_FILE="${BACKUP_NAME}.json"
fi

# Verify backup was created and has content
if [[ ! -s "$OUTPUT_DIR/$BACKUP_FILE" ]]; then
    echo -e "${RED}Error: Backup file is empty. Check RabbitMQ management API.${NC}"
    rm -f "$OUTPUT_DIR/$BACKUP_FILE"
    exit 1
fi

# Calculate backup size
BACKUP_SIZE=$(du -h "$OUTPUT_DIR/$BACKUP_FILE" | cut -f1)

echo -e "${GREEN}Backup created successfully!${NC}"
echo -e "  File: ${BLUE}$OUTPUT_DIR/$BACKUP_FILE${NC}"
echo -e "  Size: ${BLUE}$BACKUP_SIZE${NC}"
echo ""
echo -e "${YELLOW}Note: Messages in queues are NOT backed up (transient by design).${NC}"

# Apply retention policy
if [[ "$RETENTION_DAYS" -gt 0 ]]; then
    echo ""
    echo -e "${YELLOW}Applying retention policy ($RETENTION_DAYS days)...${NC}"

    DELETED_COUNT=0
    while IFS= read -r -d '' old_backup; do
        rm -f "$old_backup"
        DELETED_COUNT=$((DELETED_COUNT + 1))
        echo -e "  Deleted: $(basename "$old_backup")"
    done < <(find "$OUTPUT_DIR" -name "rabbitmq_*.json*" -type f -mtime +"$RETENTION_DAYS" -print0 2>/dev/null)

    if [[ "$DELETED_COUNT" -eq 0 ]]; then
        echo -e "  ${GREEN}No old backups to delete.${NC}"
    else
        echo -e "  ${GREEN}Deleted $DELETED_COUNT old backup(s).${NC}"
    fi
fi

# Log backup event
LOG_DIR="$PROJECT_ROOT/storage/logs"
mkdir -p "$LOG_DIR"
echo "{\"timestamp\":\"$(date -Iseconds)\",\"action\":\"backup_created\",\"service\":\"rabbitmq\",\"file\":\"$BACKUP_FILE\",\"size\":\"$BACKUP_SIZE\",\"encrypted\":$ENCRYPT}" >> "$LOG_DIR/backup.log"

echo ""
echo -e "${GREEN}=== Backup Complete ===${NC}"
