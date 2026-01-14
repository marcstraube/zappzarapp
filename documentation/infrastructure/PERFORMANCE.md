# Performance Tuning Guide

Optimization strategies for development and production environments.

## Quick Wins

### Development Performance

| Issue                  | Solution                                     | Impact |
| ---------------------- | -------------------------------------------- | ------ |
| Slow container startup | Use `make up` (not `make fresh`)             | High   |
| Xdebug slows requests  | Set `XDEBUG_MODE=off` in `.env`              | High   |
| File sync lag          | Bind mounts provide instant sync             | Medium |
| IDE indexing slow      | Exclude `node_modules/`, `vendor/`, `build/` | Medium |

### Production Performance

| Issue                | Solution                                | Impact |
| -------------------- | --------------------------------------- | ------ |
| Large Docker images  | Multi-stage builds (already configured) | High   |
| Slow PHP responses   | Enable OPcache (enabled by default)     | High   |
| Static asset loading | Brotli compression via Nginx            | Medium |
| Database queries     | Add indexes, use connection pooling     | High   |

## PHP Optimization

### OPcache Configuration

OPcache is pre-configured in `docker/php/conf.d/opcache.ini`:

```ini
; Production-optimized settings (already configured)
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0          ; Disable in production
opcache.revalidate_freq=0
opcache.fast_shutdown=1
opcache.jit=1255                        ; PHP 8.0+ JIT
opcache.jit_buffer_size=128M
```

**Development override** (`docker/php/conf.d/development.ini`):

```ini
opcache.validate_timestamps=1           ; Enable for file changes
opcache.revalidate_freq=2               ; Check every 2 seconds
```

### Xdebug Performance Impact

Xdebug significantly impacts performance even when not debugging:

| Mode       | Performance Impact |
| ---------- | ------------------ |
| `off`      | Baseline (fastest) |
| `develop`  | ~5-10% slower      |
| `debug`    | ~15-25% slower     |
| `coverage` | ~30-50% slower     |

**Recommendation:** Keep `XDEBUG_MODE=off` in `.env` and only enable when
needed.

### PHP-FPM Tuning

Default configuration in `docker/php/php-fpm.d/www.conf`:

```ini
; Process management
pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35
pm.max_requests = 500

; Timeouts
request_terminate_timeout = 60s
request_slowlog_timeout = 5s
```

**Adjust based on available memory:**

| RAM   | max_children | start_servers |
| ----- | ------------ | ------------- |
| 1 GB  | 10-15        | 2             |
| 2 GB  | 25-30        | 5             |
| 4 GB  | 50-60        | 10            |
| 8 GB+ | 100+         | 20            |

**Formula:**
`max_children = (Available RAM - OS overhead) / Average PHP process size`

Check PHP process memory:

```bash
docker compose exec php ps aux --sort=-%mem | head -10
```

## Node.js Optimization

### PM2 Configuration

PM2 is configured in `ecosystem.config.cjs`:

```javascript
module.exports = {
  apps: [
    {
      name: 'backend',
      script: './dist/server.js',
      instances: 'max', // Use all CPU cores
      exec_mode: 'cluster', // Cluster mode for load balancing
      max_memory_restart: '500M', // Restart if memory exceeds
      node_args: '--max-old-space-size=512',
    },
  ],
};
```

**Monitor PM2:**

```bash
make node-pm2-status
make node-pm2-logs
```

### Vite Build Optimization

Production build configuration in `vite.config.js`:

```javascript
build: {
  minify: 'terser',
  sourcemap: false,           // Disable in production
  rollupOptions: {
    output: {
      manualChunks: {
        vendor: ['vue', 'react'],  // Separate vendor chunks
      }
    }
  }
}
```

**Analyze bundle size:**

```bash
docker compose exec node pnpm run build -- --report
```

## Database Optimization

### PostgreSQL Tuning

Key parameters to adjust in `postgresql.conf`:

```ini
# Memory (adjust based on available RAM)
shared_buffers = 256MB              # 25% of RAM
effective_cache_size = 768MB        # 75% of RAM
work_mem = 16MB                     # Per-operation memory
maintenance_work_mem = 128MB        # For VACUUM, CREATE INDEX

# Connections
max_connections = 100

# Query Planning
random_page_cost = 1.1              # For SSD storage
effective_io_concurrency = 200      # For SSD storage

# Write Performance
wal_buffers = 16MB
checkpoint_completion_target = 0.9
```

### MariaDB Tuning

Key parameters for `my.cnf`:

```ini
[mysqld]
# InnoDB Buffer Pool (50-70% of RAM)
innodb_buffer_pool_size = 512M
innodb_buffer_pool_instances = 4

# Query Cache (MariaDB 10.1+)
query_cache_type = 1
query_cache_size = 64M
query_cache_limit = 2M

# Connections
max_connections = 150
thread_cache_size = 16

# I/O
innodb_io_capacity = 2000           # For SSD
innodb_io_capacity_max = 4000
```

### Index Recommendations

**Find missing indexes (PostgreSQL):**

```sql
SELECT schemaname, tablename, indexrelname, idx_scan, idx_tup_read
FROM pg_stat_user_indexes
WHERE idx_scan = 0 AND indexrelname NOT LIKE '%pkey';
```

**Find slow queries (PostgreSQL):**

```sql
SELECT query, calls, mean_time, total_time
FROM pg_stat_statements
ORDER BY mean_time DESC
LIMIT 10;
```

**Find slow queries (MariaDB):**

