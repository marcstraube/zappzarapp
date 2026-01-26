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
- Execute tests (PHPUnit, Vitest, BATS)
- Static Analysis Security Testing (SAST with Semgrep)
- Dependency validation (Composer, package.json)
- Security audits (Composer, pnpm)
- Production build verification

**Note:** Both pipelines are synchronized to ensure consistent CI/CD experience
across platforms.

## GitLab CI/CD

### Pipeline Stages

```text
build → test → quality → security → deploy
```

### Jobs Overview

| Stage        | Job                     | Description                                  |
| ------------ | ----------------------- | -------------------------------------------- |
| **build**    | `build:php`             | Build PHP container                          |
| **build**    | `build:node`            | Build Node container                         |
| **build**    | `build:nginx`           | Build Nginx container                        |
| **test**     | `php:unit-tests`        | Run PHPUnit tests                            |
| **test**     | `php:coverage`          | Generate PHP coverage (master/develop only)  |
| **test**     | `node:tests`            | Run Vitest tests                             |
| **test**     | `node:coverage`         | Generate Node coverage (master/develop only) |
| **test**     | `bats:quick`            | BATS Makefile validation (dry-run)           |
| **test**     | `bats:integration`      | BATS integration tests (master/develop/MR)   |
| **quality**  | `php:coding-standards`  | PHP-CS-Fixer check                           |
| **quality**  | `php:static-analysis`   | PHPStan Level 5                              |
| **quality**  | `php:mess-detector`     | PHPMD (allow_failure)                        |
| **quality**  | `php:rector-check`      | Rector dry-run (allow_failure)               |
| **quality**  | `php:composer-validate` | Composer.json/lock validation                |
| **quality**  | `node:lint`             | ESLint                                       |
| **quality**  | `node:format-check`     | Prettier check                               |
| **quality**  | `node:type-check`       | TypeScript type checking                     |
| **quality**  | `node:markdownlint`     | Markdown linting                             |
| **quality**  | `node:package-validate` | Package.json validation                      |
| **security** | `sast:semgrep`          | Static analysis (Semgrep)                    |
| **security** | `dependency-audit`      | Composer + pnpm audit                        |
| **deploy**   | `build:production`      | Test production build (master/develop only)  |

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

**Main Pipeline (every push):**

- SAST Analysis with Semgrep (security-audit, secrets, PHP, TypeScript)
- Dependency audit (Composer, pnpm)

**Comprehensive Scans (weekly schedule):**

Additional security scans are defined in `.gitlab/security-scan.gitlab-ci.yml`:

- Trivy container scanning (all images)
- OWASP ZAP DAST scan (production configuration)
- Secret detection (Gitleaks)
- Filesystem scanning
- Configuration scanning
- Weekly schedule (Sunday 2 AM UTC)

See [SECURITY-SCANNING.md](../security/SECURITY-SCANNING.md) for details.

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
| `node-quality`     | TypeScript, ESLint, Prettier, Markdown     | 15 min  |
| `node-tests`       | Vitest + Coverage (master only)            | 15 min  |
| `dependency-audit` | Composer + pnpm audit                      | 10 min  |
| `sast-scan`        | Semgrep static analysis                    | 15 min  |
| `bats-quick`       | BATS Makefile validation (dry-run)         | 10 min  |
| `bats-integration` | BATS integration tests (PR/master/develop) | 30 min  |
| `build-production` | Production build test (master/develop)     | 20 min  |

### Triggers

```yaml
on:
  push:
    branches: [master, develop]
  pull_request:
    branches: [master, develop]
  workflow_dispatch: # Manual trigger
```

### Coverage Integration (Codecov)

Coverage reports are automatically uploaded to Codecov on pushes to `master`.

**Public repositories:** Works automatically without configuration.

**Private repositories:** Requires a Codecov token.

#### Codecov Setup for Private Repos

