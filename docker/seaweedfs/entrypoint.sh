#!/bin/sh
# shellcheck shell=sh
# SeaweedFS entrypoint script
# Loads credentials from Docker Secret files, configures TLS, and starts SeaweedFS

set -e

# Config and key files must never be world-readable, not even between
# creation and their explicit chmod below
umask 077

# ─────────────────────────────────────────────────────────────────────────────
# Load credentials from secret files
# ─────────────────────────────────────────────────────────────────────────────

S3_ACCESS_KEY="${SEAWEEDFS_S3_ACCESS_KEY:-}"
S3_SECRET_KEY="${SEAWEEDFS_S3_SECRET_KEY:-}"

# A configured-but-missing secret file is a broken deployment - never start
# with silently degraded credentials
if [ -n "${SEAWEEDFS_S3_ACCESS_KEY_FILE:-}" ]; then
    if [ ! -f "${SEAWEEDFS_S3_ACCESS_KEY_FILE}" ]; then
        echo "[entrypoint] FATAL: SEAWEEDFS_S3_ACCESS_KEY_FILE is set but missing: ${SEAWEEDFS_S3_ACCESS_KEY_FILE}" >&2
        echo "[entrypoint] Fix: run 'make secrets' and restart the container." >&2
        exit 1
    fi
    S3_ACCESS_KEY="$(cat "${SEAWEEDFS_S3_ACCESS_KEY_FILE}")"
fi

if [ -n "${SEAWEEDFS_S3_SECRET_KEY_FILE:-}" ]; then
    if [ ! -f "${SEAWEEDFS_S3_SECRET_KEY_FILE}" ]; then
        echo "[entrypoint] FATAL: SEAWEEDFS_S3_SECRET_KEY_FILE is set but missing: ${SEAWEEDFS_S3_SECRET_KEY_FILE}" >&2
        echo "[entrypoint] Fix: run 'make secrets' and restart the container." >&2
        exit 1
    fi
    S3_SECRET_KEY="$(cat "${SEAWEEDFS_S3_SECRET_KEY_FILE}")"
fi

# Without any configured credentials: development falls back to well-known
# local defaults (loudly), production refuses to start
if [ -z "$S3_ACCESS_KEY" ] || [ -z "$S3_SECRET_KEY" ]; then
    if [ "${ZAPPZARAPP_ENV:-development}" = "production" ]; then
        echo "[entrypoint] FATAL: No S3 credentials configured (env or secret files) in production mode" >&2
        echo "[entrypoint] Refusing to start object storage with default credentials." >&2
        exit 1
    fi
    echo "[entrypoint] WARNING: No S3 credentials configured - using development defaults (admin/admin)"
    S3_ACCESS_KEY="${S3_ACCESS_KEY:-admin}"
    S3_SECRET_KEY="${S3_SECRET_KEY:-admin}"
fi

# ─────────────────────────────────────────────────────────────────────────────
# Generate S3 credentials config
# ─────────────────────────────────────────────────────────────────────────────

cat > /etc/seaweedfs/config/s3.json << EOF
{
  "identities": [
    {
      "name": "admin",
      "credentials": [
        {
          "accessKey": "${S3_ACCESS_KEY}",
          "secretKey": "${S3_SECRET_KEY}"
        }
      ],
      "actions": [
        "Admin",
        "Read",
        "Write",
        "List",
        "Tagging"
      ]
    }
  ]
}
EOF

# Only the root startup path can and needs to chown; on the unprivileged
# path (production preset runs as uid 1000) the file is created by the
# seaweedfs user itself.
if [ "$(id -u)" = "0" ]; then
    chown 1000:1000 /etc/seaweedfs/config/s3.json
fi
chmod 600 /etc/seaweedfs/config/s3.json

# ─────────────────────────────────────────────────────────────────────────────
# Configure TLS certificates
# ─────────────────────────────────────────────────────────────────────────────

TLS_ARGS=""
SSL_CERT="/etc/ssl/certs/cert.crt"
SSL_KEY="/etc/ssl/private/cert.key"

