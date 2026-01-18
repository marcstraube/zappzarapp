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

- `.claude/` - Claude Code settings
- `.cursor/` - Cursor IDE settings
- `.aider/` - Aider settings
- Any other AI assistant or personal IDE configurations

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

## Questions?

Check the [Troubleshooting Guide](TROUBLESHOOTING.md) or open an issue.
