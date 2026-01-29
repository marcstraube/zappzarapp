# 25: Fix ZAP Security Scan Findings

## Goal

Address all findings from the ZAP (Zed Attack Proxy) security scan before
release, regardless of severity level.

## Scan Results (25. Jan 2026)

| Risk Level | Count | Status |
|------------|-------|--------|
| High | 0 | - |
| Medium | 5 | Must fix |
| Low | 1 | Should fix |
| Informational | 5 | Should fix |

## Medium Findings (5)

### 1. Application Error Disclosure

**Problem:** Stack traces or detailed error messages exposed to clients.

**Fix:**
- PHP: Ensure `display_errors = Off` in production
- Node: Don't expose stack traces in error responses
- Nginx: Custom error pages without technical details

**Files:**
- `docker/php/conf.d/php.production.ini`
- `src/node/backend/` - Error handling middleware
- `docker/nginx/conf.d/` - Error page config

### 2. CSP: Failure to Define Directive with No Fallback

**Problem:** Content-Security-Policy lacks `default-src` fallback directive.

**Fix:** Add `default-src 'self'` as base policy.

### 3. CSP: script-src unsafe-eval

**Problem:** `eval()` and similar constructs allowed - XSS risk.

**Fix:**
- Development: May need for Vite HMR - acceptable
- Production: Remove `unsafe-eval`, use nonces or hashes if needed

### 4. CSP: script-src unsafe-inline

**Problem:** Inline scripts allowed - XSS risk.

**Fix:**
- Development: Needed for Vite HMR
- Production: Remove `unsafe-inline`, use nonces for necessary inline scripts

### 5. CSP: style-src unsafe-inline

**Problem:** Inline styles allowed.

**Fix:**
- Often needed for legitimate use (e.g., style attributes)
- Consider if can be removed or use nonces
- Lower priority than script-src issues

## Low Finding (1)

### 6. Insufficient Site Isolation Against Spectre Vulnerability

**Problem:** Missing `Cross-Origin-Opener-Policy` header.

**Fix:** Add to Nginx config:
```nginx
add_header Cross-Origin-Opener-Policy "same-origin" always;
```

## Informational Findings (5)

### 7-10. Sec-Fetch-* Headers Missing

**Problem:** `Sec-Fetch-Dest`, `Sec-Fetch-Mode`, `Sec-Fetch-Site`,
`Sec-Fetch-User` headers not present.

**Analysis:** These are **request** headers set by the browser, not response
headers set by the server. ZAP flags them because it doesn't send them in its
automated requests.

**Fix:** No server-side action needed. These are browser-controlled.
Mark as "Won't Fix" / "By Design".

### 11. Storable and Cacheable Content

**Problem:** Responses can be cached.

**Analysis:** This is expected behavior for static content and public pages.

**Fix:** Review caching headers for sensitive endpoints only. Generally no
action needed.

## Implementation Plan

### 1. Create Separate CSP for Dev vs Production

```nginx
# docker/nginx/conf.d/security-headers.conf

# Development CSP (permissive for HMR)
# set $csp_policy "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; ...";

# Production CSP (strict)
# set $csp_policy "default-src 'self'; script-src 'self'; style-src 'self'; ...";
```

### 2. Add Missing Security Headers

```nginx
# Cross-Origin policies
add_header Cross-Origin-Opener-Policy "same-origin" always;
add_header Cross-Origin-Embedder-Policy "require-corp" always;
add_header Cross-Origin-Resource-Policy "same-origin" always;
```

### 3. Error Handling Improvements

- Ensure production PHP config hides errors
- Add error handling middleware in Node that sanitizes responses
- Verify Nginx error pages don't leak information

## Tasks

- [ ] Create `security-headers.conf` with environment-aware CSP
- [ ] Add COOP, COEP, CORP headers
- [ ] Verify PHP production error handling
- [ ] Add Node error sanitization middleware
- [ ] Review Nginx error pages
- [ ] Re-run ZAP scan to verify fixes
- [ ] Document CSP differences between dev and production

## Files to Modify

- `docker/nginx/conf.d/security-headers.conf` (create or update)
- `docker/nginx/templates/default.conf.template`
- `docker/php/conf.d/php.production.ini`
- `src/node/backend/` - Error middleware

## Priority

High - Security findings should be addressed before release.

## Notes

- Sec-Fetch-* headers are browser request headers, not fixable server-side
- CSP needs to be environment-aware (dev permissive, prod strict)
- Some inline styles may be unavoidable - evaluate case by case
- Consider adding ZAP scan to CI/CD pipeline post-release

## Reference

- ZAP Report: `report_html.html`
- OWASP CSP Cheat Sheet: https://cheatsheetseries.owasp.org/cheatsheets/Content_Security_Policy_Cheat_Sheet.html
