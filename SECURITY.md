# Security Policy

## Reporting a Vulnerability

If you discover a security vulnerability in zappzarapp, please report it
privately so it can be addressed before public disclosure.

1. Go to the
   [Security Advisories page](https://github.com/marcstraube/zappzarapp/security/advisories/new)
2. Click **Report a vulnerability**
3. Fill in the template

Please include:

- The affected component (Docker/Compose setup, Makefile target, PHP or Node
  code, Kubernetes chart) and version or commit hash
- A reproduction (minimal steps starting from `make setup` if possible)
- The impact you observed and the impact you believe a real attacker could
  achieve

Avoid filing public GitHub issues, social-media posts, or pull requests for
security problems before they are fixed.

## Supported Versions

Until the 1.0 release, only the latest state of the default branch is supported.
After 1.0, the latest minor release receives security fixes.

## Security Model

zappzarapp is a developer platform that ships security-by-default infrastructure
for the projects built on it:

- **Secrets** are generated locally (`make secrets`) and passed to containers as
  Docker secrets — never committed, never baked into images.
- **Internal TLS** between services (`make ssl-internal`) with a local CA;
  external TLS via self-signed certificates in development and Let's Encrypt in
  production.
- **Network segmentation** between frontend, backend, and database networks in
  Docker Compose.
- **Development-only surfaces** (Dev Dashboard, database web tools, Xdebug) are
  disabled in production mode and guarded by environment checks.
- **Encryption at rest** helpers and **audit logging** support GDPR-compliance
  requirements of downstream projects.

Reports about weaknesses in these defaults are in scope even when a downstream
project could mitigate them itself — the boilerplate's defaults are the product.

Scanning is part of CI (composer audit, pnpm audit, Trivy, Semgrep, OWASP ZAP);
see `.zappzarapp/docs/security/SECURITY-SCANNING.md` for details.
