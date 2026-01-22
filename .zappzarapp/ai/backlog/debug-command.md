# Debug Command

**Status:** Planned **Size:** Medium **Scope:** feature **Created:** 2026-01-22
**Planning:** Required

## Context

Need a meta-tool to analyze why instructions from CLAUDE.md, agents, or
knowledge files were not correctly followed. Helps improve instruction quality.

## Goal

Command that can diagnose instruction failures and suggest improvements.

## Design

```bash
/debug "issue"              # Quick: Analyze in current context
/debug --deep "issue"       # Deep: Additionally search all instruction files
/debug --session <file>     # Past: Analyze a previous session
```

## Key Analysis Points

1. Which instruction should have applied?
2. Was it clear enough?
3. Was there a conflicting rule?
4. How can we improve it?

## Workflow

```text
/debug "Language rule not followed"
    ↓
1. Search relevant instructions:
   - CLAUDE.md
   - agents/*.md
   - standards/*.md
    ↓
2. Analyze why instruction failed:
   - Ambiguous wording?
   - Conflicting rules?
   - Missing context trigger?
   - Rule buried too deep?
    ↓
3. Output:
   - Instruction found: [quote]
   - Likely cause: [analysis]
   - Suggestion: [improvement]
    ↓
4. Optional: "Apply fix?" → Edit the instruction
```

## Why No Agent

Agent would lose access to current conversation context, which is critical for
analyzing what actually happened. Command in main context is better.

## Files

- `.claude/commands/debug.md`

## Notes

This is a meta-tool for AI configuration improvement. Low priority but valuable
for long-term instruction quality.
