FROM nginx:alpine3.22

# Create Log Directory
RUN mkdir -p /var/log/nginx && \
    chown -R nginx:nginx /var/log/nginx

# Expose Port
EXPOSE 80

# Health Check
HEALTHCHECK --interval=30s --timeout=3s --start-period=10s --retries=3 \
    CMD curl --fail http://localhost/health || exit 1

CMD ["nginx", "-g", "daemon off;"]
