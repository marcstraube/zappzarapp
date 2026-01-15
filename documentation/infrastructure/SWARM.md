# Docker Swarm Deployment

Docker Swarm is **required for production deployments**. This guide covers setup and deployment.

## Why Swarm is Required

Docker Compose standalone ignores `uid`, `gid`, and `mode` options for secrets. Docker Swarm respects these options, ensuring secrets are mounted with correct ownership and permissions.

**Without Swarm**, database services (postgres, mariadb) cannot read secrets because:
- Secrets are mounted as root:root with mode 0444
- Database processes run as non-root users (postgres:70, mysql:999)
- DAC_OVERRIDE capability would be needed (security risk)

**With Swarm**, secrets are mounted with correct ownership:
- postgres secrets: uid=70, gid=70, mode=0400
- mariadb secrets: uid=999, gid=999, mode=0400
- No DAC_OVERRIDE capability needed

Benefits:
- **Minimal capabilities**: No DAC_OVERRIDE for databases
- **Proper secrets permissions**: Each service gets secrets owned by the correct user
- **Production-ready**: Built-in service orchestration, rolling updates, health monitoring
- **Single-node compatible**: Works locally for testing without a cluster

## Quick Start

### Single-Node (Local Testing or Production)

```bash
# Initialize Swarm
make swarm-init

# Deploy the stack
make swarm-deploy

# Check status
make swarm-status

# View logs
make swarm-logs php

# Remove stack
make swarm-remove

# Leave Swarm mode
make swarm-leave
```

### Multi-Node Cluster

```bash
# On Manager server: Initialize with public IP
make swarm-init ADDR=192.168.1.10
# Note the join command shown in output

# On Worker server(s): Join the cluster
make swarm-join TOKEN=SWMTKN-1-xxx MANAGER=192.168.1.10:2377

# Deploy from local machine (with project files)
# Set DOCKER_HOST in .env or export it:
DOCKER_HOST=ssh://user@192.168.1.10 make swarm-deploy
```

## Architecture

### Compose Files

The deployment uses two compose files:

1. **compose.yaml** - Base configuration (services, networks, volumes, secrets definitions)
2. **compose.production.yaml** - Production hardening (capabilities, read-only, resource limits, secrets with uid/gid/mode)

```bash
docker stack deploy -c compose.yaml -c compose.production.yaml zappzarapp
```

### Secrets Permissions

The `compose.production.yaml` file specifies ownership for each secret:

| Service   | User     | UID   | Secrets                                     |
|-----------|----------|-------|---------------------------------------------|
| nginx     | nginx    | 101   | ssl_cert (0444), ssl_key (0400)             |
| php       | www-data | 82    | db_password, encryption_key (0400)          |
| node      | node     | 50000 | db_password, encryption_key (0400)          |
| redis     | redis    | 999   | ssl_cert (0444), ssl_key (0400)             |
| postgres  | postgres | 70    | db_password, ssl_cert, ssl_key (0400)       |
| mariadb   | mysql    | 999   | db_password, db_root_password, ssl_* (0400) |

## Prerequisites

### 1. Initialize Swarm

```bash
# Single-node (local testing or production)
make swarm-init

# Multi-node: Initialize manager with public IP
make swarm-init ADDR=192.168.1.10
# Output shows join command for workers

# Multi-node: Join as worker
make swarm-join TOKEN=SWMTKN-1-xxx MANAGER=192.168.1.10:2377
```

### 2. Prepare Environment

```bash
# Ensure .env exists
make init

# Set ENV=production in .env
sed -i 's/ENV=development/ENV=production/' .env

# Generate secrets
make secrets

# Build production images
make build
```

### 3. Push Images (Multi-Node Only)

For multi-node clusters, images must be available on all nodes:

```bash
# Tag and push to registry
docker tag zappzarapp-php:latest registry.example.com/zappzarapp-php:latest
docker push registry.example.com/zappzarapp-php:latest
# Repeat for all images
```

## Deployment

### Deploy Stack

```bash
make swarm-deploy
```

This runs:
```bash
docker stack deploy -c compose.yaml -c compose.production.yaml zappzarapp
```

### Verify Deployment

```bash
# List services
docker stack services zappzarapp

# List tasks (containers)
docker stack ps zappzarapp

# Check service logs
docker service logs zappzarapp_php
```

### Update Services

```bash
# Rebuild and update
make build
docker service update --image zappzarapp-php:latest zappzarapp_php

# Or redeploy entire stack
make swarm-deploy
```

### Remove Stack

```bash
make swarm-remove
```

## Makefile Commands

