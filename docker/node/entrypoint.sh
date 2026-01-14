#!/bin/sh
# Docker Node.js Entrypoint Script
# Validates dependencies (for services) and starts based on NODE_MODE

set -e

# If arguments are passed, run them directly (command mode, e.g., pnpm install)
if [ $# -gt 0 ]; then
    exec "$@"
fi

# Service mode: validate dependencies before starting
echo "[entrypoint] Starting Node.js container..."
echo "[entrypoint] NODE_MODE: ${NODE_MODE:-none}"

# Fail fast if dependencies are missing (explicit install required)
if [ ! -d "/app/node_modules" ] || [ -z "$(ls -A /app/node_modules 2>/dev/null)" ]; then
    echo "[entrypoint] ERROR: Node.js dependencies not installed!"
    echo "[entrypoint] Run 'make pnpm-install' to install dependencies."
    exit 1
fi
echo "[entrypoint] Dependencies OK"

# Start services based on NODE_MODE
case "${NODE_MODE:-none}" in
    full-stack)
        echo "[entrypoint] Starting Full-Stack mode (Vite + Express via PM2)..."
        exec pnpm run dev:full
        ;;
    vite-only)
        echo "[entrypoint] Starting Vite-Only mode (Frontend HMR via PM2)..."
        exec pnpm run dev:frontend
        ;;
    backend-only)
        echo "[entrypoint] Starting Backend-Only mode (Express API via PM2)..."
        exec pnpm run dev:backend
        ;;
    none|*)
        echo "[entrypoint] Idle mode - container running without services"
        exec sleep infinity
        ;;
esac
