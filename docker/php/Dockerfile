# PHP Multi-Stage Dockerfile
#
# Version Pinning:
# - Minor version pinned for stability
# - To update: check https://hub.docker.com/_/php/tags
# - For SHA digest pinning in production, use:
#   FROM php:8.4-fpm-alpine3.23@sha256:<digest>

ARG PHP_VERSION=8.4
ARG ALPINE_VERSION=3.23

# --- 1. PHP BUILDER STAGE (Compilation) ---
# This stage installs all build dependencies and compiles the extensions.
FROM php:${PHP_VERSION}-fpm-alpine${ALPINE_VERSION} AS php-builder

# Install ALL necessary dependencies to compile extensions
# We do NOT use --virtual here, as we will discard the builder stage later.
RUN apk add --no-cache \
    # General Build Toolchain (Required for all compilations)
    $PHPIZE_DEPS \
    libtool \
    linux-headers \
    libgomp \
    # Dependencies for curl (Extension) - speeds up Composer significantly
    curl-dev \
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
    # Compilation and installation of extensions
    && docker-php-ext-install curl exif intl opcache zip pdo_pgsql pdo_mysql \
    && docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
        --with-webp \
        --with-avif \
    && docker-php-ext-install -j$(nproc) gd \
    # gmagick 2.0.6RC1 is the only version supporting PHP 8.4 (no stable release yet)
    # Check https://pecl.php.net/package/gmagick for updates
    && pecl install gmagick-2.0.6RC1 \
    && pecl install redis \
    && docker-php-ext-enable gmagick redis \
    && rm -rf /tmp/pear

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
    tzdata

# Copy the compiled extensions and configuration files from the builder
COPY --from=php-builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/
COPY --from=php-builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/

# Health Check (Unix Socket)
HEALTHCHECK --interval=30s --timeout=3s --start-period=40s --retries=3 \
    CMD SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET cgi-fcgi -bind -connect /var/run/php-fpm/php-fpm.sock || exit 1

# Setup Log Directories
RUN mkdir -p /var/log/{app,php} && \
    chown -R www-data:www-data /var/log/{app,php}

# Set Working Directory
WORKDIR /var/www/html

# --- 3. DEVELOPMENT STAGE ---
FROM base AS development

ARG USER_ID=1000
ARG GROUP_ID=1000

# Install development utilities and build tools
RUN apk add --no-cache git unzip $PHPIZE_DEPS linux-headers && \
    if [ "$USER_ID" != "82" ]; then \
        apk add --no-cache --virtual .user-deps shadow && \
        usermod -u $USER_ID www-data && \
        groupmod -g $GROUP_ID www-data && \
        apk del .user-deps; \
    fi && \
    chown -R www-data:www-data /var/log/{app,php} /home/www-data

# Install Composer (only in development)
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install PIE (PHP Installer for Extensions)
COPY --from=ghcr.io/php/pie:bin /pie /usr/bin/pie

# Install Xdebug
RUN pie install xdebug/xdebug

# Copy configuration files (same as production for consistency)
# Use www.conf to override the default pool configuration
COPY docker/php/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf
# Override zz-docker.conf to use Unix socket instead of TCP
COPY docker/php/zz-docker.conf /usr/local/etc/php-fpm.d/zz-docker.conf

# Copy entrypoint script (sets up timezone from TZ env var)
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Copy source code into image (Docker Compose Watch will sync changes)
COPY --chown=www-data:www-data src/php/ ./src/php/
COPY --chown=www-data:www-data tests/php/ ./tests/php/
COPY --chown=www-data:www-data templates/ ./templates/

# Keep as root for entrypoint (entrypoint will handle permissions and su to www-data)
# PHP-FPM will still run as www-data (configured in php-fpm.conf)

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]

# --- 4. PRODUCTION STAGE ---
FROM base AS production

# Copy configuration files
COPY docker/php/php.ini /usr/local/etc/php/php.ini
# Use www.conf to override the default pool configuration
COPY docker/php/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf
# Override zz-docker.conf to use Unix socket instead of TCP
COPY docker/php/zz-docker.conf /usr/local/etc/php-fpm.d/zz-docker.conf

# Copy entrypoint script (sets up timezone from TZ env var)
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Install Composer temporarily for dependency installation
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install unzip temporarily (required by composer)
RUN apk add --no-cache --virtual .composer-deps unzip

# Copy composer files and install dependencies
COPY --chown=www-data:www-data composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# Copy application source code and public files
COPY --chown=www-data:www-data config ./config
COPY --chown=www-data:www-data public/ ./public
COPY --chown=www-data:www-data src/php ./src/php
COPY --chown=www-data:www-data templates ./templates

# Copy built frontend assets from Node build stage
# This allows serving production-built Vite assets via NGINX
# Note: NODE_IMAGE must be built first (see compose.yaml build order)
COPY --from=docker-webdev-node:latest --chown=www-data:www-data /app/public/build/ ./public/build/

# Optimize Autoloader & Cleanup
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative && \
    rm -rf /usr/bin/composer /home/www-data/.composer /tmp/* && \
    apk del .composer-deps

# Permission Hardening
RUN chown -R root:root /var/www/html && \
    chmod -R 555 /var/www/html && \
    mkdir -p /var/www/html/storage && \
    chown -R www-data:www-data /var/www/html/storage && \
    chmod 770 /var/www/html/storage && \
    find /var/www/html/storage -type f -exec chmod 660 {} \; 2>/dev/null || true

# Entrypoint runs as root to set up timezone, PHP-FPM runs as www-data (configured in php-fpm.conf)
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
