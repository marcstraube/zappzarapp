# docker/rabbitmq/Dockerfile
# RabbitMQ - Enterprise message broker
# Reliable messaging with routing, priorities, and acknowledgments
# https://www.rabbitmq.com/
#
# Security Note: No USER directive is set because RabbitMQ requires root
# privileges during container startup for:
# - Erlang cookie permission setup (~/.erlang.cookie must be 400)
# - Data directory initialization (/var/lib/rabbitmq)
# - Plugin loading and configuration
# The official entrypoint drops privileges to the rabbitmq user (uid 999)
# via su-exec before starting the Erlang VM.

ARG RABBITMQ_VERSION=4.3

# Version Pinning:
# - Major version pinned for stability
# - Alpine version follows RabbitMQ official image (currently 3.23)
# - To update: check https://hub.docker.com/_/rabbitmq/tags
# - For SHA digest pinning in production, use:
#   FROM rabbitmq:4.3-management-alpine@sha256:<digest>

FROM rabbitmq:${RABBITMQ_VERSION}-management-alpine

# Copy entrypoint wrapper to load credentials from Docker Secrets
# (RABBITMQ_DEFAULT_USER_FILE and RABBITMQ_DEFAULT_PASS_FILE are deprecated in 4.0)
COPY docker/rabbitmq/entrypoint.sh /entrypoint.sh

# Copy healthcheck script (uses su-exec to run as rabbitmq user)
COPY docker/rabbitmq/healthcheck.sh /healthcheck.sh

RUN chmod +x /entrypoint.sh /healthcheck.sh

# Health check for RabbitMQ
# Uses su-exec to run rabbitmq-diagnostics as rabbitmq user
# This avoids needing DAC_READ_SEARCH capability to read .erlang.cookie
HEALTHCHECK --interval=15s --timeout=10s --start-period=30s --retries=5 \
    CMD /healthcheck.sh || exit 1

ENTRYPOINT ["/entrypoint.sh"]
CMD ["rabbitmq-server"]
