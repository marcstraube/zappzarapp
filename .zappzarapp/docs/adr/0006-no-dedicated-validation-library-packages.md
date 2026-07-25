# 0006: No Dedicated Validation Library Packages

**Date:** 2026-07-24

**Status:** Accepted

**Context:** A pre-release task proposed extracting `zappzarapp/validation`
(Packagist) and `@zappzarapp/validation` (npm) as shared validation packages
with cross-language schema parity and TypeScript type inference. Experience from
the security, audit-logger, and devtoolbar extractions showed the real
per-package cost (CI, mutation-testing ratchet, SBOM, releases, security review,
ongoing maintenance) — poor value for ~200 LOC per language. The proposed
feature set (composable schemas, type inference) effectively re-implements Zod,
and the underlying audit finding ("no Zod/io-ts runtime validation") points at
adopting such a library, not cloning it.

**Decision:**

- TypeScript: adopt Zod (or Valibot if bundle size matters) directly in the
  boilerplate when API input validation becomes real (frontend work).
- PHP: if reusable validation primitives are needed, add a `Validation`
  namespace to the existing `zappzarapp/security` package (minor release)
  instead of creating a new package; input validation is within its scope. For
  plain assert-style checks, `webmozart/assert` is an alternative.
- Audit-logger packages keep their internal checks and stay zero-dependency;
  migrating them onto a validation dependency would break their core promise.

**Consequences:**

- (+) No new package family to build, publish, and maintain
- (+) Established, battle-tested validation on the TS side
- (+) Audit-logger zero-dependency promise preserved
- (-) No cross-language schema parity (no real consumer required it)
- (-) Validation stays scattered until the frontend work triggers adoption
