# 0001: Agent-Workflow Granular Structure

**Date:** 2026-01-21

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
