# Development Dashboard

A comprehensive development dashboard for monitoring and managing your
Docker-based development environment.

## Minimal Requirements

The DevDashboard has minimal dependencies to ensure it works in most
configurations:

| Service     | Required | Notes                                        |
| ----------- | -------- | -------------------------------------------- |
| **Nginx**   | Yes      | Serves the dashboard                         |
| **PHP-FPM** | Yes      | Runs the dashboard PHP code                  |
| Node.js     | No       | Only needed for welcome page (Vite HMR)      |
| Database    | No       | Dashboard shows connection status if enabled |
| Redis       | No       | Dashboard shows connection status if enabled |

**Minimal configuration:**

```bash
# .env - Minimal setup for DevDashboard
ENABLE_PHP=true
ENABLE_NODE=false
ENABLE_DATABASE=false
ENABLE_REDIS=false
```

## Features

### 📊 Dashboard Overview

- Real-time system health status
- Quick access to all features
- Git repository status
- System information summary

### 💻 System Information

- Complete PHP information (phpinfo)
- PHP version and extensions
- Environment variables (sensitive data masked)
- Server configuration

### 🏥 Health Checks

Services are organized by category:

- **Core Services**: Nginx, PHP-FPM, Node.js (if enabled)
- **Data Services**: PostgreSQL/MariaDB, Redis (if enabled)
- **Optional Services**: Mercure, Meilisearch, Elasticsearch, Mailpit, MinIO,
  RabbitMQ (when enabled)

Additional health information:

- **Connection Tests**: Detailed database and Redis connectivity with version
  info
- **SSL Certificates**: Certificate validity and expiration warnings

### ✅ Code Quality

- PHPStan, PHPMD, PHP_CodeSniffer status
- ESLint, Prettier status
- Test commands and instructions

### 💾 Database Tools

- Database connection information
- Database type detection (PostgreSQL/MariaDB)
- Connection string examples

### 📝 Logs Viewer

- Unified log viewer for all services
- Application logs from `storage/logs/`
- Docker container logs access
- CLI command references

## Access

The dashboard is accessible at: **`/_dev`**

All dashboard routes are prefixed with `/_dev/`:

- `/_dev` - Dashboard home
- `/_dev/system` - System information
- `/_dev/health` - Health checks
- `/_dev/quality` - Code quality tools and commands
- `/_dev/database` - Database connection info
- `/_dev/logs` - Log viewer and commands

### API Endpoints

JSON API endpoints for integrations:

- `/_dev/api/health-check` - Overall health status
- `/_dev/api/services` - Service status by category (core/data/optional)
- `/_dev/api/logs?file=<filename>&lines=<n>` - Application log file content

## Configuration

### Environment Variables

**Disable in Production:**

```bash
# .env
ENABLE_DEV_DASHBOARD=false
```

The dashboard automatically disables in production unless explicitly enabled:

```bash
APP_ENV=production
ENABLE_DEV_DASHBOARD=false  # Dashboard disabled
```

## Security

### Sensitive Data Protection

The dashboard automatically masks sensitive environment variables:

- Passwords (`*PASSWORD*`)
- Secrets (`*SECRET*`)
- Keys (`*KEY*`)
- Tokens (`*TOKEN*`)
- Private data (`*PRIVATE*`)

### Production Safety

**Recommendations:**

1. Set `ENABLE_DEV_DASHBOARD=false` in production `.env`
2. Use firewall rules to block `/_dev` routes in production
3. Never expose development dashboard to public internet

## Architecture

### Directory Structure

```text
src/php/DevDashboard/
├── Controllers/
│   └── DashboardController.php    # Main controller
├── Services/
│   ├── HealthCheckService.php     # Health checks
│   ├── LogService.php             # Log management
│   └── SystemInfoService.php      # System information
├── Views/
│   ├── layout.php                 # Base layout
│   ├── dashboard.php              # Dashboard home
│   ├── system.php                 # System info page
│   ├── health.php                 # Health checks page
│   ├── quality.php                # Code quality page
│   ├── database.php               # Database info page
│   └── logs.php                   # Log viewer page
└── routes.php                     # Route definitions

tests/php/DevDashboard/
├── Controllers/
│   └── DashboardControllerTest.php
└── Services/
    ├── HealthCheckServiceTest.php
    ├── LogServiceTest.php
    └── SystemInfoServiceTest.php
```

### Integration

The dashboard integrates into the main application via `public/index.php`:

```php
// Check if request is for development dashboard
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if (str_starts_with($requestPath, '/_dev')) {
    require_once __DIR__ . '/../src/php/DevDashboard/routes.php';
}
```

### Routing

Simple function-based routing without external dependencies:

```php
route('GET', '/_dev', [$controller, 'index']);
route('GET', '/_dev/system', [$controller, 'system']);
route('GET', '/_dev/health', [$controller, 'health']);
```

## Services

### HealthCheckService

