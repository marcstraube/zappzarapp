# 0004: Package Extraction Strategy

**Date:** 2026-02-13

**Status:** Accepted

**Context:** The boilerplate provides cross-cutting building blocks (security
primitives, audit logging, developer tooling) that are useful beyond a single
project. Keeping them inline couples reusable code to the boilerplate; every
extracted package, on the other hand, carries real per-package cost: CI,
mutation-testing ratchet, SBOM, releases, security review, and ongoing
maintenance.

**Decision:** Reusable, project-independent building blocks ship as standalone
packages with their own test and release cycles — `zappzarapp/security`,
`zappzarapp/audit-logger` (Packagist) and `@zappzarapp/audit-logger` (npm),
`zappzarapp/devtoolbar`. The boilerplate consumes them as regular dependencies
and provides thin convenience adapters where framework glue is needed (e.g. the
`HasAuditLogging` trait wrapping session context, a `QueryExecutor` bridging the
package's driver-free API to the boilerplate's `ConnectionFactory`). Usage stays
optional for user projects.

Extraction criteria: a candidate must carry enough scope to justify the
per-package cost, and packages promise a stable, dependency-light API — the
audit-logger packages stay zero-dependency by design.

## Refinement (2026-07-24): no dedicated validation packages

A proposed `zappzarapp/validation` / `@zappzarapp/validation` pair (shared
schemas, cross-language parity, type inference) fails the extraction criteria:
~200 LOC per language against the full per-package cost, and the feature set
would re-implement established libraries. Instead:

- TypeScript: adopt Zod (or Valibot if bundle size matters) directly in the
  boilerplate when API input validation becomes real (frontend work).
- PHP: if reusable validation primitives are needed, add a `Validation`
  namespace to the existing `zappzarapp/security` package (minor release)
  instead of creating a new package; for plain assert-style checks,
  `webmozart/assert` is an alternative.

**Consequences:**

- (+) Building blocks are reusable across projects without a boilerplate
  dependency, with isolated full test coverage
- (+) No package family exists below the cost threshold; established,
  battle-tested validation libraries cover the TS side
- (+) Audit-logger zero-dependency promise preserved
- (-) The boilerplate needs adapter code and package-linking infrastructure
  during package development
- (-) No cross-language schema parity for validation (no real consumer required
  it)

---

## Amendment 2026-07-27

The Node backend uses `@zappzarapp/browser-utils/core` (`Validator`, `Result`)
for primitive input validation in its demo endpoints. This does not conflict
with the refinement above: it rules out building or adopting a dedicated
validation _framework_ (Zod/Valibot stay the choice when real API schema
validation arrives); browser-utils is an existing ecosystem package whose
assert-style primitives replace hand-rolled checks, analogous to the
`webmozart/assert` option on the PHP side.
