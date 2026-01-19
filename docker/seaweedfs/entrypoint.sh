#!/bin/sh
# SeaweedFS entrypoint script
# Loads credentials from Docker Secret files, configures TLS, and starts SeaweedFS

set -e

# ─────────────────────────────────────────────────────────────────────────────
# Load credentials from secret files
# ─────────────────────────────────────────────────────────────────────────────

# Default credentials (will be overridden by secrets if available)
S3_ACCESS_KEY="${SEAWEEDFS_S3_ACCESS_KEY:-admin}"
S3_SECRET_KEY="${SEAWEEDFS_S3_SECRET_KEY:-admin}"

# Load access key from secret file if specified
if [ -n "${SEAWEEDFS_S3_ACCESS_KEY_FILE}" ] && [ -f "${SEAWEEDFS_S3_ACCESS_KEY_FILE}" ]; then
    S3_ACCESS_KEY="$(cat "${SEAWEEDFS_S3_ACCESS_KEY_FILE}")"
fi

# Load secret key from secret file if specified
if [ -n "${SEAWEEDFS_S3_SECRET_KEY_FILE}" ] && [ -f "${SEAWEEDFS_S3_SECRET_KEY_FILE}" ]; then
    S3_SECRET_KEY="$(cat "${SEAWEEDFS_S3_SECRET_KEY_FILE}")"
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

chown 1000:1000 /etc/seaweedfs/config/s3.json
chmod 600 /etc/seaweedfs/config/s3.json

# ─────────────────────────────────────────────────────────────────────────────
# Configure TLS certificates
# ─────────────────────────────────────────────────────────────────────────────

TLS_ARGS=""

# Copy TLS certificates if mounted
if [ -f "/etc/ssl/certs/cert.crt" ] && [ -f "/etc/ssl/private/cert.key" ]; then
    cp /etc/ssl/certs/cert.crt /etc/seaweedfs/certs/s3.crt
    cp /etc/ssl/private/cert.key /etc/seaweedfs/certs/s3.key
    chown 1000:1000 /etc/seaweedfs/certs/s3.crt /etc/seaweedfs/certs/s3.key
    chmod 644 /etc/seaweedfs/certs/s3.crt
    chmod 600 /etc/seaweedfs/certs/s3.key

    # Add TLS arguments for S3 endpoint
    TLS_ARGS="-s3.cert.file=/etc/seaweedfs/certs/s3.crt -s3.key.file=/etc/seaweedfs/certs/s3.key"
    echo "TLS enabled for S3 API"
fi

# ─────────────────────────────────────────────────────────────────────────────
# Ensure data directory has correct ownership
# ─────────────────────────────────────────────────────────────────────────────

chown -R 1000:1000 /data 2>/dev/null || true

# ─────────────────────────────────────────────────────────────────────────────
# Start SeaweedFS
# ─────────────────────────────────────────────────────────────────────────────

# Build the command with S3 config
# shellcheck disable=SC2086
# Run as UID 1000 (seaweedfs user)
exec su-exec 1000:1000 weed "$@" \
    -s3.config=/etc/seaweedfs/config/s3.json \
    ${TLS_ARGS}
