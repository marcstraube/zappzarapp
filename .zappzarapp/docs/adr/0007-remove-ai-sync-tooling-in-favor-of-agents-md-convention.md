# 0007: Remove AI Sync Tooling in Favor of AGENTS.md Convention

**Date:** 2026-07-24

**Status:** Accepted

**Context:** The boilerplate shipped `make ai-sync` (`ai-commands-sync` via
ai-command-converter, `ai-rules-sync` via rulesync) to propagate Claude
configuration to other AI tools. Evidence showed the feature was never used:
`.gemini/` stayed empty since January, and `.rulesync/` could never persist
because the dev-tools container does not mount it. The tools themselves aged
badly: ai-command-converter only supports the Claude <-> Gemini pair (Gemini CLI
is now Antigravity), and rulesync went through 6 majors in 6 months, dragging in
a vulnerable transitive (`@hono/node-server`, GHSA-frvp-7c67-39w9) that even the
latest release cannot fix (its MCP SDK pins the vulnerable 1.x line). Under
rulesync 15, the lazy-init flow would default to `delete: true` with target
`claudecode` and overwrite the hand-maintained `.claude/settings.json`.
Meanwhile the industry converged on a root `AGENTS.md` read natively by Codex
CLI, Gemini CLI / Antigravity, Cursor, Copilot, OpenCode, Cline and Roo Code.

**Decision:** Remove the sync targets and both dependencies. The boilerplate
ships one first-class integration (`.claude/`) and documents the tool-neutral
`AGENTS.md` convention for everything else. Which additional AI tools a project
adopts — and whether to sync their configs — is a per-project decision.
`make ai-setup` (labels/milestones for `/tasks`) stays.

**Consequences:**

- (+) `GHSA-frvp-7c67-39w9` dropped from `pnpm.auditConfig.ignoreGhsas` (no
  audit exception needed)
- (+) ~930 fewer transitive packages; no rulesync major-update treadmill
- (+) No risk of a sync run clobbering `.claude/settings.json`
- (-) No automated conversion of `.claude/skills/` to other tools' command
  formats (teams needing it can adopt a converter in their own project)
