# Deployment Guide

This guide covers the CI/CD pipelines and deployment strategies for the
zappzarapp boilerplate.

## CI/CD Overview

The project includes pre-configured pipelines for both major Git platforms:

| Platform           | Configuration              | Status           |
| ------------------ | -------------------------- | ---------------- |
| **GitLab CI**      | `.gitlab-ci.yml`           | Fully configured |
| **GitHub Actions** | `.github/workflows/ci.yml` | Fully configured |

Both pipelines provide identical functionality:

- Build all Docker images
- Run quality checks (PHPStan, PHP-CS-Fixer, PHPMD, Rector, ESLint, Prettier)
- Execute tests (PHPUnit, Vitest)
- Security audits (Composer, pnpm)
- Production build verification

## GitLab CI/CD

### Pipeline Stages

```text
build → test → quality → security → deploy
```

### Jobs Overview

| Stage        | Job                    | Description                                  |
| ------------ | ---------------------- | -------------------------------------------- |
| **build**    | `build:php`            | Build PHP container                          |
| **build**    | `build:node`           | Build Node container                         |
| **build**    | `build:nginx`          | Build Nginx container                        |
| **test**     | `php:unit-tests`       | Run PHPUnit tests                            |
| **test**     | `php:coverage`         | Generate PHP coverage (master/develop only)  |
| **test**     | `node:tests`           | Run Vitest tests                             |
| **test**     | `node:coverage`        | Generate Node coverage (master/develop only) |
| **quality**  | `php:coding-standards` | PHP-CS-Fixer check                           |
| **quality**  | `php:static-analysis`  | PHPStan Level 5                              |
| **quality**  | `php:mess-detector`    | PHPMD (allow_failure)                        |
| **quality**  | `php:rector-check`     | Rector dry-run (allow_failure)               |
| **quality**  | `node:lint`            | ESLint                                       |
| **quality**  | `node:format-check`    | Prettier check                               |
| **quality**  | `node:type-check`      | TypeScript type checking                     |
| **security** | `dependency-audit`     | Composer + pnpm audit                        |
| **deploy**   | `build:production`     | Test production build (master/develop only)  |

### Configuration

The pipeline uses Docker-in-Docker (DinD):

```yaml
# .gitlab-ci.yml
.docker-setup: &docker-setup
  image: docker:24-cli
  services:
    - docker:24-dind
```

### Triggers

- **Push** to `master` or `develop` branch
- **Merge requests** to `master` or `develop`

### Artifacts

| Artifact             | Retention | Branch          |
| -------------------- | --------- | --------------- |
| PHPUnit coverage     | 30 days   | master, develop |
| Vitest coverage      | 30 days   | master, develop |
| Code quality reports | Always    | All             |

### Security Scans

Additional security scans are defined in `.gitlab/security-scan.gitlab-ci.yml`:

- Trivy container scanning
- Secret detection
- Filesystem scanning
- Weekly schedule

## GitHub Actions

### Workflow Structure

```text
┌──────────────────┐  ┌──────────────────┐
│   php-quality    │  │   node-quality   │
└────────┬─────────┘  └────────┬─────────┘
         │                     │
         ▼                     ▼
┌──────────────────┐  ┌──────────────────┐
│    php-tests     │  │    node-tests    │
└────────┬─────────┘  └────────┬─────────┘
         │                     │
         └─────────┬───────────┘
                   ▼
         ┌──────────────────┐
         │ dependency-audit │
         └────────┬─────────┘
                  ▼
         ┌──────────────────┐
         │ build-production │
         └──────────────────┘
```

### Jobs Overview

| Job                | Description                                | Timeout |
| ------------------ | ------------------------------------------ | ------- |
| `php-quality`      | CS-Fixer, PHPStan, PHPMD, Rector, Validate | 15 min  |
| `php-tests`        | PHPUnit + Coverage (master only)           | 15 min  |
| `node-quality`     | TypeScript, ESLint, Prettier               | 15 min  |
| `node-tests`       | Vitest + Coverage (master only)            | 15 min  |
| `dependency-audit` | Composer + pnpm audit                      | 10 min  |
| `build-production` | Production build test                      | 20 min  |

### Triggers

```yaml
on:
  push:
    branches: [master, develop]
  pull_request:
    branches: [master, develop]
  workflow_dispatch: # Manual trigger
```

### Coverage Integration

Coverage reports are automatically uploaded to Codecov:

```yaml
- name: Upload coverage to Codecov
  uses: codecov/codecov-action@v4
  with:
    files: ./build/coverage/clover.xml
    flags: php
```

### Security Scans

Additional security scans in `.github/workflows/security-scan.yml`:

- Trivy container scanning
- Weekly schedule

## Local CI Simulation

Test the CI pipeline locally before pushing:

```bash
# Run all checks (same as CI)
make check

# Individual checks
make cs-check      # PHP coding standards
make analyse       # PHPStan
make phpmd         # PHPMD
make rector-check  # Rector
make test          # All tests

# Production build test
ENV=production make build
ENV=production docker compose -f compose.yaml -f compose.production.yaml up -d
curl http://localhost:8080/health
```

## Deployment Strategies

### Manual Deployment

1. **Build production images locally:**

   ```bash
   ENV=production make build
   ```

2. **Tag and push to registry:**

   ```bash
   docker tag zappzarapp-php:latest registry.example.com/myapp/php:v1.0.0
   docker push registry.example.com/myapp/php:v1.0.0
   ```

