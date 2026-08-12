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
# Certificates are public, private keys must not be world-readable.
chmod 644 "$INTERNAL_DIR/cert.crt" "$INTERNAL_DIR/ca.crt" "$NGINX_DIR/cert.crt"
chmod 600 "$INTERNAL_DIR/cert.key" "$NGINX_DIR/cert.key"

# Grant read access to the container users that consume the keys via bind
# mounts (Docker bind mounts do not remap ownership, and the production
# preset runs services unprivileged with cap_drop: ALL, so neither
# world-read nor DAC_OVERRIDE is available):
#   u:0    root entrypoints with dropped capabilities (development preset:
#          postgres/mariadb copy certs from /tmp/certs without DAC_OVERRIDE)
#   u:70   postgres (production preset runs as 70:70, the Alpine postgres user)
#   u:100  nginx (100:101, the Alpine nginx user) and rabbitmq (100:101,
#          the Alpine rabbitmq user)
#   u:999  redis (999:1000) and mariadb (999:999)
#   u:1000 seaweedfs/meilisearch/mercure/elasticsearch (uid 1000) and the
#          default container USER_ID (CI hosts may generate certs as a
#          different UID, e.g. 1001 on GitHub runners)
if command -v setfacl >/dev/null 2>&1; then
    setfacl -m u:0:r,u:70:r,u:100:r,u:999:r,u:1000:r \
        "$INTERNAL_DIR/cert.key" "$NGINX_DIR/cert.key"
else
    echo "WARNING: setfacl not found - keys are mode 600 without ACLs."
    echo "         Unprivileged container users (production preset, redis," \
        "rabbitmq)"
    echo "         will not be able to read them. Install the 'acl' package" \
        "and re-run."
fi

echo "============================================================================"
echo "Internal certificates generated successfully!"
echo "============================================================================"
echo "Nginx:    $NGINX_DIR/cert.crt"
echo "Internal: $INTERNAL_DIR/cert.crt"
echo "CA:       $INTERNAL_DIR/ca.crt (for service trust)"
echo ""
echo "To avoid browser warnings, run: make ssl-trust-ca"
echo "============================================================================"
