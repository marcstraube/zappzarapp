# Developer Toolbar

## Overview

The Developer Toolbar provides real-time debugging information during
development, including request details, database queries, log messages, and
exceptions. It's designed to be minimal, non-intrusive, and secure-by-default.

## Features

- **Always Visible Mini Bar**: Request time, memory usage, query count
- **Expandable Panel**: Detailed debugging information with tabbed interface
- **REQUEST Tab**: HTTP method, headers, body, session, cookies
- **MESSAGES Tab**: Log messages from Monolog (when configured)
- **QUERIES Tab**: Database queries with execution time and stack traces
- **EXCEPTIONS Tab**: All exceptions (even caught ones)
- **Security-by-Design**: Multiple layers of protection
- **Zero-Config**: Works automatically in development mode

## Access

The toolbar automatically appears on all HTML pages in development mode. It will
not appear:

- In production (`APP_ENV=production`)
- When explicitly disabled (`ENABLE_DEV_TOOLBAR=false`)
- In CLI mode
- On AJAX requests

## Configuration

### Enable/Disable

```bash
# .env
ENABLE_DEV_TOOLBAR=true   # Default in development
ENABLE_DEV_TOOLBAR=false  # Disable explicitly
```

The toolbar is automatically disabled in production (`APP_ENV=production`).

### Integration

To enable the toolbar in your application, add this to your `public/index.php`:

```php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// Start DevToolbar (if enabled)
if (DevToolbar\Guard\DevToolbarGuard::isEnabled()) {
    $toolbar = DevToolbar\DevToolbar::getInstance();
    $toolbar->boot();

    // Register shutdown handler to inject toolbar HTML
    register_shutdown_function([$toolbar, 'render']);
}

// ... rest of application bootstrap ...
```

### Monolog Integration

To capture log messages in the toolbar, add the MessageCollector to your Monolog
configuration:

```php
<?php

use DevToolbar\DataCollectors\MessageCollector;
use Monolog\Logger;

$logger = new Logger('app');

// Add DevToolbar handler in development
if (DevToolbar\Guard\DevToolbarGuard::isEnabled()) {
    $messageCollector = new MessageCollector();
    $logger->pushHandler($messageCollector);
}

// ... other handlers ...
```

### Query Tracking

To track database queries, use the QueryCollector singleton:

```php
<?php

use DevToolbar\DataCollectors\QueryCollector;

$collector = QueryCollector::getInstance();

// Before query execution
$start = hrtime(true);
$result = $pdo->query($sql);
$time = (hrtime(true) - $start) / 1_000_000; // Convert to ms

// Track the query
$collector->trackQuery($sql, $bindings, $time);
```

### Exception Tracking

To track exceptions, use the ExceptionCollector singleton:

```php
<?php

use DevToolbar\DataCollectors\ExceptionCollector;

$collector = ExceptionCollector::getInstance();

try {
    // Your code
} catch (\Exception $e) {
    // Track the exception
    $collector->trackException($e, handled: true);
    // Handle exception
}
```

## Usage

### Basic Usage

1. Load any page in development mode
2. Mini bar appears in bottom-right corner showing:
   - Environment (DEV/STAGING)
   - Request execution time
   - Peak memory usage
   - Number of database queries
3. Click mini bar to expand full panel
4. Click tabs to switch between views
5. Click close button (▼) or press ESC to collapse panel

### Query Analysis

1. Open toolbar
2. Click QUERIES tab
3. Review query list with color-coded performance indicators:
   - **Green**: Fast (<100ms)
   - **Orange**: Slow (100-500ms)
   - **Red**: Very slow (>500ms)
4. Review query bindings and stack traces
5. Identify N+1 query problems or slow queries

### Log Message Review

1. Open toolbar
2. Click MESSAGES tab
3. Messages are color-coded by level:
   - **Gray**: DEBUG
   - **Cyan**: INFO
   - **Yellow**: WARNING
   - **Red**: ERROR
4. Review message timestamps and context

### Exception Review

1. Open toolbar
2. Click EXCEPTIONS tab
3. Exceptions are color-coded:
   - **Yellow**: Handled exceptions
   - **Red**: Unhandled exceptions
4. Review exception class, message, file, and line number

## Security

### Sensitive Data Protection

The toolbar automatically filters sensitive data in:

- Request POST data
- Headers
- Cookies
- Session data

Filtered patterns include:

- `password`, `passwd`, `pwd`
- `secret`, `token`, `api_key`
- `private_key`, `access_token`, `refresh_token`
- `session`, `cookie`, `authorization`
- `credit_card`, `cvv`, `ssn`

Filtered values show as `[FILTERED]`.

### Production Safety

The toolbar is protected by multiple security layers:

1. **Environment Check**: Disabled in production (`APP_ENV=production`)
2. **Explicit Disable**: Respects `ENABLE_DEV_TOOLBAR=false`
3. **CLI Detection**: Never loads in CLI mode
4. **AJAX Skip**: Skips AJAX requests to avoid breaking JSON responses

## Troubleshooting

### Toolbar Not Appearing

1. Check environment: `echo $APP_ENV` (should be `development`)
2. Check flag: `echo $ENABLE_DEV_TOOLBAR` (should be `true` or unset)
3. Verify page contains `</body>` tag (required for injection)
4. Check browser console for JavaScript errors

### Queries Not Showing

1. Verify `QueryCollector::trackQuery()` is being called
2. Ensure toolbar is booted before queries execute
3. Check that collector is started

### Messages Not Showing

1. Verify Monolog is configured with `MessageCollector` handler
2. Check log level threshold
3. Ensure messages are logged after toolbar boot

### Styling Issues

1. All CSS and JavaScript is inline - no external files needed
2. Verify no CSS conflicts with application styles (toolbar uses
   `.dev-toolbar-*` prefix)
3. Check browser console for JavaScript errors

## Technical Details

### Architecture

- **Singleton Pattern**: Main toolbar and some collectors use singleton
- **Collector Pattern**: Modular data collection via `CollectorInterface`
- **Output Buffering**: Injects HTML before `</body>` tag
- **Self-Contained**: All CSS and JS inline - no external dependencies (no Vite,
  no build process)

### Performance Impact

**Development Mode:**

- Memory: ~2-5MB per request
- Execution Time: ~5-15ms per request

**Production Mode:**

- Memory: 0 (not loaded)
- Execution Time: 0 (not loaded)

### Browser Compatibility

Tested and supported in:

- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+

## Future Enhancements

Planned for Phase 2 and Phase 3:

- HTTP Client tracking (cURL, Guzzle)
- Cache operations (Redis hits/misses)
- Timeline visualization
- Request history (last 10 requests)
- N+1 query detection
- XDebug integration
- Node.js backend integration
- Resizable panel
- Persistent panel state

## Support

For issues or feature requests, please create a ticket in the project's issue
tracker or contact the development team.
