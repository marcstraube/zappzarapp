# docker/mercure/Dockerfile
# Mercure Hub - Real-time messaging server using Server-Sent Events (SSE)
# https://mercure.rocks/

ARG MERCURE_VERSION=0.24

FROM dunglas/mercure:v${MERCURE_VERSION}

# Apply available OS security patches. The Mercure image is Alpine-based, so its
# curl/openssl/c-ares packages accumulate fixable CVEs between upstream rebuilds;
# Trivy runs with ignore-unfixed, so reported CVEs have a fix available here.
# (The Caddy/Go-binary CVEs remain — those are only fixable by an upstream bump.)
# The base image runs as root (the upstream default, used by the development
# preset), so no USER switch is required — and adding an explicit `USER root`
# would trip Semgrep's last-user-is-root rule for a condition that is the
# upstream default anyway. The production preset runs the container as uid
# 1000 via its user: directive and binds :443 through the
# ip_unprivileged_port_start sysctl.
#
# The chown covers Caddy's writable directories (XDG_DATA_HOME and
# XDG_CONFIG_HOME): the image has no dedicated service user, so named
# volumes must inherit uid-1000 ownership on first creation.
RUN apk upgrade --no-cache \
    && chown -R 1000:1000 /data /config

# Copy entrypoint wrapper to load JWT secret from Docker Secret file
COPY --chmod=0755 docker/mercure/entrypoint.sh /entrypoint.sh

# Health check for Mercure Hub (HTTPS for zero-trust internal TLS)
# Mercure exposes a health endpoint at /healthz
# See: https://github.com/dunglas/mercure/blob/main/docs/hub/config.md
# Use 127.0.0.1 instead of localhost to avoid IPv6 resolution issues
HEALTHCHECK --interval=10s --timeout=3s --start-period=5s --retries=3 \
    CMD wget --no-verbose --tries=1 --spider --no-check-certificate https://127.0.0.1:443/healthz || exit 1

ENTRYPOINT ["/entrypoint.sh"]
