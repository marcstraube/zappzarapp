# IDE Integration

The zappzarapp boilerplate provides complete IDE integration for both PhpStorm
and VS Code, with pre-configured settings for Docker, debugging, code quality
tools, and database connections.

## Quick Links

For detailed IDE-specific setup and configuration:

- **PhpStorm/JetBrains IDEs**: See [`.idea/README.md`](../../../.idea/README.md)
- **Visual Studio Code**: See [`.vscode/README.md`](../../../.vscode/README.md)

## Overview

Both IDEs are configured with:

### Docker Integration

- PHP 8.4 via Docker Compose
- Automatic container management
- Path mappings for debugging
- Remote interpreter support

### Code Quality Tools

**PHP:**

- PHP CS Fixer (PER-CS standard)
- PHPStan (Level 5 static analysis)
- PHPMD (Mess Detection)
- PHPUnit (with coverage)

**JavaScript/TypeScript:**

- ESLint
- Prettier
- TypeScript type checking
- Vitest (with coverage)

### Debugging

- **PHP**: Xdebug 3.5.0 with path mappings
- **Node.js**: Chrome DevTools Protocol
- Breakpoints, step debugging, variable inspection

### Database Tools

Pre-configured connections for:

- PostgreSQL (Docker, localhost:5432)
- MariaDB (Docker, localhost:3306)
- Remote databases via SSH tunnel (optional)

### Run Configurations / Tasks

25+ pre-configured commands for:

- Docker operations (up, down, restart, rebuild)
- Quality checks (lint, format, analyze)
- Testing (PHP, Node.js, all)
- Documentation generation
- SSL certificate management

## Comparison

| Feature            | PhpStorm             | VS Code                  |
| ------------------ | -------------------- | ------------------------ |
| **Setup**          | Ready out-of-the-box | Install extensions first |
| **PHP Support**    | Built-in, excellent  | Via Intelephense         |
| **Refactoring**    | Advanced             | Basic                    |
| **Database Tools** | Built-in DataGrip    | Via SQL Tools extension  |
| **Performance**    | Resource-intensive   | Lightweight              |
| **Cost**           | Commercial (paid)    | Free                     |
| **Learning Curve** | Moderate             | Easy                     |
| **Customization**  | Extensive            | Highly customizable      |

## Common Tasks

### Format on Save

Both IDEs support automatic formatting:

- **PhpStorm**: `Settings → Tools → Actions on Save → Reformat code`
- **VS Code**: Enabled by default in workspace settings

### Run Quality Checks

- **PhpStorm**: Use Run Configurations dropdown (top-right)
- **VS Code**: `Ctrl+Shift+P` → "Tasks: Run Task"

### Debug PHP with Xdebug

1. Enable Xdebug: Set `XDEBUG_MODE=debug` in `.env`
2. Restart containers: `make restart`
3. Set breakpoints in PHP files
4. **PhpStorm**: `Run → Start Listening for PHP Debug Connections`
5. **VS Code**: Press `F5` → Select "Listen for Xdebug"
6. Trigger request in browser

### Database Access

**PhpStorm:**

1. Open `View → Tool Windows → Database`
2. Click data source → Test Connection
3. Password: `cat secrets/db_password.txt`

**VS Code:**

1. Open SQLTools sidebar (database icon)
2. Click connection → Enter password
3. Password: `cat secrets/db_password.txt`

## IDE-Specific Features

### PhpStorm Advantages

- Superior PHP type inference and code completion
- Advanced refactoring (extract method, inline, rename across files)
- Built-in database tools (no extension needed)
- Deep Laravel/Symfony framework integration
- Professional code navigation

### VS Code Advantages

- Lightweight and fast startup
- Highly extensible with thousands of extensions
- Better for frontend/JavaScript development
- Integrated terminal with full customization
- Free and open source

## Documentation Location

Component-specific documentation remains with the component for easy discovery:

```text
.idea/README.md          → PhpStorm configuration (331 lines)
.vscode/README.md        → VS Code configuration (303 lines)
tests/goss/README.md     → GOSS testing documentation
```

**Principle:** "Documentation lives with the code it describes"

## Quick Start

### PhpStorm

1. Open project in PhpStorm
2. Docker interpreter auto-configured
3. Start containers: `make up`
4. Verify interpreter: `Settings → PHP → CLI Interpreter`
5. Database password: `cat secrets/db_password.txt`

### VS Code

1. Open project in VS Code
2. Install recommended extensions (prompt appears)
3. Start containers: `make up`
4. Database password: `cat secrets/db_password.txt`
5. Enable Xdebug (optional): Set `XDEBUG_MODE=debug` in `.env`

## Automatic Config Locking

During `make setup`, certain IDE config files are automatically locked using
git's skip-worktree flag to prevent unnecessary git noise:

| File            | Reason                                                  |
| --------------- | ------------------------------------------------------- |
| `.idea/php.xml` | PhpStorm regenerates vendor paths on `composer install` |

This means local changes to these files won't appear in `git status`.

**For zappzarapp contributors** who need to commit changes to these files, see
[CONTRIBUTING.md](../CONTRIBUTING.md#ide-config-files).

## Troubleshooting

### Common Issues

**Docker interpreter not working:**

- Ensure Docker is running
- Restart IDE
- Verify `compose.yaml` exists in project root

**Xdebug not connecting:**

- Check `XDEBUG_MODE=debug` in `.env`
- Restart containers: `make restart`
- Verify port 9003 is not in use
- Check path mappings (PhpStorm: Settings → PHP → Servers)

**Database connection fails:**

- Ensure containers are running: `docker compose ps`
- Check credentials match `.env` file
- Verify ports: PostgreSQL (5432), MariaDB (3306)
- Password: `cat secrets/db_password.txt`

## Additional Resources

- [PhpStorm Documentation](https://www.jetbrains.com/help/phpstorm/)
- [VS Code PHP Development](https://code.visualstudio.com/docs/languages/php)
- [Xdebug Setup Guide](./../development/XDEBUG.md)
- [Docker Integration](../infrastructure/ARCHITECTURE.md)

## Support

For detailed setup instructions, configuration options, and advanced features,
consult the IDE-specific README files:

- `.idea/README.md` - Complete PhpStorm setup guide
- `.vscode/README.md` - Complete VS Code setup guide
