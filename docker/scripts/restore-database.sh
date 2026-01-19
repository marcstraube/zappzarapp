#!/bin/bash
# =============================================================================
# DATABASE RESTORE SCRIPT
# =============================================================================
# Restores PostgreSQL or MariaDB databases from encrypted or unencrypted backups.
#
# Usage:
#   ./restore-database.sh <backup_file> [options]
#
# Arguments:
#   backup_file            Path to backup file (.sql.gz or .sql.gz.enc)
#
# Options:
#   -d, --database TYPE    Database type: postgres or mariadb (auto-detected from filename)
#   -y, --yes              Skip confirmation prompt
#   -h, --help             Show this help message
#
# Environment Variables (from .env):
#   DATABASE_URL           Full connection URL (takes precedence over individual vars)
#                          postgresql://user:pass@host:port/dbname
#                          mysql://user:pass@host:port/dbname
#   DB_TYPE                Database type (postgres/mariadb) - fallback if not in filename
#   DB_NAME                Database name
#   DB_USER                Database user
#   DB_PASSWORD            Database password (fallback if secrets file not found)
#   BACKUP_ENCRYPTION_KEY  Encryption key for encrypted backups
#
# Secrets (preferred over environment variables when DATABASE_URL is not set):
#   ./secrets/db_password.txt     Database password (host path)
#   /run/secrets/db_password.txt  Database password (container path)
#
# Supported Formats:
#   - {DB_TYPE}_{DB_NAME}_{TIMESTAMP}.sql.gz.enc (encrypted)
#   - {DB_TYPE}_{DB_NAME}_{TIMESTAMP}.sql.gz     (unencrypted)
#   - Any .sql.gz or .sql.gz.enc file
#
# GDPR Compliance:
#   - Restore operations are logged for audit purposes
#   - Encrypted backups require BACKUP_ENCRYPTION_KEY
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
SKIP_CONFIRM=false
BACKUP_FILE=""
DB_TYPE_OVERRIDE=""

# Load .env if exists
if [[ -f "$PROJECT_ROOT/.env" ]]; then
    # shellcheck disable=SC1091
    source "$PROJECT_ROOT/.env"
fi

# Load database configuration (supports DATABASE_URL and secrets files)
# shellcheck disable=SC1091
source "$SCRIPT_DIR/parse-db-url.sh"

BACKUP_ENCRYPTION_KEY="${BACKUP_ENCRYPTION_KEY:-}"

# Help message
show_help() {
    head -35 "$0" | tail -30 | sed 's/^# //' | sed 's/^#//'
    exit 0
}

# Parse arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        -d|--database)
            DB_TYPE_OVERRIDE="$2"
            shift 2
            ;;
        -y|--yes)
            SKIP_CONFIRM=true
            shift
            ;;
        -h|--help)
            show_help
            ;;
        -*)
            echo -e "${RED}Unknown option: $1${NC}"
            echo "Use --help for usage information"
            exit 1
            ;;
        *)
            if [[ -z "$BACKUP_FILE" ]]; then
                BACKUP_FILE="$1"
            else
                echo -e "${RED}Error: Multiple backup files specified.${NC}"
                exit 1
            fi
            shift
            ;;
    esac
done

