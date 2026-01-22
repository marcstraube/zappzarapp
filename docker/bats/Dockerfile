# syntax=docker/dockerfile:1
# BATS Testing Container
# Provides BATS test framework for Makefile target testing

ARG ALPINE_VERSION=3.21

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
    parallel

# Copy BATS from builder (complete installation)
COPY --from=bats-builder /usr/local/bin/bats /usr/local/bin/bats
COPY --from=bats-builder /usr/local/libexec/bats-core /usr/local/libexec/bats-core
COPY --from=bats-builder /usr/local/lib/bats-core /usr/local/lib/bats-core
COPY --from=bats-builder /usr/local/lib/bats /usr/local/lib/bats

# Set BATS library path
ENV BATS_LIB_PATH=/usr/local/lib/bats

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
    && bats /tmp/test.bats

# Final production image
FROM runtime AS production

# Copy project files (mounted at runtime, but can be baked in for CI)
# Tests are mounted via docker compose or docker run -v

USER bats
