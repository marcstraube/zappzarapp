# Architecture Decisions

Record of significant architectural and design decisions (ADR-style).

---

## Template

```markdown
## [YYYY-MM-DD] Decision Title

**Status:** Accepted / Superseded / Deprecated

**Context:** What is the issue?

**Decision:** What was decided?

**Consequences:** What are the trade-offs?
```

---

## 2026-01-26: Twig Template Engine Adoption

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

---

## 2026-01-26: Vite HMR WebSocket Custom Path Routing

**Status:** Accepted

**Context:** Vite HMR requires WebSocket connection for live reloading. Default
WebSocket path is `/` (root), which conflicts with PHP routing (index.php
handles all root requests). Previous attempts to use conditional routing based
on WebSocket headers were complex and unreliable.

**Decision:** Use custom WebSocket path with Nginx path rewriting:

- Configure Vite `hmr.path: '/vite-hmr-ws'` (client connects to custom path)
- Configure Nginx to proxy `/vite-hmr-ws` to `http://node:5173/` (trailing slash
  rewrites path)
- Vite's WebSocket server listens on default root path `/` (no server-side
  changes needed)

**Consequences:**

**Positive:**

- (+) No conflicts with PHP routing (custom path isolated)
- (+) Simple Nginx configuration (single location block, no conditionals)
- (+) Standard Nginx path rewriting pattern (trailing slash)
- (+) Explicit and maintainable (clear what's happening)
- (+) Works reliably across browsers and WebSocket clients

**Negative:**

- (-) Non-standard Vite HMR path (developers might be surprised by
  `/vite-hmr-ws`)
- (-) Requires understanding of Nginx path rewriting behavior

**Alternatives considered:**

1. Conditional routing at root path based on `Sec-WebSocket-Protocol: vite-hmr`
   header
   - Rejected: Complex, unreliable with `if` directive in Nginx
2. Use Vite's default root path `/` with sub-path for PHP
   - Rejected: Major architecture change, would break existing URLs
3. Use port-based routing (different port for WebSocket)
   - Rejected: Requires opening additional ports, complicates firewall rules

---

## 2026-01-21: Agent-Workflow Granular Structure

**Status:** Accepted

**Context:** Single `coding-standards.md` was too large, agents loaded
unnecessary context.

**Decision:** Split into granular files:

- `.zappzarapp/standards/` — language-specific rules (php, node, sql, markdown,
  docker) — shared across all AI agents
- `.claude/agents/` — workflow documentation (architect, coder, reviewer)

**Consequences:**

- (+) Minimal context per agent
- (+) Easier to maintain
- (-) More files to manage

---

## 2026-02-13: Audit Logger Package Extraction

**Status:** Accepted

**Context:** The boilerplate had tightly coupled audit logger implementations in
both PHP (`App\Infrastructure\Audit`) and Node (`Shared/Audit/`). Following the
successful extraction of `zappzarapp/security` (PHP) and
`@zappzarapp/browser-utils` (Node), audit logging was the next extraction
candidate.

**Decision:** Extract into two standalone packages with identical APIs:

- **PHP:** `zappzarapp/audit-logger` — Packagist package
- **Node:** `@zappzarapp/audit-logger` — npm package

Key design decisions:

1. **Injectable encryption strategy:** `EncryptionInterface` with `AppEncryption`
   (AES-256-GCM) default and `DatabaseEncryption` for existing DB-function users
2. **No superglobals:** All context (userId, IP, userAgent) passed as parameters;
   `HasAuditLogging` trait stays in boilerplate as convenience wrapper with
   `$_SESSION` access
3. **AuditLogEntry DTO:** Immutable DTO replaces individual parameters in
   `log()` method
4. **Node QueryExecutor interface:** Zero DB driver dependencies in package;
   boilerplate wraps its `ConnectionFactory`/`DatabaseConnection`
5. **Database dialect support:** Both PostgreSQL and MariaDB in both packages
6. **Configurable table name:** Constructor parameter (default `'audit_logs'`)
7. **File logging optional:** Constructor flag, DB error fallback always active
8. **Purge not implemented:** Documented for GDPR data minimization

**Consequences:**

- (+) Reusable across projects without boilerplate dependency
- (+) Full test coverage in isolated packages (100% line coverage)
- (+) Clean separation of concerns (no superglobals, DI-friendly)
- (+) Both DB dialects supported from day one
- (-) Boilerplate needs adapter code (HasAuditLogging trait, QueryExecutor
  wrapper)
- (-) Package linking infrastructure needed during development

---

## 2026-01-21: Session-Log Minimal Structure

**Status:** Accepted

**Context:** Session-logs had redundant fields (Learnings, Open Items,
Decisions) that were duplicated in dedicated files.

**Decision:** Slim session-log to: Goal, Branch, Changes, References, Summary.
Other data goes directly to dedicated files.

**Consequences:**

- (+) No double maintenance
- (+) Faster session logging
- (-) Need to update multiple files
