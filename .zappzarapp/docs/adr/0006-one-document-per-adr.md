# 0006: One Document per ADR

**Date:** 2026-07-25

**Status:** Accepted

**Context:** Architecture decisions need stable links, per-decision git history,
and a status lifecycle (accepted, superseded). A single collective decisions
file scales poorly on all three counts and makes reviewing an individual
decision awkward. Decisions are primarily documentation for humans and belong
with the project documentation, not in an AI-specific location.

**Decision:** Each ADR is its own numbered document under `docs/adr/`
(`NNNN-slug.md`) with an index README, following the common ADR practice
(status, context, decision, consequences). Numbers are stable: new decisions get
the next free number, superseded decisions keep their file and are marked in the
status line of both documents. For the boilerplate itself the location is
`.zappzarapp/docs/adr/`; projects created from the boilerplate use `docs/adr/`.
`LEARNINGS.md` stays the fast-capture inbox for gotchas; mature entries graduate
into the regular documentation during periodic triage.

**Consequences:**

- (+) Stable per-decision links, history, and status lifecycle
- (+) Decisions live in the documentation tree where humans look
- (-) More files than a single collective document