# Check if backup file is provided
if [[ -z "$BACKUP_FILE" ]]; then
    echo -e "${RED}Error: No backup file specified.${NC}"
    echo ""
    echo "Usage: $0 <backup_file> [options]"
    echo ""
    echo "Available backups:"
    if [[ -d "$PROJECT_ROOT/backups" ]]; then
        ls -la "$PROJECT_ROOT/backups"/*.sql.gz* 2>/dev/null || echo "  No backups found in ./backups/"
    else
        echo "  No backups directory found."
    fi
    exit 1
fi

# Check if backup file exists
if [[ ! -f "$BACKUP_FILE" ]]; then
    # Try with backups directory prefix
    if [[ -f "$PROJECT_ROOT/backups/$BACKUP_FILE" ]]; then
        BACKUP_FILE="$PROJECT_ROOT/backups/$BACKUP_FILE"
    else
        echo -e "${RED}Error: Backup file not found: $BACKUP_FILE${NC}"
        exit 1
    fi
fi

# Determine if encrypted
ENCRYPTED=false
if [[ "$BACKUP_FILE" == *.enc ]]; then
    ENCRYPTED=true
fi

# Auto-detect database type from filename
FILENAME=$(basename "$BACKUP_FILE")
if [[ -n "$DB_TYPE_OVERRIDE" ]]; then
    DB_TYPE="$DB_TYPE_OVERRIDE"
elif [[ "$FILENAME" == postgres_* ]]; then
    DB_TYPE="postgres"
elif [[ "$FILENAME" == mariadb_* ]]; then
    DB_TYPE="mariadb"
fi
# Otherwise use DB_TYPE from .env

# Validate database type
if [[ "$DB_TYPE" != "postgres" && "$DB_TYPE" != "mariadb" ]]; then
    echo -e "${RED}Error: Invalid database type '$DB_TYPE'. Use 'postgres' or 'mariadb'.${NC}"
    exit 1
fi

# Check encryption key if backup is encrypted
if [[ "$ENCRYPTED" == true && -z "$BACKUP_ENCRYPTION_KEY" ]]; then
    echo -e "${RED}Error: BACKUP_ENCRYPTION_KEY is not set but backup is encrypted.${NC}"
    echo -e "${YELLOW}Set BACKUP_ENCRYPTION_KEY in .env or as environment variable.${NC}"
    exit 1
fi

# Calculate backup info
BACKUP_SIZE=$(du -h "$BACKUP_FILE" | cut -f1)

echo -e "${BLUE}=== Database Restore ===${NC}"
echo -e "Backup File:   ${GREEN}$BACKUP_FILE${NC}"
echo -e "File Size:     ${GREEN}$BACKUP_SIZE${NC}"
echo -e "Encrypted:     ${GREEN}$([[ "$ENCRYPTED" == true ]] && echo "yes" || echo "no")${NC}"
echo -e "Database Type: ${GREEN}$DB_TYPE${NC}"
echo -e "Database Name: ${GREEN}$DB_NAME${NC}"
echo ""

# Confirmation prompt
if [[ "$SKIP_CONFIRM" != true ]]; then
    echo -e "${RED}WARNING: This will OVERWRITE the current database '$DB_NAME'!${NC}"
    echo -e "${YELLOW}All existing data will be lost.${NC}"
    echo ""
    read -p "Type 'YES' to confirm restore: " CONFIRM
    if [[ "$CONFIRM" != "YES" ]]; then
        echo -e "${BLUE}Restore cancelled.${NC}"
        exit 0
    fi
    echo ""
fi

# Perform restore based on database type
if [[ "$DB_TYPE" == "postgres" ]]; then
    echo -e "${YELLOW}Restoring PostgreSQL database...${NC}"

    # Check if running in Docker
    if docker compose ps postgres 2>/dev/null | grep -q "Up"; then
        # Docker mode
        if [[ "$ENCRYPTED" == true ]]; then
            openssl enc -aes-256-cbc -d -salt -pbkdf2 -pass pass:"$BACKUP_ENCRYPTION_KEY" -in "$BACKUP_FILE" \
                | gunzip \
                | docker compose exec -T postgres psql -U "$DB_USER" -d "$DB_NAME" --quiet
        else
            gunzip -c "$BACKUP_FILE" \
                | docker compose exec -T postgres psql -U "$DB_USER" -d "$DB_NAME" --quiet
        fi
    else
        echo -e "${RED}Error: PostgreSQL container is not running.${NC}"
        echo -e "${YELLOW}Start with: make up${NC}"
        exit 1
    fi

elif [[ "$DB_TYPE" == "mariadb" ]]; then
    echo -e "${YELLOW}Restoring MariaDB database...${NC}"

    # Check if running in Docker
    if docker compose ps mariadb 2>/dev/null | grep -q "Up"; then
        # Docker mode
        if [[ "$ENCRYPTED" == true ]]; then
            openssl enc -aes-256-cbc -d -salt -pbkdf2 -pass pass:"$BACKUP_ENCRYPTION_KEY" -in "$BACKUP_FILE" \
                | gunzip \
                | docker compose exec -T mariadb mariadb -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME"
        else
            gunzip -c "$BACKUP_FILE" \
                | docker compose exec -T mariadb mariadb -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME"
        fi
    else
        echo -e "${RED}Error: MariaDB container is not running.${NC}"
        echo -e "${YELLOW}Start with: make up${NC}"
        exit 1
    fi
fi

# Log restore event
LOG_DIR="$PROJECT_ROOT/storage/logs"
mkdir -p "$LOG_DIR"
echo "{\"timestamp\":\"$(date -Iseconds)\",\"action\":\"backup_restored\",\"database\":\"$DB_TYPE\",\"db_name\":\"$DB_NAME\",\"file\":\"$(basename "$BACKUP_FILE")\",\"encrypted\":$ENCRYPTED}" >> "$LOG_DIR/backup.log"

echo ""
echo -e "${GREEN}=== Restore Complete ===${NC}"
echo -e "Database '$DB_NAME' has been restored from backup."
