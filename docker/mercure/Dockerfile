# docker/mercure/Dockerfile
# Mercure Hub - Real-time messaging server using Server-Sent Events (SSE)
# https://mercure.rocks/

ARG MERCURE_VERSION=0.16

FROM dunglas/mercure:v${MERCURE_VERSION}

# Copy entrypoint wrapper to load JWT secret from Docker Secret file
COPY docker/mercure/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Health check for Mercure Hub
# Mercure exposes a health endpoint at /.well-known/mercure/health
HEALTHCHECK --interval=10s --timeout=3s --start-period=5s --retries=3 \
    CMD wget --no-verbose --tries=1 --spider http://localhost:80/.well-known/mercure/health || exit 1

ENTRYPOINT ["/entrypoint.sh"]
