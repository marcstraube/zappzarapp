# PhpStorm/JetBrains IDE Configuration

This directory contains PhpStorm/JetBrains IDE project configuration for the zappzarapp project.

## Files

### Project Settings

#### `php.xml`

PHP interpreter and quality tools configuration:

- **Docker Compose Interpreter**: PHP 8.4 via `compose.yaml`
- **PHP CS Fixer**: PER-CS standard with risky rules
- **PHPStan**: Level 5 static analysis
- **PHPMD**: Mess detection with custom ruleset
- **PHPUnit**: Test framework configuration
- **Xdebug**: Version 3.5.0 debugging support

#### `dataSources.xml`

Pre-configured database connections:

- **PostgreSQL (Docker)**: localhost:5432/app
  - Username: app
  - Password: secret (update from .env if changed)
- **MariaDB (Docker)**: localhost:3306/app
  - Username: app
  - Password: secret (update from .env if changed)

#### `inspectionProfiles/Project_Default.xml`

Code inspection settings:

- MessDetector validation (WEAK WARNING)
- PHP CS Fixer validation (WEAK WARNING)
- PHPStan global validation (WEAK WARNING)

### Run Configurations (`runConfigurations/`)

25 pre-configured run configurations organized by category:

#### Browser

- **Browser: Open App** - Open application in browser (localhost:8080)

#### Docker/Make

- **Make: Up** - Start all containers
- **Make: Down** - Stop all containers
- **Make: Restart** - Restart containers
- **Make: Fresh Build** - Clean rebuild all images (make fresh)
- **Make: Rebuild** - Rebuild Docker images (make rebuild)

#### PHP Quality

- **PHP: CS Fixer** - Format PHP code (PER-CS)
- **PHP: PHPStan** - Static analysis (Level 5)
- **PHP: PHPMD** - Mess detection
- **PHP: Run Tests** - Execute PHPUnit tests
- **PHP: Coverage Report** - Generate test coverage report

#### Node.js Quality

- **Node: ESLint** - Lint JavaScript/TypeScript
- **Node: Prettier** - Format JavaScript/TypeScript
- **Node: Type Check** - TypeScript type checking
- **Node: Run Tests** - Execute Vitest tests
- **Node: Coverage Report** - Generate test coverage report

#### General

- **Quality: Run All Checks** - Run all quality checks (PHP + Node.js)
- **Quality: Fix All** - Auto-fix all code issues
- **Test: Run All Tests** - Execute all tests (PHP + Node.js)

#### Documentation

- **Docs: Generate API Documentation** - Generate PHPDoc and TypeDoc

#### SSL

- **SSL: Generate Self-Signed** - Generate self-signed certificate for development
- **SSL: Show Certificate Info** - Display SSL certificate information

#### Utilities

- **Logs: View All** - View Docker container logs
- **Shell: PHP Container** - Open bash shell in PHP container
- **Shell: Node Container** - Open bash shell in Node container

**Access Run Configurations:** `Alt+Shift+F10` (Linux/Windows) or `Ctrl+Option+R` (macOS)

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
4. Verify PHP version: 8.4.15
5. Check Xdebug is loaded: Version 3.5.0

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

Alternatively, use Run Configuration: `PHP: CS Fixer`

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

- Use run configurations: `PHP: CS Fixer`, `PHP: PHPStan`, etc.
- Or via Makefile: Right-click `Makefile` → Run Make Target

**Run All Quality Checks:**

- Select: `Quality: Run All Checks`
- Or terminal: `make quality`

**Format Code:**

- `Ctrl+Alt+L`: Reformat file
- `Ctrl+Alt+Shift+L`: Reformat dialog with options

### Format on Save

Enable automatic formatting:

1. Go to: `Settings → Tools → Actions on Save`
2. Check: "Reformat code"
3. Select file types: PHP, JavaScript, TypeScript
4. Check: "Optimize imports"

## Comparison with VS Code

This PhpStorm configuration provides feature parity with VS Code:

| Feature | PhpStorm | VS Code |
|---------|----------|---------|
| PHP Interpreter | Docker Compose | Intelephense + Docker |
| Code Style | PHP CS Fixer | PHP CS Fixer |
| Static Analysis | PHPStan Level 5 | PHPStan Level 5 |
| Mess Detection | PHPMD | PHPMD |
| Testing | PHPUnit | PHPUnit Test Explorer |
| Debugging | Xdebug 3.5.0 | Xdebug 3.5.0 |
| JS/TS Linting | ESLint (built-in) | ESLint |
| Formatting | Prettier (built-in) | Prettier |
| Database Tools | Built-in DataGrip | SQL Tools |
| Git Integration | Built-in VCS | GitLens |
| Run Configurations | 25 pre-configured | 23 tasks |
| Docker Integration | Built-in | Docker Extension |

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
