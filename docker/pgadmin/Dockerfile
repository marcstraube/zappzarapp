# pgAdmin PostgreSQL Admin UI
#
# Full-featured PostgreSQL management tool with query tool, ERD diagrams.
# Development-only: Used via Nginx proxy at /_dev/pgadmin/
#
# Pre-configured with PostgreSQL connection using Docker secrets.

FROM dpage/pgadmin4:9

# Copy server pre-configuration
COPY docker/pgadmin/servers.json /pgadmin4/servers.json
# Explicit absolute-mode chmod after COPY (not `chmod +x`): the container runs as
# the unprivileged pgadmin user (uid 5050), and a bare `chmod +x` on a umask-077
# source (mode 700, root-owned) yields 711 (execute-only, no read) → pgadmin
# cannot open the script. `chmod 0755` guarantees world read+execute and is
# umask-safe. (COPY --chmod is avoided: it is BuildKit-only and other project
# images must build under the classic builder for local `COPY --from` refs.)
COPY docker/pgadmin/entrypoint.sh /entrypoint-wrapper.sh
RUN chmod 0755 /entrypoint-wrapper.sh

USER root
RUN chown 5050:0 /pgadmin4/servers.json

USER pgadmin

# Labels for container identification
LABEL org.opencontainers.image.title="Zappzarapp pgAdmin"
LABEL org.opencontainers.image.description="PostgreSQL admin UI for development"

# Use wrapper entrypoint to set password from secrets
ENTRYPOINT ["/entrypoint-wrapper.sh"]

EXPOSE 80
