# Nginx Multi-Stage Dockerfile (Unprivileged)
#
# Security: Runs entirely as nginx user (uid 101) - NO root, NO capabilities needed
#
# Version Pinning:
# - Alpine version pinned (includes nginx + brotli module)
# - Alpine 3.23 provides nginx 1.28.0 and nginx-mod-http-brotli 1.28.0 (version-matched)
# - To update: check https://hub.docker.com/_/alpine/tags
# - For SHA digest pinning in production, use:
#   FROM alpine:3.23@sha256:<digest>

ARG ALPINE_VERSION=3.23
ARG GOSS_VERSION=v0.4.9

# GOSS stage for test stages (workaround: --from doesn't support ARG expansion)
FROM ghcr.io/goss-org/goss:${GOSS_VERSION} AS goss

# --- 1. BASE STAGE (Shared) ---
# Using Alpine 3.23 directly for version-matched nginx + brotli module
FROM alpine:${ALPINE_VERSION} AS base

# Install Nginx with Brotli module & envsubst for template processing & Setup
# All directories owned by nginx user for unprivileged operation
RUN apk add --no-cache \
        nginx \
        nginx-mod-http-brotli \
        wget \
        gettext \
        tzdata && \
    mkdir -p /var/cache/nginx /var/log/nginx /tmp/nginx /var/www/html \
             /etc/nginx/conf.d /etc/nginx/snippets /run/nginx/conf.d /run/nginx/snippets && \
    touch /var/run/nginx.pid && \
    chown -R nginx:nginx /var/cache/nginx /var/log/nginx /etc/nginx/conf.d \
           /etc/nginx/snippets /var/run/nginx.pid /tmp/nginx /run/nginx /var/www/html

# Copy Config (owned by nginx)
COPY --chown=nginx:nginx docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY --chown=nginx:nginx docker/nginx/snippets/ /etc/nginx/snippets/
# Copy custom error pages
COPY --chown=nginx:nginx docker/nginx/errors/ /usr/share/nginx/html/errors/

# Copy Entrypoint Script for SSL template processing
COPY --chown=nginx:nginx docker/nginx/entrypoint.sh /docker-entrypoint.sh
RUN chmod +x /docker-entrypoint.sh

# Set Working Directory
WORKDIR /var/www/html

# Expose Unprivileged Port
EXPOSE 8080

# Health Check (accepts self-signed certificates in development)
HEALTHCHECK --interval=30s --timeout=3s --start-period=10s --retries=3 \
    CMD wget --no-verbose --tries=1 --spider --no-check-certificate http://127.0.0.1:8080/health || exit 1

# Entrypoint processes templates as nginx user (no root needed)
ENTRYPOINT ["/docker-entrypoint.sh"]
CMD ["nginx", "-g", "daemon off;"]

# --- 2. DEVELOPMENT STAGE ---
FROM base AS development

# Development: May need to match host user ID for volume mounts
ARG USER_ID=1000
ARG GROUP_ID=1000

# Install shadow to modify user ID matching the host user (if needed)
# This ensures that the nginx user has write access to mounted host volumes
USER root
RUN if [ "$USER_ID" != "101" ]; then \
        apk add --no-cache --virtual .user-deps shadow && \
        usermod -u $USER_ID nginx && \
        groupmod -g $GROUP_ID nginx && \
        apk del .user-deps ; \
    fi && \
    chown -R nginx:nginx /var/cache/nginx /var/log/nginx /etc/nginx/conf.d \
           /var/run/nginx.pid /tmp/nginx /run/nginx

# Run as nginx user (unprivileged)
USER nginx

# --- 3. PRODUCTION STAGE (with Vite Assets) ---
# Use for: NODE_MODE=assets, assets-api, api
# Serves Vite-built frontend assets directly
# Security: Runs entirely as nginx user - NO capabilities needed
FROM base AS production

# Copy Public Assets (as nginx user)
COPY --chown=nginx:nginx public/ /var/www/html/public/

# Copy built frontend assets from Node backend build stage
# This allows NGINX to serve production-built Vite assets directly
# Note: node-backend image must be built first (see Makefile build order)
COPY --from=zappzarapp-node-backend:latest --chown=nginx:nginx /app/public/build/ /var/www/html/public/build/

# Harden permissions (read-only for content, nginx user owns it)
USER root
RUN chmod -R 555 /var/www/html/public

# Run as nginx user (unprivileged) - NO root, NO capabilities
USER nginx

# --- 4. TEST STAGE (CI only) ---
# Validates the production image using GOSS
# Usage: docker build --target test -f docker/nginx/Dockerfile .
FROM production AS test

# Copy GOSS binary from official image (needs root temporarily)
USER root
COPY --from=goss /usr/bin/goss /usr/local/bin/goss
RUN chmod +x /usr/local/bin/goss

# Copy test specification
COPY --chown=nginx:nginx tests/goss/services/nginx.yaml /goss.yaml

# Run GOSS tests as nginx user
USER nginx
RUN goss -g /goss.yaml validate --format documentation

# --- 5. PRODUCTION PROXY STAGE (no Vite Assets) ---
# Use for: NODE_MODE=framework, framework-api
# Pure reverse proxy - frontend frameworks (Nuxt/Next) serve their own assets
# Security: Runs entirely as nginx user - NO capabilities needed
FROM base AS production-proxy

# Copy Public Assets (favicon, robots.txt, etc. - but no Vite build)
COPY --chown=nginx:nginx public/ /var/www/html/public/

# Harden permissions (read-only)
USER root
RUN chmod -R 555 /var/www/html/public

# Run as nginx user (unprivileged) - NO root, NO capabilities
USER nginx
