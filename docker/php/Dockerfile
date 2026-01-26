# PHP Multi-Stage Dockerfile

ARG PHP_VERSION=8.4
ARG ALPINE_VERSION=3.23
ARG GMAGICK_VERSION=2.0.6RC1
ARG COMPOSER_VERSION=2

# Version Pinning:
# - Minor version pinned for stability
# - Alpine version pinned for consistency across services
# - To update: check https://hub.docker.com/_/php/tags
# - For SHA digest pinning in production, use:
#   FROM php:8.4-fpm-alpine3.23@sha256:<digest>

# Alias stages for external images (BuildKit doesn't support variable expansion in --from)
FROM composer:${COMPOSER_VERSION} AS composer-image

# --- 1. PHP BUILDER STAGE (Compilation) ---
# This stage installs all build dependencies and compiles the extensions.
FROM php:${PHP_VERSION}-fpm-alpine${ALPINE_VERSION} AS php-builder

# Redeclare ARG (global scope ARGs don't persist after FROM)
ARG GMAGICK_VERSION

# Install ALL necessary dependencies to compile extensions
# We do NOT use --virtual here, as we will discard the builder stage later.
RUN apk add --no-cache \
    # General Build Toolchain (Required for all compilations)
    $PHPIZE_DEPS \
    libtool \
    linux-headers \
    libgomp \
    # Dependencies for GD (Extension)
    freetype \
    freetype-dev \
    libjpeg-turbo \
    libjpeg-turbo-dev \
    libpng \
    libpng-dev \
    libwebp \
    libwebp-dev \
    libavif \
    libavif-dev \
    # Dependencies for gmagick (Extension)
    graphicsmagick \
    graphicsmagick-dev \
    # Dependencies for intl (Extension)
    icu-libs \
    icu-dev \
    # Dependencies for zip (Extension)
    libzip \
    libzip-dev \
    # Dependencies for PostgreSQL PDO (Extension)
    postgresql-dev \
    postgresql-libs \
    # Dependencies for SQLite PDO (Extension)
    sqlite-dev \
    sqlite-libs \
    # Dependencies for soap (Extension)
    libxml2-dev \
    # Dependencies for amqp (Extension - RabbitMQ)
    rabbitmq-c \
    rabbitmq-c-dev \
    # Compilation and installation of extensions
    && docker-php-ext-install exif intl opcache zip pdo_pgsql pdo_mysql pdo_sqlite soap bcmath sockets pcntl \
    && docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
        --with-webp \
        --with-avif \
    && docker-php-ext-install -j$(nproc) gd \
    # gmagick 2.0.6RC1 is the only version supporting PHP 8.4 (no stable release yet)
    # Check https://pecl.php.net/package/gmagick for updates
    && pecl install gmagick-${GMAGICK_VERSION} \
    && pecl install redis \
    && pecl install amqp \
    && docker-php-ext-enable gmagick redis amqp \
    && rm -rf /tmp/pear \
    # Remove auto-generated INI files (we use our own in conf.d/)
    && rm -f /usr/local/etc/php/conf.d/docker-php-ext-*.ini

# --- 2. BASE STAGE (Shared Runtime Image - Small & Fast) ---
FROM php:${PHP_VERSION}-fpm-alpine${ALPINE_VERSION} AS base

# Install only the system dependencies required at runtime.
RUN apk add --no-cache \
    fcgi \
    openssl \
    libgomp \
    libcurl \
    freetype \
    libjpeg-turbo \
    libpng \
    libwebp \
    libavif \
    graphicsmagick \
    icu-libs \
    libzip \
    postgresql-libs \
    sqlite-libs \
    libxml2 \
    rabbitmq-c \
    tzdata

# Remove auto-generated extension INIs from base PHP image (we use our own)
RUN rm -f /usr/local/etc/php/conf.d/docker-php-ext-*.ini

# Copy the compiled extensions from the builder (INI files are our own)
COPY --from=php-builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/

