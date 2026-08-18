# Visual Studio Code Configuration

This directory contains VS Code workspace configuration for the zappzarapp
project.

## Files

### `extensions.json`

Recommended extensions for optimal development experience:

**PHP Development:**

- Intelephense (bmewburn.vscode-intelephense-client)
- PHP Debug (felixfbecker.php-debug)
- PHP CS Fixer (junstyle.php-cs-fixer)
- PHPStan (swordev.phpstan)
- PHPMD (ecodes.vscode-phpmd)
- PHPUnit Test Explorer (recca0120.vscode-phpunit)

**JavaScript/TypeScript:**

- ESLint (dbaeumer.vscode-eslint)
- Prettier (esbenp.prettier-vscode)
- Vitest Explorer (vitest.explorer)

**Docker:**

- Docker (ms-azuretools.vscode-docker)
- Remote Containers (ms-vscode-remote.remote-containers)

**Utilities:**

- GitLens (eamodio.gitlens)
- EditorConfig (editorconfig.editorconfig)
- SQL Tools (mtxr.sqltools)
- TODO Tree (gruntfuggly.todo-tree)

### `settings.json`

> **Formatting note:** `make ide-config` rewrites this file through `jq`, so
> it is committed in jq's canonical style (2-space indent, one array element
> per line). Do not reformat it manually — Prettier does not cover `.vscode/`
> (the directory is not mounted into containers), and any other style would
> reappear as diff noise on the next `make ide-config` run.

Workspace settings including:

**PHP Configuration:**

- Intelephense with PHP 8.5 support
- PHP CS Fixer integration (PER-CS standard)
- PHPStan Level 8
- PHPMD integration
- Format on save enabled

**JavaScript/TypeScript:**

- ESLint validation
- Prettier formatting
- Auto imports
- TypeScript strict mode

**Editor:**

- Tab size: 4 for PHP, 2 for JS/TS
- Line length ruler at 120 chars
- Trim trailing whitespace
- Insert final newline
- Unix line endings (LF)

**Database Connections:**

- PostgreSQL (localhost:5432)
- MariaDB (localhost:3306)

### `tasks.json`

44 curated tasks, one per frequently used Makefile target. Every task invokes
`make`, so CLI and IDE behave identically.

| Prefix       | Contents                                                              |
| ------------ | --------------------------------------------------------------------- |
| `Test:`      | PHPUnit / Vitest, coverage, watch mode, Xdebug                        |
| `Quality:`   | check, CS, PHPStan, PHPMD, Rector, ESLint, Prettier, TS, linters      |
| `Docker:`    | Up, Down, Restart, Status, Build, Rebuild, Check Health               |
| `Node:`      | Dev servers and builds                                                |
| `Open:`      | Browser shortcuts: app, Dev Dashboard, API docs, coverage             |
| Dependencies | Composer / pnpm install + update                                      |

The set is deliberately limited to frequent, non-interactive, non-destructive
actions. When adding new tasks, keep these rules:

- **Interactive targets** (shells, CLIs, monitors) belong in the integrated
  terminal, not in tasks
- **Destructive targets** (`make fresh`, `make redis-flush`, secret rotation)
  must be typed consciously in a terminal
- **Rare / one-time targets** (setup, SSL, backups, releases) stay CLI-only —
  see `make help` for the full target list

**Access Tasks:** `Ctrl+Shift+B` (Linux/Windows) or `Cmd+Shift+B` (macOS)

### `launch.json`

Debug configurations:

**PHP Debugging:**

- Listen for Xdebug (port 9003)
- Launch currently open script
- Path mappings: `/var/www/html` → `${workspaceFolder}`

**Node.js Debugging:**

- Attach to Node Backend (port 9229)
- Launch Node Script

**Frontend Debugging:**

