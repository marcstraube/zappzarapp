# docker/seaweedfs/Dockerfile
# SeaweedFS - S3-compatible distributed object storage
# Fast storage for billions of files with O(1) disk seek
# https://github.com/seaweedfs/seaweedfs
#
# Version Pinning:
# - To update: check https://hub.docker.com/r/chrislusf/seaweedfs/tags
# - For SHA digest pinning in production, use:
#   FROM chrislusf/seaweedfs:<tag>@sha256:<digest>

ARG SEAWEEDFS_VERSION=4.40

FROM chrislusf/seaweedfs:${SEAWEEDFS_VERSION}

# Install su-exec for privilege dropping (Alpine-based image)
# nosemgrep: last-user-is-root -- intentional: the entrypoint reads secrets as root, then drops to the seaweedfs user via su-exec (see ENTRYPOINT note below).
USER root
RUN apk add --no-cache su-exec \
    # Strip setuid/setgid bits (Dockle CIS-DI-0008): the only setuid binary is
    # fusermount, and the S3 server mode does not use FUSE mounts. su-exec is not
    # setuid (it drops privileges from root via the entrypoint).
    && find / -xdev -type f \( -perm -4000 -o -perm -2000 \) -exec chmod -s {} \;

# Create directories with correct permissions
# Base image uses UID/GID 1000 for the existing user, we use numeric IDs for portability
RUN mkdir -p /data /etc/seaweedfs/certs /etc/seaweedfs/config && \
    chown -R 1000:1000 /data /etc/seaweedfs

# Copy entrypoint script for loading credentials from secret files
COPY --chmod=0755 docker/seaweedfs/entrypoint.sh /entrypoint.sh

# Set working directory
WORKDIR /data

# Expose ports:
# - 8333: S3 API (main interface for applications)
# - 9333: Master server (internal, for cluster management)
# - 8080: Volume server (internal, for data storage)
# - 8888: Filer server (internal, for file operations)
EXPOSE 8333 9333 8080 8888

# Health check: Master cluster status (returns 200)
# S3 API returns 403 without auth, but master endpoint is reliable
HEALTHCHECK --interval=10s --timeout=5s --start-period=15s --retries=3 \
    CMD wget -q -O /dev/null http://127.0.0.1:9333/cluster/status || exit 1

# Run as root initially so entrypoint can read secrets and setup config
# SeaweedFS will be started as seaweedfs user via su-exec in entrypoint
ENTRYPOINT ["/entrypoint.sh"]

# Default command: run all-in-one server with S3 API
# -master: runs master server
# -volume: runs volume server
# -filer: runs filer server
# -s3: enables S3 API gateway
CMD ["server", "-s3", "-s3.port=8333", "-volume.max=100", "-master.volumeSizeLimitMB=1024"]
