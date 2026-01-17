# docker/rabbitmq/Dockerfile
# RabbitMQ - Enterprise message broker
# Reliable messaging with routing, priorities, and acknowledgments
# https://www.rabbitmq.com/

ARG RABBITMQ_VERSION=4.0

FROM rabbitmq:${RABBITMQ_VERSION}-management-alpine

# Copy entrypoint wrapper to load credentials from Docker Secrets
# (RABBITMQ_DEFAULT_USER_FILE and RABBITMQ_DEFAULT_PASS_FILE are deprecated in 4.0)
COPY docker/rabbitmq/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Health check for RabbitMQ
# Uses rabbitmq-diagnostics to check node health
HEALTHCHECK --interval=15s --timeout=10s --start-period=30s --retries=5 \
    CMD rabbitmq-diagnostics -q ping || exit 1

ENTRYPOINT ["/entrypoint.sh"]
CMD ["rabbitmq-server"]