3. **Deploy on server:**

   ```bash
   docker pull registry.example.com/myapp/php:v1.0.0
   docker compose -f compose.yaml -f compose.production.yaml up -d
   ```

### Docker Registry Integration

#### GitLab Container Registry

```yaml
# Add to .gitlab-ci.yml
deploy:registry:
  stage: deploy
  script:
    - docker login -u $CI_REGISTRY_USER -p $CI_REGISTRY_PASSWORD $CI_REGISTRY
    - docker build -t $CI_REGISTRY_IMAGE/php:$CI_COMMIT_SHA -f
      docker/php/Dockerfile --target production .
    - docker push $CI_REGISTRY_IMAGE/php:$CI_COMMIT_SHA
  only:
    - master
```

#### GitHub Container Registry (ghcr.io)

```yaml
# Add to .github/workflows/ci.yml
- name: Login to GitHub Container Registry
  uses: docker/login-action@v3
  with:
    registry: ghcr.io
    username: ${{ github.actor }}
    password: ${{ secrets.GITHUB_TOKEN }}

- name: Build and push
  uses: docker/build-push-action@v5
  with:
    context: .
    file: docker/php/Dockerfile
    target: production
    push: true
    tags: ghcr.io/${{ github.repository }}/php:${{ github.sha }}
```

### Docker Swarm Deployment

```bash
# Initialize Swarm (on manager node)
docker swarm init

# Deploy stack
docker stack deploy -c compose.yaml -c compose.production.yaml myapp

# Scale services
docker service scale myapp_php=3

# Update service
docker service update --image registry.example.com/myapp/php:v1.0.1 myapp_php
```

### Kubernetes Deployment

Generate Kubernetes manifests from Docker Compose:

```bash
# Using Kompose
kompose convert -f compose.yaml -f compose.production.yaml

# Apply to cluster
kubectl apply -f .
```

Or use the production compose file with Kubernetes:

```yaml
# k8s/deployment.yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: php
spec:
  replicas: 3
  selector:
    matchLabels:
      app: php
  template:
    spec:
      containers:
        - name: php
          image: registry.example.com/myapp/php:latest
          resources:
            limits:
              memory: '512Mi'
              cpu: '500m'
          readinessProbe:
            httpGet:
              path: /health
              port: 9000
            initialDelaySeconds: 5
            periodSeconds: 10
```

## Environment Configuration

### Production Checklist

```bash
# .env (Production)
ENV=production
XDEBUG_MODE=off
LOG_LEVEL=warning
LOG_FORMAT=json
CORS_ORIGINS=https://your-domain.com
```

### Secrets Management

**Development:** File-based secrets in `./secrets/`

**Production options:**

- Docker Swarm secrets
- Kubernetes secrets
- HashiCorp Vault
- AWS Secrets Manager
- Azure Key Vault

Example for Docker Swarm:

```bash
# Create secret
echo "your-db-password" | docker secret create db_password -

# Reference in compose
secrets:
  db_password:
    external: true
```

## Health Checks

Both CI pipelines verify the production build with a health check:

```bash
curl -f http://localhost:8080/health || exit 1
```

Expected response:

```json
{
  "overall_status": "ok",
  "services": {
    "php-fpm": { "status": "ok" },
    "database": { "status": "ok" },
    "redis": { "status": "ok" }
  }
}
```

## Rollback Procedures

### Docker Compose

```bash
# Keep previous images tagged
docker tag zappzarapp-php:latest zappzarapp-php:previous

# Rollback
docker tag zappzarapp-php:previous zappzarapp-php:latest
docker compose up -d
```

### Docker Swarm

```bash
# Automatic rollback on failure
docker service update --rollback myapp_php

# Or specify previous image
docker service update --image registry.example.com/myapp/php:v1.0.0 myapp_php
```

### Blue-Green Deployment

```bash
# Deploy new version to "green" stack
docker stack deploy -c compose.green.yaml myapp-green

# Test green deployment
curl http://green.example.com/health

# Switch traffic (update load balancer/DNS)
# ...

# Remove old "blue" stack
docker stack rm myapp-blue
```

## Monitoring Deployments

### GitLab

- **Pipeline status:** Project → CI/CD → Pipelines
- **Job logs:** Click on any job
- **Artifacts:** Download from job details

### GitHub

- **Workflow runs:** Actions tab
- **Job logs:** Click on workflow run → job
- **Artifacts:** Download from workflow run summary

### Notifications

Configure notifications in your Git platform:

- Slack integration
- Email notifications
- Webhook triggers

## Troubleshooting CI/CD

### Common Issues

| Issue              | Solution                                       |
| ------------------ | ---------------------------------------------- |
| Docker build fails | Check Dockerfile syntax, verify base images    |
| Tests timeout      | Increase timeout, check for infinite loops     |
| Permission denied  | Check file permissions, Docker socket access   |
| Out of disk space  | Clean up old images, use `docker system prune` |

### Debug Mode

**GitLab:**

```yaml
variables:
  CI_DEBUG_TRACE: 'true'
```

**GitHub:**

```yaml
- name: Debug
  run: |
    docker compose logs
    docker ps -a
```

## Related Documentation

- [ARCHITECTURE.md](ARCHITECTURE.md) - System architecture
- [PERFORMANCE.md](PERFORMANCE.md) - Performance tuning for production
- [MONITORING.md](MONITORING.md) - External monitoring integration
- [security/SECRETS.md](security/SECRETS.md) - Secrets management
- [security/SSL-CERTIFICATES.md](security/SSL-CERTIFICATES.md) - SSL for
  production
