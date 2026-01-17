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

### Single-Server Production (Docker Compose)

For single-server deployments, use Docker Compose with production overrides:

```bash
# Build production images
ENV=production make build

# Start in production mode
ENV=production make up
```

### Multi-Node Production (Kubernetes)

For multi-node or cloud deployments, use Kubernetes with Helm.

1. **Build and push images to registry:**

   ```bash
   ENV=production make build
   docker tag zappzarapp-php:latest registry.example.com/myapp/php:v1.0.0
   docker push registry.example.com/myapp/php:v1.0.0
   ```

2. **Deploy with Helm:**

   ```bash
   make k8s-deploy
   ```

See [KUBERNETES.md](./KUBERNETES.md) for complete Kubernetes documentation.

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

### Kubernetes Deployment

Deploy using the project's Helm chart:

```bash
# Deploy to Kubernetes
make k8s-deploy

# Check status
make k8s-status

# View logs
make k8s-logs

# Remove deployment
make k8s-remove
```

For production deployment with custom values:

```bash
helm upgrade --install zappzarapp ./kubernetes \
  --namespace zappzarapp \
  --create-namespace \
  -f kubernetes/values.production.yaml
```

See [KUBERNETES.md](./KUBERNETES.md) for complete documentation.

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

- Kubernetes secrets
- HashiCorp Vault
- AWS Secrets Manager
- Azure Key Vault

Example for Kubernetes:

```bash
# Create secret
kubectl create secret generic db-credentials \
  --from-literal=db_password=your-db-password \
  --namespace zappzarapp

# Reference in Helm values
# kubernetes/values.yaml
secrets:
  db_password:
    existingSecret: db-credentials
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

### Kubernetes (Helm)

```bash
# Automatic rollback to previous revision
helm rollback zappzarapp --namespace zappzarapp

# Or specify revision number
helm rollback zappzarapp 2 --namespace zappzarapp

# List release history
helm history zappzarapp --namespace zappzarapp
```

### Blue-Green Deployment (Kubernetes)

```bash
# Deploy new version to "green" namespace
helm upgrade --install zappzarapp-green ./kubernetes \
  --namespace zappzarapp-green --create-namespace

# Test green deployment
kubectl port-forward svc/nginx 8081:80 -n zappzarapp-green
curl http://localhost:8081/health

# Switch traffic (update Ingress or Service)
kubectl patch ingress ... -n zappzarapp

# Remove old "blue" deployment
helm uninstall zappzarapp-blue -n zappzarapp-blue
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
