# Internal TLS (Zero-Trust Networking)

This document describes the internal TLS implementation for service-to-service
communication within the Docker network.

## Overview

All internal service communication uses TLS encryption to implement zero-trust
networking principles. This ensures that even if an attacker gains access to the
Docker network, they cannot intercept or modify traffic between services.

## Architecture

```text
┌─────────────────────────────────────────────────────────────────┐
│                        External Clients                          │
└─────────────────────────────────────────────────────────────────┘
                              │
                         HTTPS:8443
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                          nginx                                   │
│                    (TLS Termination)                             │
└─────────────────────────────────────────────────────────────────┘
         │                    │                    │
    HTTPS:3000           HTTPS:3001          Unix Socket
         ▼                    ▼                    ▼
┌─────────────┐      ┌─────────────┐      ┌─────────────┐
│ node-backend│      │    node     │      │     php     │
│  (Express)  │      │  (Nuxt/Next)│      │  (PHP-FPM)  │
└─────────────┘      └─────────────┘      └─────────────┘
         │                    │                    │
    HTTPS:7700           HTTPS:443          HTTPS:9200
         ▼                    ▼                    ▼
┌─────────────┐      ┌─────────────┐      ┌─────────────┐
│ meilisearch │      │   mercure   │      │elasticsearch│
└─────────────┘      └─────────────┘      └─────────────┘
```

## TLS Configuration by Service

| Service          | Port | Protocol | Certificate Path                         |
| ---------------- | ---- | -------- | ---------------------------------------- |
| nginx (external) | 8443 | HTTPS    | `/etc/nginx/certs/`                      |
| node-backend     | 3000 | HTTPS    | `/etc/ssl/certs/`, `/etc/ssl/private/`   |
| node (frontend)  | 3001 | HTTPS    | `/etc/ssl/certs/`, `/etc/ssl/private/`   |
| mercure          | 443  | HTTPS    | `/etc/mercure/certs/`                    |
| meilisearch      | 7700 | HTTPS    | `/etc/ssl/certs/`, `/etc/ssl/private/`   |
| elasticsearch    | 9200 | HTTPS    | `/usr/share/elasticsearch/config/certs/` |
| redis            | 6379 | TLS      | `/etc/redis/certs/`                      |
| postgres         | 5432 | TLS      | `/var/lib/postgresql/certs/`             |
| mariadb          | 3306 | TLS      | `/etc/mysql/certs/`                      |

## Certificate Management

### Shared Certificate Model

All services use the same certificate generated in `docker/certs/`:

```bash
# Generate self-signed certificate for development
make ssl-selfsigned

# Files created:
# - docker/certs/cert.crt  (public certificate)
# - docker/certs/cert.key  (private key)
```

### Subject Alternative Names (SANs)

The certificate includes all internal service hostnames:

- `localhost`
- `nginx`, `php`, `node`, `node-backend`
- `redis`, `postgres`, `mariadb`
- `meilisearch`, `elasticsearch`, `mercure`
- `rabbitmq`, `seaweedfs`, `mailpit`
- Domain name from `.env` (e.g., `zappzarapp.localhost`)

### Production Certificates

For production, replace the self-signed certificates with Let's Encrypt or other
CA-signed certificates. The certificate paths remain the same.

## TLS Verification

Certificate verification is automatically configured based on the environment:

| Environment | Verification | Reason                   |
| ----------- | ------------ | ------------------------ |
| Development | Disabled     | Self-signed certificates |
| Production  | Enabled      | CA-signed certificates   |

### Node.js

```typescript
// Auto-detected from NODE_ENV
const tlsRejectUnauthorized = NODE_ENV === 'production';
process.env.NODE_TLS_REJECT_UNAUTHORIZED = tlsRejectUnauthorized ? '1' : '0';
```

### nginx

```nginx
# Self-signed in development, Let's Encrypt in production
proxy_ssl_verify off;  # Development
proxy_ssl_verify on;   # Production (configure separately)
```

### PHP

```php
$isProduction = getenv('ENV') === 'production';
$context = [
    'ssl' => [
        'verify_peer' => $isProduction,
        'verify_peer_name' => $isProduction,
        'allow_self_signed' => !$isProduction,
    ],
];
```

## Testing TLS Connections

### Verify TLS is Active

```bash
# Test node-backend TLS
docker exec node-backend openssl s_client -connect localhost:3000 -brief

# Test nginx to node-backend
docker exec nginx openssl s_client -connect node-backend:3000 -brief

# Test meilisearch TLS
docker exec meilisearch wget -qO- --no-check-certificate https://localhost:7700/health
```

### Runtime Integration Tests

```bash
# Run all TLS-enabled tests
./tests/goss/runtime-tests.sh all

# Test specific service
./tests/goss/runtime-tests.sh node-backend
./tests/goss/runtime-tests.sh meilisearch
```

## Troubleshooting

### Certificate Not Found

```text
TLS certificates not found. Run "make ssl-selfsigned" to generate them.
```

**Solution:** Generate certificates with `make ssl-selfsigned`.

### Connection Refused

```text
curl: (7) Failed to connect to node-backend port 3000: Connection refused
```

**Possible causes:**

- Service not running: `docker compose ps`
- Wrong port: Check service is listening on expected port
- Firewall: Check Docker network configuration

### Certificate Verification Failed

```text
SSL certificate problem: self-signed certificate
```

**Solution:** Use `--insecure` flag for curl or `--no-check-certificate` for
wget in development. In production, ensure proper CA-signed certificates are
installed.

### Service-Specific Issues

**Elasticsearch:**

```text
xpack.security.http.ssl.enabled requires xpack.security.enabled
```

Elasticsearch requires both security and SSL to be enabled together.

**Mercure (Caddy):**

```text
tls: failed to get certificate
```

Ensure certificate paths in `CADDY_SERVER_EXTRA_DIRECTIVES` are correct.

## Security Considerations

1. **No mTLS:** This implementation uses server-side TLS only. Mutual TLS (mTLS)
   would require client certificates, adding complexity without significant
   benefit for internal traffic.

2. **Network Isolation:** TLS protects against network-level attacks but does
   not replace proper network segmentation. Services should still be on
   appropriate Docker networks.

3. **Certificate Rotation:** Certificates should be rotated regularly. For Let's
   Encrypt, this happens automatically. For self-signed development
   certificates, regenerate periodically.

4. **Secret Management:** Certificate private keys should be protected:
   - File permissions: `chmod 600 cert.key`
   - Not committed to version control
   - In production, use Docker Secrets or external secret management

## Related Documentation

- [SSL Certificates](./SSL-CERTIFICATES.md) - External TLS configuration
- [Secrets Management](./SECRETS.md) - Docker Secrets for sensitive data
- [Encryption](./ENCRYPTION.md) - Data encryption at rest
