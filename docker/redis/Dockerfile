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
ARG GOSS_VERSION=v0.4.9

FROM redis:${REDIS_VERSION}-alpine${ALPINE_VERSION} AS base

# Copy health check script that handles both dev and prod cert paths
COPY docker/redis/healthcheck.sh /usr/local/bin/healthcheck.sh
RUN chmod +x /usr/local/bin/healthcheck.sh

# Health check with TLS support (script handles dev/prod cert paths)
HEALTHCHECK --interval=10s --timeout=3s --start-period=5s --retries=3 \
    CMD /usr/local/bin/healthcheck.sh

# --- TEST STAGE (CI only) ---
# Validates the production image using GOSS
# Usage: docker build --target test -f docker/redis/Dockerfile .
FROM base AS test

# Copy GOSS binary from official image
COPY --from=ghcr.io/goss-org/goss:${GOSS_VERSION} /usr/bin/goss /usr/local/bin/goss
RUN chmod +x /usr/local/bin/goss

# Copy test specification
COPY tests/goss/services/redis.yaml /goss.yaml

# Run GOSS tests (fails build if tests fail)
RUN goss -g /goss.yaml validate --format documentation
