# Adminer Database Admin UI
#
# Lightweight database management tool supporting PostgreSQL, MariaDB, SQLite.
# Development-only: Used via Nginx proxy at /_dev/adminer/
#
# Security: Runs as www-data user - NO capabilities needed

FROM adminer:4-standalone

# Use the pepa-linha design for better UX
ENV ADMINER_DESIGN=pepa-linha

# Pre-configure database servers (user still needs to click Login)
COPY docker/adminer/login-servers.php /var/www/html/plugins-enabled/

# Labels for container identification
LABEL org.opencontainers.image.title="Zappzarapp Adminer"
LABEL org.opencontainers.image.description="Database admin UI for development"

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=10s --retries=3 \
    CMD php -r 'exit(file_get_contents("http://127.0.0.1:8080/") !== false ? 0 : 1);'

EXPOSE 8080