- Launch Chrome (<http://localhost:8080>)

**Compound Configurations:**

- Full Stack Debug (PHP + Frontend)
- Full Stack Debug (Node + Frontend)

**Access Debugging:** `F5` to start, `Shift+F5` to stop

## Setup

### 1. Install Recommended Extensions

When you open this project in VS Code, you'll be prompted to install recommended
extensions. Click "Install All" to get started.

Alternatively, run:

```bash
code --install-extension bmewburn.vscode-intelephense-client
code --install-extension dbaeumer.vscode-eslint
code --install-extension esbenp.prettier-vscode
# ... (see extensions.json for complete list)
```

### 2. Configure Intelephense License (Optional)

For premium Intelephense features:

1. Purchase license at <https://intelephense.com/>
2. Add to User Settings (`Ctrl+,`):

   ```json
   {
     "intelephense.licenceKey": "YOUR_LICENSE_KEY"
   }
   ```

### 3. Enable Xdebug (for PHP debugging)

Set in `.env`:

```bash
XDEBUG_MODE=debug
```

Restart containers:

```bash
make restart
```

### 4. Database Connections

Update credentials in `settings.json` if you changed them in `.env`:

```json
"sqltools.connections": [
  {
    "username": "your_db_user",
    "password": "your_db_password"
  }
]
```

## Usage

### Running Tasks

1. Open Command Palette: `Ctrl+Shift+P` (Linux/Windows) or `Cmd+Shift+P` (macOS)
2. Type "Tasks: Run Task"
3. Select desired task

Or use keyboard shortcuts:

- `Ctrl+Shift+B`: Run build task (Docker: Up)
- `Ctrl+Shift+T`: Run test task (PHP: Run Tests)

### Debugging

#### PHP (Xdebug)

1. Ensure `XDEBUG_MODE=debug` in `.env`
2. Set breakpoints in PHP files
3. Press `F5` or select "Listen for Xdebug (PHP)" configuration
4. Trigger request in browser

#### Node.js

1. Start Node.js backend in debug mode
2. Set breakpoints in TypeScript/JavaScript files
3. Select "Attach to Node (Backend)" configuration
4. Press `F5`

#### Frontend

1. Select "Launch Chrome (Frontend)" configuration
2. Press `F5`
3. Set breakpoints in browser DevTools or VS Code

### Format on Save

Files are automatically formatted on save:

- PHP: PHP CS Fixer (PER-CS standard)
- JavaScript/TypeScript: Prettier
- JSON/YAML: Prettier

### Linting

Linting happens in real-time:

- PHP: PHPStan Level 5, PHPMD
- JavaScript/TypeScript: ESLint

## Comparison with PhpStorm

This VS Code configuration provides feature parity with the PhpStorm setup:

| Feature         | PhpStorm        | VS Code               |
| --------------- | --------------- | --------------------- |
| PHP Interpreter | Docker Compose  | Intelephense + Docker |
| Code Style      | PHP CS Fixer    | PHP CS Fixer          |
| Static Analysis | PHPStan Level 8 | PHPStan Level 8       |
| Mess Detection  | PHPMD           | PHPMD                 |
| Testing         | PHPUnit         | PHPUnit Test Explorer |
| Debugging       | Xdebug 3.5.0    | Xdebug 3.5.0          |
| JS/TS Linting   | ESLint          | ESLint                |
| Formatting      | Prettier        | Prettier              |
| Database Tools  | Built-in        | SQL Tools             |
| Git Integration | Built-in        | GitLens               |

## Troubleshooting

### Intelephense not working

- Ensure PHP files are in workspace
- Check PHP version in settings (8.5)
- Restart VS Code
- Check Output panel → Intelephense

### PHP CS Fixer fails

- Ensure containers are running: `make up`
- Check path: `./vendor/bin/php-cs-fixer` exists
- Run manually: `make php-cs-fix`

### Xdebug not connecting

- Verify `XDEBUG_MODE=debug` in `.env`
- Restart containers: `make restart`
- Check port 9003 is not in use
- Verify path mappings in `launch.json`

### Tasks not working

- Ensure Makefile is in workspace root
- Check terminal working directory
- Verify Docker is running

## Additional Resources

- [VS Code PHP Development](https://code.visualstudio.com/docs/languages/php)
- [Intelephense Documentation](https://intelephense.com/)
- [Xdebug Setup](https://xdebug.org/docs/step_debug)
- [ESLint in VS Code](https://marketplace.visualstudio.com/items?itemName=dbaeumer.vscode-eslint)
- [Prettier in VS Code](https://marketplace.visualstudio.com/items?itemName=esbenp.prettier-vscode)
