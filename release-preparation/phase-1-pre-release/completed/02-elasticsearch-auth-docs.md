# Task 02: Enable Elasticsearch Security by Default

## Priority

HIGH - Pre-Release (Security by Design)

## Estimated Effort

2-3 hours

## Context

**Security by Design Principle:** The boilerplate should be secure by default,
not require users to manually enable security for production.

**Current Situation:**
- `xpack.security.enabled: 'true'` - Security IS enabled
- `xpack.security.http.ssl.enabled: 'true'` - HTTPS IS enabled
- **BUT:** `xpack.security.authc.anonymous.roles: 'superuser'` bypasses all security!

**Application Code Pattern:**
- Both PHP and Node.js use **API key authentication** (not username/password)
- `ELASTICSEARCH_API_KEY` → `Authorization: ApiKey <key>`
- This is the correct pattern to follow

## Current State

### compose.yaml (lines ~395-404)

```yaml
xpack.security.enabled: 'true'
xpack.security.http.ssl.enabled: 'true'
# ...
# Problem: Anonymous superuser bypasses all security
xpack.security.authc.anonymous.roles: 'superuser'
xpack.security.authc.anonymous.username: 'anonymous_user'
```

### App Code (already correct)

**PHP** (`src/php/App/Infrastructure/Elasticsearch/ElasticsearchConfig.php`):
```php
$apiKey = $this->loadCredential('elasticsearch_api_key', 'ELASTICSEARCH_API_KEY', '');
// Used as: Authorization: ApiKey $apiKey
```

**Node.js** (`src/node/backend/services/ElasticsearchService.ts`):
```typescript
this.apiKey = loadCredential('elasticsearch_api_key', 'ELASTICSEARCH_API_KEY', '');
// Used as: Authorization: ApiKey ${this.apiKey}
```

### Tests (need updating)

**BATS** (`tests/bats/integration/database.bats:98`):
```bash
# Currently: No authentication
run timeout 30 docker compose exec -T elasticsearch curl -s http://localhost:9200/_cluster/health
```

**GOSS** (`tests/goss/services/elasticsearch.yaml`):
```yaml
# Currently: No authentication
http:
  http://localhost:9200/_cluster/health:
    status: 200
```

## Target State

1. Remove anonymous superuser (enforce API key auth)
2. Generate development API key via `make setup`
3. Update all tests to use authentication
4. Provide `make es-*` convenience targets

## Implementation Steps

### Step 1: Update compose.yaml

**Remove anonymous superuser, keep TLS enabled:**

```yaml
elasticsearch:
  # ... existing build config ...
  environment:
    TZ: ${TZ:-UTC}
    discovery.type: single-node
    # Security with TLS
    xpack.security.enabled: 'true'
    xpack.security.http.ssl.enabled: 'true'
    xpack.security.http.ssl.key: /usr/share/elasticsearch/config/certs/cert.key
    xpack.security.http.ssl.certificate: /usr/share/elasticsearch/config/certs/cert.crt
    xpack.security.http.ssl.verification_mode: 'none'
    xpack.security.transport.ssl.enabled: 'false'
    # REMOVED: Anonymous superuser lines
    # xpack.security.authc.anonymous.roles: 'superuser'
    # xpack.security.authc.anonymous.username: 'anonymous_user'
    # Bootstrap password for initial API key generation
    ELASTIC_PASSWORD_FILE: /run/secrets/elasticsearch_bootstrap_password
    # ... rest of config ...
  secrets:
    - elasticsearch_bootstrap_password
  # ... volumes, networks ...

secrets:
  elasticsearch_bootstrap_password:
    file: ./secrets/elasticsearch_bootstrap_password.txt
```

### Step 2: Create Secret Files

**Create `secrets/elasticsearch_bootstrap_password.example.txt`:**

```
dev-bootstrap-password
```

**Create `secrets/elasticsearch_api_key.example.txt`:**

```
# Generated during make setup - see Step 3
```

**Update `secrets/.gitignore`:**

```gitignore
# Real secrets (never commit)
*.txt

# Keep examples
!*.example.txt
```

### Step 3: Update make setup for API Key Generation

**Add to `Makefile` or `scripts/generate-secrets.sh`:**

```bash
# Generate Elasticsearch bootstrap password
if [ ! -f secrets/elasticsearch_bootstrap_password.txt ]; then
    cp secrets/elasticsearch_bootstrap_password.example.txt secrets/elasticsearch_bootstrap_password.txt
    echo "Created: secrets/elasticsearch_bootstrap_password.txt"
fi

# Note: API key is generated after ES starts (see make es-setup-api-key)
```

**Add `make es-setup-api-key` target:**

