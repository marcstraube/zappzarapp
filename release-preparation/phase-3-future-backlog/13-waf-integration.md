# Web Application Firewall (WAF) - Optional

**Status:** Planned **Size:** Large **Scope:** feature **Created:** 2026-01-20
**Planning:** Required

## Context

Advanced security feature for production deployments with external traffic.

## Goal

Add optional WAF (ModSecurity + OWASP CRS) with consistent rules across Docker
Compose and Kubernetes.

## Architecture

```text
Development/Small Production (Docker Compose):
┌─────────────────────────────────────────────────────┐
│ ENABLE_WAF=false (default)                          │
│   Internet → Nginx:8080 → PHP/Node                  │
├─────────────────────────────────────────────────────┤
│ ENABLE_WAF=true                                     │
│   Internet → WAF:8080 → Nginx:80 (internal) → App   │
└─────────────────────────────────────────────────────┘

Production (Kubernetes):
┌─────────────────────────────────────────────────────┐
│ ModSecurity disabled (default)                      │
│   Internet → Ingress-NGINX → Service → Pod          │
├─────────────────────────────────────────────────────┤
│ ModSecurity enabled (annotation/ConfigMap)          │
│   Internet → Ingress-NGINX+WAF → Service → Pod      │
└─────────────────────────────────────────────────────┘
```

## Benefits

- Same OWASP CRS rules in both environments
- Resource-conscious: disable for small setups, enable for exposed production
- No extra container in Kubernetes (built into Ingress-NGINX)
- Protection against OWASP Top 10 (SQLi, XSS, LFI, RCE, etc.)

## Implementation

### Part 1: Docker Compose Integration

```yaml
waf:
  image: owasp/modsecurity-crs:nginx-alpine
  profiles: ['waf']
  environment:
    BACKEND: http://nginx:80
  ports:
    - '${NGINX_PORT:-8080}:8080'
    - '${NGINX_SSL_PORT:-8443}:8443'
  volumes:
    - ./docker/waf/modsecurity.conf:/etc/modsecurity.d/modsecurity-override.conf:ro
```

### Part 2: Kubernetes Integration

Add Helm values for ModSecurity:

```yaml
waf:
  enabled: false
  modsecurity:
    enabled: true
    owasp: true
```

### Part 3: Shared Configuration

Create `docker/waf/` directory with:

- `modsecurity.conf` (base config)
- `crs-setup.conf` (OWASP CRS tuning)
- `rules-exclusions.conf` (false positive suppressions)

## Files

Create:

- `compose.yaml` (new waf service with profile)
- `docker/waf/` (ModSecurity configuration)

Modify:

- `.env` (ENABLE_WAF documentation)
- `Makefile` (conditional port logic, waf targets)
- `kubernetes/values.yaml` (waf.enabled)
- `kubernetes/templates/ingress.yaml` (ModSecurity annotations)
- `documentation/security/WAF.md` (setup guide)

## Resources

- <https://github.com/coreruleset/modsecurity-crs-docker>
- <https://kubernetes.github.io/ingress-nginx/user-guide/third-party-addons/modsecurity/>
- <https://coreruleset.org/docs/>

## Notes

- Not a v1.0 requirement — advanced feature for security-conscious deployments
- Requires tuning for false positives (application-specific)
- Consider "detection only" mode as safe default
