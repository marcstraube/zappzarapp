# Review 07: Messaging & Search Services

**Role**: Messaging and Search Infrastructure Expert

**Weight**: 6% of final score

**Report Location**: `reports/review-07-messaging-search.md`

---

## Verification Commands

```bash
# Enable optional services
ENABLE_RABBITMQ=true ENABLE_ELASTICSEARCH=true ENABLE_MEILISEARCH=true make up

# Test RabbitMQ
docker compose exec rabbitmq rabbitmqctl status

# Test Elasticsearch (with auth)
API_KEY=$(cat secrets/elasticsearch_api_key.txt 2>/dev/null)
curl -sk -H "Authorization: ApiKey $API_KEY" https://localhost:9200/_cluster/health

# Test Meilisearch
curl http://localhost:7700/health
```

---

## Analysis Checklist

### A. RabbitMQ

- [ ] Management UI secured
- [ ] Default guest user disabled/changed
- [ ] Virtual hosts configured
- [ ] TLS enabled
- [ ] Queue durability settings correct
- [ ] Dead letter exchange configured
- [ ] Example producers/consumers present
- [ ] **Sane Defaults**: memory/disk limits configured
- [ ] **Sane Defaults**: heartbeat timeout appropriate

### B. Mercure

- [ ] JWT authentication configured
- [ ] Publisher/subscriber separation
- [ ] CORS settings appropriate
- [ ] Example integration present
- [ ] **Sane Defaults**: heartbeat interval configured
- [ ] **Sane Defaults**: write timeout appropriate

### C. Elasticsearch

- [ ] Security enabled (not anonymous superuser!)
- [ ] API key authentication pattern
- [ ] TLS enabled
- [ ] Index templates documented
- [ ] Mapping examples present
- [ ] Health check uses authentication
- [ ] **Sane Defaults**: heap size configured
- [ ] **Sane Defaults**: cluster name appropriate
- [ ] **Sane Defaults**: discovery.type for single-node

### D. Meilisearch

- [ ] API key configured
- [ ] Index settings documented
- [ ] Search examples present
- [ ] Ranking configuration
- [ ] **Sane Defaults**: max_indexing_memory appropriate
- [ ] **Sane Defaults**: max_indexing_threads configured

---

## Output Format

See `00-overview.md` for standard report format.
