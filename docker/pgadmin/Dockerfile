# pgAdmin PostgreSQL Admin UI
#
# Full-featured PostgreSQL management tool with query tool, ERD diagrams.
# Development-only: Used via Nginx proxy at /_dev/pgadmin/
#
# Pre-configured with PostgreSQL connection using Docker secrets.

FROM dpage/pgadmin4:9

# Copy server pre-configuration
COPY docker/pgadmin/servers.json /pgadmin4/servers.json
COPY docker/pgadmin/entrypoint.sh /entrypoint-wrapper.sh

USER root
RUN chmod +x /entrypoint-wrapper.sh && \
    chown 5050:0 /pgadmin4/servers.json

USER pgadmin

# Labels for container identification
LABEL org.opencontainers.image.title="Zappzarapp pgAdmin"
LABEL org.opencontainers.image.description="PostgreSQL admin UI for development"

# Use wrapper entrypoint to set password from secrets
ENTRYPOINT ["/entrypoint-wrapper.sh"]

EXPOSE 80
