# Kubernetes Deployment Guide

This guide covers deploying the zappzarapp application stack to Kubernetes using
Helm.

## Prerequisites

Install the following tools:

```bash
# kubectl
curl -LO "https://dl.k8s.io/release/$(curl -L -s https://dl.k8s.io/release/stable.txt)/bin/linux/amd64/kubectl"
chmod +x kubectl && sudo mv kubectl /usr/local/bin/

# Helm
curl https://raw.githubusercontent.com/helm/helm/main/scripts/get-helm-3 | bash

# Optional: minikube for local testing
curl -LO https://storage.googleapis.com/minikube/releases/latest/minikube-linux-amd64
chmod +x minikube-linux-amd64 && sudo mv minikube-linux-amd64 /usr/local/bin/minikube

# Optional: k9s for terminal UI
# https://k9scli.io/topics/install/
```

## Quick Start

### Local Development (minikube)

```bash
# Start minikube
minikube start

# Build and load images
eval $(minikube docker-env)
make build

# Deploy
make k8s-deploy

# Access
kubectl port-forward svc/zappzarapp-nginx 8080:80 -n zappzarapp
```

### Production Deployment

```bash
# Build and push images to registry
ENV=production make build
docker tag zappzarapp-php:latest registry.example.com/myapp/php:v1.0.0
docker push registry.example.com/myapp/php:v1.0.0
# Repeat for nginx, node, etc.

# Create secrets
kubectl create namespace zappzarapp
kubectl create secret generic db-credentials \
  --from-literal=db_password=$(openssl rand -base64 24) \
  --namespace zappzarapp

# Deploy with production values
helm upgrade --install zappzarapp ./kubernetes \
  --namespace zappzarapp \
  -f kubernetes/values.production.yaml \
  --set global.imageRegistry=registry.example.com/myapp \
  --set global.domain=your-domain.com
```

## Make Targets

| Command           | Description                             |
| ----------------- | --------------------------------------- |
| `make k8s-deploy` | Deploy to Kubernetes using Helm         |
| `make k8s-remove` | Remove deployment from Kubernetes       |
| `make k8s-status` | Show deployment status (pods, services) |
| `make k8s-logs`   | View logs: `make k8s-logs <pod-name>`   |

## Helm Chart Structure

```text
kubernetes/
├── Chart.yaml              # Chart metadata
├── values.yaml             # Default values (development)
├── values.production.yaml  # Production overrides
└── templates/
    ├── _helpers.tpl        # Template helpers
    ├── namespace.yaml      # Namespace definition
    ├── configmap.yaml      # Environment config
    ├── secrets.yaml        # Secret templates
    ├── NOTES.txt           # Post-install notes
    ├── nginx/              # Nginx deployment, service, ingress
    ├── php/                # PHP-FPM deployment, service
    ├── node/               # Node.js Frontend (Nuxt/Next.js)
    ├── node-backend/       # Node.js Backend (Express API)
    ├── postgres/           # PostgreSQL statefulset, service
    ├── mariadb/            # MariaDB statefulset, service
    ├── redis/              # Redis deployment, service, pvc
    ├── mercure/            # Mercure deployment, service
    ├── meilisearch/        # Meilisearch statefulset, service
    ├── elasticsearch/      # Elasticsearch statefulset, service
    ├── seaweedfs/          # SeaweedFS statefulset, service
    ├── rabbitmq/           # RabbitMQ statefulset, service
    ├── mailpit/            # Mailpit deployment, service (dev only)
    └── security/           # NetworkPolicies, ServiceAccounts
```

## Configuration

### Service Toggles

Enable/disable services in `values.yaml`:

```yaml
php:
  enabled: true
# Node Frontend (Nuxt/Next.js) - for framework/framework-api modes
node:
  enabled: false # Enable for framework modes
# Node Backend (Express API) - for api/assets-api/framework-api modes
nodeBackend:
  enabled: true # Enable for API modes
redis:
  enabled: true
postgres:
  enabled: true
mariadb:
  enabled: false
```

