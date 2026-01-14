# Nginx Multi-Stage Dockerfile
#
# Version Pinning:
# - Alpine version pinned (includes nginx + brotli module)
# - Alpine 3.23 provides nginx 1.28.0 and nginx-mod-http-brotli 1.28.0 (version-matched)
# - To update: check https://hub.docker.com/_/alpine/tags
# - For SHA digest pinning in production, use:
#   FROM alpine:3.23@sha256:<digest>

ARG ALPINE_VERSION=3.23

# --- 1. BASE STAGE (Shared) ---
# Using Alpine 3.23 directly for version-matched nginx + brotli module
FROM alpine:${ALPINE_VERSION} AS base

# Install Nginx with Brotli module & envsubst for template processing & Setup
RUN apk add --no-cache \
        nginx \
        nginx-mod-http-brotli \
        wget \
        gettext \
        tzdata && \
    mkdir -p /var/cache/nginx /var/log/nginx /tmp/nginx /var/www/html /etc/nginx/conf.d && \
    touch /var/run/nginx.pid && \
    chown -R nginx:nginx /var/cache/nginx /var/log/nginx /etc/nginx/conf.d /var/run/nginx.pid /tmp/nginx

# Copy Config
COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
# Copy only base configs (SSL configs are mounted via volumes when enabled)
COPY docker/nginx/conf.d/default.conf /etc/nginx/conf.d/default.conf
COPY docker/nginx/conf.d/csp-production.conf /etc/nginx/conf.d/csp-production.conf
# Copy custom error pages
COPY docker/nginx/errors/ /usr/share/nginx/html/errors/

# Copy Entrypoint Script for SSL template processing
COPY docker/nginx/entrypoint.sh /docker-entrypoint.sh
RUN chmod +x /docker-entrypoint.sh

# Set Working Directory
WORKDIR /var/www/html

# Expose Unprivileged Port
EXPOSE 8080

# Health Check (accepts self-signed certificates in development)
HEALTHCHECK --interval=30s --timeout=3s --start-period=10s --retries=3 \
    CMD wget --no-verbose --tries=1 --spider --no-check-certificate http://127.0.0.1:8080/health || exit 1

# Entrypoint processes templates (runs as root, then starts nginx)
ENTRYPOINT ["/docker-entrypoint.sh"]
CMD ["nginx", "-g", "daemon off;"]

# --- 2. DEVELOPMENT STAGE ---
FROM base AS development

USER root

ARG USER_ID=1000
ARG GROUP_ID=1000

# Install shadow to modify user ID matching the host user
# This ensures that the nginx user has write access to mounted host volumes (logs, code)
RUN if [ "$USER_ID" != "101" ]; then \
        apk add --no-cache --virtual .user-deps shadow && \
        usermod -u $USER_ID nginx && \
        groupmod -g $GROUP_ID nginx && \
        apk del .user-deps ; \
    fi && \
    chown -R nginx:nginx /var/cache/nginx /var/log/nginx /etc/nginx/conf.d /var/run/nginx.pid /tmp/nginx

# Note: Container runs as root to process SSL templates via entrypoint, then starts nginx

# --- 3. PRODUCTION STAGE ---
FROM base AS production

USER root

# Copy Public Assets
COPY --chown=nginx:nginx public/ /var/www/html/public/

# Copy built frontend assets from Node build stage
# This allows NGINX to serve production-built Vite assets directly
# Note: NODE_IMAGE must be built first (see compose.yaml build order)
COPY --from=zappzarapp-node:latest --chown=nginx:nginx /app/public/build/ /var/www/html/public/build/

# Harden permissions (read-only)
RUN chmod -R 555 /var/www/html/public

# Note: Container runs as root to process SSL templates via entrypoint, then starts nginx
