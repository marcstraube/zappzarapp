ARG PHP_VERSION=8.4
ARG ALPINE_VERSION=3.22

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
    && docker-php-ext-install exif intl opcache zip pdo_pgsql pdo_mysql \
    && docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
        --with-webp \
        --with-avif \
    && docker-php-ext-install -j$(nproc) gd \
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
    freetype \
    libjpeg-turbo \
    libpng \
    libwebp \
    libavif \
    graphicsmagick \
    icu-libs \
    libzip \
    postgresql-libs

# Copy the compiled extensions and configuration files from the builder
COPY --from=php-builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/
COPY --from=php-builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/

# Health Check
HEALTHCHECK --interval=30s --timeout=3s --start-period=40s --retries=3 \
    CMD SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET cgi-fcgi -bind -connect 127.0.0.1:9000 || exit 1

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

# Switch to App User
USER www-data

CMD ["php-fpm"]

# --- 4. PRODUCTION STAGE ---
FROM base AS production

# Copy configuration files
COPY docker/php/php.ini /usr/local/etc/php/php.ini
COPY docker/php/php-fpm.conf /usr/local/etc/php-fpm.d/zz-custom.conf

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

# Switch to App User
USER www-data

CMD ["php-fpm"]

# @TODO Where do we uninstall composer?
