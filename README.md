# PHP Web Development Environment (Docker Compose)

A production-ready Docker environment for high-performance PHP web development, based on Alpine Linux, Nginx, 
and PHP-FPM 8.4. This setup provides a secure, efficient, and fully configurable environment suitable for various PHP
applications.

## Features

- **Alpine Linux 3.22** - Minimal and secure base image with pinned versions
- **Nginx** - High-performance web server with optimized configuration
- **PHP-FPM 8.4** - With comprehensive extension support
- **Composer** - Automatic dependency installation on startup
- **Configurable** - Environment-based configuration (development/production)
- **Best Practices** - Security, performance, and comprehensive logging
- **Xdebug** - Full debugging support in development mode

## Prerequisites

- Docker (20.10 or higher)
- Docker Compose V2
- Make (optional, but recommended)

## Quick Start

### 1. Setup

```bash
# With Make
make setup

# Or manually
mkdir -p app/src data logs/{app,nginx,php} vendor
cp .env.example .env
```

### 2. Configure Environment

Edit the `.env` file:

```bash
# Environment: development or production
ENV=development

# Nginx Port
NGINX_PORT=8080

# Log Directory
LOG_DIR=./logs

# Storage Directory (for application data storage)
STORAGE_DIR=./storage

# User/Group IDs (recommended: set to your host user)
USER_ID=1000
GROUP_ID=1000
```

### 3. Start Containers

```bash
# With Make
make build
make up

# Or with Docker Compose
docker compose build
docker compose up -d
```

Your application is now available at `http://localhost:8080`.

## Directory Structure

```
.
├── docker/
│   ├── nginx/
│   │   ├── Dockerfile
│   │   ├── nginx.conf
│   │   └── conf.d/
│   │       └── default.conf
│   └── php/
│       ├── Dockerfile
│       ├── docker-entrypoint.sh
│       ├── php.ini                    # Base configuration
│       ├── php-fpm.conf
│       └── conf.d/
│           ├── development.ini        # Development overrides
│           ├── production.ini         # Production overrides
│           └── xdebug.ini             # Xdebug configuration (dev only)
├── app/
│   ├── public/          # Document Root (web-accessible files)
│   │   └── index.php
│   └── src/             # Application logic (classes, services)
├── storage/             # Application data storage (mounted to /var/www/html/storage)
├── logs/
│   ├── app/             # Application logs
│   ├── nginx/           # Nginx access and error logs
│   └── php/             # PHP-FPM logs
├── vendor/              # Composer dependencies (stored in named volume)
├── composer.json
├── composer.lock
├── .env
├── compose.yaml         # Base configuration
├── compose.override.yaml # Development configuration (auto-loaded)
├── compose.prod.yaml    # Production configuration
├── Makefile
└── README.md
```

## Make Commands

```bash
make help        # Show all available commands
make setup       # Create project structure
make build       # Build Docker images
make up          # Start containers
make down        # Stop containers
make restart     # Restart containers
make logs        # Show all logs
make logs-php    # Show PHP logs only
make logs-nginx  # Show Nginx logs only
make shell-php   # Open shell in PHP container
make shell-nginx # Open shell in Nginx container
make composer    # Execute Composer command
make clean       # Clean up containers and volumes
make rebuild     # Complete rebuild
```

## Using Composer

```bash
# Install package
make composer CMD="require vendor/package"

# Or directly in container
docker compose exec php composer require vendor/package
```

## PHP Configuration

### Memory and Timeouts

PHP is configured with reasonable defaults that can be adjusted:

- `memory_limit = 512M`
- `max_execution_time = 300`
- `post_max_size = 50M`
- `upload_max_filesize = 50M`

Base configuration is in `docker/php/php.ini`.

### Environment-Specific Configuration

PHP uses a base configuration with environment-specific overrides:

- **Base**: `docker/php/php.ini` - Common settings for all environments
- **Development**: `docker/php/conf.d/development.ini` - Development overrides
- **Production**: `docker/php/conf.d/production.ini` - Production overrides