# Copy extension configuration files (production-ready defaults)
# Note: development.ini and xdebug.ini are NOT copied here (dev-only, mounted via volumes)
# Note: curl is statically compiled into PHP and cannot be disabled via INI
COPY docker/php/conf.d/amqp.ini /usr/local/etc/php/conf.d/
COPY docker/php/conf.d/bcmath.ini /usr/local/etc/php/conf.d/
COPY docker/php/conf.d/exif.ini /usr/local/etc/php/conf.d/
COPY docker/php/conf.d/gd.ini /usr/local/etc/php/conf.d/
COPY docker/php/conf.d/gmagick.ini /usr/local/etc/php/conf.d/
COPY docker/php/conf.d/intl.ini /usr/local/etc/php/conf.d/
COPY docker/php/conf.d/opcache.ini /usr/local/etc/php/conf.d/
COPY docker/php/conf.d/pcntl.ini /usr/local/etc/php/conf.d/
COPY docker/php/conf.d/pdo_mysql.ini /usr/local/etc/php/conf.d/
COPY docker/php/conf.d/pdo_pgsql.ini /usr/local/etc/php/conf.d/
COPY docker/php/conf.d/pdo_sqlite.ini /usr/local/etc/php/conf.d/
COPY docker/php/conf.d/redis.ini /usr/local/etc/php/conf.d/
COPY docker/php/conf.d/soap.ini /usr/local/etc/php/conf.d/
COPY docker/php/conf.d/sockets.ini /usr/local/etc/php/conf.d/
COPY docker/php/conf.d/sodium.ini /usr/local/etc/php/conf.d/
COPY docker/php/conf.d/zip.ini /usr/local/etc/php/conf.d/

# Health Check (Unix Socket)
HEALTHCHECK --interval=30s --timeout=3s --start-period=40s --retries=3 \
    CMD SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET cgi-fcgi -bind -connect /var/run/php-fpm/php-fpm.sock || exit 1

# Setup Log Directories
RUN mkdir -p /var/log/app /var/log/php && \
    chown -R www-data:www-data /var/log/app /var/log/php

# Set Working Directory
WORKDIR /var/www/html

# --- 3. DEVELOPMENT STAGE ---
FROM base AS development

ARG USER_ID=1000
ARG GROUP_ID=1000

# Install development utilities, build tools, and database CLI clients
# hadolint ignore=SC2086 -- $PHPIZE_DEPS must be unquoted for word splitting (multiple packages)
RUN apk add --no-cache git unzip su-exec $PHPIZE_DEPS linux-headers \
    # Database CLI clients (standard)
    postgresql-client mysql-client sqlite \
    # Database CLI clients (enhanced with auto-complete)
    pgcli mycli litecli \
    # Docker CLI for backup script (uses host docker socket)
    docker-cli docker-cli-compose && \
    if [ "$USER_ID" != "82" ]; then \
        apk add --no-cache --virtual .user-deps shadow && \
        usermod -u $USER_ID www-data && \
        groupmod -g $GROUP_ID www-data && \
        apk del .user-deps; \
    fi && \
    # Add docker group and add www-data to it (for docker socket access)
    addgroup -g 973 docker 2>/dev/null || true && \
    addgroup www-data docker && \
    chown -R www-data:www-data /var/log/app /var/log/php /home/www-data

# Install Composer (only in development)
COPY --from=composer-image /usr/bin/composer /usr/bin/composer

# Install PIE (PHP Installer for Extensions)
# Note: PIE only provides :bin tag, no versioned releases available yet
COPY --from=ghcr.io/php/pie:bin /pie /usr/bin/pie

# Install Xdebug (INI is mounted via volumes in compose.override.yaml)
RUN pie install xdebug/xdebug \
    && rm -f /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && rm -f /usr/local/etc/php/conf.d/50-xdebug.ini

# Copy configuration files (same as production for consistency)
# Use www.conf to override the default pool configuration
COPY docker/php/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf
# Override zz-docker.conf to use Unix socket instead of TCP
COPY docker/php/zz-docker.conf /usr/local/etc/php-fpm.d/zz-docker.conf

# Note: Source code, configs, and dependencies are provided via bind mounts
# in development (see compose.override.yaml). No COPY needed here.

# Copy development entrypoint script
COPY docker/php/entrypoint.development.sh /entrypoint.development.sh
RUN chmod +x /entrypoint.development.sh

ENTRYPOINT ["/entrypoint.development.sh"]
CMD ["php-fpm"]

# --- 4. PRODUCTION BASE STAGE (Shared) ---
FROM base AS production-base

# Copy configuration files
COPY docker/php/php.ini /usr/local/etc/php/php.ini
# Use www.conf to override the default pool configuration
COPY docker/php/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf
# Override zz-docker.conf to use Unix socket instead of TCP
COPY docker/php/zz-docker.conf /usr/local/etc/php-fpm.d/zz-docker.conf

