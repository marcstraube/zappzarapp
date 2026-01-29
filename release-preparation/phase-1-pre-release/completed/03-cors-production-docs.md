# Task 03: CORS Security by Default

## Priority

HIGH - Pre-Release (Security by Design)

## Estimated Effort

30-45 minutes

## Context

**Security by Design Principle:** The boilerplate should have secure defaults
that don't require manual hardening for production.

Currently, `CORS_ORIGINS=*` allows any website to make API requests. This is a
security risk:

- Enables CSRF-like attacks from malicious sites
- Allows credential theft via cross-origin requests
- Violates principle of least privilege

**Environment Configuration Pattern:**

| File | Purpose | Committed |
|------|---------|-----------|
| `.env` | Base config (secure defaults) | ✅ Yes |
| `.env.local` | Local dev overrides | ❌ No |
| `.env.production` | Production overrides | ❌ No |

## Current State

### .env

```bash
CORS_ORIGINS=*
```

### Problems

1. `*` allows any origin - insecure by default
2. Easy to accidentally deploy with `*`
3. Inconsistent with "security by default" principle

## Target State

1. `.env` has restrictive default (localhost only)
2. Developers override in `.env.local` if needed
3. Production requires explicit domain configuration
4. Clear documentation for all scenarios

## Implementation Steps

### Step 1: Update .env (Base Config)

**Change CORS_ORIGINS to secure default:**

```bash
# =============================================================================
# CORS Configuration
# =============================================================================
# Allowed origins for Cross-Origin requests (comma-separated, no spaces)
# Default: Only localhost for development
# Override in .env.local (dev) or .env.production (prod)
CORS_ORIGINS=https://localhost,https://localhost:5173,http://localhost:5173
```

This allows:
- `https://localhost` - Main app (nginx with TLS)
- `https://localhost:5173` - Vite dev server (HTTPS)
- `http://localhost:5173` - Vite dev server (HTTP fallback)

### Step 2: Create .env.local.example

**Create `.env.local.example` for developers who need more origins:**

```bash
# =============================================================================
# Local Development Overrides
# =============================================================================
# Copy to .env.local and adjust as needed
# .env.local is gitignored and overrides .env values

# Uncomment to allow all origins (convenient but less secure)
# CORS_ORIGINS=*

# Or add specific additional origins
# CORS_ORIGINS=https://localhost,https://localhost:5173,http://localhost:3000
```

### Step 3: Update .env.production (if exists) or Create Example

**Create `.env.production.example`:**

```bash
# =============================================================================
# Production Environment Configuration
# =============================================================================
# Copy to .env.production and configure for your deployment

# REQUIRED: Set your actual domain(s)
# Example single domain:
CORS_ORIGINS=https://your-domain.com

# Example multiple domains:
# CORS_ORIGINS=https://your-domain.com,https://api.your-domain.com,https://admin.your-domain.com
```

### Step 4: Update .gitignore

**Ensure override files are ignored:**

```gitignore
# Environment overrides (contain local/production secrets)
.env.local
.env.production
.env.*.local
```

### Step 5: Update Documentation

**Create or update `.zappzarapp/docs/security/CORS.md`:**

```markdown
# CORS Configuration

Cross-Origin Resource Sharing (CORS) controls which domains can make requests
to your API from browsers.

## Default Configuration (Security by Default)

The base `.env` file restricts CORS to localhost only:

```bash
CORS_ORIGINS=https://localhost,https://localhost:5173,http://localhost:5173
```

This is secure by default - only local development URLs are allowed.

## Environment Override Pattern

| Environment | Config File | CORS Setting |
|-------------|-------------|--------------|
| Development (default) | `.env` | localhost only |
| Development (relaxed) | `.env.local` | `*` or custom |
| Production | `.env.production` | Your domain(s) |

## Configuration Examples

### Development: Allow All Origins

If you need `*` for testing (e.g., mobile app development):

```bash
# .env.local
CORS_ORIGINS=*
```

### Development: Specific Additional Origins

```bash
# .env.local
CORS_ORIGINS=https://localhost,https://localhost:5173,http://192.168.1.100:3000
```

### Production: Single Domain

```bash
# .env.production
CORS_ORIGINS=https://your-app.com
```

### Production: Multiple Domains

```bash
# .env.production
CORS_ORIGINS=https://your-app.com,https://admin.your-app.com,https://api.your-app.com
```

## Security Best Practices

1. **Never use `*` in production** - Always list specific domains
2. **Use HTTPS only** - No HTTP origins in production
3. **Minimal origins** - Only include actually needed domains
4. **Review periodically** - Remove unused origins

## Verification

### Check Current Configuration

```bash
# View effective CORS setting
docker compose exec php printenv CORS_ORIGINS
docker compose exec node printenv CORS_ORIGINS
```

### Test CORS Headers

```bash
# Allowed origin (should return Access-Control-Allow-Origin header)
curl -I -H "Origin: https://localhost" https://localhost/api/health

