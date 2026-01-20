# Contributing

Thank you for your interest in contributing to zappzarapp!

## Important: What NOT to Commit

### Lock Files

**Do not commit `composer.lock` or `pnpm-lock.yaml`.**

These files belong to the application developer, not the boilerplate:

- `composer.lock` - Generated when the app developer runs `composer install`
- `pnpm-lock.yaml` - Generated when the app developer runs `pnpm install`

The boilerplate provides `composer.json` and `package.json` as templates. Each
project using this boilerplate will have its own lock files.

### Personal Configuration

Do not commit personal tool configurations:

- `.claude/*` - Claude Code (except `settings.json` which contains shared
  permissions)
- `.cursor/` - Cursor IDE settings
- `.aider/` - Aider settings
- Any other AI assistant or personal IDE configurations

**Note:** `.claude/settings.json` is committed intentionally. It contains:

- Allowed tool permissions for project-specific scripts
- Denied commands (e.g., direct composer/pnpm usage to enforce make targets)
- Default hooks (e.g., reminder to rebuild after Docker config changes)

**Important:** If you create `.claude/settings.local.json` for personal
settings, hooks are completely overwritten (not merged). Copy any desired hooks
from `settings.json` to your local file.

## Development Workflow

### Before Committing

Run the full quality check suite:

```bash
make check
```

This runs: `cs-check`, `analyse`, `phpmd`, `rector-check`, `prettier-check`,
`type-check`, `lint-node`, `test`, `validate`, `lint-md`

### Commit Messages

Use [Conventional Commits](https://www.conventionalcommits.org/) format:

- `feat:` - New features
- `fix:` - Bug fixes
- `docs:` - Documentation changes
- `refactor:` - Code refactoring
- `test:` - Test additions or changes
- `chore:` - Maintenance tasks

Examples:

```text
feat(docker): add Redis cluster support
fix(nginx): resolve SSL certificate reload issue
docs(security): update encryption guide for PostgreSQL 17
```

### Git Hooks

The project uses [CaptainHook](https://github.com/captainhookphp/captainhook)
for automated checks:

- **pre-commit:** Auto-fixes code style (PHP-CS-Fixer, Prettier, ESLint,
  Markdownlint)
- **commit-msg:** Validates conventional commit format
- **pre-push:** Runs static analysis (PHPStan, TypeScript) and tests (PHPUnit,
  Vitest)

Install hooks after cloning:

```bash
vendor/bin/captainhook install
```

## Adding Documentation

1. Place files in the appropriate `documentation/` subdirectory
2. Update `documentation/README.md` with a link
3. Use UPPERCASE filenames (e.g., `NEW-FEATURE.md`)
4. Include cross-references to related documentation

## AI Tool Slash Commands

This project includes slash commands for AI coding assistants. Claude commands
are in `.claude/commands/`, Gemini commands in `.gemini/commands/`. These
commands are project-specific and work within this repository.

### Using /backlog Across Projects

The `/backlog` command is particularly useful for any project. To use it
globally:

1. Copy the command to your user commands directory:

   ```bash
   cp .claude/commands/backlog.md ~/.claude/commands/
   ```

2. The command supports three backlog locations:

   | Target    | Path                         | Purpose                  |
   | --------- | ---------------------------- | ------------------------ |
   | `project` | `./documentation/BACKLOG.md` | Team backlog (committed) |
   | `user`    | `./.claude/BACKLOG.md`       | Personal project tasks   |
   | `global`  | `~/.claude/BACKLOG.md`       | Cross-project tasks      |

3. Usage examples:

   ```bash
   /backlog                        # List project + user backlogs
   /backlog --add                  # Add task (defaults to project)
   /backlog --add --target user    # Add personal task
   /backlog --add --target global  # Add cross-project task
   /backlog --choose               # Select task to work on
   ```

**Note:** After copying, updates to the project's `/backlog` command won't
automatically sync to your global copy. Use `make claude-commands-install` to
sync updates.

## AI Tool Synchronization

This project supports multiple AI coding assistants through automated sync
tools:

### Supported Tools

| Tool           | Commands (`ai-commands-sync`) | Rules (`ai-rules-sync`) |
| -------------- | ----------------------------- | ----------------------- |
| Claude Code    | Yes                           | Yes                     |
| Gemini CLI     | Yes                           | Yes                     |
| Cursor         | No                            | Yes                     |
| GitHub Copilot | No                            | Yes                     |
| Cline          | No                            | Yes                     |
| Roo Code       | No                            | Yes                     |

**Note:** Command sync (slash commands) is limited to Claude ↔ Gemini due to
[ai-command-converter](https://github.com/Commands-com/ai-command-converter)
limitations. Rules sync supports all tools via
[rulesync](https://github.com/dyoshikawa/rulesync).

### Sync Commands

```bash
# Sync Claude commands to user's home directory
make claude-commands-install

# Sync commands between AI tools (Claude ↔ Gemini)
make ai-commands-sync FROM=claude TO=gemini
make ai-commands-sync FROM=claude              # Sync to all other tools

# Sync rules to all AI tools
make ai-rules-sync

# Sync both commands and rules
make ai-sync FROM=claude
```

### Configuration

Set defaults in `.env.local` to simplify sync commands:

```bash
AI_SYNC_FROM=claude
# AI_SYNC_TO=gemini  # Optional: leave empty to sync to all
```

Then simply run `make ai-commands-sync` without arguments.

### How It Works

- **Commands** (`.claude/commands/*.md`): Converted using
  [ai-command-converter](https://github.com/Commands-com/ai-command-converter)
- **Rules** (`.rulesync/*.md`, `CLAUDE.md`): Generated using
  [rulesync](https://github.com/dyoshikawa/rulesync)

### Team Workflow

1. Claude Code is the source of truth for commands
2. Team members using other tools run `make ai-commands-sync FROM=claude` after
   pulling
3. CaptainHook notifies when synced commands change

## Questions?

Check the [Troubleshooting Guide](TROUBLESHOOTING.md) or open an issue.
