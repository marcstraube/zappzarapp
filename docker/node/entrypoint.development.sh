#!/bin/sh
# Docker Node.js Development Entrypoint Script
# Runs as root initially to fix volume permissions, then re-execs as node user
#
# Why root first:
# Docker named volumes are created as root. Workspace node_modules volumes
# need ownership changed to the node user before pnpm can write to them.

set -e

# ═══════════════════════════════════════════════════════════════════════════
# ROOT-ONLY TASKS (runs first, before privilege drop)
# ═══════════════════════════════════════════════════════════════════════════
if [ "$(id -u)" = "0" ]; then
    # Fix ownership of workspace node_modules volumes (only when empty = first run)
    fix_workspace_permissions() {
        local backend_nm="/app/src/node/backend/node_modules"
        local frontend_nm="/app/src/node/frontend/node_modules"
        local needs_fix=false

        if [ -d "$backend_nm" ] && [ -z "$(ls -A "$backend_nm" 2>/dev/null)" ]; then
            needs_fix=true
        fi
        if [ -d "$frontend_nm" ] && [ -z "$(ls -A "$frontend_nm" 2>/dev/null)" ]; then
            needs_fix=true
        fi

        if [ "$needs_fix" = true ]; then
            echo "[entrypoint.development] Fixing workspace node_modules permissions..."
            NODE_UID=$(id -u node)
            NODE_GID=$(id -g node)
            [ -d "$backend_nm" ] && chown -R "$NODE_UID:$NODE_GID" "$backend_nm"
            [ -d "$frontend_nm" ] && chown -R "$NODE_UID:$NODE_GID" "$frontend_nm"
            echo "[entrypoint.development] Permissions fixed for node:node ($NODE_UID:$NODE_GID)"
        fi
    }

    fix_workspace_permissions

    # Copy secrets to readable location (as root, for all users)
    if [ -d "/run/secrets" ]; then
        mkdir -p /tmp/secrets
        chmod 755 /tmp/secrets
        for secret in /run/secrets/*; do
            if [ -f "$secret" ]; then
                name=$(basename "$secret")
                cp "$secret" "/tmp/secrets/$name" && chmod 444 "/tmp/secrets/$name"
            fi
        done
        echo "[entrypoint.development] Secrets copied to /tmp/secrets (readable)"
    fi

    # Fix stdout/stderr permissions for PM2 (needed after privilege drop)
    chmod 666 /dev/stdout /dev/stderr 2>/dev/null || true

    # Re-exec this script as node user (privilege drop)
    exec su-exec node "$0" "$@"
fi

# ═══════════════════════════════════════════════════════════════════════════
# NODE USER TASKS (runs after privilege drop)
# ═══════════════════════════════════════════════════════════════════════════

# If arguments are passed, run them directly (command mode, e.g., pnpm install)
if [ $# -gt 0 ]; then
    exec "$@"
fi

# Service mode: start based on NODE_MODE
echo "[entrypoint.development] Starting Node.js container..."
echo "[entrypoint.development] NODE_MODE: ${NODE_MODE:-idle}"

# CORS Configuration Warning
if [ "${CORS_ORIGINS:-}" = "*" ]; then
    echo "[entrypoint.development] WARNING: CORS_ORIGINS is set to wildcard (*)" >&2
    echo "[entrypoint.development] Wildcard origin allows requests from ANY domain (development mode)" >&2
    echo "[entrypoint.development] Credentials header disabled for browser compatibility" >&2
    if [ "${NODE_ENV:-development}" = "production" ]; then
        echo "[entrypoint.development] CRITICAL: Wildcard CORS in production environment detected!" >&2
        echo "[entrypoint.development] Fix: Set specific origins in .env.production" >&2
    fi
fi

# Helper function: check if dependencies are installed
check_dependencies() {
    if [ ! -d "/app/node_modules" ] || [ -z "$(ls -A /app/node_modules 2>/dev/null)" ]; then
        echo "[entrypoint.development] ERROR: Node.js dependencies not installed!"
        echo "[entrypoint.development] Run 'make pnpm-install' to install dependencies."
        exit 1
    fi
    echo "[entrypoint.development] Dependencies OK"
}

# Start services based on NODE_MODE
# Note: framework-api mode is handled by running two containers (node + node-backend)
# Note: idle mode skips dependency check (used in CI for docker compose exec)
case "${NODE_MODE:-idle}" in
    assets-api)
        check_dependencies
        echo "[entrypoint.development] Starting assets-api mode (Vite HMR + Express via PM2)..."
        exec pnpm run dev:full
        ;;
    assets)
        check_dependencies
        echo "[entrypoint.development] Starting assets mode (Vite HMR via PM2)..."
        exec pnpm run dev:vite
        ;;
    api)
        check_dependencies
        echo "[entrypoint.development] Starting api mode (Express API via PM2)..."
        exec pnpm run dev:backend
        ;;
    framework)
        check_dependencies
        echo "[entrypoint.development] Starting framework mode (Node frontend framework)..."
        if [ ! -f "/app/src/node/frontend/package.json" ]; then
            echo "[entrypoint.development] ERROR: No frontend framework installed!"
            echo "[entrypoint.development] See src/node/frontend/README.md for setup instructions."
            exit 1
        fi
        exec pnpm run frontend:dev
        ;;
    idle|*)
        echo "[entrypoint.development] Idle mode - container running without services"
        exec sleep infinity
        ;;
esac
