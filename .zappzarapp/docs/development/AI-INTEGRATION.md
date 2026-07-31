# AI Integration

This project ships a first-class Claude Code integration. Other AI coding
assistants are supported through the tool-neutral `AGENTS.md` convention.

## Supported Tools

| Tool        | Integration                              | Agent Workflow |
| ----------- | ---------------------------------------- | -------------- |
| Claude Code | Full (`.claude/`: skills, hooks, agents) | Yes            |
| Other tools | `AGENTS.md` convention (see below)       | No             |

Most current AI coding assistants (Codex CLI, Gemini CLI / Antigravity, Cursor,
GitHub Copilot, OpenCode, Cline, Roo Code, ...) read a root-level `AGENTS.md`
file natively. The boilerplate ships a starter `AGENTS.md` in the project root —
customize it with your project rules; no sync tooling required.

## Knowledge Architecture

| Artifact  | Location (project / boilerplate)      | Purpose                                |
| --------- | ------------------------------------- | -------------------------------------- |
| LEARNINGS | `.ai/` / `.zappzarapp/ai/`            | Fast-capture inbox for gotchas         |
| ADRs      | `docs/adr/` / `.zappzarapp/docs/adr/` | One document per architecture decision |

`LEARNINGS.md` is an inbox: mature entries graduate into the regular
documentation during periodic triage (`/optimize --learnings`). ADRs follow the
common one-document-per-decision practice with an index README and a status
lifecycle (see `.zappzarapp/docs/adr/0009-one-document-per-adr.md`).

**Task Management:** Handled via `/tasks` command with automatic storage
detection (GitHub Issues, GitLab Issues, or local `.ai/TASKS.md`).

## Configuration

### Claude Code

#### Directory Structure

```text
.claude/
├── CLAUDE.md           # Instructions (swapped by make setup)
├── agents/             # Agent workflow definitions
├── skills/             # Skills (slash commands, source of truth)
├── settings.json       # Shared permissions & hooks
├── settings.local.json # Personal overrides (gitignored)
├── context/            # Project context (gitignored)
├── state/              # Persistent state (gitignored)
├── cache/              # Temporary data (gitignored)
├── temp/               # Agent work files (gitignored)
└── reports/            # Generated reports (gitignored)
```

#### Settings Structure

`settings.json` contains shared configuration:

```json
{
  "permissions": {
    "allow": ["Bash(make:*)", "Bash(git:*)"],
    "deny": ["Bash(composer:*)", "Bash(pnpm:*)"]
  },
  "hooks": {
    "PostToolUse": [...]
  }
}
```

#### Settings Scope Behavior

Hooks are **merged across scopes** (all matching hooks run):

1. Managed hooks (highest priority)
2. User hooks (`~/.claude/settings.json`)
3. Project hooks (`.claude/settings.json`)
4. Local hooks (`.claude/settings.local.json`)

Permissions follow standard scope precedence (local overrides project).

See: <https://code.claude.com/docs/en/settings#hook-configuration>

#### Hooks Configuration

Hooks provide immediate feedback and contextual reminders during work.

**Available Hook Events:**

| Event              | When                       | Use Case                         |
| ------------------ | -------------------------- | -------------------------------- |
| `SessionStart`     | Session begins             | Load context, environment checks |
| `SessionEnd`       | Session ends               | Remind about cleanup tasks       |
| `UserPromptSubmit` | User sends message         | Analyze input, detect patterns   |
| `PreCompact`       | Before context compression | Save work before context loss    |
| `PreToolUse`       | Before tool execution      | Validate, block, or modify       |
| `PostToolUse`      | After tool execution       | Lint, notify, log                |
| `Stop`             | Claude finishes responding | Post-response actions            |

**Project Hooks (`.claude/settings.json`):**

| Hook                | Script                  | Purpose                                                                  |
| ------------------- | ----------------------- | ------------------------------------------------------------------------ |
| `UserPromptSubmit`  | `user-prompt-submit.sh` | Branch check (reminder when on a protected branch)                       |
| `PostToolUse(Edit)` | (inline)                | Reminds to rebuild containers on config-file edits (matches `file_path`) |
| `PostToolUse(Bash)` | (inline)                | Reminds to sync deps after `make composer/pnpm CMD=add/remove`           |

