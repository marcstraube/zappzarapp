# AI Integration

This project supports multiple AI coding assistants with a unified configuration
approach.

## Supported Tools

| Tool           | Commands | Rules | Agent Workflow |
| -------------- | -------- | ----- | -------------- |
| Claude Code    | Yes      | Yes   | Yes            |
| Gemini CLI     | Yes      | Yes   | No             |
| Cursor         | No       | Yes   | No             |
| GitHub Copilot | No       | Yes   | No             |
| Cline          | No       | Yes   | No             |
| Roo Code       | No       | Yes   | No             |

## 2-Layer Architecture

AI knowledge files are organized in two layers:

| Layer        | Location          | Committed | Purpose                 |
| ------------ | ----------------- | --------- | ----------------------- |
| `project`    | `.ai/`            | Yes       | Team-shared knowledge   |
| `zappzarapp` | `.zappzarapp/ai/` | Yes       | Boilerplate development |

### Knowledge Files

| File       | Layers | Available In        |
| ---------- | ------ | ------------------- |
| LEARNINGS  | 2      | project, zappzarapp |
| DECISIONS  | 2      | project, zappzarapp |
| REFERENCES | 2      | project, zappzarapp |

**Task Management:** Handled via `/tasks` command with automatic storage
detection (GitHub Issues, GitLab Issues, or local `.ai/TASKS.md`).

## Configuration

### Claude Code

#### Directory Structure

```text
.claude/
├── CLAUDE.md           # Instructions (swapped by make setup)
├── agents/             # Agent workflow definitions
├── commands/           # Slash commands (source of truth)
├── settings.json       # Shared permissions & hooks
├── settings.local.json # Personal overrides (gitignored)
├── sessions/           # Session logs (gitignored)
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

Hooks automate session management and provide contextual reminders.

**Available Hook Events:**

| Event              | When                       | Use Case                           |
| ------------------ | -------------------------- | ---------------------------------- |
| `SessionStart`     | Session begins             | Create session files, load context |
| `SessionEnd`       | Session ends               | Remind about cleanup tasks         |
| `UserPromptSubmit` | User sends message         | Analyze input, detect patterns     |
| `PreCompact`       | Before context compression | Save work before context loss      |
| `PreToolUse`       | Before tool execution      | Validate, block, or modify         |
| `PostToolUse`      | After tool execution       | Lint, notify, log                  |
| `Stop`             | Claude finishes responding | Post-response actions              |

**Project Hooks (`.claude/settings.json`):**

| Hook                | Script                  | Purpose                                             |
| ------------------- | ----------------------- | --------------------------------------------------- |
| `SessionStart`      | `session-start.sh`      | Creates pending session file                        |
| `SessionEnd`        | `session-end.sh`        | Reminds about Summary, Learnings                    |
| `UserPromptSubmit`  | `user-prompt-submit.sh` | Detects context continuation + implementation tasks |
| `PreCompact`        | (inline)                | Reminds to update session before compression        |
| `PostToolUse(Edit)` | `post-edit-lint.sh`     | Runs linters after file edits                       |

**Hook Format:**

```json
{
  "hooks": {
    "SessionStart": [
      {
        "matcher": "",
        "hooks": [
          {
            "type": "command",
            "command": "./.claude/hooks/session-start.sh"
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

#### UserPromptSubmit Detection Examples

When detecting a continued session:

```text
[Context Continuation] Previous session: session-2026-01-24-1827-tls-verification.md
```

When detecting an implementation task:

```text
[Implementation Task] Check .claude/agents/workflow.md for scope before starting.
```

### Gemini CLI

Generated from Claude commands via `make ai-commands-sync`.

```text
.gemini/
└── commands/           # Generated .toml files (gitignored)
```

### Other Tools

Rules are synced via `make ai-rules-sync` to:

- `.cursor/rules/`
- `.github/copilot-instructions.md`
- etc.

## Synchronization

### Commands (Claude ↔ Gemini)

```bash
# Sync to specific tool
make ai-commands-sync FROM=claude TO=gemini

# Sync to all supported tools
make ai-commands-sync FROM=claude
```

Uses
[ai-command-converter](https://github.com/Commands-com/ai-command-converter).

### Rules (All Tools)

```bash
# Sync CLAUDE.md rules to all tools
make ai-rules-sync
```

Uses [rulesync](https://github.com/dyoshikawa/rulesync).

### Both

```bash
# Sync everything
make ai-sync FROM=claude
```

### Task Integration Setup

```bash
# Initialize labels and milestones (auto-detects GitHub/GitLab)
make ai-setup
```

This creates:

- Standard labels (bug, enhancement, chore, status::in-progress, etc.)
- "Backlog" milestone for deferred tasks
- GitLab uses `::` for scoped labels (mutually exclusive)

### Environment Configuration

Set defaults in `.env.local`:

```bash
AI_SYNC_FROM=claude
# AI_SYNC_TO=gemini  # Optional: leave empty for all
```

## Slash Commands

Available commands in `.claude/commands/`:

| Command       | Purpose                                        |
| ------------- | ---------------------------------------------- |
| `/status`     | Project overview (Git, Docker, tasks)          |
| `/tasks`      | Task management with GitHub/GitLab integration |
| `/commit`     | Guided commit workflow with quality checks     |
| `/audit`      | Project audit (quality, security, docs)        |
| `/learnings`  | View and manage project learnings              |
| `/sync-check` | Verify config file synchronization             |
| `/optimize`   | Self-optimization of config, docs, terminology |

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
| 6     | `--commands`    | Audit slash commands                      |
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

When users clone the boilerplate and run `make setup`:

1. **CLAUDE.md swap**: Boilerplate instructions moved to
   `.zappzarapp/CLAUDE.md`, generic template copied to `.claude/CLAUDE.md`
2. **Gemini commands**: Not generated automatically, run `make ai-sync` if
   needed

After setup:

- `.claude/CLAUDE.md` contains generic project instructions
- `.zappzarapp/CLAUDE.md` preserves boilerplate-specific instructions

## Team Workflow

1. Claude Code is the source of truth for commands
2. Team members using other tools run `make ai-sync FROM=claude` after pulling
3. CaptainHook can notify when synced commands change (optional)

## Troubleshooting

### Hooks Not Working

1. Check format in `settings.json` (see Hooks Configuration above)
2. Run `/hooks` to see loaded hooks
3. Check `~/.claude/debug/` for settings loading logs
4. "Found 0 hook matchers" = hooks format broken

### Commands Not Syncing

1. Verify source files exist in `.claude/commands/`
2. Check Docker containers are running (`make up`)
3. Run with verbose output: `VERBOSE=1 make ai-commands-sync FROM=claude`
