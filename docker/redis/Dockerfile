# Redis with TLS support
#
# This is a minimal wrapper around the official Redis image.
# TLS configuration is handled via command arguments in compose files.
#
# Version Pinning:
# - Minor version pinned for stability
# - To update: check https://hub.docker.com/_/redis/tags
# - For SHA digest pinning in production, use:
#   FROM redis:7.4-alpine3.21@sha256:<digest>

ARG REDIS_VERSION=7.4
ARG ALPINE_VERSION=3.21

FROM redis:${REDIS_VERSION}-alpine${ALPINE_VERSION}

# Copy health check script that handles both dev and prod cert paths
COPY docker/redis/healthcheck.sh /usr/local/bin/healthcheck.sh
RUN chmod +x /usr/local/bin/healthcheck.sh

# Health check with TLS support (script handles dev/prod cert paths)
HEALTHCHECK --interval=10s --timeout=3s --start-period=5s --retries=3 \
    CMD /usr/local/bin/healthcheck.sh
