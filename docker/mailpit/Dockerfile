# docker/mailpit/Dockerfile
# Mailpit - Email testing tool for development
# Catches all outgoing emails and displays them in a web UI
# https://mailpit.axllent.org/

ARG MAILPIT_VERSION=1.30

FROM axllent/mailpit:v${MAILPIT_VERSION}

# The image has no dedicated service user; chown the data directory so the
# named volume inherits uid-1000 ownership on first creation, then run
# unprivileged. Mailpit needs no root: it listens on unprivileged ports
# (1025/8025) and writes only to /data.
RUN mkdir -p /data && chown -R 1000:1000 /data
USER 1000:1000

# Health check for Mailpit
# Mailpit exposes a readiness endpoint at /readyz
HEALTHCHECK --interval=10s --timeout=3s --start-period=5s --retries=3 \
    CMD wget --no-verbose --tries=1 --spider http://localhost:8025/readyz || exit 1
