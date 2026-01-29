# Review 17: Claude/AI Integration

**Role**: AI Assistant Configuration and Workflow Expert

**Weight**: 4% of final score

**Report Location**: `reports/review-17-claude-ai.md`

---

## Analysis Checklist

### A. CLAUDE.md Quality

- [ ] Instructions clear and actionable
- [ ] No conflicting instructions
- [ ] Session management documented
- [ ] Knowledge file paths correct
- [ ] Workflow references valid
- [ ] Language guidelines clear

### B. Slash Commands

For each command in `.claude/commands/`:

- [ ] Command works as documented
- [ ] Workflow is logical
- [ ] Error handling present
- [ ] Output is useful

Known issues to verify:
- [ ] `/commit` workflow logical (review before commit, not after)
- [ ] Command flow is intuitive

### C. Hooks

- [ ] Hooks in settings.json work
- [ ] No instructions in CLAUDE.md that should be hooks
- [ ] Hook format correct (nested structure)
- [ ] No conflicting hooks

### D. Agent Workflow

- [ ] Agent workflow documented (.claude/agents/)
- [ ] Scope detection works
- [ ] Pre-flight checks appropriate
- [ ] Subagent coordination clear

### E. Templates

- [ ] Session template complete
- [ ] Knowledge file templates present
- [ ] Templates match documented structure

### F. Knowledge Files

- [ ] LEARNINGS.md contains valuable info only
- [ ] DECISIONS.md format consistent
- [ ] REFERENCES.md links work
- [ ] No duplicated information across files

### G. Optimization Opportunities

Identify what could be improved:

- [ ] Instructions that should be hooks
- [ ] Redundant configuration
- [ ] Missing automation
- [ ] Workflow inefficiencies

---

## Output Format

See `00-overview.md` for standard report format.
