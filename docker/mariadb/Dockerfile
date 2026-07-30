# MariaDB with custom entrypoint for SSL certificate setup
#
# This is a minimal wrapper around the official MariaDB image.
# It adds a custom entrypoint script to set up SSL certificates
# with correct permissions before starting MariaDB.
#
# Security Note: No USER directive is set because MariaDB requires root
# privileges during container startup for:
# - Data directory initialization (/var/lib/mysql)
# - SSL certificate permission setup
# - Socket directory creation
# The official entrypoint (docker-entrypoint.sh) drops privileges to the
# mysql user (uid 999) via gosu before starting mariadbd.
#
# Version Pinning:
# - Minor version pinned for stability
# - To update: check https://hub.docker.com/_/mariadb/tags
# - For SHA digest pinning in production, use:
#   FROM mariadb:12.3.2@sha256:<digest>

ARG MARIADB_VERSION=12.3
ARG MARIADB_PATCH=2

FROM mariadb:${MARIADB_VERSION}.${MARIADB_PATCH}

# Create certs directory (owned by root, entrypoint will chown files after copy)
RUN mkdir -p /etc/mysql/certs

# Copy custom entrypoint for SSL certificate setup
# Path matches compose.override.yaml volume mount for development hot-reload
COPY docker/mariadb/entrypoint.sh /entrypoint-wrapper.sh

# Copy database initialization script
# Runs on first init AND on existing volumes (via entrypoint)
COPY docker/mariadb/init-db.sh /docker-entrypoint-initdb.d/10-init-db.sh

# Copy custom healthcheck (uses gosu to run as mysql user)
# This avoids needing DAC_OVERRIDE capability to read .my-healthcheck.cnf
COPY docker/mariadb/healthcheck.sh /custom-healthcheck.sh

RUN chmod +x /entrypoint-wrapper.sh /custom-healthcheck.sh /docker-entrypoint-initdb.d/10-init-db.sh

# Override default healthcheck to use gosu wrapper
HEALTHCHECK --interval=10s --timeout=3s --start-period=10s --retries=3 \
    CMD /custom-healthcheck.sh || exit 1

ENTRYPOINT ["/entrypoint-wrapper.sh"]
CMD ["mariadbd"]
