# 0010: Environment Mode Variable Named ZAPPZARAPP_ENV

**Date:** 2026-08-03

**Status:** Accepted

**Context:** The build/runtime mode switch (`development` / `production`) is a
cross-layer contract: PHP reads it via `getenv()`, Docker Compose interpolates
it into container environments, the Makefile validates it and honors a
caller-supplied value over the env files, the Helm chart renders it into the app
ConfigMap, and both CI pipelines set it globally. It was originally named `ENV`
— short, but generic in two problematic ways. POSIX shells assign `$ENV` a
startup-file path, so an exported `ENV` from an interactive-shell setup could
leak into builds. More importantly, since a caller-supplied value deliberately
wins over the env files, any third-party tool or user shell that happens to
export `ENV` with a matching value would silently flip the build mode. `APP_ENV`
was rejected because Symfony assigns it fixed semantics, which would collide for
Symfony-based projects. `ZAPP_ENV` was rejected because the project's own
tooling uses `ZAP_*` variables for the OWASP ZAP scanner (`ZAP_SCAN_MODE`),
making the two easy to confuse in CI configuration.

**Decision:** Name the variable `ZAPPZARAPP_ENV`. The platform layer keeps its
`zappzarapp` branding after `make setup` (built images `zappzarapp-*`, Helm
helpers), so the environment contract carries the platform name too. The rename
covers every layer (Makefile, Compose, PHP, Helm, CI, tests, docs); `ENV` is not
read anywhere anymore, with no fallback — done before the 1.0 release so no
migration path is required.

**Consequences:**

- (+) No collision with POSIX `$ENV`, Symfony `APP_ENV`, or any known tool — an
  accidental external export flipping the mode is practically ruled out
- (+) Self-explanatory in CI configs and docs; greppable without word-boundary
  gymnastics
- (-) Longer to type in ad-hoc commands (`ZAPPZARAPP_ENV=production make ...`)
- (-) Historical documents (CHANGELOG, LEARNINGS) still reference the old name
  in their narratives
