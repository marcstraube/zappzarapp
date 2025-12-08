ARG NODE_VERSION=24
ARG ALPINE_VERSION=3.22

# --- 1. BASE STAGE (Shared Foundation) ---
# Installs pnpm and curl.
# Use a recent Node.js LTS version on Alpine for a slim image
FROM node:${NODE_VERSION}-alpine${ALPINE_VERSION} AS base

# Install pnpm globally and add curl for health checks
ENV PNPM_HOME="/usr/local/bin/pnpm"
ENV PATH="$PNPM_HOME:$PATH"
RUN corepack enable && corepack prepare pnpm@latest --activate && \
    apk add --no-cache curl

# Set Working Directory
WORKDIR /app

# --- 2. DEVELOPMENT STAGE (Local Setup) ---
FROM base AS development

ARG USER_ID=1000
ARG GROUP_ID=1000

RUN addgroup -g ${GROUP_ID} appgroup && adduser -u ${USER_ID} -G appgroup -s /bin/sh -D appuser && \
    chown -R ${USER_ID}:${GROUP_ID} /app /usr/local/bin/pnpm

# Health Check: Verifies that the source code volume is mounted correctly.
HEALTHCHECK --interval=30s --timeout=5s --start-period=5s --retries=3 \
    CMD test -f /app/package.json

USER appuser

# Default command: A long sleep to keep the container available for Exec commands
CMD ["sleep", "infinity"]

# --- 3. PRODUCTION BUILD STAGE (Secure Asset Build) ---
FROM base AS build

# Using a high, non-conflicting UID for maximum security in production.
ARG USER_ID=50000
ARG GROUP_ID=50000

RUN addgroup -g ${GROUP_ID} appgroup && adduser -u ${USER_ID} -G appgroup -s /bin/sh -D appuser && \
    chown -R ${USER_ID}:${GROUP_ID} /app /usr/local/bin/pnpm

USER appuser

# Copy dependency files
COPY package.json pnpm-lock.yaml ./

# Install ONLY production dependencies
RUN pnpm install --frozen-lockfile --prod

# Copy all application code
COPY . .

# Execute the actual build command (e.g., for Webpack, Vite, etc.)
# This assumes a "build" script is defined in package.json
RUN pnpm run build

# --- 4. PRODUCTION ASSET SERVER STAGE (Final for static assets/proxy) ---
# Default target: Inherits from 'build'.
# For image slimming, uncomment the 'FROM' and 'COPY' lines below.
FROM build AS asset-server
# OPTIONAL SLIMMING: FROM node:${NODE_VERSION}-alpine-slim${ALPINE_VERSION} AS asset-server

# Health Check: Verifies that the build artifacts are present.
HEALTHCHECK --interval=30s --timeout=5s --start-period=5s --retries=3 \
    CMD test -f /app/package.json

USER appuser

# --- Optional Slimming/Artifact Copy (Uncomment if using a slimmer base image above) ---
# COPY --from=build /etc/passwd /etc/group /etc/
# COPY --from=build /app/node_modules /app/node_modules
# COPY --from=build /app/dist /app/dist

# Cleanup step: Remove non-essential files to reduce final image size.
RUN rm -rf /app/pnpm-lock.yaml /app/.git* /app/docker /app/Makefile

# Standard command: Keep the container alive but idle.
CMD ["sleep", "infinity"]

# --- 5. PRODUCTION APP SERVER STAGE (Final for long-running Node.js process) ---
# Inherits from 'build' and defines the server start CMD.
FROM build AS app-server
# OPTIONAL SLIMMING: FROM node:${NODE_VERSION}-alpine-slim${ALPINE_VERSION} AS asset-server

# Health Check: Pings the running Node.js server to ensure it is responsive.
# Assumes the server runs on port 3000.
HEALTHCHECK --interval=15s --timeout=3s --start-period=10s --retries=3 \
    CMD curl --fail http://localhost:3000 || exit 1

USER appuser

# --- Optional Slimming/Artifact Copy (Uncomment if using a slimmer base image) ---
# COPY --from=build /etc/passwd /etc/group /etc/
# COPY --from=build /app/node_modules /app/node_modules
# COPY --from=build /app/dist /app/dist

# Cleanup step: Remove non-essential files to reduce final image size.
RUN rm -rf /app/pnpm-lock.yaml /app/.git* /app/docker /app/Makefile

# Starts the long-running Node.js server process.
CMD ["node", "dist/server.js"]