| Command | Description |
|---------|-------------|
| `make swarm-init` | Initialize Swarm (single-node, 127.0.0.1) |
| `make swarm-init ADDR=<ip>` | Initialize Swarm as multi-node manager |
| `make swarm-join TOKEN=<t> MANAGER=<ip:port>` | Join existing cluster as worker |
| `make swarm-leave` | Leave Swarm mode |
| `make swarm-deploy` | Deploy stack (supports DOCKER_HOST) |
| `make swarm-remove` | Remove deployed stack (supports DOCKER_HOST) |
| `make swarm-status` | Show services and tasks (supports DOCKER_HOST) |
| `make swarm-logs [service]` | View logs (supports DOCKER_HOST) |

## Remote Deployment

For deploying to a remote Swarm manager, set `DOCKER_HOST` in your `.env` file or export it:

### Via .env

```bash
# .env
DOCKER_HOST=ssh://user@manager-server
```

Then all swarm commands automatically target the remote host:

```bash
make swarm-deploy   # Deploys to remote
make swarm-status   # Shows remote status
make swarm-logs php # Streams remote logs
make swarm-remove   # Removes from remote
```

### Via Environment Variable

```bash
DOCKER_HOST=ssh://user@192.168.1.10 make swarm-deploy
```

### CI/CD Integration

In CI/CD pipelines, inject `DOCKER_HOST` from secrets:

```yaml
# GitLab CI
deploy:
  script:
    - export DOCKER_HOST=$SWARM_MANAGER_HOST
    - make swarm-deploy

# GitHub Actions
- name: Deploy
  env:
    DOCKER_HOST: ${{ secrets.SWARM_MANAGER_HOST }}
  run: make swarm-deploy
```

## Comparison: Compose vs Swarm

| Feature                    | Compose (Standalone) | Docker Swarm           |
|----------------------------|----------------------|------------------------|
| Secrets uid/gid/mode       | Ignored (warnings)   | Respected              |
| Capability requirements    | Higher (DAC_OVERRIDE)| Lower (proper perms)   |
| Rolling updates            | Manual               | Built-in               |
| Health-based routing       | No                   | Yes                    |
| Multi-node                 | No                   | Yes                    |
| Resource constraints       | deploy.resources     | deploy.resources       |
| Local development          | Ideal                | Possible but heavier   |

## Troubleshooting

### Service Won't Start

```bash
# Check task status
docker stack ps zappzarapp --no-trunc

# Check service logs
docker service logs zappzarapp_php 2>&1 | tail -50
```

### Secrets Permission Denied

If a service can't read secrets, verify the uid/gid in compose.production.yaml matches the container user:

```bash
# Check container user ID
docker run --rm zappzarapp-php id
```

### Network Issues

Swarm creates overlay networks by default. Ensure all nodes can communicate:

```bash
# Check network
docker network ls
docker network inspect zappzarapp_backend
```

### Leave Swarm After Testing

```bash
make swarm-leave
# or
docker swarm leave --force
```

## Security Considerations

1. **Secrets are encrypted at rest** in Swarm's Raft log
2. **Secrets are only sent to nodes** that need them
3. **Secrets are mounted in memory** (tmpfs), not on disk
4. **Use external secrets** for production (HashiCorp Vault, AWS Secrets Manager)

## External Secrets (Production)

For production, use external secrets instead of file-based secrets. A template is provided:

```bash
# 1. Create secrets in Swarm
echo "db_password" | docker secret create zappzarapp_db_password -
echo "encryption_key" | docker secret create zappzarapp_encryption_key -
# ... repeat for all required secrets

# 2. Copy and adjust the template
cp compose.production-external.yaml.example compose.production-external.yaml

# 3. Deploy (automatically uses external secrets)
make swarm-deploy
```

The `swarm-deploy` command automatically detects `compose.production-external.yaml` and includes it.

### Available Secrets

| Secret                              | Required | Used by                         |
|-------------------------------------|----------|---------------------------------|
| `zappzarapp_ssl_cert`               | Yes      | nginx, redis, postgres, mariadb |
| `zappzarapp_ssl_key`                | Yes      | nginx, redis, postgres, mariadb |
| `zappzarapp_db_password`            | Yes      | php, node, postgres/mariadb     |
| `zappzarapp_db_root_password`       | MariaDB  | mariadb                         |
| `zappzarapp_encryption_key`         | Yes      | php, node                       |
| `zappzarapp_backup_encryption_key`  | Yes      | php                             |
| `zappzarapp_meilisearch_master_key` | Optional | meilisearch                     |
| `zappzarapp_minio_root_*`           | Optional | minio                           |
| `zappzarapp_rabbitmq_*`             | Optional | rabbitmq                        |
| `zappzarapp_mercure_jwt_secret`     | Optional | mercure                         |

### External Providers

For integration with HashiCorp Vault, AWS Secrets Manager, or other providers, Docker supports secret
store plugins. This requires additional setup beyond the scope of this guide.

See:

- [Docker Secrets](https://docs.docker.com/engine/swarm/secrets/)
- [HashiCorp Vault](https://developer.hashicorp.com/vault/docs)
