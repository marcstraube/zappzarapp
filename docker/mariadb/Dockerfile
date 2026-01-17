# MariaDB with custom entrypoint for SSL certificate setup
#
# This is a minimal wrapper around the official MariaDB image.
# It adds a custom entrypoint script to set up SSL certificates
# with correct permissions before starting MariaDB.
#
# Version Pinning:
# - Minor version pinned for stability
# - To update: check https://hub.docker.com/_/mariadb/tags
# - For SHA digest pinning in production, use:
#   FROM mariadb:12.1.2@sha256:<digest>

ARG MARIADB_VERSION=12.1
ARG MARIADB_PATCH=2

FROM mariadb:${MARIADB_VERSION}.${MARIADB_PATCH}

# Create certs directory (owned by root, entrypoint will chown files after copy)
RUN mkdir -p /etc/mysql/certs

# Copy custom entrypoint for SSL certificate setup
# Path matches compose.override.yaml volume mount for development hot-reload
COPY docker/mariadb/entrypoint.sh /entrypoint-wrapper.sh
RUN chmod +x /entrypoint-wrapper.sh

ENTRYPOINT ["/entrypoint-wrapper.sh"]
CMD ["mariadbd"]
