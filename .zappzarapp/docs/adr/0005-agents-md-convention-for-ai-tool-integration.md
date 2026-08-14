# 0005: AGENTS.md Convention for AI Tool Integration

**Date:** 2026-07-24

**Status:** Accepted

**Context:** The boilerplate ships a complete Claude Code integration
(`.claude/`: skills, agents, hooks, settings). Teams use a growing variety of
other AI coding tools — Codex CLI, Gemini CLI / Antigravity, Cursor, Copilot,
OpenCode, Cline, Roo Code — and the industry has converged on a root `AGENTS.md`
file that these tools read natively. Maintaining per-tool configuration
converters would tie the boilerplate to specific tool pairs and their release
cycles, and generated configs risk overwriting hand-maintained ones.

**Decision:** Ship one first-class integration (`.claude/`) and the tool-neutral
`AGENTS.md` convention for everything else. Which additional AI tools a project
adopts — and whether to sync their configs — is a per-project decision.
`make ai-setup` (labels/milestones for `/tasks`) is part of the first-class
integration.

**Consequences:**

- (+) No per-tool converter dependencies to maintain, update, or audit
- (+) Every AGENTS.md-aware tool works out of the box
- (+) `.claude/settings.json` stays hand-maintained — nothing generated can
  overwrite it
- (-) No automated conversion of `.claude/skills/` to other tools' command
  formats (teams needing it can adopt a converter in their own project)
