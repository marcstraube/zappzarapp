#!/bin/sh
# Docker Node.js Entrypoint Script
# Handles dependency installation and service startup based on NODE_MODE

set -e

echo "[entrypoint] Starting Node.js container..."
echo "[entrypoint] NODE_MODE: ${NODE_MODE:-none}"
echo "[entrypoint] NODE_ENV: ${NODE_ENV:-production}"

# Install dependencies if node_modules doesn't exist or package.json changed
if [ ! -d "/app/node_modules" ] || [ ! -f "/app/node_modules/.pnpm-lock.yaml" ]; then
    echo "[entrypoint] Installing dependencies..."
    pnpm install --frozen-lockfile
    echo "[entrypoint] Dependencies installed successfully"
else
    echo "[entrypoint] Dependencies already installed, skipping..."
fi

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
        echo "[entrypoint] Run commands manually:"
        echo "[entrypoint]   - Via make: 'make node-exec CMD=\"pnpm run <command>\"'"
        echo "[entrypoint]   - Direct:   'docker compose exec node pnpm run <command>'"
        exec sleep infinity
        ;;
esac
