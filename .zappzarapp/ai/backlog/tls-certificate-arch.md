# TLS Certificate Architecture

**Status:** Planned **Size:** Medium **Scope:** feature **Created:** 2026-01-19
**Planning:** Required

## Context

User question about separate certs for frontend/backend services.

## Questions to Investigate

1. Should nginx frontend and backend services have separate certificates?
2. How can a dev use custom CA certificates (corporate environments)?
3. Is there a clean way to inject custom CA bundles into containers?
4. Should we support `EXTRA_CA_CERTS` or similar environment variable?

## Potential Solutions

- Volume mount for custom CA certs
- Environment variable pointing to CA bundle
- Init script that adds certs to system trust store
