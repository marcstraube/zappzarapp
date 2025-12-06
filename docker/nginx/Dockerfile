# --- 1. BASE STAGE (Shared) ---
FROM nginx:1.29-alpine3.22 AS base

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
    CMD wget --no-verbose --tries=1 --spider http://localhost:8080/health || exit 1

CMD ["nginx", "-g", "daemon off;"]

# --- 2. DEVELOPMENT STAGE ---
FROM base AS development

USER root

ARG USER_ID=1000
ARG GROUP_ID=1000

# Install shadow to modify user ID matching the host user
# This ensures that the nginx user has write access to mounted host volumes (logs, code)
RUN apk add --no-cache --virtual .user-deps shadow && \
    if [ "$USER_ID" != "101" ]; then \
        usermod -u $USER_ID nginx && \
        groupmod -g $GROUP_ID nginx; \
    fi && \
    # Re-apply permissions to nginx directories after ID change
    chown -R nginx:nginx /var/cache/nginx /var/log/nginx /etc/nginx/conf.d /var/run/nginx.pid /tmp/nginx && \
    apk del .user-deps

USER nginx

# --- 3. PRODUCTION STAGE ---
FROM base AS production

USER root

# Copy Public Assets
COPY --chown=nginx:nginx app/public /var/www/html/app/public

# Harden permissions (read-only)
RUN chmod -R 555 /var/www/html/app/public

USER nginx