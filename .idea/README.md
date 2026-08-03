# PhpStorm/JetBrains IDE Configuration

This directory contains PhpStorm/JetBrains IDE project configuration for the
zappzarapp project.

## Files

### Project Settings

#### `php.xml`

PHP interpreter and quality tools configuration:

- **Docker Compose Interpreter**: PHP 8.5 via `compose.yaml`
- **PHP CS Fixer**: PER-CS standard with risky rules
- **PHPStan**: Level 5 static analysis
- **PHPMD**: Mess detection with custom ruleset
- **PHPUnit**: Test framework configuration
- **Xdebug**: Debugging support

#### `dataSources.xml`

Pre-configured database connections:

- **PostgreSQL (Docker)**: localhost:5432/app
  - Username: app
  - Password: secret (update from .env if changed)
- **MariaDB (Docker)**: localhost:3306/app
  - Username: app
  - Password: secret (update from .env if changed)

#### `inspectionProfiles/Project_Default.xml`

Code inspection settings, shared via git so all developers get the same
warnings:

- MessDetector validation (WEAK WARNING)
- PHP CS Fixer validation (WEAK WARNING)
- PHPStan global validation (WEAK WARNING)

#### `scopes/`

Named scopes for targeted inspection rules (shared via git). `Tests.xml`
defines the test file pattern:

```xml
<component name="DependencyValidationManager">
  <scope name="Tests" pattern="file:tests//*" />
</component>
```

The inspection profile references scopes to relax rules for specific file
sets. Example: "Variable only used in closure" is disabled for the Tests
scope, because defining test data at method start improves readability even
when it is only used in closures:

```xml
<inspection_tool class="PhpVariableUsedOnlyInClosureInspection" enabled="true">
  <scope name="Tests" level="INFORMATION" enabled="false" />
</inspection_tool>
```

### Run Configurations (`runConfigurations/`)

45 curated run configurations, one per frequently used Makefile target. Every
configuration invokes `make`, so CLI and IDE behave identically.

| Prefix         | Contents                                                             |
| -------------- | -------------------------------------------------------------------- |
| `Test:`        | PHPUnit / Vitest, coverage, watch mode, Xdebug (8)                   |
| `Quality:`     | check, CS, PHPStan, PHPMD, Rector, ESLint, Prettier, TS, linters (16) |
| `Docker:`      | Up, Down, Restart, Status, Build, Rebuild, Check Health (7)          |
| `Node:`        | Dev servers and builds (6)                                           |
| `Open:`        | Browser shortcuts: app, Dev Dashboard, API docs, coverage (4)        |
| Dependencies   | Composer / pnpm install + update (4)                                 |

The set is deliberately limited to frequent, non-interactive, non-destructive
actions. When adding new configurations, keep these rules:

- **Interactive targets** (shells, CLIs, monitors) belong in the IDE terminal,
  not in run configurations
- **Destructive targets** (`make fresh`, `make redis-flush`, secret rotation)
  must be typed consciously in a terminal
- **Rare / one-time targets** (setup, SSL, backups, releases) stay CLI-only —
  see `make help` for the full target list

**Access Run Configurations:** `Alt+Shift+F10` (Linux/Windows) or
`Ctrl+Option+R` (macOS)

### Other Configuration Files

#### `vcs.xml`

Version control settings (Git)

#### `jsLibraryMappings.xml`

JavaScript library mappings for auto-completion

#### `remote-mappings.xml`

Docker container path mappings for debugging

#### `php-test-framework.xml`

PHPUnit version cache

## Setup

### 1. Docker Interpreter

The Docker Compose interpreter is pre-configured. Verify it's working:

1. Go to: `Settings → PHP → CLI Interpreter`
2. Select: `Docker PHP` (should point to `compose.yaml`)
3. Click `...` to view interpreter details
4. Verify the PHP version matches the `PHP_VERSION` ARG in `docker/php/Dockerfile`
5. Check Xdebug is loaded

### 2. Database Tools

Database connections are pre-configured. Connect to databases:

1. Open: `View → Tool Windows → Database`
2. You'll see two data sources:
   - PostgreSQL (Docker)
   - MariaDB (Docker)
3. Click connection to test
4. If credentials changed in `.env`, update data sources:
   - Right-click data source → Properties
   - Update username/password

### 3. Enable Xdebug (for debugging)

Set in `.env`:

```bash
XDEBUG_MODE=debug
```

Restart containers:

```bash
make restart
```

Configure Xdebug in PhpStorm:

1. Go to: `Settings → PHP → Debug`
2. Verify Xdebug port: 9003
3. Path mappings are pre-configured: `/var/www/html` → project root

### 4. Code Style Settings

PHP CS Fixer is configured for format-on-save:

1. Go to: `Settings → Tools → External Tools`
2. Verify PHP CS Fixer is configured
3. Enable: `Settings → Tools → Actions on Save`
   - Check: "Reformat code"
   - Select: "PHP CS Fixer"

Alternatively, use Run Configuration: `Quality: CS Fix`

## Usage

### Running Tasks

**Via Run Configurations:**

1. Click dropdown next to Run button (top-right)
2. Select desired configuration
3. Click Run

**Via Keyboard Shortcuts:**

- `Alt+Shift+F10`: Show run configurations menu
- `Shift+F10`: Run last configuration
- `Ctrl+Shift+F10`: Run configuration from context

