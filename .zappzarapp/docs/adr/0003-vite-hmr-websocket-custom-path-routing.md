# 0003: Vite HMR WebSocket Custom Path Routing

**Date:** 2026-01-26

**Status:** Accepted

**Context:** Vite HMR requires a WebSocket connection for live reloading. The
default WebSocket path is `/` (root), which conflicts with PHP routing
(index.php handles all root requests).

**Decision:** Use custom WebSocket path with Nginx path rewriting:

- Configure Vite `hmr.path: '/__vite_hmr__'` (client connects to custom path)
- Configure Nginx to proxy `/__vite_hmr__` to `http://node:5173/` (trailing
  slash rewrites path)
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
  `/__vite_hmr__`)
- (-) Requires understanding of Nginx path rewriting behavior

**Alternatives considered:**

1. Conditional routing at root path based on `Sec-WebSocket-Protocol: vite-hmr`
   header
   - Rejected: Complex, unreliable with `if` directive in Nginx
2. Use Vite's default root path `/` with sub-path for PHP
   - Rejected: Major architecture change, would break existing URLs
3. Use port-based routing (different port for WebSocket)
   - Rejected: Requires opening additional ports, complicates firewall rules
