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
