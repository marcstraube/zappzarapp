#!/bin/bash
# ============================================================================
# INTERNAL CA GENERATOR (Development/Internal Use)
# ============================================================================
# Generates a Certificate Authority for signing internal service certificates.
# The CA certificate can be added to system trust stores for browser access.
#
# Usage: ./generate-ca.sh
# ============================================================================

set -e

# Private keys must never be world-readable, not even between creation and
# the final chmod: create everything 0600/0700, public certs are opened up
# explicitly below
umask 077

CERT_DIR="$(cd "$(dirname "$0")" && pwd)"
CA_DIR="$CERT_DIR/ca"
DAYS=1095  # 3 years - regeneration is a single `make ssl-internal`

# shellcheck source=docker/certs/internal-hostnames.sh
. "$CERT_DIR/internal-hostnames.sh"

# Name constraints (critical): even a leaked CA key can only mint
# certificates for the internal service names, never for arbitrary
# domains - the CA may end up in system trust stores via ssl-trust-ca
NAME_CONSTRAINTS="critical"
for host in $INTERNAL_HOSTNAMES; do
    NAME_CONSTRAINTS="$NAME_CONSTRAINTS,permitted;DNS:$host"
done
NAME_CONSTRAINTS="$NAME_CONSTRAINTS,permitted;IP:$INTERNAL_IP/255.255.255.255"

echo "============================================================================"
echo "Generating Internal Certificate Authority"
echo "============================================================================"

mkdir -p "$CA_DIR"
# Directory stays traversable (the dev container mounts docker/certs read-only);
# the key itself is protected by its 600 file mode
chmod 755 "$CA_DIR"

# Generate CA private key
echo "Generating CA private key..."
openssl genrsa -out "$CA_DIR/ca.key" 4096

# Generate CA certificate
echo "Generating CA certificate..."
openssl req -x509 -new -nodes \
    -key "$CA_DIR/ca.key" \
    -sha256 -days $DAYS \
    -out "$CA_DIR/ca.crt" \
    -subj "/C=DE/ST=Development/L=Local/O=Zappzarapp/OU=Internal CA/CN=Zappzarapp Internal CA" \
    -addext "nameConstraints=$NAME_CONSTRAINTS"

# Set permissions
chmod 644 "$CA_DIR/ca.crt"
chmod 600 "$CA_DIR/ca.key"

echo "============================================================================"
echo "Internal CA generated successfully!"
echo "============================================================================"
echo "CA Certificate: $CA_DIR/ca.crt"
echo "CA Private Key: $CA_DIR/ca.key (keep secure!)"
echo ""
echo "To trust this CA in your browser/system, run: make ssl-trust-ca"
echo "============================================================================"
