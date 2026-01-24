#!/bin/bash
# shellcheck shell=bash
# PostgreSQL Database Initialization Script
#
# This script ensures the application database and user exist.
# It runs:
# - On first initialization (via /docker-entrypoint-initdb.d/)
# - On subsequent starts (via entrypoint-wrapper.sh) for existing volumes
#
# Security: Application user has NO CREATEDB privilege (only root can create DBs)

set -e

# Database configuration from environment
# Note: POSTGRES_USER/POSTGRES_PASSWORD are set by the official image
# from POSTGRES_PASSWORD_FILE if present
DB_NAME="${POSTGRES_DB:-app}"
DB_USER="${POSTGRES_USER:-app}"

echo "[init-db] Initializing database: $DB_NAME for user: $DB_USER"

# Function to run SQL as postgres superuser
run_sql() {
    psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname postgres -c "$1"
}

# Check if database exists
if psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname postgres -tAc \
    "SELECT 1 FROM pg_database WHERE datname = '$DB_NAME'" | grep -q 1; then
    echo "[init-db] Database '$DB_NAME' already exists"
else
    echo "[init-db] Creating database '$DB_NAME'..."
    run_sql "CREATE DATABASE \"$DB_NAME\" OWNER \"$DB_USER\" ENCODING 'UTF8' LC_COLLATE 'en_US.UTF-8' LC_CTYPE 'en_US.UTF-8';"
    echo "[init-db] Database '$DB_NAME' created successfully"
fi

# Ensure user has correct privileges (restricted for security)
# Note: The official PostgreSQL image creates POSTGRES_USER as SUPERUSER.
# For development this is acceptable. For production, create a separate
# non-superuser application account.
if [ "$DB_USER" != "postgres" ]; then
    echo "[init-db] Configuring privileges for user '$DB_USER'..."

    # Revoke CREATEDB privilege (security hardening)
    # Note: SUPERUSER overrides this, but it documents our security intent
    run_sql "ALTER USER \"$DB_USER\" NOCREATEDB NOCREATEROLE;" || true

    # Grant all privileges on the application database
    run_sql "GRANT ALL PRIVILEGES ON DATABASE \"$DB_NAME\" TO \"$DB_USER\";"

    # Connect to app database and grant schema privileges
    psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$DB_NAME" -c \
        "GRANT ALL ON SCHEMA public TO \"$DB_USER\";"

    echo "[init-db] Privileges configured for user '$DB_USER'"
fi

echo "[init-db] Database initialization complete"
