# Nginx Configuration

This document covers the Nginx configuration structure, snippets, and production
customization guidelines.

## Configuration Structure

```text
docker/nginx/
├── conf.d/
│   ├── ssl-development.conf.template  # Development config (auto-enabled)
│   └── ssl-production.conf.template   # Production config (use make ssl-prod-enable)
├── snippets/
│   ├── deny-rules.conf                # Block access to hidden/sensitive files
│   ├── error-pages.conf               # Custom error page configuration
│   ├── node-backend-proxy.conf        # Node.js backend API proxy
│   ├── node-frontend-proxy.conf       # Node.js frontend framework proxy
│   ├── security-headers.conf          # Security headers (X-Frame-Options, etc.)
│   ├── server-defaults.conf           # Rate limiting and logging
│   └── ssl-settings.conf              # TLS protocols, ciphers, session settings
└── Dockerfile
```

## Snippets Overview

### node-backend-proxy.conf

Routes requests to the Node.js Express backend API:

- **Route:** `/api/node/*` → `node:3000/api/*`
- **Health:** `/api/node/health` → `node:3000/health`
- **Features:** WebSocket support, JSON error responses, no buffering

### node-frontend-proxy.conf

Routes requests to Node.js frontend frameworks (Next.js, Nuxt, Remix,
SvelteKit):

- **Route:** `/app/*` → `node:3000/*`
- **Internal routes:** `/_next/*`, `/_nuxt/*`, `/__nuxt/*` (framework
  HMR/assets)
- **Features:** WebSocket support for HMR, SSR streaming (buffering disabled)

### Other Snippets

| Snippet                 | Purpose                                           |
| ----------------------- | ------------------------------------------------- |
| `deny-rules.conf`       | Block `.git`, `.env`, `composer.json`, etc.       |
| `error-pages.conf`      | Custom 4xx/5xx error pages                        |
| `security-headers.conf` | X-Frame-Options, X-Content-Type-Options, Referrer |
| `server-defaults.conf`  | Rate limiting zones, access/error logging         |
| `ssl-settings.conf`     | TLS 1.2/1.3, modern ciphers, session cache        |

## Development vs Production

### Key Differences

| Aspect              | Development                  | Production                          |
| ------------------- | ---------------------------- | ----------------------------------- |
| **SSL Certificate** | Self-signed (auto-generated) | Let's Encrypt or commercial         |
| **CSP Header**      | Relaxed (allows Vite HMR)    | Strict (no unsafe-inline/eval)      |
| **HSTS**            | Disabled                     | Enabled (1 year, includeSubDomains) |
| **Static Caching**  | No-cache                     | 1 year with immutable               |
| **Vite Proxy**      | Enabled (HMR support)        | Not needed (assets pre-built)       |
| **Docs Routes**     | Enabled (/docs/\*)           | Should be removed or protected      |

## Production Customization

The production template (`ssl-production.conf.template`) includes **all stack
options** by default. Before deploying, customize it based on your chosen stack.

### Stack Configuration Overview

The template contains sections for different stack components. Remove or adjust
sections based on what you actually use:

| Section                    | Keep if using...                  | Remove if...             |
| -------------------------- | --------------------------------- | ------------------------ |
| PHP-FPM locations          | PHP backend                       | Node.js only or static   |
| `node-backend-proxy.conf`  | Node.js Express/Fastify API       | PHP only or static       |
| `node-frontend-proxy.conf` | Node.js SSR (Next.js, Nuxt, etc.) | Vite SPA, PHP, or static |
| `/api/` location           | PHP API endpoints                 | Node.js API only         |
| Health check (PHP)         | PHP backend                       | Node.js only or static   |
| Health check (static)      | Static/JAMstack or fallback       | Always keep as fallback  |

### Stack Examples

#### PHP Only (no Node.js)

```nginx
# Remove these includes:
# include /etc/nginx/snippets/node-backend-proxy.conf;
# include /etc/nginx/snippets/node-frontend-proxy.conf;

# Keep: PHP-FPM locations, /api/ location, PHP health check
```

#### Node.js Only (no PHP)

