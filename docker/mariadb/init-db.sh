#!/bin/bash
# shellcheck shell=bash
# MariaDB Database Initialization Script
#
# This script ensures the application database and user exist.
# It runs:
# - On first initialization (via /docker-entrypoint-initdb.d/)
# - On subsequent starts (via entrypoint-wrapper.sh) for existing volumes
#
# Security: Application user has NO CREATE privilege on server level
# (only root can create new databases)

set -e

# Database configuration from environment
# Note: Passwords are read from _FILE env vars by the official image
DB_NAME="${MARIADB_DATABASE:-${DB_NAME:-app}}"
DB_USER="${MARIADB_USER:-${DB_USER:-app}}"

# For init scripts, MARIADB_ROOT_PASSWORD is available
# For runtime checks, we read from the secrets file
if [ -z "$MARIADB_ROOT_PASSWORD" ] && [ -f /run/secrets/db_root_password.txt ]; then
    MARIADB_ROOT_PASSWORD=$(cat /run/secrets/db_root_password.txt)
fi

echo "[init-db] Initializing database: $DB_NAME for user: $DB_USER"

# Function to run SQL as root
run_sql() {
    mariadb -u root -p"$MARIADB_ROOT_PASSWORD" -e "$1"
}

# Check if database exists
if mariadb -u root -p"$MARIADB_ROOT_PASSWORD" -e "USE \`$DB_NAME\`" 2>/dev/null; then
    echo "[init-db] Database '$DB_NAME' already exists"
else
    echo "[init-db] Creating database '$DB_NAME'..."
    run_sql "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    echo "[init-db] Database '$DB_NAME' created successfully"
fi

# Check if user exists and configure privileges
# The official image creates the user, but we ensure correct privileges
if [ -n "$DB_USER" ] && [ "$DB_USER" != "root" ]; then
    echo "[init-db] Configuring privileges for user '$DB_USER'..."

    # Grant all privileges on the application database only
    # User cannot create other databases (no GRANT on *.*)
    run_sql "GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'%';"

    # Explicitly revoke global CREATE privilege if somehow granted
    run_sql "REVOKE CREATE ON *.* FROM '$DB_USER'@'%';" 2>/dev/null || true

    # Flush privileges to apply changes
    run_sql "FLUSH PRIVILEGES;"

    echo "[init-db] Privileges configured for user '$DB_USER'"
fi

echo "[init-db] Database initialization complete"
