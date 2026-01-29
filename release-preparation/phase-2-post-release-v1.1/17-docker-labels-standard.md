# Docker Labels Standard

## Overview

Standardize OCI labels across all Dockerfiles for better image management and
documentation.

## Current State

- `docker/bats/Dockerfile` has labels (newly added)
- All other Dockerfiles have no labels

## Scope

Add OCI-compliant labels to all Dockerfiles:

```dockerfile
LABEL org.opencontainers.image.title="Service Name" \
      org.opencontainers.image.description="Description" \
      org.opencontainers.image.version="${VERSION:-dev}" \
      org.opencontainers.image.vendor="zappzarapp" \
      org.opencontainers.image.source="https://github.com/..."
```

## Files to Update

- `docker/php/Dockerfile`
- `docker/nginx/Dockerfile`
- `docker/node/Dockerfile`
- `docker/goss/Dockerfile`
- `docker/bats/Dockerfile` (already has basic labels)

## Considerations

- Use OCI standard label names (`org.opencontainers.image.*`)
- Consider build-time ARGs for version injection
- Document label convention in standards

## References

- [OCI Image Spec - Annotations](https://github.com/opencontainers/image-spec/blob/main/annotations.md)
- [Docker Label Best Practices](https://docs.docker.com/develop/develop-images/dockerfile_best-practices/#label)