# Copy production entrypoint script
COPY docker/php/entrypoint.production.sh /entrypoint.production.sh
RUN chmod +x /entrypoint.production.sh

# Install Composer temporarily for dependency installation
COPY --from=composer-image /usr/bin/composer /usr/bin/composer

# Install unzip temporarily (required by composer)
RUN apk add --no-cache --virtual .composer-deps unzip

# Copy composer files and install dependencies
# Note: composer.lock is optional (not committed in boilerplate)
COPY --chown=www-data:www-data composer.json ./
COPY --chown=www-data:www-data composer.lock* ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# Copy application source code and public files
COPY --chown=www-data:www-data config ./config
COPY --chown=www-data:www-data public/ ./public
COPY --chown=www-data:www-data src/php ./src/php
COPY --chown=www-data:www-data templates ./templates

# --- 5. PRODUCTION STAGE (with Vite Assets) ---
# Use for: NODE_MODE=assets, assets-api, api
# Serves Vite-built frontend assets (copied from node-backend build)
# Security: Runs entirely as www-data user - NO capabilities needed
FROM production-base AS production

# Copy built frontend assets from Node backend build stage
# This allows serving production-built Vite assets via NGINX
# Note: node-backend image must be built first (see Makefile build order)
# Note: :latest tag is an alias created by Makefile during production build
#       for cross-image COPY compatibility (BuildKit doesn't support variables in --from)
COPY --from=zappzarapp-node-backend:latest --chown=www-data:www-data /app/public/build/ ./public/build/

# Optimize Autoloader, Cleanup & Permission Hardening
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative && \
    rm -rf /usr/bin/composer /home/www-data/.composer /tmp/* && \
    apk del .composer-deps && \
    # Permission hardening
    chown -R root:root /var/www/html && \
    chmod -R 555 /var/www/html && \
    mkdir -p /var/www/html/storage && \
    chown -R www-data:www-data /var/www/html/storage && \
    chmod 770 /var/www/html/storage && \
    find /var/www/html/storage -type f -exec chmod 660 {} \; 2>/dev/null || true && \
    # Prepare socket directory for unprivileged operation
    mkdir -p /var/run/php-fpm && \
    chown www-data:www-data /var/run/php-fpm

# Run as www-data user (unprivileged) - NO root, NO capabilities
USER www-data

ENTRYPOINT ["/entrypoint.production.sh"]
CMD ["php-fpm"]

# --- 6. TEST STAGE (CI only) ---
# Validates the production image using GOSS
# Usage: docker build --target test -f docker/php/Dockerfile .
FROM production-base AS test

# Generate autoloader (normally done in production stage, but test runs on production-base)
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative

# Copy GOSS binary from centralized image
COPY --from=zappzarapp-goss:latest /usr/bin/goss /usr/local/bin/goss
RUN chmod +x /usr/local/bin/goss

# Copy test specification
COPY tests/goss/services/php.yaml /goss.yaml

# Run GOSS tests (fails build if tests fail)
RUN goss -g /goss.yaml validate --format documentation

# --- 7. PRODUCTION FRAMEWORK STAGE (no Vite Assets) ---
# Use for: NODE_MODE=framework, framework-api
# Pure PHP backend - frontend frameworks (Nuxt/Next) serve their own assets
FROM production-base AS production-framework

# Optimize Autoloader, Cleanup & Permission Hardening (no Vite assets to copy)
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative && \
    rm -rf /usr/bin/composer /home/www-data/.composer /tmp/* && \
    apk del .composer-deps && \
    # Permission hardening
    chown -R root:root /var/www/html && \
    chmod -R 555 /var/www/html && \
    mkdir -p /var/www/html/storage && \
    chown -R www-data:www-data /var/www/html/storage && \
    chmod 770 /var/www/html/storage && \
    find /var/www/html/storage -type f -exec chmod 660 {} \; 2>/dev/null || true && \
    # Prepare socket directory for unprivileged operation
    mkdir -p /var/run/php-fpm && \
    chown www-data:www-data /var/run/php-fpm

# Run as www-data user (unprivileged) - NO root, NO capabilities
USER www-data

ENTRYPOINT ["/entrypoint.production.sh"]
CMD ["php-fpm"]
