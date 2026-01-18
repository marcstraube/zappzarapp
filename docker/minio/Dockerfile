# docker/minio/Dockerfile
# MinIO - S3-compatible object storage
# Use same SDKs as AWS S3, switch to real S3 in production without code changes
# https://min.io/
#
# Version Pinning:
# - MinIO uses date-based releases (RELEASE.YYYY-MM-DDTHH-MM-SSZ)
# - To update: check https://hub.docker.com/r/minio/minio/tags
# - For SHA digest pinning in production, use:
#   FROM minio/minio:RELEASE.2025-10-15T17-29-55Z@sha256:<digest>

ARG MINIO_VERSION=RELEASE.2025-10-15T17-29-55Z

FROM minio/minio:${MINIO_VERSION}

# Health check for MinIO (HTTPS for zero-trust internal TLS)
# MinIO exposes a health endpoint at /minio/health/live
HEALTHCHECK --interval=10s --timeout=5s --start-period=10s --retries=3 \
    CMD curl -sfk https://localhost:9000/minio/health/live || exit 1
