# CORS Configuration

Cross-Origin Resource Sharing (CORS) controls which domains can make requests to
your API from browsers.

## Default Configuration (Security by Default)

The base `.env` file restricts CORS to localhost only:

```bash
CORS_ORIGINS=https://localhost,https://localhost:5173,http://localhost:5173
```

This is secure by default - only local development URLs are allowed.

## Environment Override Pattern

| Environment           | Config File       | CORS Setting   |
| --------------------- | ----------------- | -------------- |
| Development (default) | `.env`            | localhost only |
| Development (relaxed) | `.env.local`      | `*` or custom  |
| Production            | `.env.production` | Your domain(s) |

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

# Or use the make target
make check-cors
```

### Test CORS Headers

```bash
# Allowed origin (should return Access-Control-Allow-Origin header)
curl -I -H "Origin: https://localhost" https://localhost/api/health

# Disallowed origin (should NOT return Access-Control-Allow-Origin)
curl -I -H "Origin: https://evil-site.com" https://localhost/api/health
```

## How to Override CORS for Development

If you need to allow additional origins during development:

1. Copy the example file:

   ```bash
   cp .env.local.example .env.local
   ```

2. Edit `.env.local` and uncomment/modify the CORS_ORIGINS line:

   ```bash
   # Allow all origins (convenient but less secure)
   CORS_ORIGINS=*

   # OR add specific origins
   # CORS_ORIGINS=https://localhost,https://localhost:5173,http://192.168.1.100:3000
   ```

3. Restart containers for changes to take effect:

   ```bash
   make restart
   ```

4. Verify the new configuration:

   ```bash
   make check-cors
   ```

## Production Setup

1. Edit the committed `.env.production` template for your deployment.

2. Set your actual domain(s):

   ```bash
   # Edit .env.production
   CORS_ORIGINS=https://your-domain.com
   ```

3. Keep real secrets out of `.env.production` — it holds non-secret production
   config only; inject credentials via Docker secrets (see
   [SECRETS.md](SECRETS.md))

## Troubleshooting

### CORS errors in browser console

**Error:** "No 'Access-Control-Allow-Origin' header is present"

**Solution:** Add your frontend's origin to CORS_ORIGINS

```bash
# Development (.env.local)
CORS_ORIGINS=https://localhost,https://localhost:5173,http://localhost:3000

# Production (.env.production)
CORS_ORIGINS=https://your-app.com
```

### Wildcard (\*) not working in production

**This is intentional** - Using `*` in production is a security risk.

**Solution:** List specific domains instead:

```bash
CORS_ORIGINS=https://your-domain.com,https://admin.your-domain.com
```

### Changes not taking effect

**Solution:** Restart containers after changing environment variables:

```bash
make restart
# or
make down && make up
```

## Related Documentation

- [Environment Configuration](../infrastructure/ENVIRONMENT.md) - How .env files
  work
- [Security Overview](./README.md) - Security best practices
- [Secrets Management](./SECRETS.md) - Managing sensitive data