```makefile
##@ Elasticsearch

.PHONY: es-setup-api-key es-health es-api-key

es-setup-api-key: ## Generate Elasticsearch API key (run after ES is healthy)
	@echo "Generating Elasticsearch API key..."
	@BOOTSTRAP_PW=$$(cat secrets/elasticsearch_bootstrap_password.txt) && \
	docker compose exec -T elasticsearch curl -sk \
		-u "elastic:$$BOOTSTRAP_PW" \
		-X POST "https://localhost:9200/_security/api_key" \
		-H "Content-Type: application/json" \
		-d '{"name": "zappzarapp-dev", "role_descriptors": {"all_access": {"cluster": ["all"], "indices": [{"names": ["*"], "privileges": ["all"]}]}}}' \
		| jq -r '.encoded' > secrets/elasticsearch_api_key.txt
	@echo "API key saved to secrets/elasticsearch_api_key.txt"

es-health: ## Check Elasticsearch cluster health
	@API_KEY=$$(cat secrets/elasticsearch_api_key.txt 2>/dev/null) && \
	docker compose exec -T elasticsearch curl -sk \
		-H "Authorization: ApiKey $$API_KEY" \
		"https://localhost:9200/_cluster/health" | jq .

es-api-key: ## Show Elasticsearch API key
	@cat secrets/elasticsearch_api_key.txt 2>/dev/null || echo "No API key found. Run: make es-setup-api-key"
```

### Step 4: Update BATS Tests

**Edit `tests/bats/integration/database.bats`:**

```bash
@test "[Integration] Elasticsearch is accessible" {
    require_service "elasticsearch"

    # Load API key from secrets
    local api_key
    api_key=$(cat secrets/elasticsearch_api_key.txt 2>/dev/null || echo "")

    if [ -z "$api_key" ]; then
        skip "Elasticsearch API key not configured"
    fi

    # Use HTTPS with API key authentication
    run timeout 30 docker compose exec -T elasticsearch curl -sk \
        -H "Authorization: ApiKey $api_key" \
        "https://localhost:9200/_cluster/health"
    assert_success
    assert_output --partial "status"
}
```

### Step 5: Update GOSS Tests

**Edit `tests/goss/services/elasticsearch.yaml`:**

```yaml
# GOSS Tests for elasticsearch container
# Note: HTTP tests use internal localhost (inside container)
# Authentication via API key header

# ============================================================================
# PROCESS TESTS (Internal)
# ============================================================================
process:
  java:
    running: true

# ============================================================================
# PORT TESTS (Internal)
# ============================================================================
port:
  tcp:9200:
    listening: true
    ip:
      - 0.0.0.0
  tcp:9300:
    listening: true
    ip:
      - 0.0.0.0

# ============================================================================
# FILE TESTS (Internal)
# ============================================================================
file:
  /usr/share/elasticsearch/data:
    exists: true
    filetype: directory
  /usr/share/elasticsearch/backup:
    exists: true
    filetype: directory
  /usr/share/elasticsearch/config/elasticsearch.yml:
    exists: true
  # API key secret should be mounted
  /run/secrets/elasticsearch_bootstrap_password:
    exists: true

# ============================================================================
# COMMAND TESTS (Internal - with authentication)
# ============================================================================
command:
  # Health check using bootstrap password (API key may not exist yet during GOSS)
  "curl -sk -u elastic:$(cat /run/secrets/elasticsearch_bootstrap_password) https://localhost:9200/_cluster/health":
    exit-status: 0
    timeout: 35000
    stdout:
      - '"status"'
      - '"cluster_name"'

  # Verify security is enabled (anonymous access should fail)
  "curl -sk https://localhost:9200/_cluster/health":
    exit-status: 0
    stdout:
      - '"error"'
      - '"security_exception"'
```

### Step 6: Update Elasticsearch Dockerfile Healthcheck

**Edit `docker/elasticsearch/Dockerfile`:**

```dockerfile
# Healthcheck uses bootstrap password (available before API key generation)
HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=5 \
    CMD curl -sk -u "elastic:$(cat /run/secrets/elasticsearch_bootstrap_password 2>/dev/null || echo '')" \
        "https://localhost:9200/_cluster/health" | grep -q '"status"' || exit 1
```

### Step 7: Update .env

**Add Elasticsearch section to `.env`:**

```bash
# =============================================================================
# Elasticsearch (optional - enable with ENABLE_ELASTICSEARCH=true)
# =============================================================================
ELASTICSEARCH_HOST=elasticsearch
ELASTICSEARCH_PORT=9200
# API key loaded from secrets/elasticsearch_api_key.txt
# Generate with: make es-setup-api-key (after ES is healthy)
ELASTICSEARCH_VERIFY_SSL=false
```

