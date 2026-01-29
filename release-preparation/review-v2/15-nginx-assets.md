# Review 15: Nginx & Static Assets

**Role**: Web Server and Asset Delivery Expert

**Weight**: 5% of final score

**Report Location**: `reports/review-15-nginx-assets.md`

---

## Verification Commands

```bash
# Start services
make up

# Check nginx config syntax
docker compose exec nginx nginx -t

# Check security headers
curl -I https://localhost:8443/

# Check compression
curl -H "Accept-Encoding: gzip,br" -I https://localhost:8443/
```

---

## Analysis Checklist

### A. Nginx Configuration

- [ ] Syntax valid
- [ ] Worker processes appropriate
- [ ] Connection limits set
- [ ] Timeout values sensible
- [ ] Buffer sizes appropriate
- [ ] Logging configured
- [ ] **Sane Defaults**: worker_connections reasonable for dev/prod
- [ ] **Sane Defaults**: keepalive_timeout appropriate
- [ ] **Sane Defaults**: client_max_body_size sufficient
- [ ] **Sane Defaults**: proxy_read_timeout covers slow operations

### B. Security Configuration

- [ ] Security headers present (all of them)
- [ ] SSL/TLS configuration secure
- [ ] No server version disclosure
- [ ] Rate limiting configured
- [ ] Directory listing disabled

### C. Reverse Proxy

- [ ] PHP-FPM proxy correct
- [ ] Node.js proxy correct (API and Vite)
- [ ] WebSocket support (Mercure, HMR)
- [ ] Proxy headers forwarded

### D. Static Asset Serving

- [ ] Brotli compression enabled
- [ ] Gzip fallback
- [ ] Cache headers appropriate
- [ ] Immutable assets cached long
- [ ] Dynamic content not cached

### E. Error Pages

- [ ] Custom error pages configured
- [ ] Error pages don't leak info
- [ ] Maintenance mode possible

---

## Output Format

See `00-overview.md` for standard report format.
