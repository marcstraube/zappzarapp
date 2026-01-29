# Review 06: Database & Cache Services

**Role**: Database and Caching Expert

**Weight**: 6% of final score

**Report Location**: `reports/review-06-database-cache.md`

---

## Verification Commands

```bash
# Start services
make up

# Test database connections
docker compose exec postgres psql -U app -d app -c "SELECT 1"
docker compose exec mariadb mariadb -u app -p"$(cat secrets/db_password.txt)" -e "SELECT 1"
docker compose exec redis redis-cli PING

# Check configurations
cat docker/postgres/postgresql.conf
cat docker/mariadb/my.cnf
cat docker/redis/redis.conf
```

---

## Analysis Checklist

### A. PostgreSQL

- [ ] Version current (16+)
- [ ] Configuration optimized for development
- [ ] Production configuration documented
- [ ] SSL enabled and enforced
- [ ] Connection pooling guidance present
- [ ] Backup/restore documented
- [ ] Extensions documented (pg_cron, etc.)
- [ ] Health check verifies actual connectivity
- [ ] **Sane Defaults**: work_mem, shared_buffers, max_connections reasonable
- [ ] **Sane Defaults**: logging configured appropriately
- [ ] **Sane Defaults**: timezone handling consistent

### B. MariaDB

- [ ] Version current (11+)
- [ ] Configuration optimized
- [ ] SSL enabled
- [ ] Character set UTF8MB4
- [ ] Collation appropriate
- [ ] Timezone handling correct
- [ ] **Sane Defaults**: innodb_buffer_pool_size appropriate
- [ ] **Sane Defaults**: max_connections reasonable
- [ ] **Sane Defaults**: slow_query_log enabled for dev

### C. Redis

- [ ] Version current (8+)
- [ ] TLS enabled
- [ ] Password authentication
- [ ] Persistence configured (RDB/AOF)
- [ ] Eviction policy appropriate
- [ ] Memory limits set
- [ ] Health check works with auth
- [ ] **Sane Defaults**: maxmemory configured
- [ ] **Sane Defaults**: timeout for idle connections
- [ ] **Sane Defaults**: tcp-keepalive enabled

### D. Integration Code

- [ ] Connection strings use environment variables
- [ ] SSL/TLS parameters correct
- [ ] Connection pooling implemented
- [ ] Retry logic present
- [ ] Graceful degradation
- [ ] Health checks use proper authentication

---

## Output Format

See `00-overview.md` for standard report format.
