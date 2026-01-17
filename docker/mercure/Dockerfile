# docker/mercure/Dockerfile
# Mercure Hub - Real-time messaging server using Server-Sent Events (SSE)
# https://mercure.rocks/

ARG MERCURE_VERSION=0.16

FROM dunglas/mercure:v${MERCURE_VERSION}

# Copy entrypoint wrapper to load JWT secret from Docker Secret file
COPY docker/mercure/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Health check for Mercure Hub (HTTPS for zero-trust internal TLS)
# Mercure exposes a health endpoint at /healthz
# See: https://github.com/dunglas/mercure/blob/main/docs/hub/config.md
# Use 127.0.0.1 instead of localhost to avoid IPv6 resolution issues
HEALTHCHECK --interval=10s --timeout=3s --start-period=5s --retries=3 \
    CMD wget --no-verbose --tries=1 --spider --no-check-certificate https://127.0.0.1:443/healthz || exit 1

ENTRYPOINT ["/entrypoint.sh"]
