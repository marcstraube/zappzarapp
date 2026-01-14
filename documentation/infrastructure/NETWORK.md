# Network Security Architecture

This boilerplate implements a **3-network segmentation** strategy for enhanced
security and GDPR compliance, especially important for applications handling
10,000+ user records.

## Architecture Overview

```text
┌─────────────────────────────────────────────────────────────┐
│                      FRONTEND NETWORK                       │
│  - nginx (public-facing, ports 8080/8443)                   │
└────────────────────────────┬────────────────────────────────┘
                             │
┌────────────────────────────▼────────────────────────────────┐
│                      BACKEND NETWORK                        │
│  - nginx (internal connection)                              │
│  - php                                                      │
│  - node                                                     │
└────────────────────────────┬────────────────────────────────┘
                             │
┌────────────────────────────▼────────────────────────────────┐
│                      DATABASE NETWORK                       │
│  - postgres                                                 │
│  - mariadb                                                  │
│  - redis                                                    │
│  - php (gateway access)                                     │
│  - node (gateway access)                                    │
└─────────────────────────────────────────────────────────────┘
```

## Network Definitions

### 1. Frontend Network

- **Purpose**: Public-facing edge
- **Services**: nginx only
- **Access**: Exposed to the internet via ports 8080 (HTTP) and 8443 (HTTPS)
- **Security**:
  - Only nginx can accept external traffic
  - No direct access to databases or application servers

### 2. Backend Network

- **Purpose**: Application layer
- **Services**: nginx, php, node
- **Access**: Internal communication only
- **Security**:
  - Application services cannot be accessed directly from outside
  - nginx acts as reverse proxy/gateway

### 3. Database Network

- **Purpose**: Data persistence layer
- **Services**: postgres, mariadb, redis, php, node
- **Access**: Internal communication only
- **Security**:
  - Databases are completely isolated from frontend
  - Only php and node services can access databases
  - No direct nginx → database connections possible

## Security Benefits

### Defense in Depth

Even if one layer is compromised, attackers cannot easily pivot to other layers:

- Compromised nginx ≠ database access
- Compromised database ≠ internet exposure
- Each layer acts as additional security boundary

### Compliance (GDPR, DPIA)

For applications processing personal data of 10,000+ users:

- **Art. 32 GDPR**: "State of the art" technical measures
- **DPIA Requirement**: Microsegmentation reduces risk ratings
- **Audit Readiness**: Clear network boundaries simplify security audits

### Attack Surface Reduction

- Databases not reachable from public-facing services
- Limited lateral movement potential
- Principle of Least Privilege enforced at network level

## Communication Paths

### Allowed Connections

```text
Internet → nginx:8080/8443 (frontend network)
nginx → php (backend network, via Unix socket /var/run/php-fpm/php-fpm.sock)
nginx → node:3000 (backend network)
php → postgres:5432 (database network)
php → mariadb:3306 (database network)
php → redis:6379 (database network)
node → postgres:5432 (database network)
node → redis:6379 (database network)
```

Note: PHP-FPM uses Unix sockets instead of TCP for better performance and
security

### Blocked Connections

```text
nginx ✗ postgres (nginx not in database network)
nginx ✗ mariadb (nginx not in database network)
nginx ✗ redis (nginx not in database network)
Internet ✗ php (php only in backend network)
Internet ✗ databases (databases only in database network)
```

## Configuration

The network segmentation is defined in `compose.yaml`:

```yaml
networks:
  frontend:
    driver: bridge
    name: ${COMPOSE_PROJECT_NAME:-docker-webdev}-frontend
  backend:
    driver: bridge
    name: ${COMPOSE_PROJECT_NAME:-docker-webdev}-backend
  database:
    driver: bridge
    name: ${COMPOSE_PROJECT_NAME:-docker-webdev}-database
```

### Service Network Assignments

**nginx**:

```yaml
networks:
  - frontend # Receive external traffic
  - backend # Proxy to php/node
```

**php**:

```yaml
networks:
  - backend # Receive requests from nginx
  - database # Access databases
```

**node**:

```yaml
networks:
  - backend # Receive requests from nginx
  - database # Access databases
```

**databases** (postgres, mariadb, redis):

```yaml
networks:
  - database # Isolated from frontend/internet
```

## Verification

### Check Network Isolation

1. **Start services**:

   ```bash
    make up
   ```

2. **Verify nginx cannot reach database**:

   ```bash
    docker exec docker-webdev-nginx ping -c 1 postgres
   # Expected: ping: bad address 'postgres'
   ```

3. **Verify php CAN reach database**:

   ```bash
   docker exec docker-webdev-php ping -c 1 postgres
   # Expected: PING postgres (172.x.x.x): 56 data bytes
   ```

### Inspect Networks

```bash
# List all networks
docker network ls | grep docker-webdev

# Inspect specific network
docker network inspect docker-webdev-frontend
docker network inspect docker-webdev-backend
docker network inspect docker-webdev-database
```

## Performance Impact

Network segmentation in Docker has **minimal performance overhead**:

- All networks use the same `bridge` driver
- Inter-container communication remains fast (kernel routing)
- No additional latency for production workloads

## Alternative Architectures

### Simpler (Single Network)

**Not recommended** for production or GDPR-regulated applications:

```yaml
networks:
  app-network: # All services in one network
```

- ❌ No isolation
- ❌ Databases accessible from nginx
- ❌ Larger attack surface

### More Complex (4+ Networks)

Possible but often **over-engineered** for most applications:

```yaml
networks:
  public: # nginx only
  application: # nginx ↔ php/node
  cache: # redis separate
  data-access: # postgres/mariadb separate
```

- ✅ Maximum isolation
- ❌ Increased complexity
- ❌ Harder to maintain
- ⚠️ Only needed for high-security environments

## Best Practices

1. **Never expose database ports** to the host machine in production
2. **Use Unix sockets** for PHP-FPM (already configured)
3. **Enable SSL/TLS** for all database connections (see
   `compose.production.yaml`)
4. **Monitor network traffic** with tools like `docker stats`
5. **Regular security audits** of network configurations

## Troubleshooting

### Service Cannot Connect to Database

**Symptom**: `php` service cannot reach `postgres`

**Check**:

1. Verify both services are in `database` network
2. Check `docker network inspect docker-webdev-database`
3. Ensure service names match (not IPs)

### Nginx Cannot Proxy to PHP

**Symptom**: 502 Bad Gateway

**Check**:

1. Verify both `nginx` and `php` are in `backend` network
2. Check Unix socket is mounted correctly
3. Verify PHP-FPM is running:
   `docker exec docker-webdev-php ps aux | grep php-fpm`

## References

- [GDPR Article 32](https://gdpr-info.eu/art-32-gdpr/) - Security of Processing
- [Docker Network Security](https://docs.docker.com/network/network-tutorial-standalone/)
- [OWASP Network Segmentation](https://cheatsheetseries.owasp.org/cheatsheets/Network_Segmentation_Cheat_Sheet.html)
- [NIST SP 800-125A](https://nvlpubs.nist.gov/nistpubs/SpecialPublications/NIST.SP.800-125A.pdf) -
  Security Recommendations for Hypervisor Deployment