### Step 8: Update Documentation

**Update `.zappzarapp/docs/infrastructure/OPTIONAL-SERVICES.md`:**

```markdown
## Elasticsearch

Full-text search engine with security enabled by default.

### Quick Start

```bash
# 1. Enable Elasticsearch
ENABLE_ELASTICSEARCH=true
make up

# 2. Wait for healthy (may take 60+ seconds)
make status  # Wait until elasticsearch shows "healthy"

# 3. Generate API key (one-time setup)
make es-setup-api-key

# 4. Verify
make es-health
```

### Authentication

Elasticsearch uses API key authentication. The boilerplate generates a
development API key during setup.

| Credential | Location | Purpose |
|------------|----------|---------|
| Bootstrap password | `secrets/elasticsearch_bootstrap_password.txt` | Initial setup, healthchecks |
| API key | `secrets/elasticsearch_api_key.txt` | Application access |

### Production Setup

1. Generate strong bootstrap password:

   ```bash
   openssl rand -base64 32 > secrets/elasticsearch_bootstrap_password.txt
   ```

2. Start Elasticsearch and generate API key:

   ```bash
   make up
   make es-setup-api-key
   ```

3. Create application-specific API keys with limited permissions:

   ```bash
   curl -sk -u "elastic:$(cat secrets/elasticsearch_bootstrap_password.txt)" \
     -X POST "https://localhost:9200/_security/api_key" \
     -H "Content-Type: application/json" \
     -d '{
       "name": "app-readonly",
       "role_descriptors": {
         "app_role": {
           "indices": [{"names": ["app-*"], "privileges": ["read"]}]
         }
       }
     }'
   ```

### Make Targets

| Target | Description |
|--------|-------------|
| `make es-setup-api-key` | Generate API key (run once after ES starts) |
| `make es-health` | Check cluster health |
| `make es-api-key` | Show current API key |
```

## Verification

1. **Anonymous access is blocked:**

   ```bash
   # This should return security_exception (401)
   curl -sk https://localhost:9200/_cluster/health
   # Expected: {"error":{"type":"security_exception"...}}
   ```

2. **API key authentication works:**

   ```bash
   make es-health
   # Expected: {"cluster_name":"...","status":"green"...}
   ```

3. **GOSS tests pass:**

   ```bash
   make goss-test-elasticsearch
   ```

4. **BATS tests pass:**

   ```bash
   make test-bats
   ```

## Files to Modify

| File | Change |
|------|--------|
| `compose.yaml` | Remove anonymous superuser, add bootstrap password secret |
| `docker/elasticsearch/Dockerfile` | Update healthcheck to use bootstrap password |
| `secrets/elasticsearch_bootstrap_password.example.txt` | Create with dev password |
| `secrets/elasticsearch_api_key.example.txt` | Create placeholder |
| `secrets/.gitignore` | Ensure secrets are ignored |
| `Makefile` | Add es-setup-api-key, es-health, es-api-key targets |
| `.env` | Add ES environment variables |
| `tests/bats/integration/database.bats` | Update ES test with API key auth |
| `tests/goss/services/elasticsearch.yaml` | Update HTTP tests with auth |
| `.zappzarapp/docs/infrastructure/OPTIONAL-SERVICES.md` | Update documentation |

## Testing

```bash
# 1. Fresh setup
rm -f secrets/elasticsearch_*.txt
make setup

# 2. Start Elasticsearch
make up  # or: ENABLE_ELASTICSEARCH=true make up

# 3. Wait for healthy
make status  # Wait for "healthy" status

# 4. Verify anonymous access is blocked
curl -sk https://localhost:9200/_cluster/health | grep -q "security_exception" && echo "OK: Auth required"

# 5. Generate API key
make es-setup-api-key

# 6. Verify API key works
make es-health | grep -q '"status"' && echo "OK: API key works"

# 7. Run tests
make test-bats
make goss-test-elasticsearch
```

## Rollback

If issues occur, temporarily restore anonymous access (NOT for production):

```yaml
# compose.override.yaml
services:
  elasticsearch:
    environment:
      xpack.security.authc.anonymous.roles: 'superuser'
      xpack.security.authc.anonymous.username: 'anonymous_user'
```

## Notes

- **HTTPS is required** - Elasticsearch uses TLS internally (`https://localhost:9200`)
- **Two-phase auth setup:** Bootstrap password for initial access, then API key for apps
- **API keys are preferred** over username/password for application access
- Anonymous superuser is completely removed (security by design)
- Consistent with the existing API key pattern in PHP and Node.js code
- Always use `make up` not `docker compose up` directly (ensures correct .env loading)

