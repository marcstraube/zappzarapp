# Node.js Multi-Stage Dockerfile

ARG NODE_VERSION=24.18-alpine3.23

# Version Pinning:
# - Minor version pinned for stability (LTS)
# - Full tag lives in ONE ARG: Renovate cannot update a tag composed from
#   multiple ARGs (version + alpine suffix)
# - To update: check https://hub.docker.com/_/node/tags
# - For SHA digest pinning in production, use:
#   FROM node:<tag>@sha256:<digest>

# --- 1. BASE STAGE (Shared Foundation) ---
# Installs pnpm and curl.
# Use a recent Node.js LTS version on Alpine for a slim image
FROM node:${NODE_VERSION} AS base

# Install pnpm globally and add curl for health checks, git for commitlint
# PNPM_VERSION mirrors the packageManager pin in package.json - `make
# pnpm-upgrade` updates both. Pinned so image builds are reproducible and do
# not depend on the npm registry resolving "latest" at build time.
ARG PNPM_VERSION=11.18.0
ENV PNPM_HOME="/usr/local/bin/pnpm"
ENV PATH="$PNPM_HOME:$PATH"
ENV COREPACK_ENABLE_DOWNLOAD_PROMPT=0
# Apply available OS security patches within the pinned Alpine line (Trivy runs
# with ignore-unfixed, so reported CVEs have a fix available here).
RUN apk upgrade --no-cache && \
    corepack enable && corepack prepare "pnpm@${PNPM_VERSION}" --activate && \
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

# Copy development entrypoint
# Explicit absolute-mode --chmod: the stages run as the unprivileged `node`
# user, so a bare `chmod +x` on a umask-077 source yields 711 (execute-only, no
# read) and node cannot read the script. `--chmod=0755` is umask-safe.
COPY --chmod=0755 docker/node/entrypoint.development.sh /usr/local/bin/entrypoint.development.sh

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
# Note: pnpm-lock.yaml is optional (not committed in boilerplate)
COPY --chown=node:node package.json pnpm-workspace.yaml ./
COPY --chown=node:node pnpm-lock.yaml* ./

# Copy workspace package.json files (required for pnpm install)
COPY --chown=node:node src/node/backend/package.json ./src/node/backend/

# Install ALL dependencies (including devDependencies for build)
# Use --frozen-lockfile if lockfile exists AND is not empty (user's project)
# Otherwise generate lockfile (boilerplate or empty placeholder from CI)
RUN if [ -f pnpm-lock.yaml ] && [ -s pnpm-lock.yaml ]; then pnpm install --frozen-lockfile; else pnpm install; fi

# Copy ONLY necessary source files
COPY --chown=node:node vite.config.js ./
COPY --chown=node:node tsconfig.json tsconfig.base.json tsconfig.build.json ./
COPY --chown=node:node postcss.config.js ./
COPY --chown=node:node resources/ ./resources/
COPY --chown=node:node src/node/backend/ ./src/node/backend/

# Execute the build commands (Frontend assets + Backend) and slim image
# Note: We delete node_modules and reinstall with --prod instead of using
# 'pnpm prune --prod' because prune breaks pnpm workspace symlinks
RUN pnpm run build && pnpm --filter @zappzarapp/backend build && \
    rm -rf node_modules src/node/backend/node_modules && \
    pnpm install --prod --frozen-lockfile

# --- 4. PRODUCTION ASSETS STAGE ---
# Serves pre-built Vite assets via nginx (container sleeps, nginx serves files)
# Used for: NODE_MODE=assets
FROM base AS assets

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

# --- 5. PRODUCTION FRAMEWORK STAGE ---
# Runs Node.js frontend frameworks (Next.js, Nuxt, Remix, SvelteKit) in production
# Used for: NODE_MODE=framework, NODE_MODE=framework-api
FROM base AS framework

# Using a high, non-conflicting UID for maximum security in production.
# In Compose, container runs as node user via 'user: 50000:50000' directive.
RUN apk add --no-cache --virtual .user-deps shadow && \
    usermod -u 50000 node && \
    groupmod -g 50000 node && \
    apk del .user-deps && \
    mkdir -p /home/node/.cache/node/corepack /app && \
    chown -R node:node /home/node /app /usr/local/bin/pnpm

# Copy production entrypoint (sets up secret file paths)
COPY --chmod=0755 docker/node/entrypoint.production.sh /usr/local/bin/entrypoint.production.sh

# Build as node user for security
USER node