# Disallowed origin (should NOT return Access-Control-Allow-Origin)
curl -I -H "Origin: https://evil-site.com" https://localhost/api/health
```
```

### Step 6: Add Make Target for CORS Verification

**Add to Makefile:**

```makefile
##@ Security

.PHONY: check-cors

check-cors: ## Show current CORS configuration
	@echo "CORS_ORIGINS (from environment):"
	@docker compose exec php printenv CORS_ORIGINS 2>/dev/null || echo "  PHP container not running"
	@docker compose exec node printenv CORS_ORIGINS 2>/dev/null || echo "  Node container not running"
	@echo ""
	@echo "Source files:"
	@grep "^CORS_ORIGINS" .env .env.local .env.production 2>/dev/null || echo "  (only .env found)"
```

## Verification

1. **Secure default in .env:**

   ```bash
   grep "^CORS_ORIGINS=" .env
   # Expected: CORS_ORIGINS=https://localhost,https://localhost:5173,http://localhost:5173
   # NOT: CORS_ORIGINS=*
   ```

2. **.env.local.example exists:**

   ```bash
   [ -f .env.local.example ] && echo "OK" || echo "MISSING"
   ```

3. **.env.production.example exists:**

   ```bash
   [ -f .env.production.example ] && echo "OK" || echo "MISSING"
   ```

4. **Override files are gitignored:**

   ```bash
   grep -q "\.env\.local" .gitignore && echo "OK" || echo "NOT IGNORED"
   grep -q "\.env\.production" .gitignore && echo "OK" || echo "NOT IGNORED"
   ```

5. **Functional test:**

   ```bash
   make up
   # Allowed origin
   curl -sI -H "Origin: https://localhost" https://localhost/api/health | grep -i "access-control"
   # Should show: Access-Control-Allow-Origin: https://localhost
   ```

## Files to Modify/Create

| File | Action | Content |
|------|--------|---------|
| `.env` | Modify | Change `CORS_ORIGINS=*` to localhost-only |
| `.env.local.example` | Create | Template for local overrides |
| `.env.production.example` | Create | Template for production |
| `.gitignore` | Verify | Ensure .env.local/.env.production ignored |
| `Makefile` | Add | `check-cors` target |
| `.zappzarapp/docs/security/CORS.md` | Create | Full documentation |

## Testing

```bash
# 1. Verify default is restrictive
grep "^CORS_ORIGINS=" .env | grep -v "\*"

# 2. Start containers
make up

# 3. Test localhost is allowed
curl -sI -H "Origin: https://localhost" https://localhost/api/health \
  | grep -i "access-control-allow-origin"
# Expected: https://localhost

# 4. Test random origin is blocked
curl -sI -H "Origin: https://evil.com" https://localhost/api/health \
  | grep -i "access-control-allow-origin"
# Expected: (no output - header not present)

# 5. Test with .env.local override
echo "CORS_ORIGINS=*" > .env.local
make restart
curl -sI -H "Origin: https://evil.com" https://localhost/api/health \
  | grep -i "access-control-allow-origin"
# Expected: * (now allowed due to override)

# 6. Cleanup
rm .env.local
make restart
```

## Notes

- Localhost-only default may require `.env.local` for some dev scenarios
- Mobile app testing often needs `*` - document this in .env.local.example
- The override pattern (.env → .env.local) must be supported by the app's env loading
- Verify PHP and Node.js both respect the CORS_ORIGINS variable

