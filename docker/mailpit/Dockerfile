# docker/mailpit/Dockerfile
# Mailpit - Email testing tool for development
# Catches all outgoing emails and displays them in a web UI
# https://mailpit.axllent.org/

ARG MAILPIT_VERSION=1.30

FROM axllent/mailpit:v${MAILPIT_VERSION}

# Health check for Mailpit
# Mailpit exposes a readiness endpoint at /readyz
HEALTHCHECK --interval=10s --timeout=3s --start-period=5s --retries=3 \
    CMD wget --no-verbose --tries=1 --spider http://localhost:8025/readyz || exit 1
