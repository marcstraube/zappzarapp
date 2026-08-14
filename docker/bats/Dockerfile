# syntax=docker/dockerfile:1
# BATS Testing Container
# Provides BATS test framework for Makefile target testing

ARG ALPINE_VERSION=3.24

# Version Pinning:
# - Alpine version pinned for consistency across services
# - To update: check https://hub.docker.com/_/alpine/tags
# - For SHA digest pinning in production, use:
#   FROM alpine:<tag>@sha256:<digest>

FROM alpine:${ALPINE_VERSION} AS bats-builder

# Install build dependencies
RUN apk add --no-cache \
    bash \
    git \
    make

# Install BATS core, pinned by commit SHA. A shallow fetch of a commit avoids
# the benign-but-noisy warning git emits when shallow-cloning an annotated tag,
# and a SHA cannot be moved after the fact (supply-chain pin).
# BATS_COMMIT is the commit behind tag v1.11.1.
ARG BATS_COMMIT=b640ec3cf2c7c9cfc9e6351479261186f76eeec8
RUN git init -q /tmp/bats \
    && git -C /tmp/bats fetch --depth 1 \
       https://github.com/bats-core/bats-core.git ${BATS_COMMIT} \
    && git -C /tmp/bats checkout -q FETCH_HEAD \
    && /tmp/bats/install.sh /usr/local

# Install BATS helper libraries, pinned by commit SHA (they were previously
# floating on the default branch; SHAs snapshot that state as of 2026-08-04)
ARG BATS_SUPPORT_COMMIT=0954abb9925cad550424cebca2b99255d4eabe96
ARG BATS_ASSERT_COMMIT=697471b7a89d3ab38571f38c6c7c4b460d1f5e35
ARG BATS_FILE_COMMIT=6bee58bec7c2f4aed1a7425ccd4bdc42b4a84599
RUN mkdir -p /usr/local/lib/bats \
    && for lib in \
       "bats-support ${BATS_SUPPORT_COMMIT}" \
       "bats-assert ${BATS_ASSERT_COMMIT}" \
       "bats-file ${BATS_FILE_COMMIT}"; do \
       set -- $lib; \
       git init -q "/usr/local/lib/bats/$1" \
       && git -C "/usr/local/lib/bats/$1" fetch --depth 1 \
          "https://github.com/bats-core/$1.git" "$2" \
       && git -C "/usr/local/lib/bats/$1" checkout -q FETCH_HEAD \
       || exit 1; \
    done

# Final runtime image
FROM alpine:${ALPINE_VERSION} AS runtime

LABEL maintainer="zappzarapp" \
      description="BATS testing container for Makefile target validation" \
      version="1.0"

# Install runtime dependencies
RUN apk add --no-cache \
    # setfacl: the make targets under test generate secrets/certificates
    # with per-uid read ACLs - without it the container users (redis
    # 999:1000 etc.) cannot read the 0600 files and the started stack
    # crash-loops during suite runs
    acl \
    bash \
    curl \
    docker-cli \
    # buildx is required: Compose v5 (Alpine >= 3.24) no longer ships built-in
    # BuildKit and silently falls back to the legacy builder without it, which
    # cannot build the BuildKit-only Dockerfiles (COPY --chmod, syntax=...)
    docker-cli-buildx \
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
    && pnpm --version \
    && docker buildx version

# Final production image
FROM runtime AS production

# Copy project files (mounted at runtime, but can be baked in for CI)
# Tests are mounted via docker compose or docker run -v

# Run as root to avoid permission issues with mounted volumes in CI
# In GitHub Actions, the workspace is owned by the runner user (not UID 1000)
# Running as root ensures tests can create/modify files regardless of host ownership
# This is safe as the container only executes isolated test validation
