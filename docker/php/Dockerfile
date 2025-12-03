FROM php:8.4-fpm-alpine3.22

ARG ENV
ARG USER_ID=1000
ARG GROUP_ID=1000

# Install System Dependencies
RUN apk add --no-cache \
    $PHPIZE_DEPS \
    libtool \
    linux-headers \
    graphicsmagick \
    graphicsmagick-dev \
    freetype-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    libwebp-dev \
    libavif-dev \
    libzip \
    libzip-dev \
    git \
    unzip

# Install ZIP extension
RUN docker-php-ext-install zip

# Configure and install GD with all image formats
RUN docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg \
    --with-webp \
    --with-avif && \
    docker-php-ext-install -j$(nproc) gd

# Install EXIF extension
RUN docker-php-ext-install exif

# Install OPcache
RUN docker-php-ext-install opcache

# Install GraphicsMagick PHP Extension (gmagick)
RUN pecl install gmagick-2.0.6RC1 && \
    docker-php-ext-enable gmagick

# Install PIE (PHP Installer for Extensions)
COPY --from=ghcr.io/php/pie:bin /pie /usr/bin/pie

# Install Xdebug (but don't auto-enable)
RUN pie install --skip-enable-extension xdebug/xdebug

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Update USER_ID und GROUP_ID for www-data
RUN deluser www-data && \
    addgroup -g $GROUP_ID -S www-data && \
    adduser -u $USER_ID -G www-data -h /home/www-data -s /sbin/nologin -S www-data && \
    chown -R www-data:www-data /var/www/html

# Create Log Directories
RUN mkdir -p /var/log/{app,php} && \
    chown -R www-data:www-data /var/log/{app,php}

# Set Working Directory
WORKDIR /var/www/html

# Copy Entrypoint Script
COPY docker/php/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Cleanup
RUN rm -rf /tmp/* /var/cache/apk/*

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["php-fpm"]