```nginx
# Keep these includes:
include /etc/nginx/snippets/node-backend-proxy.conf;
include /etc/nginx/snippets/node-frontend-proxy.conf;  # If using SSR

# Remove: All PHP-FPM locations (location ~ \.php$, @php_router)
# Remove: /api/ location block (use /api/node/* instead)
# Modify: Health check to use static fallback or Node.js health endpoint

# Change root to static assets location:
root /var/www/html/public;  # For pre-built Vite assets
# Or proxy everything to Node.js frontend
```

#### Full Stack (PHP + Node.js)

```nginx
# Keep all includes and locations
include /etc/nginx/snippets/node-backend-proxy.conf;
include /etc/nginx/snippets/node-frontend-proxy.conf;

# Routes:
# /api/node/*  → Node.js backend
# /api/*       → PHP backend
# /app/*       → Node.js frontend (SSR)
# /*           → PHP (default)
```

#### Static/JAMstack (Nginx only)

```nginx
# Remove all proxy includes
# Remove all PHP-FPM locations
# Keep only: static file serving, health check fallback

location / {
    try_files $uri $uri/ /index.html;  # SPA fallback
}
```

### SSL Certificates

Replace self-signed certificates with Let's Encrypt or commercial certs:

```bash
# Using Let's Encrypt (recommended)
make ssl-prod-enable
```

Or update paths manually:

```nginx
ssl_certificate /etc/letsencrypt/live/${DOMAIN}/fullchain.pem;
ssl_certificate_key /etc/letsencrypt/live/${DOMAIN}/privkey.pem;
```

### Content Security Policy

The default production CSP is strict. Adjust based on your application needs:

```nginx
# Default (very strict)
add_header Content-Security-Policy "default-src 'self'; script-src 'self'; ..." always;

# If you need inline scripts (not recommended)
add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline'; ..." always;

# If using external CDNs
add_header Content-Security-Policy "default-src 'self'; script-src 'self' https://cdn.example.com; ..." always;
```

### Development-Only Sections

These sections exist only in the development template and should **not** be
added to production:

| Section                    | Purpose                | Production alternative        |
| -------------------------- | ---------------------- | ----------------------------- |
| Vite HMR proxy             | Hot Module Replacement | Pre-built assets in `/public` |
| `/@vite/*`, `/@fs/*`, etc. | Vite internal routes   | Not needed                    |
| `/docs/*` routes           | API documentation      | Remove or protect with auth   |
| Relaxed CSP                | Allows Vite dev server | Use strict CSP                |

### OCSP Stapling

Enable OCSP stapling for better SSL performance:

```nginx
# Uncomment these lines in production
ssl_stapling on;
ssl_stapling_verify on;
ssl_trusted_certificate /etc/letsencrypt/live/${DOMAIN}/chain.pem;
resolver 8.8.8.8 8.8.4.4 valid=300s;
resolver_timeout 5s;
```

### Rate Limiting

Adjust rate limits based on expected traffic:

```nginx
# In server-defaults.conf or inline
limit_req_zone $binary_remote_addr zone=general:10m rate=10r/s;
limit_req_zone $binary_remote_addr zone=api:10m rate=30r/s;
limit_req_zone $binary_remote_addr zone=assets:10m rate=100r/s;
```

## Adding Custom Locations

To add custom routes, either:

1. **Create a new snippet** in `docker/nginx/snippets/` and include it
2. **Edit the template** directly (less maintainable)

Example custom snippet:

```nginx
# docker/nginx/snippets/custom-api.conf
location /custom-api/ {
    proxy_pass http://custom-service:8080/;
    proxy_http_version 1.1;
    proxy_set_header Host $host;
    # ... additional headers
}
```

Include in template:

```nginx
include /etc/nginx/snippets/custom-api.conf;
```

## Graceful Degradation

Both proxy configs are designed for graceful degradation:

- If the Node container is not running, nginx returns **502 Bad Gateway**
- This allows the same config to work regardless of which services are enabled
- No conditional mounting required in Docker Compose

## Testing Configuration

```bash
# Validate nginx config syntax
make exec-nginx nginx -t

# Reload nginx without restart
make exec-nginx nginx -s reload

# View active config
make exec-nginx cat /etc/nginx/conf.d/default.conf
```

## Related Documentation

- [ERROR-PAGES.md](ERROR-PAGES.md) - Custom error page configuration
- [DEPLOYMENT.md](DEPLOYMENT.md) - Production deployment guide
- [security/SSL-CERTIFICATES.md](../security/SSL-CERTIFICATES.md) - SSL setup
