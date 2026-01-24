#!/bin/bash
# ============================================================================
# INTERNAL CERTIFICATE GENERATOR (Signed by Internal CA)
# ============================================================================
# Generates certificates for internal services, signed by the internal CA.
# Requires: CA must exist (run generate-ca.sh first)
#
# Usage: ./generate-internal.sh
# ============================================================================

set -e

CERT_DIR="$(cd "$(dirname "$0")" && pwd)"
CA_DIR="$CERT_DIR/ca"
INTERNAL_DIR="$CERT_DIR/internal"
NGINX_DIR="$CERT_DIR/nginx"
DAYS=365

# Verify CA exists
if [[ ! -f "$CA_DIR/ca.crt" ]] || [[ ! -f "$CA_DIR/ca.key" ]]; then
    echo "ERROR: Internal CA not found. Run ./generate-ca.sh first."
    exit 1
fi

echo "============================================================================"
echo "Generating Internal Service Certificates"
echo "============================================================================"

mkdir -p "$INTERNAL_DIR" "$NGINX_DIR"

# Internal services SAN list
INTERNAL_SANS="DNS:localhost,DNS:nginx,DNS:node,DNS:node-backend,DNS:php,DNS:mariadb,DNS:postgres,DNS:redis,DNS:elasticsearch,DNS:mailpit,DNS:meilisearch,DNS:mercure,DNS:rabbitmq,DNS:seaweedfs,IP:127.0.0.1"

# Generate internal services certificate
echo "Generating internal services certificate..."
openssl genrsa -out "$INTERNAL_DIR/cert.key" 2048

openssl req -new \
    -key "$INTERNAL_DIR/cert.key" \
    -out "$INTERNAL_DIR/cert.csr" \
    -subj "/C=DE/ST=Development/L=Local/O=Zappzarapp/OU=Internal Services/CN=internal.local"

openssl x509 -req -days $DAYS \
    -in "$INTERNAL_DIR/cert.csr" \
    -CA "$CA_DIR/ca.crt" \
    -CAkey "$CA_DIR/ca.key" \
    -CAcreateserial \
    -out "$INTERNAL_DIR/cert.crt" \
    -extfile <(printf "subjectAltName=$INTERNAL_SANS")

rm -f "$INTERNAL_DIR/cert.csr"

# Copy CA cert to internal dir for easy mounting
cp "$CA_DIR/ca.crt" "$INTERNAL_DIR/ca.crt"

# Generate nginx certificate (also signed by CA for consistency)
echo "Generating nginx certificate..."
NGINX_SANS="DNS:localhost,DNS:*.localhost,DNS:nginx,IP:127.0.0.1"

openssl genrsa -out "$NGINX_DIR/cert.key" 2048

openssl req -new \
    -key "$NGINX_DIR/cert.key" \
    -out "$NGINX_DIR/cert.csr" \
    -subj "/C=DE/ST=Development/L=Local/O=Zappzarapp/OU=Web Server/CN=localhost"

openssl x509 -req -days $DAYS \
    -in "$NGINX_DIR/cert.csr" \
    -CA "$CA_DIR/ca.crt" \
    -CAkey "$CA_DIR/ca.key" \
    -CAcreateserial \
    -out "$NGINX_DIR/cert.crt" \
    -extfile <(printf "subjectAltName=$NGINX_SANS")

rm -f "$NGINX_DIR/cert.csr"

# Set permissions
chmod 644 "$INTERNAL_DIR/cert.crt" "$INTERNAL_DIR/ca.crt" "$NGINX_DIR/cert.crt"
chmod 644 "$INTERNAL_DIR/cert.key" "$NGINX_DIR/cert.key"

echo "============================================================================"
echo "Internal certificates generated successfully!"
echo "============================================================================"
echo "Nginx:    $NGINX_DIR/cert.crt"
echo "Internal: $INTERNAL_DIR/cert.crt"
echo "CA:       $INTERNAL_DIR/ca.crt (for service trust)"
echo ""
echo "To avoid browser warnings, run: make ssl-trust-ca"
echo "============================================================================"