**Hook output contract:** plain stdout only reaches Claude for
`UserPromptSubmit`/`SessionStart`. `PostToolUse` hooks must either exit 2
(stderr becomes feedback) or emit
`{"hookSpecificOutput": {"hookEventName": "PostToolUse", "additionalContext": "..."}}`.
Custom JSON shapes like `{"message": ...}` are silently dropped.

**Hook Format:**

```json
{
  "hooks": {
    "UserPromptSubmit": [
      {
        "matcher": "",
        "hooks": [
          {
            "type": "command",
            "command": "./.claude/hooks/user-prompt-submit.sh"
          }
        ]
      }
    ]
  }
}
```

**Hook Input (stdin JSON):**

```json
{
  "session_id": "uuid",
  "transcript_path": "/path/to/conversation.jsonl",
  "prompt": "user message text",
  "hook_event_name": "UserPromptSubmit"
}
```

**Hook Output (stdout JSON):**

```json
{
  "message": "[Hook Name] Message shown to Claude"
}
```

#### UserPromptSubmit Check

On every prompt the hook checks (plain stdout becomes context):

- **Branch check** — implementation-style prompt while on develop/main/master
  produces a reminder to create a feature branch or worktree

### Other Tools (`AGENTS.md` Convention)

The industry has converged on `AGENTS.md` as the tool-neutral rules file: Codex
CLI, Gemini CLI / Antigravity, Cursor, GitHub Copilot, OpenCode, Cline, Roo Code
and others read it natively.

The boilerplate ships a starter `AGENTS.md` in the project root (commands,
standards, workflow, documentation entry points). If your team uses tools
besides Claude Code:

1. Customize the root `AGENTS.md` with your shared project rules
2. Keep tool-specific configuration (e.g. `.gemini/`, `.cursor/`) personal and
   uncommitted (see [CONTRIBUTING.md](../CONTRIBUTING.md))

Which additional AI tools to adopt — and whether to keep their configurations in
sync — is a per-project decision, so the boilerplate does not prescribe sync
tooling for it.

## Task Integration Setup

```bash
# Initialize labels and milestones (auto-detects GitHub/GitLab)
make ai-setup
```

This creates:

- Standard labels (bug, enhancement, chore, status::in-progress, etc.)
- "Backlog" milestone for deferred tasks
- GitLab uses `::` for scoped labels (mutually exclusive)

## Skills

Available skills in `.claude/skills/` (one directory per skill, entry point
`SKILL.md`). The set is deliberately small: skills exist only for workflows that
built-in Claude Code capabilities do not cover.

| Skill         | Purpose                                        | Model  |
| ------------- | ---------------------------------------------- | ------ |
| `/tasks`      | Task management with GitHub/GitLab integration | sonnet |
| `/sync-check` | Verify config file synchronization             | haiku  |
| `/optimize`   | Self-optimization of config, docs, terminology | sonnet |

### Skill Structure

Skills use YAML frontmatter for metadata:

```yaml
---
name: sync-check
description: Check synchronization between related configuration files
model: haiku # haiku (fast), sonnet (balanced), opus (complex)
context: fork # Inherit conversation context
allowed-tools: # Explicit tool permissions
  - Read
  - Grep
  - Glob
  - Bash(make:*)
argument-hint: '[--fix] [--category <name>]'
---
# Skill content follows...
```

**Model Selection:**

- `haiku`: Quick tasks, simple queries (sync-check)
- `sonnet`: Balanced tasks, moderate complexity (tasks, optimize)
- `opus`: Complex planning, architecture decisions (currently unused)

### Task Management with /tasks

The `/tasks` command provides full integration with GitHub Issues and GitLab
Issues, plus local file fallback.

**Arguments:**

| Argument                       | Purpose                            |
| ------------------------------ | ---------------------------------- |
| `--add [--private]`            | Create new task                    |
| `--list [--milestone <name>]`  | List tasks                         |
| `--choose [--plan\|--no-plan]` | Select and start a task            |
| `--milestone <name> <task>`    | Assign task to milestone           |
| `--defer <task>`               | Move to "Backlog" milestone        |
| `--close <task>`               | Close task (completed/not planned) |
| `--add-label <labels> <task>`  | Add labels                         |
| `--remove-label <labels>`      | Remove labels                      |
| `--reprioritize`               | Analyze and suggest changes        |

**Storage Modes:**

