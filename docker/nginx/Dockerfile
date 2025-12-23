ARG NGINX_VERSION=1.29
ARG ALPINE_VERSION=3.22

# --- 1. BASE STAGE (Shared) ---
FROM nginx:${NGINX_VERSION}-alpine${ALPINE_VERSION} AS base

# Install Brotli Module (Optional - Uncomment if you want to use Brotli instead of Gzip)
# RUN apk add --no-cache nginx-mod-http-brotli

# Setup Permissions & PID File
RUN mkdir -p /var/cache/nginx /var/log/nginx /tmp/nginx && \
    touch /var/run/nginx.pid && \
    chown -R nginx:nginx /var/cache/nginx /var/log/nginx /etc/nginx/conf.d /var/run/nginx.pid /tmp/nginx

# Copy Config
COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/nginx/conf.d /etc/nginx/conf.d

# Switch to non-root user
USER nginx

# Set Working Directory
WORKDIR /var/www/html

# Expose Unprivileged Port
EXPOSE 8080

# Health Check
HEALTHCHECK --interval=30s --timeout=3s --start-period=10s --retries=3 \
    CMD wget --no-verbose --tries=1 --spider http://127.0.0.1:8080/health || exit 1

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

USER nginx

# --- 3. PRODUCTION STAGE ---
FROM base AS production

USER root

# Copy Public Assets
COPY --chown=nginx:nginx public/ /var/www/html/public/

# Copy built frontend assets from Node build stage
# This allows NGINX to serve production-built Vite assets directly
COPY --from=docker-webdev-node:latest --chown=nginx:nginx /app/public/build/ /var/www/html/public/build/

# Harden permissions (read-only)
RUN chmod -R 555 /var/www/html/public

USER nginx
