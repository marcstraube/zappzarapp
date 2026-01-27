#!/bin/sh
# shellcheck shell=sh
# Redis Production Entrypoint (Unprivileged)
# Runs as redis user - validates SSL certificates before starting

set -e

# =============================================================================
# SSL Certificate Validation
# =============================================================================
# Redis TLS Configuration:
# - Certificates mounted at: /etc/redis/certs/cert.{crt,key}
# - Production: SSL is MANDATORY (exit 1 if missing)
# - Development: SSL is optional (warn if missing)
# =============================================================================

SSL_CERT="/etc/redis/certs/cert.crt"
SSL_KEY="/etc/redis/certs/cert.key"

# Check if both certificate files exist
if [ -f "$SSL_CERT" ] && [ -f "$SSL_KEY" ]; then
    echo "[entrypoint] INFO: SSL certificates found - Redis will use TLS encryption"
    echo "[entrypoint] Certificate: $SSL_CERT"
    echo "[entrypoint] Key: $SSL_KEY"
else
    # No SSL certificates found - behavior depends on environment
    ENV_MODE="${ENV:-development}"

    # Diagnostic information about what was found
    if [ -d /etc/redis/certs ]; then
        echo "[entrypoint] WARNING: /etc/redis/certs exists but no valid certificates found"
        echo "[entrypoint] Expected: /etc/redis/certs/cert.{crt,key}"
        echo "[entrypoint] Found files in /etc/redis/certs:"
        ls -la /etc/redis/certs/ 2>/dev/null || echo "  (directory is empty or not accessible)"
    else
        echo "[entrypoint] INFO: No /etc/redis/certs directory found (SSL certificates not mounted)"
    fi

    # Environment-specific behavior: Fail-Fast in production, warn in development
    if [ "$ENV_MODE" = "production" ]; then
        # PRODUCTION: SSL is MANDATORY for security compliance
        echo "[entrypoint] FATAL: SSL certificates are required in production mode"
        echo "[entrypoint] Redis REQUIRES encrypted connections in production environments"
        echo "[entrypoint] Security policy: Cache must not transmit data unencrypted"
        echo "[entrypoint]"
        echo "[entrypoint] Fix: Ensure SSL certificates are properly mounted:"
        echo "[entrypoint]   1. Run: make ssl-internal"
        echo "[entrypoint]   2. Verify compose.production.yaml mounts: ./docker/certs/internal/cert.{crt,key}:/etc/redis/certs/"
        echo "[entrypoint]   3. Ensure files exist: docker/certs/internal/cert.{crt,key}"
        echo "[entrypoint]"
        echo "[entrypoint] Container startup ABORTED to prevent security violation."
        exit 1  # Fail-Fast: Do not start Redis without SSL in production
    else
        # DEVELOPMENT: Warn but allow (local development flexibility)
        echo "[entrypoint] WARNING: Redis will start WITHOUT SSL support (development mode)"
        echo "[entrypoint] This is acceptable for local development but NOT for production"
        echo "[entrypoint] To test with SSL locally: run 'make ssl-internal'"
    fi
fi

# Execute redis-server with all arguments passed to this script
exec "$@"
