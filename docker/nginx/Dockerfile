# Nginx Multi-Stage Dockerfile (Unprivileged)
#
# Security: Runs entirely as nginx user (uid 101) - NO root, NO capabilities needed

ARG ALPINE_VERSION=3.24

# Version Pinning:
# - Alpine version pinned for consistency across services
# - Alpine includes version-matched nginx + brotli module
# - To update: check https://hub.docker.com/_/alpine/tags
# - For SHA digest pinning in production, use:
#   FROM alpine:<tag>@sha256:<digest>

# --- 1. BASE STAGE (Shared) ---
# Using Alpine directly for version-matched nginx + brotli module
FROM alpine:${ALPINE_VERSION} AS base

# Install Nginx with Brotli module & envsubst for template processing & Setup
# All directories owned by nginx user for unprivileged operation
# apk upgrade first applies available OS security patches within the pinned
# Alpine line (Trivy runs with ignore-unfixed → reported CVEs have a fix here).
RUN apk upgrade --no-cache && \
    apk add --no-cache \
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
# Explicit absolute-mode --chmod (not `chmod +x`): the dev stage remaps the nginx
# user to the host UID, so the runtime user differs from the baked file owner and
# reads it as "other". A bare `chmod +x` on a umask-077 source yields 711
# (execute-only, no read) → the shell cannot open the script. `--chmod=0755`
# guarantees world read+execute regardless of host umask.
COPY --chown=nginx:nginx --chmod=0755 docker/nginx/entrypoint.sh /docker-entrypoint.sh

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

# Copy production-built Vite assets from the node-backend image so NGINX serves
# them directly. `node-backend-assets` is a TAGLESS named context, NOT an image:
# CI/bake resolve it via `docker buildx bake` (contexts = target:node-backend),
# the compose production build via `additional_contexts`
# (docker-image://…node-backend:latest). Tagless is required because a bake
# contexts key with a colon is silently ignored. See docker-bake.hcl.
COPY --from=node-backend-assets --chown=nginx:nginx /app/public/build/ /var/www/html/public/build/

# Harden permissions (read-only for content, nginx user owns it)
USER root
RUN chmod -R 555 /var/www/html/public

# Run as nginx user (unprivileged) - NO root, NO capabilities
USER nginx

# --- 4. TEST STAGE (CI only) ---
# Validates the production image using GOSS
# Usage: docker build --target test -f docker/nginx/Dockerfile .
FROM production AS test

# Copy GOSS binary from centralized image (needs root temporarily)
USER root
COPY --from=goss --chmod=0755 /usr/bin/goss /usr/local/bin/goss

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
