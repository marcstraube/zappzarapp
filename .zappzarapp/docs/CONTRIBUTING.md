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

- `.claude/sessions/`, `.claude/state/`, `.claude/cache/` - Claude Code data
- `.gemini/` - Gemini CLI (generated via `make ai-sync`)
- `.cursor/` - Cursor IDE settings
- `.aider/` - Aider settings
- Any other AI assistant or personal IDE configurations

**Note:** `.claude/settings.json` is committed intentionally (shared permissions
and hooks). See [AI Integration](development/AI-INTEGRATION.md) for details.

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

#### Automatic Setup

Git hooks are installed automatically when running `make setup` (if local
composer is available).

#### Manual Setup

If `make setup` shows a warning about missing local composer:

```bash
# 1. Install composer (https://getcomposer.org/download/)
# 2. Install local PHP dependencies (for IDE + hooks)
make composer-install-local

# 3. Install Git hooks
vendor/bin/captainhook install
```

**Note:** The `pre-commit` hook runs lint-staged in Docker, so you also need:

```bash
make pnpm-install    # Ensures lint-staged is available in the container
```

## Adding Documentation

1. Place files in the appropriate `.zappzarapp/docs/` subdirectory
2. Update `.zappzarapp/docs/README.md` with a link
3. Use UPPERCASE filenames (e.g., `NEW-FEATURE.md`)
4. Include cross-references to related documentation

## AI Tools

This project supports multiple AI coding assistants (Claude, Gemini, Cursor,
Copilot, etc.) with:

- Slash commands in `.claude/commands/`
- Automated sync between tools (`make ai-sync`)
- 3-layer knowledge architecture (zappzarapp/project/personal)
- Agent workflow for complex tasks

See [AI Integration](development/AI-INTEGRATION.md) for full documentation.

## Questions?

Check the [Troubleshooting Guide](TROUBLESHOOTING.md) or open an issue.
