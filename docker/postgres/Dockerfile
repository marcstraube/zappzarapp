# PostgreSQL 17 Alpine with pg_cron extension
# pg_cron enables scheduled jobs directly in PostgreSQL
#
# Usage: CREATE EXTENSION pg_cron;
# See: documentation/security/RETENTION-POLICY.md
#
# Security Note: No USER directive is set because PostgreSQL requires root
# privileges during container startup for:
# - Data directory initialization (/var/lib/postgresql/data)
# - SSL certificate permission setup
# - Locale and encoding configuration
# The official entrypoint (docker-entrypoint.sh) drops privileges to the
# postgres user (uid 70 on Alpine) via gosu before starting postgres.
#
# Version Pinning:
# - Minor version pinned for stability
# - To update: check https://hub.docker.com/_/postgres/tags
# - For SHA digest pinning in production, use:
#   FROM postgres:17.7-alpine3.23@sha256:<digest>

ARG POSTGRES_VERSION=17.7
ARG ALPINE_VERSION=3.23
ARG PG_CRON_VERSION=1.6.4

FROM postgres:${POSTGRES_VERSION}-alpine${ALPINE_VERSION} AS builder

# Redeclare ARG (global scope ARGs don't persist after FROM)
ARG PG_CRON_VERSION

# Install build dependencies
RUN apk add --no-cache --virtual .build-deps \
    git \
    build-base \
    clang \
    llvm \
    make

# Clone and build pg_cron
RUN git clone --branch v${PG_CRON_VERSION} --depth 1 https://github.com/citusdata/pg_cron.git /tmp/pg_cron \
    && cd /tmp/pg_cron \
    # PostgreSQL 17 expects LLVM 19 tools, Alpine has LLVM 21
    # Create compatibility symlinks
    && mkdir -p /usr/lib/llvm19/bin \
    && ln -sf /usr/bin/clang /usr/bin/clang-19 \
    && ln -sf /usr/bin/llvm-lto /usr/lib/llvm19/bin/llvm-lto \
    && make \
    && make install

# Final image
FROM postgres:${POSTGRES_VERSION}-alpine${ALPINE_VERSION} AS final

# Copy compiled extension from builder
COPY --from=builder /usr/local/lib/postgresql/pg_cron.so /usr/local/lib/postgresql/
COPY --from=builder /usr/local/share/postgresql/extension/pg_cron* /usr/local/share/postgresql/extension/

# Copy custom entrypoint for SSL certificate setup
# Path matches compose.override.yaml volume mount for development hot-reload
COPY docker/postgres/entrypoint.sh /entrypoint-wrapper.sh
RUN chmod +x /entrypoint-wrapper.sh

ENTRYPOINT ["/entrypoint-wrapper.sh"]
CMD ["postgres"]

# --- TEST STAGE (CI only) ---
# Validates the production image using GOSS
# Usage: docker build --target test -f docker/postgres/Dockerfile .
FROM final AS test

# Copy GOSS binary from centralized image
COPY --from=zappzarapp-goss:latest /usr/bin/goss /usr/local/bin/goss
RUN chmod +x /usr/local/bin/goss

# Copy test specification
COPY tests/goss/services/postgres.yaml /goss.yaml

# Run GOSS tests (fails build if tests fail)
RUN goss -g /goss.yaml validate --format documentation