**Development settings:**
```ini
display_errors = On
opcache.revalidate_freq = 0  # Reload code on every request
error_reporting = E_ALL
zend_extension = xdebug.so   # Load Xdebug
```

**Production settings:**
```ini
display_errors = Off
opcache.validate_timestamps = 0  # Maximum performance
error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT
# Xdebug not loaded
```

The appropriate configuration is loaded automatically based on the `ENV` variable in your `.env` file.

### OPcache

OPcache is enabled for better performance:

- **Production**: Aggressive caching, no timestamp validation
- **Development**: Revalidates on every request for immediate code changes

### Extensions

The following PHP extensions are installed:

- **GD** - With JPEG, PNG, WebP, AVIF support
- **GraphicsMagick (gmagick)** - For advanced image processing
- **OPcache** - Performance optimization
- **Exif** - Image metadata handling
- **Zip** - Archive handling for Composer
- **Xdebug** - Debugging and profiling (enabled in development only via development.ini)

### Adding PHP Extensions

Edit `docker/php/Dockerfile`:

```dockerfile
# For standard extensions
RUN docker-php-ext-install extension_name

# For PECL extensions
RUN pecl install extension_name && \
    docker-php-ext-enable extension_name

# For extensions via PIE
RUN pie install package/extension
```

Then rebuild:
```bash
make rebuild
```

## Development vs. Production

### Development Mode

```bash
# In .env
ENV=development
```

**Features:**
- Composer installs dev dependencies
- Verbose error reporting and display
- Xdebug loaded and enabled for debugging
- OPcache revalidates on every request
- Full exception details visible
- Assertions enabled

### Production Mode

```bash
# In .env
ENV=production
```

**Features:**
- Production dependencies only
- Optimized Composer autoloader
- Errors logged only, not displayed
- Xdebug not loaded (zero overhead)
- Aggressive OPcache settings
- Security-hardened exception handling
- Assertions disabled

## Logging

Logs are written to the configured LOG_DIR:

```
logs/
├── app/             # Your application logs
│   └── app.log
├── nginx/
│   ├── access.log
│   └── error.log
└── php/
    ├── access.log
    ├── error.log
    ├── slow.log     # Queries taking >30s
    └── xdebug.log   # Xdebug debug info
```

View logs in real-time:

```bash
make logs        # All logs
make logs-nginx  # Nginx only
make logs-php    # PHP-FPM only
```

## Security Features

- PHP `expose_php` disabled
- Dangerous functions disabled (`exec`, `shell_exec`, `system`, `popen`)
- Security headers in Nginx (X-Frame-Options, X-Content-Type-Options, X-XSS-Protection)
- Non-root processes (www-data user)
- Read-only mounts where possible
- Hidden file access denied
- Composer/Git file access denied
- Configurable user/group IDs to match host permissions

## Performance Optimizations

### Nginx

- Sendfile enabled for efficient file serving
- Gzip compression for text and JavaScript
- Long cache headers for static assets (1 year)
- Optimized buffer sizes for large files
- EPoll event processing

### PHP-FPM

- Dynamic process manager
- 50 max children (configurable)
- Request pooling and termination
- Slow-log for performance monitoring (30s threshold)
- 500 max requests per worker (prevents memory leaks)

### Composer Dependencies

- Stored in named Docker volume (`vendor-data`)
- Persists across container restarts
- No reinstallation needed on rebuild
- Better performance than host mounts

## Debugging with Xdebug

Xdebug is installed via PIE (PHP Installer for Extensions) but only loaded in development mode through
`development.ini`. This ensures zero overhead in production.

### Configuration

Xdebug is automatically configured in development mode:
- **Loaded via**: `development.ini` (zend_extension=xdebug.so)
- **Configured via**: `xdebug.ini` (mounted only in development)
- **Mode**: `develop,debug` (improved var_dump + step debugging)
- **IDE Key**: `PHPSTORM`
- **Port**: `9003`
- **Host**: `host.docker.internal` (automatically connects to your IDE)
- **Trigger**: Only starts when requested (via browser extension or query parameter)

### PhpStorm Setup

#### 1. Configure PHP Interpreter