1. Create account at [codecov.io](https://codecov.io)
2. Add your repository
3. Copy the repository token
4. Add secret to your CI platform:

**GitHub:**

```text
Settings → Secrets and variables → Actions → New repository secret
Name: CODECOV_TOKEN
Value: <your-token>
```

**GitLab:**

```text
Settings → CI/CD → Variables → Add variable
Key: CODECOV_TOKEN
Value: <your-token>
Protected: Yes
Masked: Yes
```

The workflow is already configured to use the token if available:

```yaml
- name: Upload coverage to Codecov
  uses: codecov/codecov-action@v4
  with:
    token: ${{ secrets.CODECOV_TOKEN }}
    files: ./build/coverage/clover.xml
    flags: php
    fail_ci_if_error: false # CI passes even without token
```

### Security Scans

Additional security scans in `.github/workflows/security-scan.yml`:

- Trivy container scanning
- Weekly schedule

### Production Testing

The CI/CD pipelines use intelligent production testing that respects `.env`
configuration:

**GitHub Actions:**

```yaml
- name: Test production build
  run: make test-production
```

**GitLab CI:**

```yaml
script:
  - make test-production
```

**Available test targets:**

| Target                         | Description                          | Services                  |
| ------------------------------ | ------------------------------------ | ------------------------- |
| `make test-production`         | ENV-aware (respects .env ENABLE\_\*) | Core + activated services |
| `make test-production-minimal` | Core only                            | nginx + app + db          |
| `make test-production-full`    | All services (comprehensive)         | Everything                |

**Health checks included:**

- nginx HTTP endpoint
- Database connectivity (postgres/mariadb)
- Redis connectivity (if enabled)
- Application health endpoint

See [Production Testing](#production-testing-1) section below for details.

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

# Production build test (ENV-aware, respects .env)
ENV=production make build
make test-production

# Or manually test specific configuration
ENV=production docker compose -f compose.yaml -f compose.production.yaml up -d
curl http://localhost:8080/health
```

## Production Testing

The project includes three production test targets for different scenarios:

### make test-production (Smart, Recommended)

Tests production build with services activated based on `.env` configuration:

```bash
make test-production
```

**What it tests:**

- Always: nginx
- Conditional based on `.env`:
  - `ENABLE_PHP=true` → php
  - `ENABLE_NODE=true` → node/node-backend (based on NODE_MODE)
  - `DB_TYPE=postgres` → postgres
  - `ENABLE_REDIS=true` → redis
  - `ENABLE_ELASTICSEARCH=true` → elasticsearch
  - etc.

**Health checks:**

- nginx HTTP endpoint (`/health`)
- Database connectivity (`pg_isready` or mariadb ping)
- Redis connectivity (`redis-cli ping`)

**Use case:** Default for CI/CD, tests realistic production configuration

### make test-production-minimal (Fast)

Tests minimal core services only:

```bash
make test-production-minimal
```

**What it tests:**

- nginx
- php OR node (whichever is enabled)
- Database (postgres or mariadb based on DB_TYPE)

**Health checks:**

- nginx HTTP endpoint
- Database connectivity

**Use case:** Quick validation, CI for every commit/PR (~30 seconds)

### make test-production-full (Comprehensive)

Tests ALL available services regardless of ENABLE\_\* settings:

```bash
make test-production-full
```

**What it tests:**

- All core services (nginx, php, node, node-backend)
- All data services (postgres, mariadb, redis)
- All optional services (elasticsearch, meilisearch, mercure, rabbitmq,
  seaweedfs)

**Health checks:**

- All critical services
- Extended timeouts for heavy services

**Use case:** Pre-release validation, nightly builds (~3 minutes)

### CI/CD Integration

**Current implementation:**

| Platform | Strategy               | Target            |
| -------- | ---------------------- | ----------------- |
| GitHub   | Push to master/develop | `test-production` |
| GitLab   | Push to master/develop | `test-production` |

**Alternative strategies:**

```yaml
# GitHub: Conditional based on branch
- name: Test production
  run: |
    if [ "${{ github.ref }}" = "refs/heads/master" ]; then
      make test-production-full
    else
      make test-production
    fi

# GitLab: Separate jobs
test:production:default:
  script: make test-production
  only: [develop, merge_requests]

test:production:comprehensive:
  script: make test-production-full
  only: [master]
  when: manual
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
- [SECRETS.md](../security/SECRETS.md) - Secrets management
- [SSL-CERTIFICATES.md](../security/SSL-CERTIFICATES.md) - SSL for production
