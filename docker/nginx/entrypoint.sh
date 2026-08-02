#!/bin/sh
# shellcheck shell=sh
# shellcheck disable=SC2154  # DOMAIN is set via environment variable
# nginx Entrypoint (Unprivileged)
# Runs entirely as nginx user - no root, no chown needed
set -e

# Default values if not set
export NGINX_SSL_PORT=${NGINX_SSL_PORT:-8443}

# =============================================================================
# SSL Certificate Validation
# =============================================================================
# Nginx TLS Configuration:
# - Certificates mounted at: /etc/nginx/certs/cert.{crt,key}
# - Production: SSL is MANDATORY (exit 1 if missing)
# - Development: SSL is optional (warn if missing)
# =============================================================================

SSL_CERT="/etc/nginx/certs/cert.crt"
SSL_KEY="/etc/nginx/certs/cert.key"

# Check if both certificate files exist
if [ -f "$SSL_CERT" ] && [ -f "$SSL_KEY" ]; then
    echo "[entrypoint] INFO: SSL certificates found - Nginx will use HTTPS"
    echo "[entrypoint] Certificate: $SSL_CERT"
    echo "[entrypoint] Key: $SSL_KEY"
else
    # No SSL certificates found - behavior depends on environment
    ENV_MODE="${ZAPPZARAPP_ENV:-development}"

    # Diagnostic information about what was found
    if [ -d /etc/nginx/certs ]; then
        echo "[entrypoint] WARNING: /etc/nginx/certs exists but no valid certificates found"
        echo "[entrypoint] Expected: /etc/nginx/certs/cert.{crt,key}"
        echo "[entrypoint] Found files in /etc/nginx/certs:"
        ls -la /etc/nginx/certs/ 2>/dev/null || echo "  (directory is empty or not accessible)"
    else
        echo "[entrypoint] INFO: No /etc/nginx/certs directory found (SSL certificates not mounted)"
    fi

    # Environment-specific behavior: Fail-Fast in production, warn in development
    if [ "$ENV_MODE" = "production" ]; then
        # PRODUCTION: SSL is MANDATORY for security compliance
        echo "[entrypoint] FATAL: SSL certificates are required in production mode"
        echo "[entrypoint] Nginx REQUIRES HTTPS in production environments"
        echo "[entrypoint] Security policy: Web server must not serve traffic unencrypted"
        echo "[entrypoint]"
        echo "[entrypoint] Fix: Ensure SSL certificates are properly mounted:"
        echo "[entrypoint]   1. Run: make ssl-internal"
        echo "[entrypoint]   2. Verify compose.production.yaml mounts: ./docker/certs/nginx/cert.{crt,key}:/etc/nginx/certs/"
        echo "[entrypoint]   3. Ensure files exist: docker/certs/nginx/cert.{crt,key}"
        echo "[entrypoint]"
        echo "[entrypoint] Container startup ABORTED to prevent security violation."
        exit 1  # Fail-Fast: Do not start Nginx without SSL in production
    else
        # DEVELOPMENT: Warn but allow (local development flexibility)
        echo "[entrypoint] WARNING: Nginx will start WITHOUT SSL support (development mode)"
        echo "[entrypoint] This is acceptable for local development but NOT for production"
        echo "[entrypoint] To test with SSL locally: run 'make ssl-internal'"
    fi
fi

# Configure index directive and try_files fallback based on ENABLE_PHP
# When PHP is disabled, serve static index.html instead of routing to PHP
mkdir -p /run/nginx/snippets

if [ "${ENABLE_PHP:-true}" = "true" ]; then
    export INDEX_DIRECTIVE="index.php index.html"
    export TRY_FILES_FALLBACK='/index.php?$query_string'

    # Generate health check snippet for PHP mode
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

# Readiness Probe (K8s) - Full dependency check via PHP
location = /ready {
    access_log off;
    fastcgi_pass unix:/var/run/php-fpm/php-fpm.sock;
    fastcgi_index index.php;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root/index.php;
    fastcgi_param SCRIPT_NAME /index.php;
    fastcgi_param REQUEST_URI /ready;
    fastcgi_param HTTPS on;
}
HEALTH_PHP

elif [ "${ENABLE_NODE:-false}" = "true" ]; then
    # PHP disabled but Node enabled - check if node-backend is available based on NODE_MODE
    # NODE_MODE values with backend: backend, api, assets-api, framework-api
    # NODE_MODE values without backend: framework, assets, idle
    NODE_MODE="${NODE_MODE:-assets-api}"

    case "$NODE_MODE" in
        *api*|backend)
            # Node backend is available - route health to it
            export INDEX_DIRECTIVE="index.html"
            export TRY_FILES_FALLBACK="/index.html"
            echo "PHP disabled, Node backend enabled (NODE_MODE=$NODE_MODE) - routing health to Node backend"

            # Generate health check snippet for Node backend mode
            cat > /run/nginx/snippets/health-check.conf << 'HEALTH_NODE'
