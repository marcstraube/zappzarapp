# PostgreSQL 17 Alpine with pg_cron extension
# pg_cron enables scheduled jobs directly in PostgreSQL
#
# Usage: CREATE EXTENSION pg_cron;
# See: documentation/security/RETENTION-POLICY.md

ARG POSTGRES_VERSION=17
ARG ALPINE_VERSION=3.23

FROM postgres:${POSTGRES_VERSION}-alpine${ALPINE_VERSION} AS builder

# Install build dependencies
RUN apk add --no-cache --virtual .build-deps \
    git \
    build-base \
    clang \
    llvm \
    make

# Clone and build pg_cron
ARG PG_CRON_VERSION=1.6.4
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
FROM postgres:${POSTGRES_VERSION}-alpine${ALPINE_VERSION}

# Copy compiled extension from builder
COPY --from=builder /usr/local/lib/postgresql/pg_cron.so /usr/local/lib/postgresql/
COPY --from=builder /usr/local/share/postgresql/extension/pg_cron* /usr/local/share/postgresql/extension/

# Copy custom entrypoint
COPY docker/postgres/entrypoint.sh /usr/local/bin/custom-entrypoint.sh
RUN chmod +x /usr/local/bin/custom-entrypoint.sh

ENTRYPOINT ["/usr/local/bin/custom-entrypoint.sh"]
CMD ["postgres"]
