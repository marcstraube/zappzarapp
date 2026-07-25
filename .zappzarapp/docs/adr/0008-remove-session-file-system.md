# 0008: Remove Session-File System

**Date:** 2026-07-25

**Status:** Accepted

**Context:** The boilerplate maintained a custom session lifecycle for Claude
Code: hook-created session log files under `.claude/sessions/YYYY/MM/` with
auto-derived slugs, continuation detection, and end-of-session reminders. The
system predates Claude Code's built-in persistent memory and automatic context
compaction, which now cover cross-conversation continuity. Session files were
never committed, so their value was limited to a single developer's machine —
exactly the scope the built-in memory serves. The hook-based detection machinery
additionally used an output format that Claude Code does not deliver to the
model, so the automation was inert in practice.

**Decision:** Remove the session-file system entirely (lifecycle hooks, session
template, SessionStart/SessionEnd/PreCompact wiring). Continuity relies on
built-in Claude Code memory and context compaction. The committed knowledge
files remain the team-shared, persistent project memory, and the remaining hooks
(branch check, change watch, syntax feedback, contextual reminders) use output
contracts that actually reach the model.

**Consequences:**

- (+) Less custom machinery to maintain and explain; fewer failure modes
- (+) Continuity works identically for every contributor out of the box
- (+) Hook feedback that remains is verified to reach the model
- (-) No per-session work log; git history and CHANGELOG serve that need
- Supersedes [0002](0002-session-log-minimal-structure.md)
