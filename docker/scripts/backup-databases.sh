#!/bin/bash
# =============================================================================
# DATABASE BACKUP SCRIPT
# =============================================================================
# Creates encrypted, compressed backups of PostgreSQL or MariaDB databases.
#
# Usage:
#   ./backup-databases.sh [options]
#
# Options:
#   -d, --database TYPE    Database type: postgres or mariadb (default: from .env)
#   -o, --output DIR       Output directory (default: ./backups/db)
#   -r, --retention DAYS   Keep backups for N days (default: 30, 0 = keep all)
#   -n, --no-encrypt       Skip encryption (not recommended for production)
#   -h, --help             Show this help message
#
# Environment Variables (from .env):
#   DB_TYPE                Database type (postgres/mariadb)
#   DB_NAME                Database name
#   DB_USER                Database user
#   DB_PASSWORD            Database password
#   BACKUP_ENCRYPTION_KEY  Encryption key for backups (required unless --no-encrypt)
#
# Output:
#   Encrypted:   {DB_TYPE}_{DB_NAME}_{TIMESTAMP}.sql.gz.enc
#   Unencrypted: {DB_TYPE}_{DB_NAME}_{TIMESTAMP}.sql.gz
#
# GDPR Compliance:
#   - Backups are encrypted by default (AES-256-CBC)
#   - Retention policy helps with data minimization (Art. 5)
#   - Backup logs stored for audit purposes
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
OUTPUT_DIR="$PROJECT_ROOT/backups/db"
ENCRYPT=true

# Load .env if exists
if [[ -f "$PROJECT_ROOT/.env" ]]; then
    # shellcheck disable=SC1091
    source "$PROJECT_ROOT/.env"
fi

# Retention from env or default (can be overridden via --retention flag)
RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-30}"

# Database config from environment
DB_TYPE="${DB_TYPE:-postgres}"
DB_NAME="${DB_NAME:-app}"
DB_USER="${DB_USER:-app}"
DB_PASSWORD="${DB_PASSWORD:-}"
BACKUP_ENCRYPTION_KEY="${BACKUP_ENCRYPTION_KEY:-}"

# Help message
show_help() {
    head -40 "$0" | tail -35 | sed 's/^# //' | sed 's/^#//'
    exit 0
}

# Parse arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        -d|--database)
            DB_TYPE="$2"
            shift 2
            ;;
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

# Validate database type
if [[ "$DB_TYPE" != "postgres" && "$DB_TYPE" != "mariadb" ]]; then
    echo -e "${RED}Error: Invalid database type '$DB_TYPE'. Use 'postgres' or 'mariadb'.${NC}"
    exit 1
fi

# Check encryption key if encryption is enabled
if [[ "$ENCRYPT" == true && -z "$BACKUP_ENCRYPTION_KEY" ]]; then
    echo -e "${RED}Error: BACKUP_ENCRYPTION_KEY is not set.${NC}"
    echo -e "${YELLOW}Either set it in .env or use --no-encrypt (not recommended for production).${NC}"
    echo -e "${BLUE}Generate a key with: openssl rand -base64 32${NC}"
    exit 1
fi

# Create output directory
mkdir -p "$OUTPUT_DIR"

# Timestamp for filename
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_NAME="${DB_TYPE}_${DB_NAME}_${TIMESTAMP}"

echo -e "${BLUE}=== Database Backup ===${NC}"
echo -e "Database Type: ${GREEN}$DB_TYPE${NC}"
echo -e "Database Name: ${GREEN}$DB_NAME${NC}"
echo -e "Output Dir:    ${GREEN}$OUTPUT_DIR${NC}"
echo -e "Encryption:    ${GREEN}$([[ "$ENCRYPT" == true ]] && echo "enabled" || echo "disabled")${NC}"
echo ""