```sql
SELECT * FROM information_schema.PROCESSLIST
WHERE TIME > 5;
```

### Connection Pooling

For high-traffic applications, consider:

- **PHP:** Use persistent connections (`PDO::ATTR_PERSISTENT => true`)
- **Node.js:** Use `pg-pool` (already configured) with pool size tuning
- **External:** PgBouncer for PostgreSQL, ProxySQL for MariaDB

## Redis Optimization

### Memory Management

```bash
# Check memory usage
docker compose exec redis redis-cli INFO memory

# Monitor memory in real-time
make redis-monitor
```

**Key settings (already configured):**

```text
maxmemory 256mb
maxmemory-policy allkeys-lru
```

### Persistence Trade-offs

| Option         | Durability | Performance |
| -------------- | ---------- | ----------- |
| RDB only       | Low        | Best        |
| AOF (everysec) | Medium     | Good        |
| AOF (always)   | High       | Slowest     |

Current configuration uses `appendfsync everysec` (good balance).

## Nginx Optimization

### Compression

Brotli and Gzip are configured in `docker/nginx/nginx.conf`:

```nginx
# Brotli (better compression, modern browsers)
brotli on;
brotli_comp_level 6;
brotli_types text/plain text/css application/json application/javascript;

# Gzip fallback
gzip on;
gzip_comp_level 5;
gzip_types text/plain text/css application/json application/javascript;
```

### Static Asset Caching

```nginx
location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff2)$ {
    expires 1y;
    add_header Cache-Control "public, immutable";
    access_log off;
}
```

### Worker Processes

```nginx
# Auto-detect CPU cores
worker_processes auto;

# Connections per worker
events {
    worker_connections 1024;
    use epoll;
    multi_accept on;
}
```

## Docker Optimization

### Build Performance

```bash
# Enable BuildKit (faster builds)
export DOCKER_BUILDKIT=1

# Use cache mounts for dependencies
make build
```

### Image Size

Current image sizes (approximate):

| Image | Development | Production |
| ----- | ----------- | ---------- |
| nginx | ~50 MB      | ~25 MB     |
| php   | ~150 MB     | ~80 MB     |
| node  | ~300 MB     | ~150 MB    |

**Reduce image size:**

```bash
# Check image sizes
docker images | grep zappzarapp

# Remove unused images
make prune
```

### Resource Limits

Production limits in `compose.production.yaml`:

```yaml
deploy:
  resources:
    limits:
      cpus: '2'
      memory: 512M
    reservations:
      cpus: '0.5'
      memory: 256M
```

## Profiling Tools

### PHP Profiling

**Xdebug Profiler:**

```bash
# Enable profiling
XDEBUG_MODE=profile make restart

# Access the application, then find cachegrind files:
ls storage/logs/*.cachegrind

# Analyze with KCachegrind (Linux) or QCachegrind (macOS)
```

**Blackfire (commercial):**

```bash
# Install Blackfire probe and run profiling
blackfire curl http://localhost:8080/
```

### Node.js Profiling

**Built-in profiler:**

```bash
docker compose exec node node --prof src/server.js
```

**Clinic.js:**

```bash
docker compose exec node npx clinic doctor -- node dist/server.js
```

### Database Query Analysis

**PostgreSQL EXPLAIN:**

```sql
EXPLAIN (ANALYZE, BUFFERS, FORMAT TEXT)
SELECT * FROM users WHERE email = 'test@example.com';
```

**MariaDB EXPLAIN:**

```sql
EXPLAIN EXTENDED
SELECT * FROM users WHERE email = 'test@example.com';
```

## Benchmarking

### HTTP Benchmarks

**wrk (recommended):**

```bash
# Install wrk
apt-get install wrk

# Benchmark
wrk -t12 -c400 -d30s http://localhost:8080/
```

**Apache Bench:**

```bash
ab -n 10000 -c 100 http://localhost:8080/
```

### Database Benchmarks

**pgbench (PostgreSQL):**

```bash
docker compose exec postgres pgbench -i -s 50 app
docker compose exec postgres pgbench -c 10 -j 2 -t 1000 app
```

**sysbench (MariaDB):**

```bash
sysbench oltp_read_write --mysql-host=localhost --mysql-port=3306 prepare
sysbench oltp_read_write --mysql-host=localhost --mysql-port=3306 run
```

## Performance Checklist

### Development

- [ ] `XDEBUG_MODE=off` unless debugging
- [ ] Docker Desktop: Increase memory/CPU allocation
- [ ] IDE: Exclude `node_modules/`, `vendor/`, `build/`
- [ ] Use `make up` instead of `make fresh`

### Pre-Production

- [ ] Run `make check` (all quality checks pass)
- [ ] Test with production config: `ENV=production make build`
- [ ] Profile slow endpoints
- [ ] Check database query performance

### Production

- [ ] `ENV=production` in `.env`
- [ ] `XDEBUG_MODE=off`
- [ ] Enable OPcache timestamp validation off
- [ ] Set appropriate resource limits
- [ ] Configure database connection pooling
- [ ] Enable Brotli/Gzip compression
- [ ] Set proper cache headers
- [ ] Monitor with external tools (see [MONITORING.md](MONITORING.md))

## Related Documentation

- [ARCHITECTURE.md](ARCHITECTURE.md) - System architecture
- [MONITORING.md](MONITORING.md) - External monitoring integration
- [TROUBLESHOOTING.md](TROUBLESHOOTING.md) - Common issues