| Mode    | Storage                      | When                              |
| ------- | ---------------------------- | --------------------------------- |
| GitHub  | GitHub Issues                | Upstream zappzarapp on github.com |
| GitLab  | GitLab Issues                | Upstream zappzarapp on gitlab.com |
| Local   | `.ai/TASKS.md`               | Forks, other projects             |
| Private | `~/.local/share/zappzarapp/` | With `--private` flag             |

**Features:**

- **Milestones** instead of priority labels (v1.0, v1.1, Backlog)
- **Prioritization** via Milestone → Type → Age
- **Label typo detection** with "Did you mean...?" suggestions
- **Bidirectional board sync** (GitHub Projects / GitLab Issue Boards)
- **Platform parity** between GitHub and GitLab

**Setup:**

```bash
make ai-setup  # Auto-detects GitHub/GitLab, creates labels + milestones
```

### Self-Optimization with /optimize

The `/optimize` command analyzes and improves Claude's configuration.

**Phases:**

| Phase | Argument        | Purpose                                   |
| ----- | --------------- | ----------------------------------------- |
| 1     | `--config`      | CLAUDE.md structure, redundancy, clarity  |
| 2     | `--template`    | Sync CLAUDE.md ↔ CLAUDE.template.md       |
| 3     | `--terminology` | Find outdated terms across all files      |
| 4     | `--docs`        | Sync AI-INTEGRATION.md with actual config |
| 5     | `--learnings`   | Clean up LEARNINGS.md                     |
| 6     | `--skills`      | Audit skills (slash commands)             |
| 7     | `--sessions`    | Archive old sessions                      |
| 8     | `--settings`    | Optimize settings.json                    |
| 9     | `--all`         | Run all phases                            |

**Key Features:**

- **Template drift detection**: Finds when CLAUDE.md changes aren't in template
- **Terminology registry**: Tracks deprecated terms and naming conventions
- **Language check**: Finds non-English terms in English-only files
- **Cross-file consistency**: Verifies docs match actual configuration

## Agent Workflow

For complex tasks, Claude Code uses specialized agents based on task scope:

| Scope      | Trigger                    | Workflow                                     |
| ---------- | -------------------------- | -------------------------------------------- |
| Trivial    | 1 file, simple change      | Direct (typo, config, one-liner)             |
| Small      | 1-3 files, code changes    | Coder Agent → Lint → Test                    |
| Medium     | 3-10 files                 | Plan Mode → Coder → Lint → Test              |
| Large      | >10 files                  | 4-Agent-Model (Architect→Coder→Reviewer→Doc) |
| Quick Wins | Multiple independent tasks | Parallel Coder agents → Single commit        |
| Ad-hoc Fix | ≥2 languages, independent  | Parallel Fixer → Language-specific agents    |

### 4-Agent-Model

```text
Main Agent
    ↓
Agent A (Architect) — Analysis & planning
    ↓
Agent B1-B4 (Coder) — PHP/Node/Infra/SQL implementation (parallel if independent)
    ↓
Agent C1-C6 (Reviewer) — Quality checks per language (parallel)
    ↓
Agent D (Documenter) — Documentation updates
```

### Quick Wins Batch

For multiple small, independent tasks:

```text
Main Agent validates independence
    ↓
Spawn parallel Coder agents (one per task)
    ↓
Collect results → Run lint → Single commit
```

### Parallel Fixer

For ad-hoc fix requests involving multiple languages:

```text
Main Agent detects: PHP + Node files
    ↓
Spawn parallel Fixer agents (one per language)
    ↓
Collect results → Run lint
```

See `.claude/agents/workflow.md` for detailed documentation.

## make setup Behavior

`make setup` auto-detects contributor vs boilerplate mode and skips AI config
file swaps when developing zappzarapp itself. See
[CONTRIBUTING.md](../CONTRIBUTING.md) for details.

## Team Workflow

1. Claude Code is the source of truth for skills (`.claude/skills/`) and project
   rules
2. Team members using other tools maintain a shared `AGENTS.md` (see "Other
   Tools" above)

## Troubleshooting

### Hooks Not Working

1. Check format in `settings.json` (see Hooks Configuration above)
2. Run `/hooks` to see loaded hooks
3. Check `~/.claude/debug/` for settings loading logs
4. "Found 0 hook matchers" = hooks format broken
