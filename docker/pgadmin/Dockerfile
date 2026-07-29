# pgAdmin PostgreSQL Admin UI
#
# Full-featured PostgreSQL management tool with query tool, ERD diagrams.
# Development-only: Used via Nginx proxy at /_dev/pgadmin/
#
# Pre-configured with PostgreSQL connection using Docker secrets.

FROM dpage/pgadmin4:9

# Copy server pre-configuration and the entrypoint wrapper (sets the password
# from Docker secrets)
COPY docker/pgadmin/servers.json /pgadmin4/servers.json
COPY docker/pgadmin/entrypoint.sh /entrypoint-wrapper.sh

# Normalise ownership/permissions as root. The dpage/pgadmin4 base image defaults
# to USER pgadmin (uid 5050), which cannot chmod/chown the root-owned COPYed
# files — so the chmod must run here, under USER root, NOT as a bare post-COPY
# RUN. `chmod 0755` (absolute mode) is umask-safe: a bare `chmod +x` on a
# umask-077 source would yield 711 (execute-only, no read) and pgadmin could not
# read the script. (COPY --chmod would set the mode atomically and dodge the USER
# issue, but it is BuildKit-only and other project images must build under the
# classic builder for local `COPY --from` refs — so we normalise as root instead.)
USER root
RUN chmod 0755 /entrypoint-wrapper.sh \
    && chown 5050:0 /pgadmin4/servers.json

USER pgadmin

# Labels for container identification
LABEL org.opencontainers.image.title="Zappzarapp pgAdmin"
LABEL org.opencontainers.image.description="PostgreSQL admin UI for development"

# Use wrapper entrypoint to set password from secrets
ENTRYPOINT ["/entrypoint-wrapper.sh"]

EXPOSE 80
