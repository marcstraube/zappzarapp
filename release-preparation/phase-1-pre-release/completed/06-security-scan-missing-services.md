# Task 05: Add Missing Services to CI Security Scan

## Priority

HIGH - Pre-Release (Security Coverage)

## Estimated Effort

15-20 minutes

## Context

The CI security scan (`security-scan.yml`) only covers 6 of 12 runtime services.
Optional services with custom Dockerfiles are not scanned for vulnerabilities.

**Discovery:** Manual review of CI pipeline revealed incomplete coverage.

## Current State

### Scanned Services (6)

| Service  | Dockerfile                  | Target      |
| -------- | --------------------------- | ----------- |
| php      | docker/php/Dockerfile       | development |
| node     | docker/node/Dockerfile      | development |
| nginx    | docker/nginx/Dockerfile     | development |
| postgres | docker/postgres/Dockerfile  | final       |
| mariadb  | docker/mariadb/Dockerfile   | (none)      |
| redis    | docker/redis/Dockerfile     | base        |

### Missing Services (6)

| Service       | Dockerfile                       | Profile  |
| ------------- | -------------------------------- | -------- |
| elasticsearch | docker/elasticsearch/Dockerfile  | search   |
| meilisearch   | docker/meilisearch/Dockerfile    | search   |
| rabbitmq      | docker/rabbitmq/Dockerfile       | (none)   |
| mercure       | docker/mercure/Dockerfile        | (none)   |
| mailpit       | docker/mailpit/Dockerfile        | (none)   |
| seaweedfs     | docker/seaweedfs/Dockerfile      | storage  |

### Not Applicable (2)

- `goss` - Test utility, not a runtime service
- `bats` - Test utility, not a runtime service

## Target State

All 12 runtime services included in the security scan matrix.

## Implementation Steps

### Step 1: Add Missing Services to Matrix

Edit `.github/workflows/security-scan.yml`:

```yaml
strategy:
  fail-fast: false
  matrix:
    image:
      # Core services (existing)
      - name: php
        dockerfile: docker/php/Dockerfile
        target: development
      - name: node
        dockerfile: docker/node/Dockerfile
        target: development
      - name: nginx
        dockerfile: docker/nginx/Dockerfile
        target: development
      - name: postgres
        dockerfile: docker/postgres/Dockerfile
        target: final
      - name: mariadb
        dockerfile: docker/mariadb/Dockerfile
        target: ""
      - name: redis
        dockerfile: docker/redis/Dockerfile
        target: base
      # Optional services (add these)
      - name: elasticsearch
        dockerfile: docker/elasticsearch/Dockerfile
        target: base
      - name: meilisearch
        dockerfile: docker/meilisearch/Dockerfile
        target: ""
      - name: rabbitmq
        dockerfile: docker/rabbitmq/Dockerfile
        target: ""
      - name: mercure
        dockerfile: docker/mercure/Dockerfile
        target: ""
      - name: mailpit
        dockerfile: docker/mailpit/Dockerfile
        target: ""
      - name: seaweedfs
        dockerfile: docker/seaweedfs/Dockerfile
        target: ""
```

### Step 2: Verify Target Stages

Check each Dockerfile for available stages:

```bash
for df in docker/*/Dockerfile; do
  echo "=== $df ==="
  grep -E "^FROM .* AS " "$df" || echo "(no named stages)"
done
```

### Step 3: Test Workflow Locally (Optional)

```bash
# Test building each image
for service in elasticsearch meilisearch rabbitmq mercure mailpit seaweedfs; do
  echo "Building $service..."
  DOCKER_BUILDKIT=0 docker build -t test-$service -f docker/$service/Dockerfile . || echo "FAILED: $service"
done
```

### Step 4: Commit and Verify

```bash
git add .github/workflows/security-scan.yml
git commit -m "ci(security): add missing optional services to vulnerability scan"
```

After push, verify the workflow runs successfully in GitHub Actions.

## Verification

1. **All services in matrix:**

   ```bash
   grep -A 50 "matrix:" .github/workflows/security-scan.yml | grep "name:" | wc -l
   # Should show: 12
   ```

2. **Workflow runs without errors:**

   - Check GitHub Actions for successful completion
   - All 12 image scans should appear in the job list

3. **SARIF results uploaded:**

   - Check Security tab in GitHub for all 12 service scan results

## Files to Modify

| File                                 | Change                         |
| ------------------------------------ | ------------------------------ |
| `.github/workflows/security-scan.yml` | Add 6 services to scan matrix |

## Notes

- Optional services may have different vulnerability profiles than core services
- RabbitMQ and Elasticsearch are enterprise-grade and may have more dependencies
- Consider grouping scans (core vs optional) if workflow time becomes an issue
- Mailpit is dev-only but still worth scanning for supply chain security
