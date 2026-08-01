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

# Apply available OS security patches. The MariaDB image is Debian/Ubuntu-based,
# so its libc/krb5/pam/ncurses/... apt packages accumulate fixable CVEs between
# upstream rebuilds; Trivy runs with ignore-unfixed, so reported CVEs have a fix
# available here. (The Go stdlib CVEs in the bundled gosu binary remain — those
# are only fixable by an upstream image rebuild.) The image already runs as root.
RUN apt-get update && apt-get upgrade -y \
    && rm -rf /var/lib/apt/lists/* \
    # Strip setuid/setgid bits (Dockle CIS-DI-0008): a DB server needs none of
    # the base image's setuid OS tools (mount/su/passwd/...). This also clears
    # the optional MariaDB PAM auth plugin's setuid (auth_pam_tool) and the PAM
    # helpers - re-add them only if you enable `pam` authentication; the default
    # here is native password auth via Docker secrets.
    && find / -xdev -type f \( -perm -4000 -o -perm -2000 \) -exec chmod -s {} \;

# Create certs directory (owned by root, entrypoint will chown files after copy)
RUN mkdir -p /etc/mysql/certs

# Copy custom entrypoint for SSL certificate setup
# Path matches compose.override.yaml volume mount for development hot-reload
COPY --chmod=0755 docker/mariadb/entrypoint.sh /entrypoint-wrapper.sh

# Copy database initialization script
# Runs on first init AND on existing volumes (via entrypoint)
COPY --chmod=0755 docker/mariadb/init-db.sh /docker-entrypoint-initdb.d/10-init-db.sh

# Copy custom healthcheck (uses gosu to run as mysql user)
# This avoids needing DAC_OVERRIDE capability to read .my-healthcheck.cnf
COPY --chmod=0755 docker/mariadb/healthcheck.sh /custom-healthcheck.sh

# Override default healthcheck to use gosu wrapper
HEALTHCHECK --interval=10s --timeout=3s --start-period=10s --retries=3 \
    CMD /custom-healthcheck.sh || exit 1

ENTRYPOINT ["/entrypoint-wrapper.sh"]
CMD ["mariadbd"]
