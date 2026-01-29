# Review 04: Container Security

**Role**: Container and Infrastructure Security Expert

**Weight**: 8% of final score

**Report Location**: `reports/review-04-container-security.md`

---

## Verification Commands

```bash
# Check for root users
for df in docker/*/Dockerfile; do
  echo "=== $df ==="
  grep -E "^USER|^RUN.*adduser|^RUN.*useradd" "$df" || echo "NO USER DIRECTIVE!"
done

# Check secrets permissions
ls -la secrets/

# Verify network isolation
docker network ls | grep zappzarapp
docker network inspect zappzarapp-frontend
docker network inspect zappzarapp-backend
docker network inspect zappzarapp-database
```

---

## Analysis Checklist

### A. Image Security (per service)

For EACH of the 12 services:

- [ ] Base image from official source
- [ ] Base image regularly updated
- [ ] No known CVEs in base (or documented exceptions)
- [ ] Minimal attack surface (Alpine preferred)
- [ ] No unnecessary packages installed
- [ ] No SUID/SGID binaries added

### B. Runtime Security

- [ ] All containers run as non-root
- [ ] Read-only root filesystem (where possible)
- [ ] No privileged containers
- [ ] Capabilities dropped (cap_drop: ALL)
- [ ] Only necessary capabilities added back
- [ ] No host network mode
- [ ] No host PID/IPC mode
- [ ] seccomp profiles (if applicable)

### C. Secrets Management

- [ ] Docker Secrets used (not env vars for sensitive data)
- [ ] _FILE pattern for secret consumption
- [ ] Secret files have restrictive permissions (600)
- [ ] No secrets in Dockerfiles
- [ ] No secrets in compose.yaml
- [ ] No secrets in .env (only non-sensitive config)
- [ ] .gitignore covers all secret files
- [ ] Example secrets provided (*.example.txt)

### D. Network Security

- [ ] Three-tier network isolation implemented
- [ ] Database network isolated from frontend
- [ ] Services only on required networks
- [ ] No unnecessary port exposure
- [ ] Internal services not exposed to host
- [ ] TLS for inter-service communication

### E. Security-by-Default Verification

**CRITICAL**: Verify these security defaults are ACTUALLY enforced:

- [ ] Elasticsearch: Anonymous access disabled, auth required
- [ ] Redis: TLS enabled, password required
- [ ] PostgreSQL: SSL mode enforced
- [ ] MariaDB: SSL mode enforced
- [ ] RabbitMQ: Auth required, management secured
- [ ] All services: No default passwords that work

---

## Output Format

See `00-overview.md` for standard report format.
