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

# Health check with TLS support
# Note: TLS flags are only effective when Redis is started with TLS enabled
HEALTHCHECK --interval=10s --timeout=3s --start-period=5s --retries=3 \
    CMD redis-cli --tls --cert /etc/ssl/certs/redis.crt --key /etc/ssl/private/redis.key --cacert /etc/ssl/certs/redis.crt ping || exit 1
