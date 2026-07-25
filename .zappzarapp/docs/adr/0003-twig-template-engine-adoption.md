# 0003: Twig Template Engine Adoption

**Date:** 2026-01-26

**Status:** Accepted

**Context:** PHP templates used plain PHP with `include` and output buffering.
This approach has several limitations:

- No auto-escaping (manual `htmlspecialchars()` required, XSS risk)
- No strict variable checking (typos silently fail)
- Two-pass rendering for layouts (content → layout buffering)
- Template logic mixed with presentation
- No template inheritance (only includes)
- Hard to test controllers (void return, direct output)

**Decision:** Migrate all 9 PHP templates to Twig 3.x template engine:

- Create separate `TwigService` instances for App and DevDashboard namespaces
- Register TwigService in DI containers with proper CSP nonce integration
- Use Twig template inheritance (`extends`/`block`) instead of includes
- Controllers return `Response` objects instead of void
- Enable Twig cache in production (`build/cache/twig/`), disable in development

**Consequences:**

**Positive:**

- (+) Auto-escaping by default (XSS protection)
- (+) Strict variables (errors on undefined, catches typos)
- (+) Template inheritance (clearer parent-child relationships)
- (+) Controllers testable (inject mock TwigService)
- (+) Consistent Response pattern across all controllers
- (+) Better IDE support (Twig syntax highlighting)
- (+) Performance improvement in production (compiled templates cached)

**Negative:**

- (-) Additional dependency (Twig library ~500KB)
- (-) Learning curve for developers unfamiliar with Twig syntax
- (-) Template cache directory requires write permissions

**Trade-offs accepted:**

- Keep original .php templates for 1 month (rollback safety)
- Twig cache disabled in development (instant updates, slower page loads)