### Node.js Multi-Container Architecture

The chart supports a dual-container architecture for Node.js:

| Service        | Port | Use Case                            |
| -------------- | ---- | ----------------------------------- |
| `node`         | 3001 | Frontend frameworks (Nuxt, Next.js) |
| `node-backend` | 3000 | Express API backend                 |

Enable services based on your NODE_MODE:

| NODE_MODE     | node.enabled | nodeBackend.enabled |
| ------------- | ------------ | ------------------- |
| assets        | false        | false               |
| api           | false        | true                |
| assets-api    | false        | true                |
| framework     | true         | false               |
| framework-api | true         | true                |

### Resource Limits

```yaml
php:
  resources:
    limits:
      cpu: '2'
      memory: 2Gi
    requests:
      cpu: '1'
      memory: 1Gi
```

### Persistence

```yaml
postgres:
  persistence:
    enabled: true
    size: 50Gi
    storageClass: '' # Use default storage class
```

### Ingress

```yaml
ingress:
  enabled: true
  className: nginx
  annotations:
    cert-manager.io/cluster-issuer: letsencrypt-prod
  hosts:
    - host: example.com
      paths:
        - path: /
          pathType: Prefix
  tls:
    - secretName: zappzarapp-tls
      hosts:
        - example.com
```

## Secrets Management

### Development

For development, secrets are auto-generated with placeholder values:

```bash
make k8s-deploy  # Creates secrets with default values
```

### Production

Create secrets before deployment:

```bash
# Create namespace
kubectl create namespace zappzarapp

# Database password
kubectl create secret generic my-db-password \
  --from-literal=db_password=$(openssl rand -base64 24) \
  --namespace zappzarapp

# Encryption keys
kubectl create secret generic my-encryption-key \
  --from-literal=encryption_key=$(openssl rand -base64 32) \
  --namespace zappzarapp

# Reference in values
helm upgrade --install zappzarapp ./kubernetes \
  --set secrets.existingSecrets.dbPassword=my-db-password \
  --set secrets.existingSecrets.encryptionKey=my-encryption-key
```

## Security Features

### Network Policies

In production, NetworkPolicies restrict traffic:

- Nginx: Accepts traffic from ingress, talks to PHP/Node
- PHP/Node: Accepts from Nginx, talks to Database/Redis
- Database/Redis: Only accepts from PHP/Node

### Security Contexts

All containers run with:

- `readOnlyRootFilesystem: true` (where possible)
- Dropped capabilities (`ALL`)
- Minimal required capabilities added explicitly

## Monitoring

### Check Pod Status

```bash
kubectl get pods -n zappzarapp
kubectl describe pod <pod-name> -n zappzarapp
```

### View Logs

```bash
kubectl logs -f deployment/zappzarapp-php -n zappzarapp
kubectl logs -f deployment/zappzarapp-nginx -n zappzarapp
```

### Helm Operations

```bash
# List releases
helm list -n zappzarapp

# Release history
helm history zappzarapp -n zappzarapp

# Rollback
helm rollback zappzarapp 1 -n zappzarapp
```

## Troubleshooting

### Pod Not Starting

```bash
kubectl describe pod <pod-name> -n zappzarapp
kubectl logs <pod-name> -n zappzarapp --previous
```

### Service Not Accessible

```bash
kubectl get svc -n zappzarapp
kubectl get endpoints -n zappzarapp
```

### Secret Issues

```bash
kubectl get secrets -n zappzarapp
kubectl describe secret <secret-name> -n zappzarapp
```

### Network Policy Issues

```bash
kubectl get networkpolicies -n zappzarapp
kubectl describe networkpolicy <name> -n zappzarapp
```

## Related Documentation

- [DEPLOYMENT.md](./DEPLOYMENT.md) - General deployment strategies
- [security/SECRETS.md](../security/SECRETS.md) - Secrets management
- [ARCHITECTURE.md](./ARCHITECTURE.md) - System architecture
