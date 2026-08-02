# Database Tools

Development-only database administration and CLI tools for zappzarapp.

## Overview

| Tool        | Type   | Databases                   | Access                         |
| ----------- | ------ | --------------------------- | ------------------------------ |
| **Adminer** | Web UI | PostgreSQL, MariaDB, SQLite | `/_dev/adminer/`               |
| **pgAdmin** | Web UI | PostgreSQL                  | `/_dev/pgadmin/`               |
| **pgcli**   | CLI    | PostgreSQL                  | `make postgres-cli-enhanced`   |
| **mycli**   | CLI    | MariaDB                     | `make mariadb-cli-enhanced`    |
| **litecli** | CLI    | SQLite                      | `make sqlite-cli FILE=path.db` |

## Quick Start

### Web-Based Tools

```bash
# Start Adminer (universal DB admin)
make adminer-up
# Access: https://localhost:8443/_dev/adminer/

# Start pgAdmin (PostgreSQL-specific)
make pgadmin-up
# Access: https://localhost:8443/_dev/pgadmin/

# Start both
make db-tools-up

# Stop all database tools
make db-tools-down
```

### Enhanced CLI Tools

```bash
# PostgreSQL with auto-complete
make postgres-cli-enhanced

# MariaDB with auto-complete
make mariadb-cli-enhanced

# SQLite with auto-complete
make sqlite-cli FILE=storage/app.sqlite
```

## Web Tools

### Adminer

Lightweight, single-file database manager supporting multiple database types.

**Features:**

- PostgreSQL, MariaDB, MySQL, SQLite support
- SQL editor with syntax highlighting
- Import/Export (SQL, CSV, XML)
- User/permission management
- Table structure editing

**Access:** `https://localhost:8443/_dev/adminer/`

**Connection:**

- Server: `postgres` or `mariadb`
- Username: `app` (from .env `DB_USER`)
- Password: See `secrets/db_password.txt`
- Database: `app` (from .env `DB_NAME`)

#### Pre-configured Server Dropdown

The server dropdown on the login page is pre-filled via Adminer's
`login-servers` plugin (`docker/adminer/login-servers.php`):

```php
require_once('/var/www/html/plugins/login-servers.php');

return new AdminerLoginServers([
    'PostgreSQL (local)' => [
        'server' => 'postgres',
        'driver' => 'pgsql',
    ],
    'MariaDB (local)' => [
        'server' => 'mariadb',
        'driver' => 'server',  // 'server' = MySQL/MariaDB
    ],
]);
```

The Dockerfile copies the file to `/var/www/html/plugins-enabled/`. The plugin
file must RETURN the plugin instance — returning a plain array does not work.

### pgAdmin

Full-featured PostgreSQL administration tool.

**Features:**

- Query tool with explain plans
- ERD diagrams
- Backup/Restore wizards
- Server monitoring
- Role management

**Access:** `https://localhost:8443/_dev/pgadmin/`

**Pre-configured:** PostgreSQL connection is automatically configured. The first
time you access pgAdmin, you'll need to log in with:

- Email: `admin@localhost`
- Password: See `secrets/pgadmin_password.txt`

#### CSRF Protection with Reverse Proxy

pgAdmin runs behind nginx under `/_dev/pgadmin/`. In this setup CSRF token
validation fails ("Failed to load Preferences") because cookie path and origin
headers do not match. CSRF protection is therefore disabled via environment
variables in `compose.yaml`:

```yaml
environment:
  PGADMIN_CONFIG_WTF_CSRF_ENABLED: 'False'
  PGADMIN_CONFIG_ENHANCED_COOKIE_PROTECTION: 'False'
  SCRIPT_NAME: /_dev/pgadmin
```

**Security note:** This is acceptable only because these tools are
development-only (see [Security](#security)). A production pgAdmin must keep
proper CSRF protection enabled.

#### Dockerfile UID

The pgAdmin image has no `pgadmin` group, so `chown pgadmin:pgadmin` fails
during image builds. Use the numeric UID instead (5050 is the pgadmin user):

```dockerfile
RUN chown 5050:0 /pgadmin4/servers.json
```

## CLI Tools

Enhanced CLI clients with features like:

- Auto-completion for tables, columns, keywords
- Syntax highlighting
- Multi-line editing
- History with search

### pgcli (PostgreSQL)

```bash
make postgres-cli-enhanced
```

Or directly in container:

```bash
docker compose exec php pgcli -h postgres -U app -d app
```

### mycli (MariaDB)

```bash
make mariadb-cli-enhanced
```

Or directly in container:

```bash
docker compose exec php mycli -h mariadb -u app -p app
```

### litecli (SQLite)

```bash
make sqlite-cli FILE=storage/database.sqlite
```

Or directly in container:

```bash
docker compose exec php litecli /var/www/html/storage/database.sqlite
```

## Configuration

### Enable on Startup

Add to `.env` to start tools automatically with `make up`:

```bash
# Start Adminer with other services
ENABLE_ADMINER=true

# Start pgAdmin with other services
ENABLE_PGADMIN=true
```

### Manual Start

If not enabled in `.env`, start manually when needed:

```bash
make adminer-up   # Start Adminer
make pgadmin-up   # Start pgAdmin
```

## Security

**Development Only:** These tools are blocked in production mode
(`ZAPPZARAPP_ENV=production`).

All database tool targets check `ZAPPZARAPP_ENV` and refuse to run in
production:

```bash
# In production mode, this will fail:
$ APP_ENV=production make adminer-up
Error: Adminer is only available in development mode
Set ZAPPZARAPP_ENV=development in .env to enable
```

## Troubleshooting

### Cannot access via browser

1. Check the service is running:

   ```bash
   docker compose ps adminer
   docker compose ps pgadmin
   ```

2. Check nginx is proxying correctly:

   ```bash
   docker compose logs nginx | grep -i adminer
   ```

3. Rebuild nginx to pick up new config:

   ```bash
   make build nginx && make up
   ```

### pgAdmin slow to start

pgAdmin takes time to initialize on first start. Wait for health check:

```bash
docker compose ps pgadmin
# Wait until STATUS shows "healthy"
```

### CLI tools not found

Rebuild the PHP container to install CLI tools:

```bash
make build php
```
