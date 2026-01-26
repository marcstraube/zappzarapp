# Security Headers

## Overview

This project implements comprehensive security headers to protect against common
web vulnerabilities. Headers are configured at two levels:

1. **Nginx** - Primary security headers for all HTTP responses
2. **Node.js** - API-specific headers for Express endpoints

## Header Configuration

### Shared Headers (security-headers.conf)

Applied to all responses via
`include /etc/nginx/snippets/security-headers.conf`:

| Header                         | Value                                      | Purpose                         |
| ------------------------------ | ------------------------------------------ | ------------------------------- |
| `X-Frame-Options`              | `SAMEORIGIN`                               | Prevents clickjacking           |
| `X-Content-Type-Options`       | `nosniff`                                  | Prevents MIME-type sniffing     |
| `X-XSS-Protection`             | `1; mode=block`                            | XSS filter (legacy browsers)    |
| `Referrer-Policy`              | `strict-origin-when-cross-origin`          | Controls referrer information   |
| `Permissions-Policy`           | `geolocation=(), microphone=(), camera=()` | Restricts browser features      |
| `Cross-Origin-Opener-Policy`   | `same-origin`                              | Spectre mitigation              |
| `Cross-Origin-Resource-Policy` | `same-origin`                              | Prevents cross-origin embedding |
| `Cross-Origin-Embedder-Policy` | `require-corp`                             | Enforces CORP for resources     |

### Content Security Policy (CSP)

CSP differs between development and production:

#### Development CSP

Allows Vite HMR and development tools:

```text
default-src 'self';
script-src 'self' 'unsafe-inline' 'unsafe-eval' https://localhost:${PORT};
style-src 'self' 'unsafe-inline';
img-src 'self' data:;
font-src 'self';
connect-src 'self' wss://localhost:${PORT} https://localhost:${PORT};
frame-ancestors 'self';
base-uri 'self';
form-action 'self';
```

#### Production CSP

Strict policy without unsafe directives:

```text
default-src 'self';
script-src 'self';
style-src 'self';
img-src 'self' data:;
font-src 'self';
connect-src 'self';
frame-ancestors 'self';
base-uri 'self';
form-action 'self';
```

### Production-Only Headers

| Header                      | Value                                          | Purpose       |
| --------------------------- | ---------------------------------------------- | ------------- |
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains; preload` | HSTS (1 year) |

## Error Handling

### PHP

Production error settings (`docker/php/conf.d/security.ini`):

- `display_errors = Off` - Never show errors to users
- `display_startup_errors = Off` - Hide startup errors
- `log_errors = On` - Log errors to stderr
- `expose_php = Off` - Hide PHP version

### Node.js

Error middleware in `app.ts`:

- Development: Returns error message (for debugging)
- Production: Returns generic "Internal Server Error"
- Stack traces are never sent to clients
- All errors are logged via Pino

### Nginx

Custom error pages (`docker/nginx/errors/`):

- `404.html` - User-friendly "Not Found" page
- `50x.html` - Generic "Server Error" page
- No technical details exposed

## ZAP Security Scan Compliance

The following findings from OWASP ZAP have been addressed:

| Finding                                       | Status | Solution                                            |
| --------------------------------------------- | ------ | --------------------------------------------------- |
| Application Error Disclosure                  | ✅     | Error handling configured                           |
| CSP: No default-src                           | ✅     | `default-src 'self'` present                        |
| CSP: script-src unsafe-eval                   | ✅     | Removed in production, required for Vite HMR in dev |
| CSP: script-src unsafe-inline                 | ✅     | Removed in production, nonce-based                  |
| CSP: style-src unsafe-inline                  | ✅     | Removed in production, required for Vite in dev     |
| CSP: style-src unsafe-hashes                  | ✅     | Removed from all environments                       |
| Insufficient Site Isolation (CORP: same-site) | ✅     | Changed to `same-origin`                            |
| Missing COOP header                           | ✅     | Added `Cross-Origin-Opener-Policy: same-origin`     |
| Missing COEP header                           | ✅     | Added `Cross-Origin-Embedder-Policy: require-corp`  |
| Sec-Fetch-\* headers missing                  | N/A    | Browser request headers (not server-controlled)     |
| Storable and Cacheable Content                | N/A    | Expected behavior                                   |

**Note:** ZAP scans run against production configuration (`ENV=production`) to
validate strict security headers.

## Files

| File                                                | Purpose                  |
| --------------------------------------------------- | ------------------------ |
| `docker/nginx/snippets/security-headers.conf`       | Shared security headers  |
| `docker/nginx/conf.d/ssl-development.conf.template` | Development CSP          |
| `docker/nginx/conf.d/ssl-production.conf.template`  | Production CSP + HSTS    |
| `docker/php/conf.d/security.ini`                    | PHP security settings    |
| `src/node/backend/app.ts`                           | Node.js security headers |
| `docker/nginx/errors/`                              | Custom error pages       |
