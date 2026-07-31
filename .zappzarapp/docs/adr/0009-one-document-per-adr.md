# 0009: One Document per ADR

**Date:** 2026-07-25

**Status:** Accepted

**Context:** Architecture decisions were collected in a single growing
`DECISIONS.md` under the AI knowledge layer. A monolithic file scales poorly: no
stable links to individual decisions, no per-decision git history, no status
lifecycle, and awkward review of new decisions. Decisions are primarily
documentation for humans and belong with the project documentation, not in an
AI-specific location. A separate `REFERENCES.md` bookmark list added little
value over linking sources directly where they are used.

**Decision:** Keep each ADR as its own numbered document under `docs/adr/`
(`NNNN-slug.md`) with an index README, following the common ADR practice
(status, context, decision, consequences). For the boilerplate itself the
location is `.zappzarapp/docs/adr/`; projects created from the boilerplate use
`docs/adr/`. `REFERENCES.md` is retired. `LEARNINGS.md` stays as the
fast-capture inbox for gotchas; mature entries graduate into the regular
documentation during periodic triage.

**Consequences:**

- (+) Stable per-decision links, history, and status lifecycle
- (+) Decisions live in the documentation tree where humans look
- (+) One less knowledge file (REFERENCES) to maintain
- (-) One-time migration of existing entries and scaffolding updates
