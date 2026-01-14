# docker/minio/Dockerfile
# MinIO - S3-compatible object storage
# Use same SDKs as AWS S3, switch to real S3 in production without code changes
# https://min.io/
#
# VERSION NOTE:
# MinIO uses date-based releases (e.g., RELEASE.2025-01-01T00-00-00Z) instead of
# semantic versioning. For production, pin to a specific release:
#   ARG MINIO_VERSION=RELEASE.2025-01-01T00-00-00Z
# See available tags: https://hub.docker.com/r/minio/minio/tags

ARG MINIO_VERSION=latest

FROM minio/minio:${MINIO_VERSION}

# Health check for MinIO
# MinIO exposes a health endpoint at /minio/health/live
HEALTHCHECK --interval=10s --timeout=5s --start-period=10s --retries=3 \
    CMD mc ready local || exit 1
