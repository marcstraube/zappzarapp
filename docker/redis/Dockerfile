# Redis with TLS support
#
# This is a minimal wrapper around the official Redis image.
# TLS configuration is handled via command arguments in compose files.

ARG REDIS_VERSION=8.6
ARG ALPINE_VERSION=3.23

# Version Pinning:
# - Minor version pinned for stability
# - Alpine version pinned for consistency across services
# - To update: check https://hub.docker.com/_/redis/tags
# - For SHA digest pinning in production, use:
#   FROM redis:8.6-alpine3.23@sha256:<digest>

FROM redis:${REDIS_VERSION}-alpine${ALPINE_VERSION} AS base

# Apply available OS security patches within the pinned Alpine line (Trivy runs
# with ignore-unfixed, so reported CVEs have a fix available here).
RUN apk upgrade --no-cache

# Copy entrypoint script for SSL certificate validation
COPY docker/redis/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Copy health check script that handles both dev and prod cert paths
COPY docker/redis/healthcheck.sh /usr/local/bin/healthcheck.sh
RUN chmod +x /usr/local/bin/healthcheck.sh

# Use custom entrypoint that validates SSL before starting redis-server
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["redis-server"]

# Health check with TLS support (script handles dev/prod cert paths)
HEALTHCHECK --interval=10s --timeout=3s --start-period=5s --retries=3 \
    CMD /usr/local/bin/healthcheck.sh

# --- TEST STAGE (CI only) ---
# Validates the production image using GOSS
# Usage: docker build --target test -f docker/redis/Dockerfile .
FROM base AS test

# Copy GOSS binary from centralized image
COPY --from=zappzarapp-goss:latest /usr/bin/goss /usr/local/bin/goss
RUN chmod +x /usr/local/bin/goss

# Copy test specification
COPY tests/goss/services/redis.yaml /goss.yaml

# Run GOSS tests (fails build if tests fail)
RUN goss -g /goss.yaml validate --format documentation
