---
name: research
description:
  Research topics using local knowledge, optionally extended with web search
model: sonnet
context: fork
allowed-tools:
  - Read
  - Glob
  - Grep
  - Edit
  - Write
  - WebSearch
  - WebFetch
  - AskUserQuestion
  - Bash(date:*)
argument-hint: '<topic> [--web] [--no-save]'
---

# Research Command

Research a topic by searching local knowledge first, then optionally extending
with web search.

## Arguments

Parse `$ARGUMENTS`:

- `<topic>`: Required search topic (can be quoted for multi-word)
- `--web`: Skip local search, go directly to web search
- `--no-save`: Don't prompt to save new knowledge

## Path Resolution

**Knowledge files (in priority order):**

| File       | Project Path        | Fallback Path                  |
| ---------- | ------------------- | ------------------------------ |
| LEARNINGS  | `.ai/LEARNINGS.md`  | `.zappzarapp/ai/LEARNINGS.md`  |
| DECISIONS  | `.ai/DECISIONS.md`  | `.zappzarapp/ai/DECISIONS.md`  |
| REFERENCES | `.ai/REFERENCES.md` | `.zappzarapp/ai/REFERENCES.md` |

**Sessions:** `.claude/sessions/**/*.md`

## Workflow

```text
/research "<topic>"
    v
+-------------------------------------+
| 1. LOCAL SEARCH                     |
+-------------------------------------+
| Grep all sources for topic:         |
| - LEARNINGS.md (project+boilerplate)|
| - DECISIONS.md (project+boilerplate)|
| - REFERENCES.md (project+boilerplate)|
| - Sessions (.claude/sessions/**)    |
+-------------------------------------+
    v
+-------------------------------------+
| 2. EVALUATE RESULTS                 |
+-------------------------------------+
| Sufficient? -> Display summary      |
| Insufficient/None? -> Ask user:     |
|   "No/incomplete knowledge about    |
|    '{topic}'. Run WebSearch?"       |
|   [Yes] [No]                        |
+-------------------------------------+
    v (if WebSearch)
+-------------------------------------+
| 3. WEB SEARCH                       |
+-------------------------------------+
| - WebSearch for topic               |
| - WebFetch relevant URLs if needed  |
| - Summarize findings                |
+-------------------------------------+
    v
+-------------------------------------+
| 4. KNOWLEDGE UPDATE (optional)      |
+-------------------------------------+
| Ask user what to save:              |
| [ ] Learning (new insight/pattern)  |
| [ ] Reference (useful documentation)|
| [ ] Decision (architectural choice) |
| [ ] Nothing                         |
+-------------------------------------+
```

## Local Search

Search these files in order:

```bash
# Project layer (if exists)
.ai/LEARNINGS.md
.ai/DECISIONS.md
.ai/REFERENCES.md

# Boilerplate layer (fallback)
.zappzarapp/ai/LEARNINGS.md
.zappzarapp/ai/DECISIONS.md
.zappzarapp/ai/REFERENCES.md

# Sessions (always search)
.claude/sessions/**/*.md
```

Use case-insensitive grep with context lines.

## Output Format

### Local Results Found

```text
Research: "{topic}"
============================================

LEARNINGS.md:
  -> [Category] Title of learning
    "Relevant excerpt from the learning..."

DECISIONS.md:
  -> ADR-XXX: Decision title
    "Relevant excerpt..."

REFERENCES.md:
  -> [Category] Link title
    URL: https://...

Sessions:
  -> session-2026-01-20-1234.md (Line 45)
    "Context from session..."

============================================
Found 4 matches across 3 sources.
```

### No/Insufficient Results

```text
Research: "{topic}"
============================================

! No local knowledge found for "{topic}".

Would you like to search the web?
```

Use `AskUserQuestion` with options: [Yes, search web] [No, skip]

### After WebSearch

```text
Research: "{topic}" (Web)
============================================

Web Search Results:

[Summary of findings organized by relevance]

Sources:
- [Title](URL)
- [Title](URL)

============================================

Save this knowledge?
```

Use `AskUserQuestion` with multiSelect:

```text
What would you like to save?
[ ] Learning - New insight or pattern discovered
[ ] Reference - Useful documentation link(s)
[ ] Decision - Architectural choice made during research
[ ] Nothing - Don't save
```

## Saving Knowledge

### Save as Learning

Append to LEARNINGS.md (project layer preferred):

```markdown
### {Topic Title}

- **{Subtopic}**: {Description from research}
  - Source: WebSearch {date}
```

Ask for category selection from existing categories.

### Save as Reference

Append to REFERENCES.md:

```markdown
### {Category}

- [{Title}]({URL}) - {Brief description}
```

### Save as Decision

Only if a concrete architectural decision was made during research.

Append to DECISIONS.md as ADR:

```markdown
## ADR-XXX: {Decision Title}

**Status:** Accepted **Date:** {YYYY-MM-DD} **Context:** Research on "{topic}"
revealed... **Decision:** We will use/implement... **Consequences:** ...
```

## Examples

### Basic Research

```bash
/research "redis caching patterns"
```

Searches local knowledge, asks to web search if insufficient.

### Direct Web Search

```bash
/research "vite 6 breaking changes" --web
```

Skips local search, goes directly to web.

### Research Without Saving

```bash
/research "docker compose secrets" --no-save
```

Won't prompt to save findings.

## Integration

- Complements `/learnings` (which manages, this researches)
- Can be called during any task to gather context
- New knowledge automatically categorized and saved
- References from WebSearch preserved for future use
