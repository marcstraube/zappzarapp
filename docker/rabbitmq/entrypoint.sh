#!/bin/bash
# RabbitMQ Entrypoint Wrapper
# Loads credentials from Docker Secrets for RabbitMQ 4.x
# (RABBITMQ_DEFAULT_USER_FILE and RABBITMQ_DEFAULT_PASS_FILE are deprecated in 4.0)

set -e

# Load user from secret file if available (support both with and without .txt)
if [[ -f /run/secrets/rabbitmq_user.txt ]]; then
    RABBITMQ_DEFAULT_USER=$(cat /run/secrets/rabbitmq_user.txt)
    export RABBITMQ_DEFAULT_USER
elif [[ -f /run/secrets/rabbitmq_user ]]; then
    RABBITMQ_DEFAULT_USER=$(cat /run/secrets/rabbitmq_user)
    export RABBITMQ_DEFAULT_USER
fi

# Load password from secret file if available (support both with and without .txt)
if [[ -f /run/secrets/rabbitmq_password.txt ]]; then
    RABBITMQ_DEFAULT_PASS=$(cat /run/secrets/rabbitmq_password.txt)
    export RABBITMQ_DEFAULT_PASS
elif [[ -f /run/secrets/rabbitmq_password ]]; then
    RABBITMQ_DEFAULT_PASS=$(cat /run/secrets/rabbitmq_password)
    export RABBITMQ_DEFAULT_PASS
fi

# Ensure .erlang.cookie has correct permissions for healthcheck
# The cookie is created by the original entrypoint, but we ensure permissions here
# so that rabbitmq-diagnostics (healthcheck) can read it without DAC_READ_SEARCH capability
COOKIE_FILE="/var/lib/rabbitmq/.erlang.cookie"
if [[ -f "$COOKIE_FILE" ]]; then
    chown rabbitmq:rabbitmq "$COOKIE_FILE"
    chmod 400 "$COOKIE_FILE"
elif [[ ! -f "$COOKIE_FILE" ]] && [[ -n "$RABBITMQ_ERLANG_COOKIE" ]]; then
    # Create cookie with correct permissions if RABBITMQ_ERLANG_COOKIE is set
    echo "$RABBITMQ_ERLANG_COOKIE" > "$COOKIE_FILE"
    chown rabbitmq:rabbitmq "$COOKIE_FILE"
    chmod 400 "$COOKIE_FILE"
fi

# =============================================================================
# SSL Certificate Validation
# =============================================================================
# RabbitMQ TLS Configuration:
# - Certificates mounted at: /etc/rabbitmq/certs/server.{crt,key}
# - Production: SSL is MANDATORY (exit 1 if missing)
# - Development: SSL is optional (warn if missing)
# =============================================================================

SSL_CERT="/etc/rabbitmq/certs/server.crt"
SSL_KEY="/etc/rabbitmq/certs/server.key"

# Check if both certificate files exist
if [[ -f "$SSL_CERT" ]] && [[ -f "$SSL_KEY" ]]; then
    echo "[entrypoint] INFO: SSL certificates found - RabbitMQ will use TLS encryption"
    echo "[entrypoint] Certificate: $SSL_CERT"
    echo "[entrypoint] Key: $SSL_KEY"
else
    # No SSL certificates found - behavior depends on environment
    ENV_MODE="${ENV:-development}"

    # Diagnostic information about what was found
    if [[ -d /etc/rabbitmq/certs ]]; then
        echo "[entrypoint] WARNING: /etc/rabbitmq/certs exists but no valid certificates found"
        echo "[entrypoint] Expected: /etc/rabbitmq/certs/server.{crt,key}"
        echo "[entrypoint] Found files in /etc/rabbitmq/certs:"
        ls -la /etc/rabbitmq/certs/ 2>/dev/null || echo "  (directory is empty or not accessible)"
    else
        echo "[entrypoint] INFO: No /etc/rabbitmq/certs directory found (SSL certificates not mounted)"
    fi

    # Environment-specific behavior: Fail-Fast in production, warn in development
    if [[ "$ENV_MODE" = "production" ]]; then
        # PRODUCTION: SSL is MANDATORY for security compliance
        echo "[entrypoint] FATAL: SSL certificates are required in production mode"
        echo "[entrypoint] RabbitMQ REQUIRES encrypted connections in production environments"
        echo "[entrypoint] Security policy: Message broker must not transmit data unencrypted"
        echo "[entrypoint]"
        echo "[entrypoint] Fix: Ensure SSL certificates are properly mounted:"
        echo "[entrypoint]   1. Run: make ssl-internal"
        echo "[entrypoint]   2. Verify compose.production.yaml mounts: ./docker/certs/internal/cert.{crt,key}:/etc/rabbitmq/certs/"
        echo "[entrypoint]   3. Ensure files exist: docker/certs/internal/cert.{crt,key}"
        echo "[entrypoint]"
        echo "[entrypoint] Container startup ABORTED to prevent security violation."
        exit 1  # Fail-Fast: Do not start RabbitMQ without SSL in production
    else
        # DEVELOPMENT: Warn but allow (local development flexibility)
        echo "[entrypoint] WARNING: RabbitMQ will start WITHOUT SSL support (development mode)"
        echo "[entrypoint] This is acceptable for local development but NOT for production"
        echo "[entrypoint] To test with SSL locally: run 'make ssl-internal'"
    fi
fi

# Execute the original entrypoint
exec docker-entrypoint.sh "$@"
