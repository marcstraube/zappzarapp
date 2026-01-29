# Task 01: Upgrade Redis to Version 8.x

## Priority

HIGH - Pre-Release

## Estimated Effort

15-20 minutes

## Context

During the Infrastructure & Docker review, an inconsistency was identified in
Alpine Linux base image versions across Dockerfiles. All services use Alpine
3.23 except Redis, which uses Alpine 3.21.

**Investigation Result:** `redis:7.4-alpine3.23` does not exist on Docker Hub.
The latest available for Redis 7.4 is `alpine3.21`.

**Decision:** Since the boilerplate has no existing users yet, upgrading to
Redis 8.x is the preferred solution:

- Redis 8 uses a newer Alpine base (3.22+)
- Users start with the latest major version from day one
- No migration pain since there are no existing deployments
- Better long-term maintainability

## Current State

File: `docker/redis/Dockerfile`

```dockerfile
FROM redis:7.4-alpine3.21 AS base
```

Other Dockerfiles use Alpine 3.23:

- `php:8.4-fpm-alpine3.23`
- `node:24.12-alpine3.23`
- `nginx:1.27-alpine3.23`

## Target State

Upgrade to Redis 8.x using the ARG pattern (consistent with other Dockerfiles):

```dockerfile
ARG REDIS_VERSION=8.0
ARG ALPINE_VERSION=3.22

FROM redis:${REDIS_VERSION}-alpine${ALPINE_VERSION} AS base
```

Using ARG variables ensures:

- Consistent pattern across all Dockerfiles (PHP, Node, Nginx, MariaDB)
- Explicit version pinning for reproducible builds
- Easy version updates via single-line change
- Build-time override possible: `--build-arg REDIS_VERSION=8.1`

## Implementation Steps

### Step 1: Check Redis 8.0 with Alpine 3.22 Availability

```bash
# Verify image exists and check versions
docker pull redis:8.0-alpine3.22
docker run --rm redis:8.0-alpine3.22 cat /etc/alpine-release
docker run --rm redis:8.0-alpine3.22 redis-server --version
```

If `8.0-alpine3.22` doesn't exist, check available tags:

```bash
# List available redis 8.x alpine tags
curl -s "https://hub.docker.com/v2/repositories/library/redis/tags?page_size=100" \
  | jq -r '.results[].name' | grep "^8\." | grep alpine | head -10
```

### Step 2: Review Redis 8 Changes

Key changes in Redis 8.x:

- New hash field expiration commands (HEXPIRE, HPEXPIRE, etc.)
- Improved memory efficiency
- Enhanced cluster support
- No breaking changes for basic operations (GET, SET, HGET, etc.)

Full changelog: https://github.com/redis/redis/releases/tag/8.0.0

### Step 3: Update Dockerfile

Edit `docker/redis/Dockerfile`:

**Before:**

```dockerfile
FROM redis:7.4-alpine3.21 AS base
```

**After:**

```dockerfile
# Redis version configuration
ARG REDIS_VERSION=8.0
ARG ALPINE_VERSION=3.22

# =============================================================================
# Base Stage
# =============================================================================
FROM redis:${REDIS_VERSION}-alpine${ALPINE_VERSION} AS base
```

**Note:** Place ARG declarations at the top of the Dockerfile, before any FROM statements, consistent with other Dockerfiles in the project.

### Step 4: Update Any Version References

Check for hardcoded version references:

```bash
grep -r "redis.*7\." --include="*.md" --include="*.yaml" --include="*.yml" .
```

Update documentation if needed (e.g., MONITORING.md, compose references).

### Step 5: Rebuild and Test

```bash
# Rebuild Redis image
make build-redis

# Restart services
make down && make up

# Verify Redis is healthy
docker inspect zappzarapp-redis --format='{{.State.Health.Status}}'

# Test Redis connectivity
docker compose exec redis redis-cli PING
# Expected: PONG

# Check version
docker compose exec redis redis-server --version
# Expected: Redis server v=8.x.x
```

### Step 6: Run Tests

```bash
# Run Redis container tests
make test-goss-redis

# Run full test suite to catch any compatibility issues
make test
```

## Verification

1. **Redis version:**

   ```bash
   docker compose exec redis redis-server --version
   # Should show: Redis server v=8.0.x
   ```

2. **Alpine version:**

   ```bash
   docker compose exec redis cat /etc/alpine-release
   # Should show: 3.22.x
   ```

3. **ARG pattern in Dockerfile:**

   ```bash
   head -5 docker/redis/Dockerfile | grep -E "^ARG (REDIS|ALPINE)_VERSION"
   # Should show both ARG lines
   ```

3. **Health check:**

   ```bash
   docker inspect zappzarapp-redis --format='{{.State.Health.Status}}'
   # Should output: healthy
   ```

4. **Basic operations:**

   ```bash
   docker compose exec redis redis-cli SET test "hello"
   docker compose exec redis redis-cli GET test
   # Should return: hello
   ```

## Rollback

If critical issues occur:

```bash
git checkout docker/redis/Dockerfile
make build-redis
make down && make up
```

## Related Files

- `docker/redis/Dockerfile` - Primary file to modify
- `tests/goss/services/redis.yaml` - Container tests (may need version update)
- `compose.yaml` - Service definition (no changes needed)
- `.zappzarapp/docs/` - Documentation (check for version references)

## Notes

- Redis 8 is backward-compatible for standard operations
- TLS configuration remains unchanged
- Persistence (RDB/AOF) format is compatible
- If the application uses Redis-specific features, test those explicitly
- Consider adding a note in CHANGELOG about Redis 8 for the 1.0 release
- ARG pattern allows build-time version override without editing Dockerfile:
  ```bash
  docker build --build-arg REDIS_VERSION=8.1 -t custom-redis ./docker/redis
  ```

