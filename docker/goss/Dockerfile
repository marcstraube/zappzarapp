# GOSS Testing Tool
#
# Centralized GOSS version pinning for all container tests.
# Other Dockerfiles use: COPY --from=zappzarapp-goss:latest /usr/bin/goss /usr/local/bin/goss
#
# Note: :latest is appropriate here because goss is a single-stage utility image
# used only in CI test stages. The Makefile builds this image once with :latest tag.
#
# Version Pinning:
# - Check https://github.com/goss-org/goss/releases for updates
# - For SHA digest pinning in production, use:
#   FROM ghcr.io/goss-org/goss:<tag>@sha256:<digest>

ARG GOSS_VERSION=v0.4.9

FROM ghcr.io/goss-org/goss:${GOSS_VERSION}

# No modifications needed - this image exists solely for version pinning
