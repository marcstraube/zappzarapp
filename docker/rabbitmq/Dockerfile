# docker/rabbitmq/Dockerfile
# RabbitMQ - Enterprise message broker
# Reliable messaging with routing, priorities, and acknowledgments
# https://www.rabbitmq.com/

ARG RABBITMQ_VERSION=4.0
ARG ALPINE_VERSION=3.21

FROM rabbitmq:${RABBITMQ_VERSION}-management-alpine

# Health check for RabbitMQ
# Uses rabbitmq-diagnostics to check node health
HEALTHCHECK --interval=15s --timeout=10s --start-period=30s --retries=5 \
    CMD rabbitmq-diagnostics -q ping || exit 1
