#!/bin/bash
# =============================================================================
# DATABASE URL PARSER
# =============================================================================
# Parses DATABASE_URL into individual variables for shell scripts.
# Provides consistent database configuration across all scripts.
#
# Usage:
#   source ./docker/scripts/parse-db-url.sh
#
# After sourcing, the following variables are available:
#   DB_TYPE     - Database type: postgres or mariadb
#   DB_HOST     - Database host
#   DB_PORT     - Database port
#   DB_NAME     - Database name
#   DB_USER     - Database user
#   DB_PASSWORD - Database password (URL-decoded if from DATABASE_URL)
#                 Priority: DATABASE_URL > DB_PASSWORD_FILE (secrets) > DB_PASSWORD (env var)
#
# Supports:
#   - PostgreSQL: postgresql://user:pass@host:port/dbname
#   - PostgreSQL: postgres://user:pass@host:port/dbname
#   - MySQL/MariaDB: mysql://user:pass@host:port/dbname
# =============================================================================

# URL-decode a string (handles %XX encoding)
# Usage: urldecode "encoded%20string"
urldecode() {
    local encoded="${1//+/ }"
    printf '%b' "${encoded//%/\\x}"
}

# Parse DATABASE_URL into individual components
# Sets: DB_TYPE, DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD
parse_database_url() {
    local url="$1"

    # Extract scheme (protocol)
    local scheme="${url%%://*}"

    # Normalize scheme to DB_TYPE
    case "$scheme" in
        postgresql|postgres)
            DB_TYPE="postgres"
            ;;
        mysql|mariadb)
            DB_TYPE="mariadb"
            ;;
        *)
            echo "Warning: Unknown database scheme '$scheme', defaulting to postgres" >&2
            DB_TYPE="postgres"
            ;;
    esac

    # Remove scheme from URL
    local remainder="${url#*://}"

    # Extract user:password@host:port/dbname
    # Format: [user[:password]@]host[:port]/dbname

    # Check if we have credentials (contains @)
    if [[ "$remainder" == *"@"* ]]; then
        local credentials="${remainder%%@*}"
        remainder="${remainder#*@}"

        # Extract user and password
        if [[ "$credentials" == *":"* ]]; then
            DB_USER="${credentials%%:*}"
            DB_PASSWORD=$(urldecode "${credentials#*:}")
        else
            DB_USER="$credentials"
            DB_PASSWORD=""
        fi
    else
        DB_USER=""
        DB_PASSWORD=""
    fi

    # Extract host:port/dbname
    # Remove query string if present
    remainder="${remainder%%\?*}"

    # Extract database name (after /)
    if [[ "$remainder" == *"/"* ]]; then
        DB_NAME="${remainder#*/}"
        remainder="${remainder%%/*}"
    else
        DB_NAME=""
    fi

    # Extract host and port
    if [[ "$remainder" == *":"* ]]; then
        DB_HOST="${remainder%%:*}"
        DB_PORT="${remainder#*:}"
    else
        DB_HOST="$remainder"
        DB_PORT=""
    fi

    # Export variables
    export DB_TYPE DB_HOST DB_PORT DB_NAME DB_USER DB_PASSWORD
}

# Load password from secrets file
# Checks multiple locations for Docker secrets
load_password_from_secrets() {
    local script_dir
    local project_root

    # Try to determine project root
    if [[ -n "${BASH_SOURCE[0]}" ]]; then
        script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
        project_root="$(cd "$script_dir/../.." && pwd)"
    else
        project_root="$(pwd)"
    fi

    # Check secrets file locations in order
    if [[ -f "$project_root/secrets/db_password.txt" ]]; then
        DB_PASSWORD=$(cat "$project_root/secrets/db_password.txt")
    elif [[ -f "./secrets/db_password.txt" ]]; then
        DB_PASSWORD=$(cat "./secrets/db_password.txt")
    elif [[ -f "/run/secrets/db_password.txt" ]]; then
        DB_PASSWORD=$(cat "/run/secrets/db_password.txt")
    fi

    export DB_PASSWORD
}

# Main configuration loading
# Determines source and loads all database variables
load_database_config() {
    # Check if DATABASE_URL is set and non-empty
    if [[ -n "${DATABASE_URL:-}" ]]; then
        parse_database_url "$DATABASE_URL"
        return
    fi

    # Fall back to individual variables with defaults
    DB_TYPE="${DB_TYPE:-postgres}"

    # Default host based on type
    if [[ -z "${DB_HOST:-}" ]]; then
        if [[ "$DB_TYPE" == "postgres" ]]; then
            DB_HOST="postgres"
        else
            DB_HOST="mariadb"
        fi
    fi

    # Default port based on type
    if [[ -z "${DB_PORT:-}" ]]; then
        if [[ "$DB_TYPE" == "postgres" ]]; then
            DB_PORT="5432"
        else
            DB_PORT="3306"
        fi
    fi

    DB_NAME="${DB_NAME:-app}"
    DB_USER="${DB_USER:-app}"

    # Load password: env var first, then secrets file
    if [[ -z "${DB_PASSWORD:-}" ]]; then
        load_password_from_secrets
    fi

    export DB_TYPE DB_HOST DB_PORT DB_NAME DB_USER DB_PASSWORD
}

# Auto-load configuration when sourced
load_database_config
