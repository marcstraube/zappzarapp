# Node.js Multi-Stage Dockerfile
#
# Version Pinning:
# - Minor version pinned for stability (LTS)
# - To update: check https://hub.docker.com/_/node/tags
# - For SHA digest pinning in production, use:
#   FROM node:24.12-alpine3.23@sha256:<digest>

ARG NODE_VERSION=24.12
ARG ALPINE_VERSION=3.23

# --- 1. BASE STAGE (Shared Foundation) ---
# Installs pnpm and curl.
# Use a recent Node.js LTS version on Alpine for a slim image
FROM node:${NODE_VERSION}-alpine${ALPINE_VERSION} AS base

# Install pnpm globally and add curl for health checks, git for commitlint
ENV PNPM_HOME="/usr/local/bin/pnpm"
ENV PATH="$PNPM_HOME:$PATH"
ENV COREPACK_ENABLE_DOWNLOAD_PROMPT=0
RUN corepack enable && corepack prepare pnpm@latest --activate && \
    apk add --no-cache curl git

# Set Working Directory
WORKDIR /app

# --- 2. DEVELOPMENT STAGE (Local Setup) ---
FROM base AS development

ARG USER_ID=1000
ARG GROUP_ID=1000

# Install su-exec for privilege dropping in entrypoint
RUN apk add --no-cache su-exec && \
    if [ "$USER_ID" != "1000" ]; then \
        apk add --no-cache --virtual .user-deps shadow && \
        usermod -u $USER_ID node && \
        groupmod -g $GROUP_ID node && \
        apk del .user-deps; \
    fi && \
    mkdir -p /home/node/.cache/node/corepack /app/node_modules && \
    chown -R node:node /home/node /app /usr/local/bin/pnpm

# Copy development entrypoint (production stages use direct CMD, no entrypoint)
COPY docker/node/entrypoint.development.sh /usr/local/bin/entrypoint.development.sh
RUN chmod +x /usr/local/bin/entrypoint.development.sh

# Note: Config files, source code, and dependencies are provided via bind mounts
# in development (see compose.override.yaml). No COPY needed here.

# Health Check: Verifies that at least one service (Vite or Backend) is running.
# Falls back to file check for idle mode.
HEALTHCHECK --interval=30s --timeout=5s --start-period=5s --retries=3 \
    CMD curl -sf http://localhost:5173 >/dev/null || curl -sf http://localhost:3000 >/dev/null || test -f /app/package.json

# Development runs as root initially, drops to node via entrypoint.development.sh
# This allows fixing workspace node_modules volume permissions on first run
ENTRYPOINT ["/usr/local/bin/entrypoint.development.sh"]

# --- 3. PRODUCTION BUILD STAGE (Secure Asset Build) ---
FROM base AS build

# Using a high, non-conflicting UID for maximum security in production.
RUN apk add --no-cache --virtual .user-deps shadow && \
    usermod -u 50000 node && \
    groupmod -g 50000 node && \
    apk del .user-deps && \
    mkdir -p /home/node/.cache/node/corepack && \
    chown -R node:node /home/node /app /usr/local/bin/pnpm

USER node

# Copy workspace configuration and dependency files
COPY --chown=node:node package.json pnpm-lock.yaml pnpm-workspace.yaml ./

# Copy workspace package.json files (required for pnpm install)
COPY --chown=node:node src/node/backend/package.json ./src/node/backend/

# Install ALL dependencies (including devDependencies for build)
RUN pnpm install --frozen-lockfile

# Copy ONLY necessary source files
COPY --chown=node:node vite.config.js ./
COPY --chown=node:node tsconfig.json tsconfig.base.json tsconfig.build.json ./
COPY --chown=node:node postcss.config.js ./
COPY --chown=node:node resources/ ./resources/
COPY --chown=node:node src/node/backend/ ./src/node/backend/

# Execute the build commands (Frontend assets + Backend)
RUN pnpm run build && pnpm --filter @zappzarapp/backend build

# Image Slimming - Remove DevDependencies
RUN CI=true pnpm prune --prod

# --- 4. PRODUCTION VITE ASSETS STAGE ---
# Serves pre-built Vite assets via nginx (container sleeps, nginx serves static files)
FROM base AS vite-assets

# Copy user/group from build stage
COPY --from=build /etc/passwd /etc/group /etc/

# Create cache directory with correct permissions for the node user (UID 50000 from build stage)
RUN mkdir -p /home/node/.cache/node/corepack && \
    chown -R 50000:50000 /home/node

# Health Check: Verifies that the build artifacts are present.
HEALTHCHECK --interval=30s --timeout=5s --start-period=5s --retries=3 \
    CMD test -f /app/package.json

# Copy only build artifacts
COPY --from=build --chown=node:node /app/public/build /app/public/build
COPY --from=build --chown=node:node /app/package.json /app/package.json

USER node

CMD ["sleep", "infinity"]

# --- 5. PRODUCTION FRONTEND STAGE ---
# Runs Node.js frontend frameworks (Next.js, Nuxt, Remix, SvelteKit) in production
FROM base AS frontend

# Using a high, non-conflicting UID for maximum security in production.
RUN apk add --no-cache --virtual .user-deps shadow && \
    usermod -u 50000 node && \
    groupmod -g 50000 node && \
    apk del .user-deps && \
    mkdir -p /home/node/.cache/node/corepack /app && \
    chown -R node:node /home/node /app /usr/local/bin/pnpm

USER node

# Copy frontend package files (frontend has its own package.json)
COPY --chown=node:node src/node/frontend/package.json src/node/frontend/pnpm-lock.yaml* ./

# Install dependencies
RUN pnpm install --frozen-lockfile || pnpm install

# Copy frontend source code
COPY --chown=node:node src/node/frontend/ ./

# Build the frontend (Next.js: next build, Nuxt: nuxt build, etc.)
RUN pnpm run build

# Health Check: Verifies that the frontend server is responsive
HEALTHCHECK --interval=15s --timeout=3s --start-period=30s --retries=3 \
    CMD curl --fail http://localhost:3000 || exit 1

# Start the production server (Next.js: next start, Nuxt: node .output/server/index.mjs, etc.)
CMD ["pnpm", "run", "start"]

# --- 6. PRODUCTION BACKEND STAGE ---
# Runs the Node.js backend API server
FROM base AS backend

# Copy user/group from build stage
COPY --from=build /etc/passwd /etc/group /etc/

# Create cache directory with correct permissions for the node user (UID 50000 from build stage)
RUN mkdir -p /home/node/.cache/node/corepack && \
    chown -R 50000:50000 /home/node

# Health Check: Pings the running Node.js server to ensure it is responsive.
HEALTHCHECK --interval=15s --timeout=3s --start-period=10s --retries=3 \
    CMD curl --fail http://localhost:3000/health || exit 1

# Copy production dependencies and built application
COPY --from=build --chown=node:node /app/node_modules /app/node_modules
COPY --from=build --chown=node:node /app/src/node/backend/node_modules /app/src/node/backend/node_modules
COPY --from=build --chown=node:node /app/src/node/backend/dist /app/src/node/backend/dist
COPY --from=build --chown=node:node /app/package.json /app/package.json
COPY --from=build --chown=node:node /app/src/node/backend/package.json /app/src/node/backend/package.json

USER node

CMD ["node", "src/node/backend/dist/server.js"]