Follow the official guide: [Configuring Remote Interpreters](https://www.jetbrains.com/help/phpstorm/configuring-remote-interpreters.html)

1. Go to **Settings → PHP**
2. Click **...** next to CLI Interpreter
3. Click **+** → **From Docker, Vagrant, VM, WSL, Remote...**
4. Select **Docker Compose**
5. Configuration file: `./compose.yaml`
6. Service: `php`

#### 2. Configure Xdebug

Follow the official guide: [Configuring Xdebug](https://www.jetbrains.com/help/phpstorm/configuring-xdebug.html)

1. Go to **Settings → PHP → Debug**
2. Set **Debug port**: `9003`
3. Enable **Can accept external connections**
4. **Path mappings**:
   - Project root → `/var/www/html`
   - `app/public` → `/var/www/html/app/public`

#### 3. Configure Server

1. Go to **Settings → PHP → Servers**
2. Click **+** to add a server
3. **Name**: `Docker` (or any name matching your IDE key)
4. **Host**: `localhost`
5. **Port**: `8080` (or your `NGINX_PORT`)
6. **Debugger**: `Xdebug`
7. **Use path mappings**: ✓
   - Map project root to `/var/www/html`

### Starting a Debug Session

#### Option 1: Browser Extension (Recommended)

1. Install browser extension:
   - **Chrome**: [Xdebug Helper](https://chrome.google.com/webstore/detail/xdebug-helper/eadndfjplgieldjbigjakmdgkmoaaaoc)
   - **Firefox**: [Xdebug Helper](https://addons.mozilla.org/en-US/firefox/addon/xdebug-helper-for-firefox/)

2. Configure extension with IDE Key: `PHPSTORM`

3. Start debugging:
   - Click **Start listening for PHP Debug Connections** in PhpStorm
   - Enable debug in browser extension
   - Refresh your page

#### Option 2: Query Parameter

Add `XDEBUG_TRIGGER=1` to any URL:

```
http://localhost:8080/?XDEBUG_TRIGGER=1
http://localhost:8080/api/endpoint?XDEBUG_TRIGGER=1
```

#### Option 3: Cookie

Set a cookie manually:
```
XDEBUG_TRIGGER=PHPSTORM
```

### Troubleshooting Xdebug

**Check if Xdebug is loaded:**

```bash
# In development mode
docker compose exec php php -m | grep xdebug
# Should output: xdebug

# In production mode
docker compose exec php php -m | grep xdebug
# Should output: nothing
```

**Enable verbose logging:**

Edit `docker/php/conf.d/xdebug.ini`:
```ini
xdebug.log_level=7  # Very verbose
```

Then `make restart` and check `logs/php/xdebug.log`.

**Firewall issues:**

Ensure PhpStorm can receive connections on port 9003. On some systems, you may need to allow this in your firewall.

### Xdebug in Production

Xdebug is **not loaded in production mode** - providing zero overhead. The extension is installed in the Docker image
(via PIE with `--no-enable` flag) but only activated through `development.ini` in development mode.

## Health Checks

The Nginx container includes a health check endpoint:

```bash
# Check container health
docker compose ps

# Manual health check
curl http://localhost:8080/health
```

## Troubleshooting

### Containers Won't Start

```bash
# Check logs
make logs

# Check container status
docker compose ps

# Inspect specific container
docker compose logs php
docker compose logs nginx
```

### Permission Issues

The most common issue is file permission mismatches between host and container.

**Solution:**

```bash
# Get your IDs
id -u  # Your User ID
id -g  # Your Group ID

# Update .env
USER_ID=1000  # Your user ID
GROUP_ID=1000 # Your group ID

# Rebuild containers
make rebuild
```

### Composer Issues

```bash
# Manual installation
make shell-php
composer install

# Clear Composer cache
docker compose exec php composer clear-cache

# Update dependencies
make composer CMD="update"
```

## Monitoring

### PHP-FPM Status

```bash
# Access status page
curl http://localhost:8080/status

# Access ping endpoint
curl http://localhost:8080/ping
```

### Slow Queries

Check `logs/php/slow.log` for requests taking longer than 30 seconds.

## Customization

### Nginx Configuration

Modify `docker/nginx/conf.d/default.conf` for custom routing, caching rules, or server settings.

Common customizations:
- Add additional server blocks
- Configure SSL/TLS
- Adjust buffer sizes
- Add custom headers
- Configure rate limiting

### PHP Settings

- **Base settings**: Edit `docker/php/php.ini`
- **Development-specific**: Edit `docker/php/conf.d/development.ini`
- **Production-specific**: Edit `docker/php/conf.d/production.ini`

After changes:
```bash
make restart
```

### Adjusting Resources

**PHP-FPM processes** (`docker/php/php-fpm.conf`):
```ini
pm.max_children = 50          # Adjust based on your needs
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
```

**PHP memory** (`docker/php/php.ini`):
```ini
memory_limit = 512M           # Increase for memory-intensive tasks
```

**Request timeouts** (`docker/php/php-fpm.conf`):
```ini
request_terminate_timeout = 300s  # Adjust for long-running processes
```

## Additional Resources

- [Docker Documentation](https://docs.docker.com/)
- [Docker Compose Documentation](https://docs.docker.com/compose/)
- [PHP Documentation](https://www.php.net/docs.php)
- [Nginx Documentation](https://nginx.org/en/docs/)
- [PhpStorm Docker Documentation](https://www.jetbrains.com/help/phpstorm/docker.html)
- [Xdebug Documentation](https://xdebug.org/docs/)
- [Docker & Xdebug Guide](https://www.jetbrains.com/help/phpstorm/configuring-xdebug.html#integrationWithProduct)

## License

Proprietary - All rights reserved. This software is the property of **[Your Company Name]** and may not be distributed, 
modified, or used without explicit permission.




## Dependency Management and Auto-Updates (Renovate)

This project utilizes **Renovate** to automatically manage dependency updates across the primary ecosystems: PHP (Composer), Node.js (npm), and Docker.
The core functionality is configured in the `renovate.json` file and includes:
- **Grouping**: Multiple small updates are grouped into a single Pull Request (PR) to reduce notification noise.
- **Automerge**: Patch-level updates are automatically merged once CI/tests have completed successfully.
- **Exclusion**: Critical components (such as major frameworks) are excluded from automatic grouping for individual manual review.
**For detailed help and advanced options, please refer to the official Renovate documentation: https://docs.renovatebot.com/**

### Running the Scanner (`make renovate`)

The `make renovate` command encapsulates the Docker execution of the scanner and automatically distinguishes between two modes, based on the environment variables defined in your `.env` file.

#### 1. Platform Mode (CI/CD Automation)

This mode connects to your Git host (GitHub, GitLab) and creates Pull Requests (PRs) for updates. It is intended for automated execution within CI/CD pipelines.

⚙️ Configuration (in .env):

To enable this mode, the following variables must be set:

| Variable              | Example Value                    | Description                                                          |
|-----------------------|----------------------------------|----------------------------------------------------------------------|
| `RENOVATE_PLATFORM`   | `github`                         | The Git platform being used (`github`, `gitlab`, etc.).              |
| `RENOVATE_REPO_SLUG`  | `your-organization/your-project` | The full name of the repository.                                     |
| `GITHUB_COM_TOKEN`    | `ghp_...`                        | The Personal Access Token (PAT) with write access to the repository. |

#### Execution:

When the necessary variables are set, the `make renovate` command will detect the platform, authenticate, and create all necessary PRs.
```
make renovate
```

### 2. Local Filesystem Mode (Manual Update)

This is the **default mode** (fallback) when the variables `RENOVATE_PLATFORM` and `RENOVATE_REPO_SLUG` in the `.env` are empty.

- The scanner runs against the local code clone.
- It modifies files directly (`composer.json`, `package.json`, etc.) in your local branch. 
- No Pull Requests are created.

#### Workflow:

This mode is ideal for manual reviews. Always run it on a new branch:

1. `git checkout -b renovate-updates` 
2. `make renovate` 
3. Review the changes (`git diff`) and commit/push them manually.

```
make renovate
```
