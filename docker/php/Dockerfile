# --- 1. BASE STAGE (Shared) ---
FROM php:8.4-fpm-alpine3.22 AS base

# Install System Dependencies (Runtime only)
RUN apk add --no-cache \
    fcgi \
    graphicsmagick \
    libzip \
    libpng \
    libjpeg-turbo \
    libwebp \
    libavif \
    freetype \
    libgomp \
    icu-libs

# Install Build Dependencies, Compile Extensions, then Cleanup
RUN apk add --no-cache --virtual .build-deps \
    $PHPIZE_DEPS \
    libtool \
    linux-headers \
    graphicsmagick-dev \
    freetype-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    libwebp-dev \
    libavif-dev \
    libzip-dev \
    icu-dev \
    && docker-php-ext-install zip \
    && docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
        --with-webp \
        --with-avif \
    && docker-php-ext-install -j$(nproc) gd \
    && docker-php-ext-install exif intl opcache \
    && pecl install gmagick-2.0.6RC1 \
    && docker-php-ext-enable gmagick \
    && apk del .build-deps \
    && rm -rf /tmp/pear

# Health Check
HEALTHCHECK --interval=30s --timeout=3s --start-period=40s --retries=3 \
    CMD SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET cgi-fcgi -bind -connect 127.0.0.1:9000 || exit 1

# Setup Log Directories
RUN mkdir -p /var/log/{app,php} && \
    chown -R www-data:www-data /var/log/{app,php}

# Set Working Directory
WORKDIR /var/www/html

# --- 2. DEVELOPMENT STAGE ---
FROM base AS development

ARG USER_ID=1000
ARG GROUP_ID=1000

# Install development utilities and build tools
# - git, unzip: Required for Composer
# - $PHPIZE_DEPS & linux-headers: Required to compile Xdebug or other extensions from source
# - shadow: Required for usermod/groupmod to change UID/GID matching the host user
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

# --- 3. PRODUCTION STAGE ---
FROM base AS production

# Install Composer temporarily for dependency installation
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install unzip temporarily (required by composer)
RUN apk add --no-cache --virtual .composer-deps unzip

# Copy Dependencies first (Layer Caching optimization)
COPY --chown=www-data:www-data composer.json composer.lock ./

# Install Dependencies (No Dev, Optimized)
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# Copy Application Code
COPY --chown=www-data:www-data . .

# Optimize Autoloader & Cleanup
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative && \
    rm -rf /usr/bin/composer /home/www-data/.composer /tmp/* && \
    apk del .composer-deps

# Permission Hardening
RUN chown -R root:root /var/www/html && \
    chmod -R 555 /var/www/html && \
    mkdir -p /var/www/html/data && \
    chown -R www-data:www-data /var/www/html/data && \
    chmod 770 /var/www/html/data && \
    find /var/www/html/data -type f -exec chmod 660 {} \; 2>/dev/null || true

# Switch to App User
USER www-data

CMD ["php-fpm"]