Provides health check functionality:

```php
$service = new HealthCheckService();

// Overall health status
$status = $service->getOverallStatus();
// Returns: ['status' => 'healthy|degraded', 'healthy_count' => 5, 'unhealthy_count' => 0, ...]

// Services by category (core, data, optional)
$services = $service->getServices();
// Returns: [
//   'core' => ['nginx' => [...], 'php' => [...], 'node' => [...]],
//   'data' => ['postgres' => [...], 'redis' => [...]],
//   'optional' => ['mercure' => [...], 'meilisearch' => [...], ...]
// ]

// Detailed connection tests (with version info)
$connections = $service->getConnections();
// Returns: [
//   'database' => ['connected' => true, 'type' => 'PostgreSQL', 'version' => '17.2', ...],
//   'redis' => ['connected' => true, 'type' => 'Redis', 'version' => '7.4.2', ...]
// ]

// SSL certificate info
$ssl = $service->getSslInfo();
```

### SystemInfoService

Provides system information:

```php
$service = new SystemInfoService();

// Basic info
$info = $service->getBasicInfo();

// PHP version
$version = $service->getPhpVersion();

// PHP extensions
$extensions = $service->getPhpExtensions();

// Environment variables (filtered)
$env = $service->getEnvironmentVariables();

// Git status
$git = $service->getGitStatus();
```

### LogService

Provides access to application and service logs:

```php
$service = new LogService();

// Get available log sources (only enabled services)
$sources = $service->getAvailableLogSources();
// Returns filtered list based on ENABLE_* environment variables

// Get application log files from storage/logs/
$logs = $service->getApplicationLogs();

// Read log file content (tail)
$content = $service->readLogFile('laravel.log', 100);

// Get CLI log commands
$commands = $service->getLogCommands();

// Get log statistics
$stats = $service->getLogStatistics();
```

## Testing

Run dashboard tests:

```bash
# Run all tests
make test-php

# Run only dashboard tests
docker compose exec php vendor/bin/phpunit tests/php/DevDashboard/
```

Test coverage:

```bash
docker compose exec php sh -c 'XDEBUG_MODE=coverage vendor/bin/phpunit \
  --coverage-html build/coverage \
  tests/php/DevDashboard/'
```

## Customization

### Adding New Pages

1. Create view file in `Views/`:

   ```php
   // Views/mypage.php
   <div class="bg-white rounded-lg shadow p-6">
       <h2><?= $title ?></h2>
       <!-- Your content -->
   </div>
   ```

2. Add route in `routes.php`:

   ```php
   route('GET', '/_dev/mypage', [$controller, 'mypage']);
   ```

3. Add controller method:

   ```php
   public function mypage(): void
   {
       $this->render('mypage', ['title' => 'My Page']);
   }
   ```

4. Add navigation link in `Views/layout.php`

### Adding New Services

1. Create service in `Services/`:

   ```php
   namespace DevDashboard\Services;

   class MyService
   {
       public function getData(): array
       {
           return ['key' => 'value'];
       }
   }
   ```

2. Use in controller:

   ```php
   require_once __DIR__ . '/../Services/MyService.php';
   $service = new MyService();
   $data = $service->getData();
   ```

## Removal

To completely remove the dashboard:

1. Delete directory:

   ```bash
   rm -rf src/php/DevDashboard/
   rm -rf tests/php/DevDashboard/
   ```

2. Remove from `public/index.php`:

   ```php
   // Remove the dashboard routing section
   ```

3. Update `Makefile` (remove DevDashboard from setup)

## Optional Services

The DevDashboard automatically detects and displays optional services based on
their `ENABLE_*` environment variables:

| Service       | Environment Variable   | Default |
| ------------- | ---------------------- | ------- |
| Mercure       | `ENABLE_MERCURE`       | false   |
| Meilisearch   | `ENABLE_MEILISEARCH`   | false   |
| Elasticsearch | `ENABLE_ELASTICSEARCH` | false   |
| Mailpit       | `ENABLE_MAILPIT`       | false   |
| MinIO         | `ENABLE_MINIO`         | false   |
| RabbitMQ      | `ENABLE_RABBITMQ`      | false   |

When a service is enabled, it appears in:

- **Health page**: Shows service status (running/stopped)
- **Logs page**: Shows log source for that service

See [Optional Services Documentation](../infrastructure/OPTIONAL-SERVICES.md)
for detailed setup instructions.

## Future Enhancements

Planned features for future releases:

- **Real-time Log Streaming**: WebSocket-based live log updates
- **Performance Metrics**: Request times, memory usage
- **Dependency Insights**: Outdated packages, security alerts
- **Quick Actions**: One-click test runs, cache clearing
- **Custom Widgets**: Plugin system for custom dashboard widgets

## Contributing

This dashboard is part of the docker-webdev boilerplate. Contributions and
improvements are welcome!

## License

Part of the docker-webdev project.