# Copy TLS certificates if mounted
if [ -f "$SSL_CERT" ] && [ -f "$SSL_KEY" ]; then
    cp "$SSL_CERT" /etc/seaweedfs/certs/s3.crt
    cp "$SSL_KEY" /etc/seaweedfs/certs/s3.key
    # Only the root startup path can and needs to chown (see s3.json above)
    if [ "$(id -u)" = "0" ]; then
        chown 1000:1000 /etc/seaweedfs/certs/s3.crt /etc/seaweedfs/certs/s3.key
    fi
    chmod 644 /etc/seaweedfs/certs/s3.crt
    chmod 600 /etc/seaweedfs/certs/s3.key

    # Add TLS arguments for S3 endpoint
    TLS_ARGS="-s3.cert.file=/etc/seaweedfs/certs/s3.crt -s3.key.file=/etc/seaweedfs/certs/s3.key"
    echo "[entrypoint] INFO: SSL certificates found - SeaweedFS S3 API will use TLS encryption"
    echo "[entrypoint] Certificate: $SSL_CERT"
    echo "[entrypoint] Key: $SSL_KEY"
else
    # No SSL certificates found - behavior depends on environment
    ENV_MODE="${ZAPPZARAPP_ENV:-development}"

    # Diagnostic information about what was found
    echo "[entrypoint] WARNING: SSL certificates not found"
    echo "[entrypoint] Expected: /etc/ssl/certs/cert.crt and /etc/ssl/private/cert.key"
    if [ -d "/etc/ssl/certs" ]; then
        echo "[entrypoint] Found files in /etc/ssl/certs:"
        ls -la /etc/ssl/certs/ 2>/dev/null | head -5 || echo "  (directory is empty or not accessible)"
    fi
    if [ -d "/etc/ssl/private" ]; then
        echo "[entrypoint] Found files in /etc/ssl/private:"
        ls -la /etc/ssl/private/ 2>/dev/null | head -5 || echo "  (directory is empty or not accessible)"
    fi

    # Environment-specific behavior: Fail-Fast in production, warn in development
    if [ "$ENV_MODE" = "production" ]; then
        # PRODUCTION: SSL is MANDATORY for security compliance
        echo "[entrypoint] FATAL: SSL certificates are required in production mode"
        echo "[entrypoint] SeaweedFS REQUIRES encrypted connections in production environments"
        echo "[entrypoint] Security policy: Object storage must not transmit data unencrypted"
        echo "[entrypoint]"
        echo "[entrypoint] Fix: Ensure SSL certificates are properly mounted:"
        echo "[entrypoint]   1. Run: make ssl-internal"
        echo "[entrypoint]   2. Verify compose.production.yaml mounts: ./docker/certs/internal/cert.{crt,key}"
        echo "[entrypoint]   3. Ensure files exist: docker/certs/internal/cert.{crt,key}"
        echo "[entrypoint]"
        echo "[entrypoint] Container startup ABORTED to prevent security violation."
        exit 1  # Fail-Fast: Do not start SeaweedFS without SSL in production
    else
        # DEVELOPMENT: Warn but allow (local development flexibility)
        echo "[entrypoint] WARNING: SeaweedFS will start WITHOUT SSL support (development mode)"
        echo "[entrypoint] This is acceptable for local development but NOT for production"
        echo "[entrypoint] To test with SSL locally: run 'make ssl-internal'"
    fi
fi

# ─────────────────────────────────────────────────────────────────────────────
# Ensure data directory has correct ownership (root startup path only)
# ─────────────────────────────────────────────────────────────────────────────

if [ "$(id -u)" = "0" ]; then
    chown -R 1000:1000 /data 2>/dev/null || true
fi

# ─────────────────────────────────────────────────────────────────────────────
# Start SeaweedFS as the seaweedfs user (uid 1000)
# ─────────────────────────────────────────────────────────────────────────────

# Root startup path (development): drop privileges via su-exec.
# Unprivileged path (production preset runs as uid 1000): already the
# seaweedfs user - su-exec would fail at setgroups, so exec directly.
# shellcheck disable=SC2086
if [ "$(id -u)" = "0" ]; then
    exec su-exec 1000:1000 weed "$@" \
        -s3.config=/etc/seaweedfs/config/s3.json \
        ${TLS_ARGS}
else
    exec weed "$@" \
        -s3.config=/etc/seaweedfs/config/s3.json \
        ${TLS_ARGS}
fi