# Health Check Endpoint (Node mode - no PHP)
# Routes health checks to Node.js backend
location = /health {
    access_log off;
    set $upstream_backend node-backend:3000;
    proxy_pass https://$upstream_backend/health;
    proxy_ssl_verify off;
    proxy_ssl_server_name on;
    proxy_http_version 1.1;
    proxy_set_header Host $host;
    proxy_connect_timeout 5s;
    proxy_read_timeout 5s;
    error_page 502 503 504 = @nginx_health;
}

# Fallback health check when Node backend is unreachable
location @nginx_health {
    default_type application/json;
    return 200 '{"status":"ok","service":"nginx","timestamp":"$time_iso8601"}';
}

# Readiness Probe (K8s) - Full dependency check via Node backend
location = /ready {
    access_log off;
    set $upstream_backend node-backend:3000;
    proxy_pass https://$upstream_backend/ready;
    proxy_ssl_verify off;
    proxy_ssl_server_name on;
    proxy_http_version 1.1;
    proxy_set_header Host $host;
    proxy_connect_timeout 5s;
    proxy_read_timeout 10s;
}

# Status endpoint via Node backend
location = /status {
    set $upstream_backend node-backend:3000;
    proxy_pass https://$upstream_backend/status;
    proxy_ssl_verify off;
    proxy_ssl_server_name on;
    proxy_http_version 1.1;
    proxy_set_header Host $host;
}
HEALTH_NODE
            ;;
        *)
            # Node enabled but no backend (framework, assets, idle modes)
            export INDEX_DIRECTIVE="index.html"
            export TRY_FILES_FALLBACK="/index.html"
            echo "PHP disabled, Node frontend only (NODE_MODE=$NODE_MODE) - using static health"

            # Generate health check snippet for Node frontend-only mode
            cat > /run/nginx/snippets/health-check.conf << 'HEALTH_NODE_FRONTEND'
# Health Check Endpoint (Node frontend-only mode - no PHP, no Node backend)
location = /health {
    access_log off;
    default_type application/json;
    return 200 '{"status":"ok","service":"nginx","timestamp":"$time_iso8601"}';
}

# Readiness Probe (Node frontend-only mode)
location = /ready {
    access_log off;
    default_type application/json;
    return 200 '{"status":"ok","service":"nginx","timestamp":"$time_iso8601","checks":{}}';
}
HEALTH_NODE_FRONTEND
            ;;
    esac

else
    export INDEX_DIRECTIVE="index.html"
    export TRY_FILES_FALLBACK="/index.html"
    echo "PHP and Node disabled - using static fallback"

    # Generate health check snippet for static mode (no PHP, no Node)
    cat > /run/nginx/snippets/health-check.conf << 'HEALTH_STATIC'
# Health Check Endpoint (Static mode - no PHP, no Node)
location = /health {
    access_log off;
    default_type application/json;
    return 200 '{"status":"ok","service":"nginx","timestamp":"$time_iso8601"}';
}

# Readiness Probe (Static mode - no backends)
location = /ready {
    access_log off;
    default_type application/json;
    return 200 '{"status":"ok","service":"nginx","timestamp":"$time_iso8601","checks":{}}';
}
HEALTH_STATIC
fi

# Create runtime config directory (for read-only filesystems)
mkdir -p /run/nginx/conf.d

# Process SSL config template if it exists (development or production)
# Output goes to /run/nginx/conf.d/ which is writable (tmpfs in production)
# Note: Running as nginx user, directories already owned by nginx (see Dockerfile)
if [ -f /etc/nginx/conf.d/ssl-development.conf.template ]; then
    echo "Processing SSL development configuration template..."
    envsubst '${NGINX_SSL_PORT} ${INDEX_DIRECTIVE} ${TRY_FILES_FALLBACK}' < /etc/nginx/conf.d/ssl-development.conf.template > /run/nginx/conf.d/ssl.conf
    echo "SSL configuration generated with NGINX_SSL_PORT=${NGINX_SSL_PORT}, INDEX=${INDEX_DIRECTIVE}"
elif [ -f /etc/nginx/conf.d/ssl-production.conf.template ]; then
    echo "Processing SSL production configuration template..."
    envsubst '${NGINX_SSL_PORT} ${DOMAIN} ${INDEX_DIRECTIVE} ${TRY_FILES_FALLBACK}' < /etc/nginx/conf.d/ssl-production.conf.template > /run/nginx/conf.d/ssl.conf
    echo "SSL configuration generated for DOMAIN=${DOMAIN}, NGINX_SSL_PORT=${NGINX_SSL_PORT}, INDEX=${INDEX_DIRECTIVE}"
fi

# Start nginx (already running as nginx user)
exec "$@"
