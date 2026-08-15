#!/bin/bash
# ============================================================================
# LET'S ENCRYPT SSL CERTIFICATE SETUP (Production)
# ============================================================================
# Sets up Let's Encrypt SSL certificates using Certbot.
# Requires docker-compose and internet connection.
#
# Usage: ./setup-letsencrypt.sh <domain> <email> [options]
# Example: ./setup-letsencrypt.sh example.com admin@example.com
#
# Options:
#   --staging          Use the Let's Encrypt staging environment (relaxed rate
#                      limits, certificates are not browser-trusted)
#   --server URL       Use a custom ACME directory URL (e.g. a local Pebble
#                      test server); mutually exclusive with --staging
#   --ca-bundle PATH   CA bundle for verifying the ACME server's own TLS
#                      certificate (required for Pebble)
#   --http-port N      Port for the standalone HTTP-01 challenge (default 80;
#                      Docker certbot only - local certbot uses webroot mode)
#   --cert-dir DIR     Certificate tree root (default: this script's
#                      directory; used by the test suite)
#   --yes              Assume yes on confirmation prompts (non-interactive)
#
# Prerequisites:
# 1. Domain must point to your server's public IP
# 2. Port 80 must be accessible from the internet
# 3. Docker Compose must be running
# ============================================================================

set -e

DOMAIN=""
EMAIL=""
ACME_SERVER=""
ACME_CA_BUNDLE=""
HTTP_PORT=80
STAGING=false
ASSUME_YES=false
CERT_DIR=""

usage() {
    echo "Usage: $0 <domain> <email> [--staging] [--server URL] [--ca-bundle PATH] [--http-port N] [--cert-dir DIR] [--yes]"
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --staging)
            STAGING=true
            shift
            ;;
        --server)
            [[ -n "${2:-}" ]] || { echo "❌ Error: --server requires a URL"; exit 1; }
            ACME_SERVER="$2"
            shift 2
            ;;
        --ca-bundle)
            [[ -n "${2:-}" ]] || { echo "❌ Error: --ca-bundle requires a path"; exit 1; }
            ACME_CA_BUNDLE="$(cd "$(dirname "$2")" && pwd)/$(basename "$2")"
            [[ -f "$ACME_CA_BUNDLE" ]] || { echo "❌ Error: CA bundle not found: $ACME_CA_BUNDLE"; exit 1; }
            shift 2
            ;;
        --http-port)
            [[ -n "${2:-}" ]] || { echo "❌ Error: --http-port requires a port number"; exit 1; }
            HTTP_PORT="$2"
            shift 2
            ;;
        --cert-dir)
            [[ -n "${2:-}" ]] || { echo "❌ Error: --cert-dir requires a path"; exit 1; }
            mkdir -p "$2"
            CERT_DIR="$(cd "$2" && pwd)"
            shift 2
            ;;
        --yes)
            ASSUME_YES=true
            shift
            ;;
        -*)
            echo "❌ Error: Unknown option: $1"
            usage
            exit 1
            ;;
        *)
            if [[ -z "$DOMAIN" ]]; then
                DOMAIN="$1"
            elif [[ -z "$EMAIL" ]]; then
                EMAIL="$1"
            else
                echo "❌ Error: Unexpected argument: $1"
                usage
                exit 1
            fi
            shift
            ;;
    esac
done

if [[ -z "$DOMAIN" ]] || [[ -z "$EMAIL" ]]; then
    echo "❌ Error: Missing arguments"
    echo ""
    usage
    echo "Example: $0 example.com admin@example.com"
    exit 1
fi

if [[ "$STAGING" == "true" ]] && [[ -n "$ACME_SERVER" ]]; then
    echo "❌ Error: --staging and --server are mutually exclusive"
    exit 1
fi

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
CERT_DIR="${CERT_DIR:-$SCRIPT_DIR}"
WEBROOT="$PROJECT_ROOT/public"

echo "============================================================================"
echo "Let's Encrypt SSL Certificate Setup"
echo "============================================================================"
echo "Domain: $DOMAIN"
echo "Email: $EMAIL"
echo "Project Root: $PROJECT_ROOT"
echo "Certificate Directory: $CERT_DIR"
if [[ "$STAGING" == "true" ]]; then
    echo "Environment: Let's Encrypt STAGING (certificates are not browser-trusted)"
elif [[ -n "$ACME_SERVER" ]]; then
    echo "ACME Server: $ACME_SERVER"
fi
echo "============================================================================"

# Check if domain resolves - skipped for a custom ACME server, where test
# domains that only resolve inside the test environment are the normal case
if [[ -z "$ACME_SERVER" ]]; then
    echo ""
    echo "Checking DNS resolution for $DOMAIN..."
    if ! host "$DOMAIN" > /dev/null 2>&1; then
        echo "⚠️  Warning: Domain $DOMAIN does not resolve to an IP"
        echo "   Let's Encrypt requires your domain to be publicly accessible"
        if [[ "$ASSUME_YES" == "true" ]]; then
            echo "   Continuing (--yes)"
        else
            read -rp "Continue anyway? (y/N): " -n 1
            echo
            if [[ ! $REPLY =~ ^[Yy]$ ]]; then
                exit 1
            fi
        fi
    fi
fi

# Check if certbot is available locally
USE_DOCKER=false
if ! command -v certbot &> /dev/null; then
    echo ""
    echo "Certbot not found. Using Docker instead..."
    USE_DOCKER=true
