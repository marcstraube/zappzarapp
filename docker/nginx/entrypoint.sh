#!/bin/sh
set -e

# Default values if not set
export NGINX_SSL_PORT=${NGINX_SSL_PORT:-8443}

# Configure index directive and try_files fallback based on ENABLE_PHP
# When PHP is disabled, serve static index.html instead of routing to PHP
if [ "${ENABLE_PHP:-true}" = "true" ]; then
    export INDEX_DIRECTIVE="index.php index.html"
    export TRY_FILES_FALLBACK='/index.php?$query_string'

    # Generate health check snippet for PHP mode
    mkdir -p /run/nginx/snippets
    cat > /run/nginx/snippets/health-check.conf << 'HEALTH_PHP'
# Health Check Endpoint (PHP mode)
# Full health check via PHP, falls back to static JSON if PHP unavailable
location = /health {
    access_log off;
    try_files /health.php @nginx_health;
    fastcgi_pass unix:/var/run/php-fpm/php-fpm.sock;
    fastcgi_index health.php;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root/health.php;
    fastcgi_param SCRIPT_NAME /health.php;
    fastcgi_param HTTPS on;
}

# Fallback health check when PHP-FPM is unreachable
location @nginx_health {
    default_type application/json;
    return 200 '{"status":"ok","service":"nginx","timestamp":"$time_iso8601"}';
}
HEALTH_PHP
else
    export INDEX_DIRECTIVE="index.html"
    export TRY_FILES_FALLBACK="/index.html"
    echo "PHP disabled - using static index.html fallback"

    # Generate health check snippet for static mode (no PHP)
    mkdir -p /run/nginx/snippets
    cat > /run/nginx/snippets/health-check.conf << 'HEALTH_STATIC'
# Health Check Endpoint (Static mode - no PHP)
location = /health {
    access_log off;
    default_type application/json;
    return 200 '{"status":"ok","service":"nginx","timestamp":"$time_iso8601"}';
}
HEALTH_STATIC
fi

# Create runtime config directory (for read-only filesystems)
mkdir -p /run/nginx/conf.d

# Process SSL config template if it exists (development or production)
# Output goes to /run/nginx/conf.d/ which is writable (tmpfs in production)
if [ -f /etc/nginx/conf.d/ssl-development.conf.template ]; then
    echo "Processing SSL development configuration template..."
    envsubst '${NGINX_SSL_PORT} ${INDEX_DIRECTIVE} ${TRY_FILES_FALLBACK}' < /etc/nginx/conf.d/ssl-development.conf.template > /run/nginx/conf.d/ssl.conf
    chown nginx:nginx /run/nginx/conf.d/ssl.conf
    echo "SSL configuration generated with NGINX_SSL_PORT=${NGINX_SSL_PORT}, INDEX=${INDEX_DIRECTIVE}"
elif [ -f /etc/nginx/conf.d/ssl-production.conf.template ]; then
    echo "Processing SSL production configuration template..."
    envsubst '${NGINX_SSL_PORT} ${DOMAIN} ${INDEX_DIRECTIVE} ${TRY_FILES_FALLBACK}' < /etc/nginx/conf.d/ssl-production.conf.template > /run/nginx/conf.d/ssl.conf
    chown nginx:nginx /run/nginx/conf.d/ssl.conf
    echo "SSL configuration generated for DOMAIN=${DOMAIN}, NGINX_SSL_PORT=${NGINX_SSL_PORT}, INDEX=${INDEX_DIRECTIVE}"
fi

# Start nginx as nginx user (not root)
exec "$@"
