# 0002: Twig Template Engine Adoption

**Date:** 2026-01-26

**Status:** Accepted

**Context:** Rendering PHP-side pages with plain PHP templates (`include` plus
output buffering) leaves several problems on the table:

- No auto-escaping (manual `htmlspecialchars()` everywhere, XSS risk)
- No strict variable checking (typos silently fail)
- Two-pass rendering for layouts (content → layout buffering)
- Template logic mixed with presentation
- Includes instead of real template inheritance
- Controllers pushed toward void-return direct output, which is hard to test

**Decision:** Render all PHP-side templates with the Twig 3.x template engine:

- Separate `TwigService` instances for the App and DevDashboard namespaces
- `TwigService` registered in the DI containers with CSP nonce integration
- Twig template inheritance (`extends`/`block`) instead of includes
- Controllers return `Response` objects instead of printing output
- Twig cache enabled in production (`build/cache/twig/`), disabled in
  development (instant template updates at the cost of slower page loads)

**Consequences:**

**Positive:**

- (+) Auto-escaping by default (XSS protection)
- (+) Strict variables (errors on undefined, catches typos)
- (+) Template inheritance (clearer parent-child relationships)
- (+) Controllers testable (inject mock TwigService)
- (+) Consistent Response pattern across all controllers
- (+) Better IDE support (Twig syntax highlighting)
- (+) Compiled, cached templates in production

**Negative:**

- (-) Additional dependency (Twig library ~500KB)
- (-) Learning curve for developers unfamiliar with Twig syntax
- (-) Template cache directory requires write permissions
