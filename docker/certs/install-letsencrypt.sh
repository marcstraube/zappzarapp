#!/bin/bash
# ============================================================================
# LET'S ENCRYPT CERTIFICATE INSTALLER
# ============================================================================
# Installs the current Let's Encrypt certificate into the nginx certificate
# directory (nginx/cert.crt, nginx/cert.key) that the nginx container bind
# mounts as /etc/nginx/certs. A copy is required: nginx only mounts nginx/,
# so files under letsencrypt/live/<domain>/ are not visible in the container.
#
# Pure file logic - no ACME traffic. Idempotent: the copy is skipped when the
# installed certificate already matches, but permissions and ACLs are
# re-enforced on every run (certbot renewals replace the files and drop them).
#
# Usage: ./install-letsencrypt.sh [domain] [--cert-dir DIR]
#   domain      Defaults to the single directory under letsencrypt/live/
#   --cert-dir  Certificate tree root (default: this script's directory)
#
# Prints "installed" when files changed, "unchanged" otherwise.
# ============================================================================

set -e

# Private keys must never be world-readable, not even between creation and
# the final chmod/setfacl
umask 077

CERT_DIR="$(cd "$(dirname "$0")" && pwd)"
DOMAIN=""

while [[ $# -gt 0 ]]; do
    case "$1" in
        --cert-dir)
            [[ -n "${2:-}" ]] || { echo "ERROR: --cert-dir requires a path" >&2; exit 1; }
            CERT_DIR="$(cd "$2" && pwd)"
            shift 2
            ;;
        -*)
            echo "ERROR: Unknown option: $1" >&2
            exit 1
            ;;
        *)
            DOMAIN="$1"
            shift
            ;;
    esac
done

LIVE_DIR="$CERT_DIR/letsencrypt/live"

# Auto-detect the domain when not given: certbot keeps one directory per
# certificate lineage under live/ (plus a README file, hence the -d check;
# a glob instead of find keeps this portable to BusyBox environments)
if [[ -z "$DOMAIN" ]]; then
    lineages=()
    for lineage in "$LIVE_DIR"/*/; do
        [[ -d "$lineage" ]] && lineages+=("$(basename "$lineage")")
    done
    if [[ ${#lineages[@]} -eq 0 ]]; then
        echo "ERROR: No certificate lineage found under $LIVE_DIR" >&2
        exit 1
    fi
    if [[ ${#lineages[@]} -gt 1 ]]; then
        echo "ERROR: Multiple lineages under $LIVE_DIR - pass the domain explicitly:" >&2
        printf '  %s\n' "${lineages[@]}" >&2
        exit 1
    fi
    DOMAIN="${lineages[0]}"
fi

SRC_CRT="$LIVE_DIR/$DOMAIN/fullchain.pem"
SRC_KEY="$LIVE_DIR/$DOMAIN/privkey.pem"

for src in "$SRC_CRT" "$SRC_KEY"; do
    if [[ ! -f "$src" ]]; then
        echo "ERROR: $src not found" >&2
        exit 1
    fi
    if [[ ! -r "$src" ]]; then
        echo "ERROR: Cannot read $src - certbot files are root-owned, run with sudo" >&2
        exit 1
    fi
done

NGINX_DIR="$CERT_DIR/nginx"
DST_CRT="$NGINX_DIR/cert.crt"
DST_KEY="$NGINX_DIR/cert.key"

mkdir -p "$NGINX_DIR"
# The directory must stay traversable for the container uids that read the
# certs via bind mounts; the key is protected by file mode + ACLs
chmod 755 "$NGINX_DIR"

# Copy only when the content differs (cmp follows the certbot live/ symlinks)
CHANGED=false
if ! cmp -s "$SRC_CRT" "$DST_CRT" || ! cmp -s "$SRC_KEY" "$DST_KEY"; then
    CHANGED=true
    # Write-then-rename keeps the swap atomic for a concurrently reading nginx
    cp -L "$SRC_CRT" "$DST_CRT.tmp"
    cp -L "$SRC_KEY" "$DST_KEY.tmp"
    mv "$DST_CRT.tmp" "$DST_CRT"
    mv "$DST_KEY.tmp" "$DST_KEY"
fi

# Enforce permissions and ACLs on every run: the certificate is public, the
# key must not be world-readable but needs a read ACL for nginx (100:101,
# the Alpine nginx user - Docker bind mounts do not remap ownership and the
# production preset runs nginx unprivileged with cap_drop: ALL)
chmod 644 "$DST_CRT"
chmod 600 "$DST_KEY"
if command -v setfacl >/dev/null 2>&1; then
    setfacl -m u:100:r "$DST_KEY"
else
    echo "WARNING: setfacl not found - nginx (uid 100) cannot read the" >&2
    echo "         0600 private key. Install the 'acl' package and re-run." >&2
fi

if [[ "$CHANGED" == "true" ]]; then
    echo "installed"
else
    echo "unchanged"
fi
