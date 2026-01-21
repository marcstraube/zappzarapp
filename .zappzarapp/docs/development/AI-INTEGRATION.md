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

## 3-Layer Architecture

AI knowledge files are organized in three layers:

| Layer        | Location                     | Committed | Purpose                     |
| ------------ | ---------------------------- | --------- | --------------------------- |
| `zappzarapp` | `.zappzarapp/ai/`            | Yes       | Boilerplate development     |
| `project`    | `.ai/`                       | Yes       | Team-shared knowledge       |
| `personal`   | `~/.local/share/zappzarapp/` | No        | Private cross-project notes |

### Knowledge Files

Each layer can contain:

- `BACKLOG.md` — Tasks and todos
- `LEARNINGS.md` — Technical insights
- `DECISIONS.md` — Architecture decisions (ADR-style)
- `REFERENCES.md` — Useful documentation links

### Path Configuration

Override the personal path in `.zappzarapp/ai/config.local.md`:

```markdown
personal_knowledge_path: ~/.my-custom-path/
```

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

#### Settings Override Behavior

**Important:** `settings.local.json` completely **replaces** (not merges) hooks
from `settings.json`.

If you create a local settings file, copy any desired hooks from `settings.json`
to your local file.

#### Hooks Configuration

Hooks use this format:

```json
{
  "hooks": {
    "PostToolUse": [
      {
        "matcher": "Edit",
        "hooks": [
          {
            "type": "command",
            "command": "echo 'File edited'"
          }
        ]
      }
    ]
  }
}
```

Available hook types:

- `PreToolUse` — Before tool execution
- `PostToolUse` — After tool execution

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

### Environment Configuration

Set defaults in `.env.local`:

```bash
AI_SYNC_FROM=claude
# AI_SYNC_TO=gemini  # Optional: leave empty for all
```

## Slash Commands

Available commands in `.claude/commands/`:

| Command       | Purpose                                    |
| ------------- | ------------------------------------------ |
| `/status`     | Project overview (Git, Docker, backlog)    |
| `/backlog`    | Manage tasks (add, list, prioritize)       |
| `/commit`     | Guided commit workflow with quality checks |
| `/learnings`  | View and manage project learnings          |
| `/sync-check` | Verify config file synchronization         |
| `/optimize`   | Self-optimization of config and commands   |

### Using /backlog Across Projects

Copy to your user commands directory for global use:

```bash
cp .claude/commands/backlog.md ~/.claude/commands/
```

Targets:

| Target       | Path                                   | Purpose             |
| ------------ | -------------------------------------- | ------------------- |
| `zappzarapp` | `./.zappzarapp/ai/BACKLOG.md`          | Boilerplate tasks   |
| `project`    | `./.ai/BACKLOG.md`                     | Team backlog        |
| `personal`   | `~/.local/share/zappzarapp/BACKLOG.md` | Cross-project tasks |

Default target: `personal`

## Agent Workflow

For complex tasks, Claude Code uses specialized agents:

```text
Main Agent
    ↓
Agent A (Architect) — Analysis & planning
    ↓
Agent B1/B2/B3 (Coder) — PHP/Node/SQL implementation
    ↓
Agent C1-C5 (Reviewer) — Quality checks
    ↓
Agent D (Documenter) — Documentation updates
```

See `.claude/agents/` for detailed workflow documentation.

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
