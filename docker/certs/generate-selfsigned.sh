#!/bin/bash
# ============================================================================
# SELF-SIGNED CERTIFICATE GENERATOR (Development Only)
# ============================================================================
# Generates a self-signed SSL certificate for local development.
# DO NOT use self-signed certificates in production!
#
# Usage: ./generate-selfsigned.sh [domain]
# Example: ./generate-selfsigned.sh localhost
# ============================================================================

set -e

DOMAIN="${1:-localhost}"
CERT_DIR="$(cd "$(dirname "$0")" && pwd)"
DAYS=365

echo "============================================================================"
echo "Generating Self-Signed Certificate for Development"
echo "============================================================================"
echo "Domain: $DOMAIN"
echo "Output Directory: $CERT_DIR"
echo "Validity: $DAYS days"
echo "============================================================================"

# Generate private key
echo "Generating private key..."
openssl genrsa -out "$CERT_DIR/selfsigned.key" 2048

# Generate certificate signing request (CSR)
echo "Generating certificate signing request..."
openssl req -new -key "$CERT_DIR/selfsigned.key" \
    -out "$CERT_DIR/selfsigned.csr" \
    -subj "/C=US/ST=State/L=City/O=Development/CN=$DOMAIN"

# Generate self-signed certificate
echo "Generating self-signed certificate..."
openssl x509 -req -days $DAYS \
    -in "$CERT_DIR/selfsigned.csr" \
    -signkey "$CERT_DIR/selfsigned.key" \
    -out "$CERT_DIR/selfsigned.crt" \
    -extfile <(printf "subjectAltName=DNS:$DOMAIN,DNS:*.$DOMAIN,DNS:localhost,IP:127.0.0.1,DNS:nginx,DNS:php,DNS:node,DNS:node-backend,DNS:redis,DNS:postgres,DNS:mariadb,DNS:meilisearch,DNS:elasticsearch,DNS:mercure,DNS:rabbitmq,DNS:minio,DNS:mailpit")

# Create symlinks for easier reference
ln -sf selfsigned.crt "$CERT_DIR/cert.crt"
ln -sf selfsigned.key "$CERT_DIR/cert.key"

# Cleanup CSR
rm -f "$CERT_DIR/selfsigned.csr"

# Set permissions (644 needed for bind-mount access by different container UIDs)
# For stricter security, use Kubernetes with native K8s Secrets + securityContext.fsGroup
chmod 644 "$CERT_DIR/selfsigned.crt" "$CERT_DIR/cert.crt"
chmod 644 "$CERT_DIR/selfsigned.key" "$CERT_DIR/cert.key"

echo "============================================================================"
echo "✅ Self-signed certificate generated successfully!"
echo "============================================================================"
echo "Certificate: $CERT_DIR/selfsigned.crt"
echo "Private Key: $CERT_DIR/selfsigned.key"
echo "Symlinks: cert.crt -> selfsigned.crt, cert.key -> selfsigned.key"
echo ""
echo "⚠️  IMPORTANT:"
echo "   - This certificate is for DEVELOPMENT only"
echo "   - Browsers will show a security warning (expected)"
echo "   - Add exception in browser to proceed"
echo "   - Use Let's Encrypt for production (make ssl-letsencrypt)"
echo "============================================================================"