### Debugging

#### PHP (Xdebug)

1. Ensure `XDEBUG_MODE=debug` in `.env`
2. Set breakpoints in PHP files (click gutter)
3. Click: `Run → Start Listening for PHP Debug Connections`
4. Trigger request in browser
5. Debugger will pause at breakpoints

#### Node.js

1. Add Node.js debug configuration manually (if needed)
2. Set breakpoints in TypeScript/JavaScript files
3. Click Debug button

### Database Access

**Via Database Tool Window:**

1. Open: `View → Tool Windows → Database`
2. Expand data source
3. Double-click table to view data
4. Right-click → SQL Scripts → Console
5. Write and execute queries

**Via Run Queries:**

- `Ctrl+Enter`: Execute statement under cursor
- `Ctrl+Shift+Enter`: Execute all statements

### Code Quality

**Run Individual Tools:**

- Use run configurations: `Quality: CS Fix`, `Quality: PHPStan`, etc.
- Or via Makefile: Right-click `Makefile` → Run Make Target

**Run All Quality Checks:**

- Select: `Quality: Check All`
- Or terminal: `make check`

**Format Code:**

- `Ctrl+Alt+L`: Reformat file
- `Ctrl+Alt+Shift+L`: Reformat dialog with options

### Suppressing Inspections

File-wide inspections (e.g. DuplicatedCode) are NOT suppressed by
`@noinspection` in the class DocBlock. Place the suppression as a single-line
`/* */` comment (not a DocBlock) directly after the opening PHP tag:

```php
<?php
/* @noinspection DuplicatedCode Reason for the suppression */

declare(strict_types=1);
```

Distinction:

- **File-level**: single-line comment at file start — affects the entire file
  (DuplicatedCode, UnusedMethod, ...)
- **Class-level**: `@noinspection` in the class DocBlock — only affects class
  members

This keeps the class DocBlock clean for API documentation. Every suppression
must state its reason.

### Format on Save

Enable automatic formatting:

1. Go to: `Settings → Tools → Actions on Save`
2. Check: "Reformat code"
3. Select file types: PHP, JavaScript, TypeScript
4. Check: "Optimize imports"

## Comparison with VS Code

This PhpStorm configuration provides feature parity with VS Code:

| Feature            | PhpStorm            | VS Code               |
| ------------------ | ------------------- | --------------------- |
| PHP Interpreter    | Docker Compose      | Intelephense + Docker |
| Code Style         | PHP CS Fixer        | PHP CS Fixer          |
| Static Analysis    | PHPStan Level 5     | PHPStan Level 5       |
| Mess Detection     | PHPMD               | PHPMD                 |
| Testing            | PHPUnit             | PHPUnit Test Explorer |
| Debugging          | Xdebug 3.5.0        | Xdebug 3.5.0          |
| JS/TS Linting      | ESLint (built-in)   | ESLint                |
| Formatting         | Prettier (built-in) | Prettier              |
| Database Tools     | Built-in DataGrip   | SQL Tools             |
| Git Integration    | Built-in VCS        | GitLens               |
| Run Configurations | 45 curated          | 44 tasks              |
| Docker Integration | Built-in            | Docker Extension      |

## Troubleshooting

### Docker Interpreter not working

- Ensure Docker is running
- Restart PhpStorm
- Check: `Settings → PHP → CLI Interpreter`
- Verify `compose.yaml` is in project root

### PHP CS Fixer fails

- Ensure containers are running: `make up`
- Check vendor directory exists
- Run manually: `make php-cs-fix`

### PHPStan errors

- Verify level is set to 5
- Check `phpstan.neon.dist` exists
- Clear PHPStan cache: `rm -rf .phpstan.cache`

### Xdebug not connecting

- Verify `XDEBUG_MODE=debug` in `.env`
- Restart containers: `make restart`
- Check port 9003 is not in use
- Verify path mappings: `Settings → PHP → Servers`
- Start listening: `Run → Start Listening for PHP Debug Connections`

### Database connection fails

- Ensure database container is running: `docker compose ps`
- Check credentials match `.env` file
- Verify port is exposed: PostgreSQL (5432), MariaDB (3306)
- Test connection in Database tool window

### Run configurations not appearing

- Restart PhpStorm
- Check `.idea/runConfigurations/` directory exists
- Verify XML files are valid

## IDE-Specific Features

### PhpStorm Advantages

- **Built-in Database Tools**: Full DataGrip integration
- **Refactoring**: Advanced PHP refactoring support
- **Code Completion**: Superior PHP auto-completion
- **Framework Support**: Deep Laravel/Symfony integration
- **Type Inference**: Better PHP type system understanding

### Integration with Project Tools

All run configurations execute Makefile targets:

- Consistent behavior across IDEs
- Works in terminal, CI/CD, and IDE
- Single source of truth: `Makefile`

## Additional Resources

- [PhpStorm Documentation](https://www.jetbrains.com/help/phpstorm/)
- [Docker Plugin](https://www.jetbrains.com/help/phpstorm/docker.html)
- [Xdebug in PhpStorm](https://www.jetbrains.com/help/phpstorm/configuring-xdebug.html)
- [Database Tools](https://www.jetbrains.com/help/phpstorm/relational-databases.html)
- [PHPStan Integration](https://www.jetbrains.com/help/phpstorm/using-phpstan.html)
