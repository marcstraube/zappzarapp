# Development Dashboard

A comprehensive development dashboard for monitoring and managing your
Docker-based development environment.

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

- **Docker Containers**: Status of all containers (PHP, Node, Nginx, databases,
  Redis)
- **Database Connections**: PostgreSQL and MariaDB connection status
- **Services**: PHP-FPM, Node.js, Nginx health checks
- **SSL Certificates**: Certificate validity and expiration warnings

### ✅ Code Quality (Planned)

- PHPStan, PHPMD, ESLint status
- Test coverage reports
- Code metrics and trends

### 💾 Database Tools (Planned)

- Database statistics
- Connection management
- Query console
- Table browser

### 📝 Logs Viewer (Planned)

- Unified log viewer
- Real-time streaming
- Advanced filtering
- Log export

## Access

The dashboard is accessible at: **`/_dev`**

All dashboard routes are prefixed with `/_dev/`:

- `/_dev` - Dashboard home
- `/_dev/system` - System information
- `/_dev/health` - Health checks
- `/_dev/quality` - Code quality (planned)
- `/_dev/database` - Database tools (planned)
- `/_dev/logs` - Log viewer (planned)

### API Endpoints

JSON API endpoints for integrations:

- `/_dev/api/health-check` - Overall health status
- `/_dev/api/container-status` - Docker container status

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
│   └── SystemInfoService.php      # System information
├── Views/
│   ├── layout.php                 # Base layout
│   ├── dashboard.php              # Dashboard home
│   ├── system.php                 # System info page
│   ├── health.php                 # Health checks page
│   ├── quality.php                # Quality page (placeholder)
│   ├── database.php               # Database page (placeholder)
│   └── logs.php                   # Logs page (placeholder)
└── routes.php                     # Route definitions

tests/php/DevDashboard/
├── Controllers/
│   └── DashboardControllerTest.php
└── Services/
    ├── HealthCheckServiceTest.php
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

// Container status
$containers = $service->getContainerStatus();

// Database connections
$databases = $service->getDatabaseStatus();

// Service status
$services = $service->getServiceStatus();

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

## Future Enhancements

Planned features for future releases:

- **Code Quality Dashboard**: Real-time quality metrics
- **Database Tools**: Advanced database management
- **Log Viewer**: Unified log streaming and filtering
- **Performance Metrics**: Request times, memory usage
- **Dependency Insights**: Outdated packages, security alerts
- **Quick Actions**: One-click test runs, cache clearing
- **Custom Widgets**: Plugin system for custom dashboard widgets

## Contributing

This dashboard is part of the docker-webdev boilerplate. Contributions and
improvements are welcome!

## License

Part of the docker-webdev project.
