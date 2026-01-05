#!/bin/bash
# ============================================================================
# LET'S ENCRYPT SSL CERTIFICATE SETUP (Production)
# ============================================================================
# Sets up Let's Encrypt SSL certificates using Certbot.
# Requires docker-compose and internet connection.
#
# Usage: ./setup-letsencrypt.sh <domain> <email>
# Example: ./setup-letsencrypt.sh example.com admin@example.com
#
# Prerequisites:
# 1. Domain must point to your server's public IP
# 2. Port 80 must be accessible from the internet
# 3. Docker Compose must be running
# ============================================================================

set -e

DOMAIN="$1"
EMAIL="$2"

if [ -z "$DOMAIN" ] || [ -z "$EMAIL" ]; then
    echo "❌ Error: Missing arguments"
    echo ""
    echo "Usage: $0 <domain> <email>"
    echo "Example: $0 example.com admin@example.com"
    exit 1
fi

PROJECT_ROOT="$(cd "$(dirname "$0")/../../.." && pwd)"
CERT_DIR="$PROJECT_ROOT/docker/certs"
WEBROOT="$PROJECT_ROOT/public"

echo "============================================================================"
echo "Let's Encrypt SSL Certificate Setup"
echo "============================================================================"
echo "Domain: $DOMAIN"
echo "Email: $EMAIL"
echo "Project Root: $PROJECT_ROOT"
echo "Certificate Directory: $CERT_DIR"
echo "============================================================================"

# Check if domain resolves
echo ""
echo "Checking DNS resolution for $DOMAIN..."
if ! host "$DOMAIN" > /dev/null 2>&1; then
    echo "⚠️  Warning: Domain $DOMAIN does not resolve to an IP"
    echo "   Let's Encrypt requires your domain to be publicly accessible"
    read -p "Continue anyway? (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        exit 1
    fi
fi

# Install certbot if not available
if ! command -v certbot &> /dev/null; then
    echo ""
    echo "Certbot not found. Installing via Docker..."
    CERTBOT_CMD="docker run -it --rm --name certbot \
        -v \"$CERT_DIR/letsencrypt:/etc/letsencrypt\" \
        -v \"$WEBROOT:/var/www/html\" \
        -p 80:80 \
        certbot/certbot"
else
    CERTBOT_CMD="certbot"
fi

# Request certificate
echo ""
echo "Requesting SSL certificate from Let's Encrypt..."
echo "This may take a few minutes..."
echo ""

if command -v certbot &> /dev/null; then
    sudo certbot certonly --webroot \
        -w "$WEBROOT" \
        -d "$DOMAIN" \
        --email "$EMAIL" \
        --agree-tos \
        --no-eff-email \
        --cert-path "$CERT_DIR/letsencrypt"
else
    docker run -it --rm --name certbot \
        -v "$CERT_DIR/letsencrypt:/etc/letsencrypt" \
        -v "$WEBROOT:/var/www/html" \
        -p 80:80 \
        certbot/certbot certonly --standalone \
        -d "$DOMAIN" \
        --email "$EMAIL" \
        --agree-tos \
        --no-eff-email
fi

# Create symlinks for Nginx
echo ""
echo "Creating symlinks for Nginx..."
ln -sf "letsencrypt/live/$DOMAIN/fullchain.pem" "$CERT_DIR/cert.crt"
ln -sf "letsencrypt/live/$DOMAIN/privkey.pem" "$CERT_DIR/cert.key"

echo "============================================================================"
echo "✅ Let's Encrypt certificate installed successfully!"
echo "============================================================================"
echo "Certificate: $CERT_DIR/letsencrypt/live/$DOMAIN/fullchain.pem"
echo "Private Key: $CERT_DIR/letsencrypt/live/$DOMAIN/privkey.pem"
echo "Symlinks: cert.crt, cert.key"
echo ""
echo "📋 Next Steps:"
echo "   1. Copy ssl.conf.example to ssl.conf:"
echo "      cp docker/nginx/conf.d/ssl.conf.example docker/nginx/conf.d/ssl.conf"
echo "   2. Update ssl.conf with your domain and certificate paths"
echo "   3. Mount ssl.conf in compose.prod.yaml"
echo "   4. Restart Nginx: docker compose restart nginx"
echo ""
echo "🔄 Certificate Renewal:"
echo "   Certificates expire every 90 days. Set up auto-renewal:"
echo "   - Cron: 0 0 * * * certbot renew --quiet --deploy-hook 'docker compose restart nginx'"
echo "   - Or use: make ssl-renew"
echo "============================================================================"