fi

# ACME environment arguments, shared by both certbot paths
ACME_ARGS=()
if [[ "$STAGING" == "true" ]]; then
    ACME_ARGS+=(--staging)
fi
if [[ -n "$ACME_SERVER" ]]; then
    ACME_ARGS+=(--server "$ACME_SERVER")
fi

# Request certificate
echo ""
echo "Requesting SSL certificate..."
echo "This may take a few minutes..."
echo ""

if [[ "$USE_DOCKER" == "false" ]]; then
    # Local certbot: use webroot mode (nginx serves challenge files).
    # --config-dir places the whole letsencrypt tree (live/, renewal/, ...)
    # under the project cert directory, matching the docker branch and the
    # nginx install step below. REQUESTS_CA_BUNDLE lets certbot verify a
    # custom ACME server's TLS certificate (sudo drops the caller's env).
    SUDO_ENV=()
    if [[ -n "$ACME_CA_BUNDLE" ]]; then
        SUDO_ENV=("REQUESTS_CA_BUNDLE=$ACME_CA_BUNDLE")
    fi
    sudo "${SUDO_ENV[@]}" certbot certonly --webroot \
        -w "$WEBROOT" \
        -d "$DOMAIN" \
        --email "$EMAIL" \
        --agree-tos \
        --no-eff-email \
        "${ACME_ARGS[@]}" \
        --config-dir "$CERT_DIR/letsencrypt" \
        --work-dir "$CERT_DIR/letsencrypt/work" \
        --logs-dir "$CERT_DIR/letsencrypt/logs"
else
    # Docker certbot: use standalone mode (certbot runs own webserver).
    # Pre-create the mount so Docker does not create it root-owned, and run
    # the container as the invoking user: the issued files then belong to
    # the caller and the install step below works without sudo. work/ and
    # logs/ move under the mounted config dir (the in-container defaults
    # are root-owned), matching the local certbot branch.
    mkdir -p "$CERT_DIR/letsencrypt"
    DOCKER_ARGS=(--rm --name certbot -v "$CERT_DIR/letsencrypt:/etc/letsencrypt")
    if [[ "$(id -u)" -ne 0 ]]; then
        DOCKER_ARGS+=(--user "$(id -u):$(id -g)")
    fi
    if [[ -t 0 ]] && [[ -t 1 ]]; then
        DOCKER_ARGS+=(-it)
    fi
    if [[ -n "$ACME_SERVER" ]]; then
        # A custom ACME server is local test infrastructure (e.g. Pebble):
        # host networking lets certbot reach it on localhost and serve the
        # challenge on the host port directly
        DOCKER_ARGS+=(--network host)
    else
        DOCKER_ARGS+=(-p "$HTTP_PORT:$HTTP_PORT")
    fi
    if [[ -n "$ACME_CA_BUNDLE" ]]; then
        DOCKER_ARGS+=(-v "$ACME_CA_BUNDLE:/acme-ca.pem:ro" -e "REQUESTS_CA_BUNDLE=/acme-ca.pem")
    fi
    docker run "${DOCKER_ARGS[@]}" \
        certbot/certbot certonly --standalone \
        --http-01-port "$HTTP_PORT" \
        -d "$DOMAIN" \
        --email "$EMAIL" \
        --agree-tos \
        --no-eff-email \
        --non-interactive \
        --work-dir /etc/letsencrypt/work \
        --logs-dir /etc/letsencrypt/logs \
        "${ACME_ARGS[@]}"
fi

# Install the certificate into the nginx cert directory (the nginx container
# bind mounts nginx/ only, so the certbot tree itself is not visible to it).
# sudo is only needed when the key is not readable (local certbot runs as
# root and leaves root-owned 0600 files)
echo ""
echo "Installing certificate for nginx..."
INSTALL_CMD=(bash "$SCRIPT_DIR/install-letsencrypt.sh" "$DOMAIN" --cert-dir "$CERT_DIR")
if [[ "$(id -u)" -eq 0 ]] || [[ -r "$CERT_DIR/letsencrypt/live/$DOMAIN/privkey.pem" ]]; then
    "${INSTALL_CMD[@]}"
else
    sudo "${INSTALL_CMD[@]}"
fi

echo "============================================================================"
echo "✅ Let's Encrypt certificate installed successfully!"
echo "============================================================================"
echo "Certificate: $CERT_DIR/letsencrypt/live/$DOMAIN/fullchain.pem"
echo "Private Key: $CERT_DIR/letsencrypt/live/$DOMAIN/privkey.pem"
echo "Installed:   $CERT_DIR/nginx/cert.crt, $CERT_DIR/nginx/cert.key"
echo ""
echo "📋 Next Steps:"
echo "   1. Generate production SSL config:"
echo "      make ssl-prod-enable"
echo ""
echo "   2. Deploy:"
echo "      ZAPPZARAPP_ENV=production make build && make up"
echo ""
echo "🔄 Certificate Renewal:"
echo "   Certificates expire every 90 days. Set up auto-renewal:"
echo "   Add to crontab (crontab -e):"
echo "   0 0 * * * cd $PROJECT_ROOT && make ssl-renew >> /var/log/ssl-renew.log 2>&1"
echo ""
echo "   This will automatically reload all SSL services (nginx, postgres, mariadb, redis)"
echo "============================================================================"
