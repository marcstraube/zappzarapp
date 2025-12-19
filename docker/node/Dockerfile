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

RUN if [ "$USER_ID" != "1000" ]; then \
        apk add --no-cache --virtual .user-deps shadow && \
        usermod -u $USER_ID node && \
        groupmod -g $GROUP_ID node && \
        apk del .user-deps; \
    fi && \
    chown -R node:node /home/node /app /usr/local/bin/pnpm

# Health Check: Verifies that the source code volume is mounted correctly.
HEALTHCHECK --interval=30s --timeout=5s --start-period=5s --retries=3 \
    CMD test -f /app/package.json

USER node

# Default command: A long sleep to keep the container available for Exec commands
CMD ["sleep", "infinity"]

# --- 3. PRODUCTION BUILD STAGE (Secure Asset Build) ---
FROM base AS build

# Using a high, non-conflicting UID for maximum security in production.
RUN apk add --no-cache --virtual .user-deps shadow && \
    usermod -u 50000 node && \
    groupmod -g 50000 node && \
    apk del .user-deps && \
    chown -R node:node /home/node /app /usr/local/bin/pnpm

USER node

# Copy dependency files
COPY --chown=node:node package.json pnpm-lock.yaml ./

# Install ALL dependencies (including devDependencies for build)
RUN pnpm install --frozen-lockfile

# Copy ONLY necessary source files
COPY --chown=node:node vite.config.js ./
COPY --chown=node:node tsconfig.json ./
COPY --chown=node:node postcss.config.js ./
COPY --chown=node:node resources/ ./resources/
COPY --chown=node:node src/node/ ./src/node/

# Execute the build command
RUN pnpm run build

# Image Slimming - Remove DevDependencies
RUN pnpm prune --prod

# --- 4. PRODUCTION ASSET SERVER STAGE ---
FROM base AS asset-server

# Copy user/group from build stage
COPY --from=build /etc/passwd /etc/group /etc/

# Health Check: Verifies that the build artifacts are present.
HEALTHCHECK --interval=30s --timeout=5s --start-period=5s --retries=3 \
    CMD test -f /app/package.json

# Copy only build artifacts
COPY --from=build --chown=node:node /app/public/build /app/public/build
COPY --from=build --chown=node:node /app/package.json /app/package.json

USER node

CMD ["sleep", "infinity"]

# --- 5. PRODUCTION APP SERVER STAGE ---
FROM base AS app-server

# Copy user/group from build stage
COPY --from=build /etc/passwd /etc/group /etc/

# Health Check: Pings the running Node.js server to ensure it is responsive.
HEALTHCHECK --interval=15s --timeout=3s --start-period=10s --retries=3 \
    CMD curl --fail http://localhost:3000/health || exit 1

# Copy production dependencies and built application
COPY --from=build --chown=node:node /app/node_modules /app/node_modules
COPY --from=build --chown=node:node /app/dist /app/dist
COPY --from=build --chown=node:node /app/package.json /app/package.json

USER node

CMD ["node", "dist/server.js"]