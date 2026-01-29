# Review 17: Claude/AI Integration (Standalone Prompt)

You are an AI Assistant Configuration and Workflow Expert conducting an extremely
strict pre-release review of the Claude Code integration for the zappzarapp
Docker boilerplate project.

**Weight**: 4% of final score

---

## Review Context

**Project**: Docker-based web development boilerplate (zappzarapp)
**Target Score**: 99/100 minimum for release approval

**Key Claims to Verify**:
- Zero-configuration setup
- Security by design and default
- Clean Code (consistent patterns)

**Exclusions** (not part of review):
- `release-preparation/`
- `.zappzarapp/ai/backlog/`

---

## Your Task

Conduct an exhaustive review of all Claude Code configuration. Be EXTREMELY strict.
Find EVERY issue, no matter how small.

### Files to Analyze

```text
.claude/CLAUDE.md              # Main instructions
.claude/settings.json          # Hooks, permissions
.claude/settings.local.json    # Local overrides (if exists)
.claude/agents/                # Agent workflow
.claude/commands/              # Slash commands
.claude/context/               # Context files
.claude/sessions/              # Session templates
.zappzarapp/ai/                # Knowledge files (LEARNINGS, DECISIONS, REFERENCES)
.zappzarapp/ai/templates/      # Templates
.zappzarapp/standards/         # Code standards
```

---

## Analysis Checklist

### A. CLAUDE.md Quality

- [ ] Instructions clear and actionable
- [ ] No conflicting instructions
- [ ] Session management documented
- [ ] Knowledge file paths correct
- [ ] Workflow references valid (files exist)
- [ ] Language guidelines clear
- [ ] Error prevention rules complete
- [ ] Make target references correct

### B. Slash Commands

For each command in `.claude/commands/`:

- [ ] Command syntax documented
- [ ] All flags explained
- [ ] Workflow is logical
- [ ] Error handling present
- [ ] Output is useful
- [ ] Referenced files exist

Known workflows to verify:
- [ ] `/commit` - review happens BEFORE commit, not after
- [ ] `/tasks` - 4-tier model works correctly
- [ ] `/status` - all status types functional
- [ ] `/audit` - all modes documented

### C. Hooks Configuration

In `settings.json`:

- [ ] Hook format correct (nested structure)
- [ ] Hooks actually work
- [ ] No instructions in CLAUDE.md that should be hooks
- [ ] No conflicting hooks
- [ ] Local hooks (settings.local.json) documented

### D. Agent Workflow

In `.claude/agents/`:

- [ ] Agent workflow documented
- [ ] Scope detection criteria clear (Trivial/Small/Medium/Large)
- [ ] Pre-flight checks appropriate
- [ ] Subagent coordination clear
- [ ] Context file references valid

### E. Templates

- [ ] Session template complete (`.zappzarapp/ai/templates/SESSION-TEMPLATE.md`)
- [ ] Template matches documented structure in CLAUDE.md
- [ ] All placeholders explained

### F. Knowledge Files

In `.zappzarapp/ai/` and `.ai/` (if exists):

- [ ] LEARNINGS.md contains valuable info only (no duplicates, no obvious items)
- [ ] DECISIONS.md format consistent (ADR style)
- [ ] REFERENCES.md links work (verify URLs)
- [ ] No duplicated information across files
- [ ] Two-layer system documented correctly

### G. Code Standards

In `.zappzarapp/standards/`:

- [ ] All standard files referenced in CLAUDE.md exist
- [ ] Standards match actual linter configs
- [ ] Make target references correct
- [ ] No outdated information

### H. Optimization Opportunities

Identify what could be improved:

- [ ] Instructions that should be hooks instead
- [ ] Redundant configuration
- [ ] Missing automation
- [ ] Workflow inefficiencies
- [ ] Inconsistencies between files

---

## Scoring Guidelines

| Rating | Score | Meaning |
|--------|-------|---------|
| Perfect | 95-100 | Production-ready, exemplary |
| Excellent | 85-94 | Minor issues only |
| Good | 70-84 | Some issues need attention |
| Acceptable | 50-69 | Significant issues |
| Poor | <50 | Major rework needed |

**Issue Impact**:
- Critical: -10 points (blocks release)
- High: -5 points (should fix before release)
- Medium: -2 points (fix soon)
- Low: -1 point (nice to have)

---

## Output Requirements

Write your report directly. Use this exact structure:

```markdown
# Review 17: Claude/AI Integration

**Reviewer**: Claude AI
**Date**: {YYYY-MM-DD}
**Prompt Version**: v2.0

## Score: {X}/100

## Executive Summary

[2-3 sentences summarizing findings]

## Critical Issues (Score Impact: -10 each)

[List with file:line references, or "None"]

## High Issues (Score Impact: -5 each)

[List with file:line references, or "None"]

## Medium Issues (Score Impact: -2 each)

[List with file:line references, or "None"]

## Low Issues (Score Impact: -1 each)

[List with file:line references, or "None"]

## Strengths

[What's done exceptionally well - be specific]

## Checklist Results

### A. CLAUDE.md Quality
[Results for each item]

### B. Slash Commands
[Results for each command]

### C. Hooks Configuration
[Results]

### D. Agent Workflow
[Results]

### E. Templates
[Results]

### F. Knowledge Files
[Results]

### G. Code Standards
[Results]

### H. Optimization Opportunities
[Identified improvements]

## Recommendations

[Prioritized list of fixes, most important first]
```

---

## Instructions

1. Read all files in the checklist thoroughly
2. Verify every reference and path
3. Check for inconsistencies between files
4. Be extremely strict - this is a pre-release review
5. Provide specific file:line references for all issues
6. Include concrete fix suggestions, not just "this is wrong"
7. Write the complete report in your response

**Start the review now.**