# Copy frontend package files (frontend has its own package.json)
COPY --chown=node:node src/node/frontend/package.json src/node/frontend/pnpm-lock.yaml* ./

# Install dependencies
RUN pnpm install --frozen-lockfile || pnpm install

# Copy frontend source code
COPY --chown=node:node src/node/frontend/ ./

# Build the frontend (Next.js: next build, Nuxt: nuxt build, etc.)
RUN pnpm run build

# Framework server port (nginx proxies /app to node:3001)
# Nitro (Nuxt) uses NITRO_PORT, Next.js/others use PORT
ENV PORT=3001 NITRO_PORT=3001

# Health Check: Verifies that the frontend HTTPS server is responsive
# Note: Only Nuxt/Nitro supports internal HTTPS via NITRO_SSL_CERT/NITRO_SSL_KEY
# Other frameworks (Next.js, SvelteKit, Remix) require custom server setup for HTTPS
HEALTHCHECK --interval=15s --timeout=3s --start-period=30s --retries=3 \
    CMD curl --fail --insecure https://localhost:3001 || exit 1

# Run as node user (Compose sets 'user: 50000:50000', K8s uses securityContext)
ENTRYPOINT ["/usr/local/bin/entrypoint.production.sh"]
CMD ["pnpm", "run", "start"]

# --- 6. PRODUCTION API STAGE ---
# Runs the Node.js backend API server
# Used for: NODE_MODE=api, NODE_MODE=assets-api
FROM base AS api

# Copy user/group from build stage
COPY --from=build /etc/passwd /etc/group /etc/

# Create cache directory with correct permissions for the node user (UID 50000 from build stage)
# In Compose, container runs as node user via 'user: 50000:50000' directive.
RUN mkdir -p /home/node/.cache/node/corepack && \
    chown -R 50000:50000 /home/node

# Copy production entrypoint (sets up secret file paths)
COPY --chmod=0755 docker/node/entrypoint.production.sh /usr/local/bin/entrypoint.production.sh

# Health Check: Pings the running Node.js HTTPS server to ensure it is responsive.
HEALTHCHECK --interval=15s --timeout=3s --start-period=10s --retries=3 \
    CMD curl --fail --insecure https://localhost:3000/health || exit 1

# Copy Vite build artifacts (needed by nginx/php for assets-api mode)
COPY --from=build --chown=node:node /app/public/build /app/public/build

# Copy production dependencies and built application
COPY --from=build --chown=node:node /app/node_modules /app/node_modules
COPY --from=build --chown=node:node /app/src/node/backend/node_modules /app/src/node/backend/node_modules
COPY --from=build --chown=node:node /app/src/node/backend/dist /app/src/node/backend/dist
COPY --from=build --chown=node:node /app/package.json /app/package.json
COPY --from=build --chown=node:node /app/src/node/backend/package.json /app/src/node/backend/package.json

# Run as node user (Compose sets 'user: 50000:50000', K8s uses securityContext)
USER node
ENTRYPOINT ["/usr/local/bin/entrypoint.production.sh"]
CMD ["node", "src/node/backend/dist/server.js"]

# --- 7. TEST API STAGE (CI only) ---
# Validates the API production image using GOSS
# Usage: docker build --target test-api -f docker/node/Dockerfile .
FROM api AS test-api

# nosemgrep: last-user-is-root -- CI-only GOSS test stage, never deployed; the runtime `api` stage ends as USER node.
USER root

# Copy GOSS binary from centralized image
COPY --from=goss --chmod=0755 /usr/bin/goss /usr/local/bin/goss

# Copy test specification
COPY tests/goss/services/node-backend.yaml /goss.yaml

# Run GOSS tests (fails build if tests fail)
RUN goss -g /goss.yaml validate --format documentation

# --- 8. TEST FRAMEWORK STAGE (CI only) ---
# Validates the framework production image using GOSS
# Usage: docker build --target test-framework -f docker/node/Dockerfile .
FROM framework AS test-framework

# nosemgrep: last-user-is-root -- CI-only GOSS test stage, never deployed; the runtime `framework` stage ends as USER node.
USER root

# Copy GOSS binary from centralized image
COPY --from=goss --chmod=0755 /usr/bin/goss /usr/local/bin/goss

# Copy test specification
COPY tests/goss/services/node-frontend.yaml /goss.yaml

# Run GOSS tests (fails build if tests fail)
RUN goss -g /goss.yaml validate --format documentation