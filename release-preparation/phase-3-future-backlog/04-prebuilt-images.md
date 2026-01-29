# Task 04: Publish Pre-Built Docker Images

## Priority

LOW - Future Backlog

## Estimated Effort

3-4 hours

## Context

Currently, users must build Docker images locally after cloning the repository.
Publishing pre-built images to a container registry would reduce initial setup
time and ensure consistent, tested images.

## Current State

- All images built locally via `make build`
- First setup requires ~5-10 minutes for builds
- No public images available

## Target State

1. Publish base images to GitHub Container Registry (ghcr.io)
2. Provide "quick start" option using pre-built images
3. Maintain version tags for reproducibility
4. Document when to use pre-built vs custom builds

## Implementation Outline

### CI Workflow

**Create `.github/workflows/publish-images.yml`:**

```yaml
name: Publish Images

on:
  push:
    tags: ['v*']
  workflow_dispatch:

jobs:
  build-and-push:
    runs-on: ubuntu-latest
    permissions:
      contents: read
      packages: write
    strategy:
      matrix:
        image: [php, node, nginx, redis, postgres, mariadb]
    steps:
      - uses: actions/checkout@v4

      - name: Login to GHCR
        uses: docker/login-action@v3
        with:
          registry: ghcr.io
          username: ${{ github.actor }}
          password: ${{ secrets.GITHUB_TOKEN }}

      - name: Build and push
        uses: docker/build-push-action@v5
        with:
          context: .
          file: docker/${{ matrix.image }}/Dockerfile
          target: production
          push: true
          tags: |
            ghcr.io/${{ github.repository }}/${{ matrix.image }}:${{ github.ref_name }}
            ghcr.io/${{ github.repository }}/${{ matrix.image }}:latest
```

### Quick Start Option

```bash
# Option 1: Pre-built images (faster)
PREBUILT=true make setup

# Option 2: Local build (customizable)
make setup
```

## Notes

- Images should be versioned with git tags
- Consider image scanning before publish
- Document size differences between dev/prod images
- May need to handle secrets differently for public images
