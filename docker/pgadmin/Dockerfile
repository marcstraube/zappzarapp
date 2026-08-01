# pgAdmin PostgreSQL Admin UI
#
# Full-featured PostgreSQL management tool with query tool, ERD diagrams.
# Development-only: Used via Nginx proxy at /_dev/pgadmin/
#
# Pre-configured with PostgreSQL connection using Docker secrets.

FROM dpage/pgadmin4:9

# Copy server pre-configuration and the entrypoint wrapper (sets the password
# from Docker secrets). Ownership/permissions are set at copy time via
# --chown/--chmod: the dpage/pgadmin4 base runs as USER pgadmin (uid 5050), which
# could not chmod/chown root-owned files in a post-COPY RUN. `--chmod=0755`
# (absolute mode) is umask-safe — a bare `chmod +x` on a umask-077 source would
# yield 711 (execute-only, no read) and pgadmin could not read the script.
COPY --chown=5050:0 docker/pgadmin/servers.json /pgadmin4/servers.json
COPY --chmod=0755 docker/pgadmin/entrypoint.sh /entrypoint-wrapper.sh

# Labels for container identification
LABEL org.opencontainers.image.title="Zappzarapp pgAdmin"
LABEL org.opencontainers.image.description="PostgreSQL admin UI for development"

# Use wrapper entrypoint to set password from secrets
ENTRYPOINT ["/entrypoint-wrapper.sh"]

EXPOSE 80
