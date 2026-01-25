# syntax=docker/dockerfile:1
# BATS Testing Container
# Provides BATS test framework for Makefile target testing

ARG ALPINE_VERSION=3.23

# Version Pinning:
# - Alpine version pinned for consistency across services
# - To update: check https://hub.docker.com/_/alpine/tags
# - For SHA digest pinning in production, use:
#   FROM alpine:3.23@sha256:<digest>

FROM alpine:${ALPINE_VERSION} AS bats-builder

# Install build dependencies
RUN apk add --no-cache \
    bash \
    git \
    make

# Install BATS core (v1.11.1)
ARG BATS_VERSION=1.11.1
RUN git clone --depth 1 --branch v${BATS_VERSION} \
    https://github.com/bats-core/bats-core.git /tmp/bats \
    && /tmp/bats/install.sh /usr/local

# Install BATS helper libraries
RUN mkdir -p /usr/local/lib/bats \
    && git clone --depth 1 https://github.com/bats-core/bats-support.git \
       /usr/local/lib/bats/bats-support \
    && git clone --depth 1 https://github.com/bats-core/bats-assert.git \
       /usr/local/lib/bats/bats-assert \
    && git clone --depth 1 https://github.com/bats-core/bats-file.git \
       /usr/local/lib/bats/bats-file

# Final runtime image
FROM alpine:${ALPINE_VERSION} AS runtime

LABEL maintainer="zappzarapp" \
      description="BATS testing container for Makefile target validation" \
      version="1.0"

# Install runtime dependencies
RUN apk add --no-cache \
    bash \
    curl \
    docker-cli \
    docker-cli-compose \
    git \
    jq \
    make \
    ncurses \
    openssl \
    parallel \
    # PHP + Composer for local install tests
    php83 \
    php83-phar \
    php83-mbstring \
    php83-openssl \
    php83-curl \
    php83-json \
    php83-iconv \
    php83-tokenizer \
    composer \
    # Node.js + pnpm for local install tests
    nodejs \
    npm

# Install pnpm globally
RUN npm install -g pnpm

# Create php symlink for compatibility
RUN ln -sf /usr/bin/php83 /usr/bin/php

# Copy BATS from builder (complete installation)
COPY --from=bats-builder /usr/local/bin/bats /usr/local/bin/bats
COPY --from=bats-builder /usr/local/libexec/bats-core /usr/local/libexec/bats-core
COPY --from=bats-builder /usr/local/lib/bats-core /usr/local/lib/bats-core
COPY --from=bats-builder /usr/local/lib/bats /usr/local/lib/bats

# Set BATS library path
ENV BATS_LIB_PATH=/usr/local/lib/bats

# Configure Git to trust all directories (needed for running as root on mounted volumes)
# This prevents "dubious ownership" errors when container user differs from host user
RUN git config --global --add safe.directory '*'

# Create non-root user for testing
RUN addgroup -g 1000 bats \
    && adduser -u 1000 -G bats -D -h /app bats

# Working directory
WORKDIR /app

# Default command: run all tests
ENTRYPOINT ["bats"]
CMD ["tests/bats/"]

# Test stage - validates BATS installation
FROM runtime AS test

# Verify BATS installation and run self-test
RUN bats --version \
    && echo "BATS libraries:" \
    && ls -la /usr/local/lib/bats/ \
    && echo '#!/usr/bin/env bats' > /tmp/test.bats \
    && echo 'load "/usr/local/lib/bats/bats-support/load.bash"' >> /tmp/test.bats \
    && echo 'load "/usr/local/lib/bats/bats-assert/load.bash"' >> /tmp/test.bats \
    && echo '@test "BATS works" { run echo "hello"; assert_success; assert_output "hello"; }' >> /tmp/test.bats \
    && bats /tmp/test.bats \
    && echo "Local tools:" \
    && php --version \
    && composer --version \
    && node --version \
    && pnpm --version

# Final production image
FROM runtime AS production

# Copy project files (mounted at runtime, but can be baked in for CI)
# Tests are mounted via docker compose or docker run -v

USER bats
