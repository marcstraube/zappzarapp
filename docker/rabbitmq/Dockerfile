# docker/rabbitmq/Dockerfile
# RabbitMQ - Enterprise message broker
# Reliable messaging with routing, priorities, and acknowledgments
# https://www.rabbitmq.com/
#
# Security Note: No USER directive is set - the image supports both startup
# paths:
# - Root start (development preset): the official entrypoint initializes the
#   data directory (/var/lib/rabbitmq), fixes the Erlang cookie permissions
#   (~/.erlang.cookie must be 400), and drops privileges to the rabbitmq
#   user (uid 100, gid 101 in the Alpine image) via su-exec before starting
#   the Erlang VM.
# - Unprivileged start (production preset sets user: "100:101"): the
#   official entrypoint skips its root-only chown/su-exec phase; writable
#   paths come from a uid-100 tmpfs mount and the data volume.

ARG RABBITMQ_VERSION=4.3-management-alpine

# Version Pinning:
# - Minor version pinned for stability; the Alpine base follows the official
#   RabbitMQ image
# - Full tag lives in ONE ARG: Renovate cannot update a tag composed from
#   an ARG plus a literal suffix
# - To update: check https://hub.docker.com/_/rabbitmq/tags
# - For SHA digest pinning in production, use:
#   FROM rabbitmq:<tag>@sha256:<digest>

FROM rabbitmq:${RABBITMQ_VERSION}

# Copy entrypoint wrapper to load credentials from Docker Secrets
# (RABBITMQ_DEFAULT_USER_FILE and RABBITMQ_DEFAULT_PASS_FILE are deprecated in 4.0)
COPY --chmod=0755 docker/rabbitmq/entrypoint.sh /entrypoint.sh

# Copy healthcheck script (uses su-exec to run as rabbitmq user)
COPY --chmod=0755 docker/rabbitmq/healthcheck.sh /healthcheck.sh

# Health check for RabbitMQ
# Uses su-exec to run rabbitmq-diagnostics as rabbitmq user
# This avoids needing DAC_READ_SEARCH capability to read .erlang.cookie
HEALTHCHECK --interval=15s --timeout=10s --start-period=30s --retries=5 \
    CMD /healthcheck.sh || exit 1

ENTRYPOINT ["/entrypoint.sh"]
CMD ["rabbitmq-server"]