# Perform backup based on database type
if [[ "$DB_TYPE" == "postgres" ]]; then
    echo -e "${YELLOW}Creating PostgreSQL backup...${NC}"

    # Check if running in Docker or standalone
    if docker compose ps postgres 2>/dev/null | grep -q "Up"; then
        # Docker mode
        if [[ "$ENCRYPT" == true ]]; then
            docker compose exec -T postgres pg_dump -U "$DB_USER" -d "$DB_NAME" --no-owner --no-acl \
                | gzip -9 \
                | openssl enc -aes-256-cbc -salt -pbkdf2 -pass pass:"$BACKUP_ENCRYPTION_KEY" \
                > "$OUTPUT_DIR/${BACKUP_NAME}.sql.gz.enc"
            BACKUP_FILE="${BACKUP_NAME}.sql.gz.enc"
        else
            docker compose exec -T postgres pg_dump -U "$DB_USER" -d "$DB_NAME" --no-owner --no-acl \
                | gzip -9 \
                > "$OUTPUT_DIR/${BACKUP_NAME}.sql.gz"
            BACKUP_FILE="${BACKUP_NAME}.sql.gz"
        fi
    else
        echo -e "${RED}Error: PostgreSQL container is not running.${NC}"
        echo -e "${YELLOW}Start with: make up${NC}"
        exit 1
    fi

elif [[ "$DB_TYPE" == "mariadb" ]]; then
    echo -e "${YELLOW}Creating MariaDB backup...${NC}"

    # Check if running in Docker or standalone
    if docker compose ps mariadb 2>/dev/null | grep -q "Up"; then
        # Docker mode
        if [[ "$ENCRYPT" == true ]]; then
            docker compose exec -T mariadb mariadb-dump -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" --single-transaction --routines --triggers \
                | gzip -9 \
                | openssl enc -aes-256-cbc -salt -pbkdf2 -pass pass:"$BACKUP_ENCRYPTION_KEY" \
                > "$OUTPUT_DIR/${BACKUP_NAME}.sql.gz.enc"
            BACKUP_FILE="${BACKUP_NAME}.sql.gz.enc"
        else
            docker compose exec -T mariadb mariadb-dump -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" --single-transaction --routines --triggers \
                | gzip -9 \
                > "$OUTPUT_DIR/${BACKUP_NAME}.sql.gz"
            BACKUP_FILE="${BACKUP_NAME}.sql.gz"
        fi
    else
        echo -e "${RED}Error: MariaDB container is not running.${NC}"
        echo -e "${YELLOW}Start with: make up${NC}"
        exit 1
    fi
fi

# Calculate backup size
BACKUP_SIZE=$(du -h "$OUTPUT_DIR/$BACKUP_FILE" | cut -f1)

echo -e "${GREEN}Backup created successfully!${NC}"
echo -e "  File: ${BLUE}$OUTPUT_DIR/$BACKUP_FILE${NC}"
echo -e "  Size: ${BLUE}$BACKUP_SIZE${NC}"

# Apply retention policy
if [[ "$RETENTION_DAYS" -gt 0 ]]; then
    echo ""
    echo -e "${YELLOW}Applying retention policy ($RETENTION_DAYS days)...${NC}"

    # Find and delete old backups
    DELETED_COUNT=0
    while IFS= read -r -d '' old_backup; do
        rm -f "$old_backup"
        ((DELETED_COUNT++))
        echo -e "  Deleted: $(basename "$old_backup")"
    done < <(find "$OUTPUT_DIR" -name "${DB_TYPE}_${DB_NAME}_*.sql.gz*" -type f -mtime +"$RETENTION_DAYS" -print0 2>/dev/null)

    if [[ "$DELETED_COUNT" -eq 0 ]]; then
        echo -e "  ${GREEN}No old backups to delete.${NC}"
    else
        echo -e "  ${GREEN}Deleted $DELETED_COUNT old backup(s).${NC}"
    fi
fi

# Log backup event
LOG_DIR="$PROJECT_ROOT/storage/logs"
mkdir -p "$LOG_DIR"
echo "{\"timestamp\":\"$(date -Iseconds)\",\"action\":\"backup_created\",\"database\":\"$DB_TYPE\",\"db_name\":\"$DB_NAME\",\"file\":\"$BACKUP_FILE\",\"size\":\"$BACKUP_SIZE\",\"encrypted\":$ENCRYPT}" >> "$LOG_DIR/backup.log"

echo ""
echo -e "${GREEN}=== Backup Complete ===${NC}"
