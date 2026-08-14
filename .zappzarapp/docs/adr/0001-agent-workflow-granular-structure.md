# 0001: Agent-Workflow Granular Structure

**Date:** 2026-01-21

**Status:** Accepted

**Context:** AI agents work best when they load only the context a task needs. A
single monolithic coding-standards document forces every agent to carry the
rules for all languages and roles, spending context on irrelevant material.

**Decision:** Keep standards and workflow documentation granular:

- `.zappzarapp/standards/` — language-specific rules (php, node, sql, markdown,
  docker) — shared across all AI agents
- `.claude/agents/` — workflow documentation (architect, coder, reviewer)

**Consequences:**

- (+) Minimal context per agent
- (+) Easier to maintain
- (-) More files to manage
