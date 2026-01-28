# Developer Toolbar

## Overview

The Developer Toolbar provides comprehensive real-time debugging information
during development, including request details, database queries, HTTP client
tracking, cache monitoring, log messages, exceptions, and performance analysis.
It's designed to be minimal, non-intrusive, secure-by-default, and
self-contained.

## Features

### Core Features

- **Always Visible Mini Bar**: Request time, memory usage, query count,
  performance alerts
- **Expandable Panel**: Detailed debugging information with tabbed interface
- **Performance Alerts**: Proactive warnings displayed in panel header
- **Security-by-Design**: Multiple layers of protection
- **Zero-Config**: Works automatically in development mode
- **CSP Compliant**: Built-in Content Security Policy support with nonce

### Available Tabs

#### REQUEST Tab

HTTP method, URI, status code, headers, body, session, cookies, server variables

#### MESSAGES Tab

Log messages from Monolog with color-coded severity levels

#### QUERIES Tab

- Database queries with execution time and bindings
- Color-coded performance indicators (green/yellow/red)
- **N+1 Query Detection**: Automatic detection with optimization suggestions
- Stack traces showing query origin

#### EXCEPTIONS Tab

All exceptions (both handled and unhandled) with class, message, location, and
stack trace

#### HTTP Tab

- Track all outgoing HTTP requests (cURL, file_get_contents, Guzzle)
- Request method, URL, status code
- Execution time with performance levels
- Request/Response headers and body
- Stack trace showing where call was made

#### CACHE Tab

- Monitor Redis operations (GET, SET, DELETE)
- Hit/Miss rate visualization
- TTL information
- Value sizes
- Total time per operation

#### TIMELINE Tab

- Visual breakdown of request lifecycle
- Hierarchical performance breakdown (bootstrap → middleware → controller →
  view)
- Bottleneck detection (highlights phases taking >50% of total time)
- Aggregated data from database, HTTP, and cache operations

### Performance Monitoring

**Automatic Detection & Alerts:**

- Slow requests (>1000ms)
- High memory usage (>50MB)
- Excessive queries (>50 queries)
- Slow query totals (>500ms)
- Excessive HTTP requests (>10 requests)
- Low cache hit rate (<50%)

**Request History:**

- Last 20 requests stored in session
- Filter by method, status code, URI
- Performance statistics and trends
- Navigate between requests

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

### Basic Integration

Add this to your `public/index.php`:

```php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// Start DevToolbar (if enabled)
if (DevToolbar\Guard\DevToolbarGuard::isEnabled()) {
    ob_start();
    $toolbar = DevToolbar\DevToolbar::getInstance();
    $toolbar->boot();

    // Register shutdown handler to inject toolbar HTML
    register_shutdown_function([$toolbar, 'render']);
}

// ... rest of application bootstrap ...
```

### CSP Integration

If your project uses Content Security Policy, share the nonce with DevToolbar:

```php
// After CSP header is set
if (DevToolbar\Guard\DevToolbarGuard::isEnabled() && isset($toolbar)) {
    $toolbar->setNonce(\App\Security\CspNonceHelper::get());
}
```

DevToolbar has its own self-contained nonce generation, but can use your
project's nonce for consistency.

### Data Collection Integration

#### Query Tracking

```php
use DevToolbar\DataCollectors\QueryCollector;

$collector = QueryCollector::getInstance();

// Before query execution
$start = hrtime(true);
$result = $pdo->query($sql);
$time = (hrtime(true) - $start) / 1_000_000; // Convert to ms

// Track the query
$collector->trackQuery($sql, $bindings, $time);
```

#### HTTP Request Tracking

```php
use DevToolbar\DataCollectors\HttpClientCollector;

$toolbar = DevToolbar\DevToolbar::getInstance();
$collector = $toolbar->getCollector('http');

// Track HTTP request
$collector->trackRequest(
    'GET',
    'https://api.example.com/users',
    $time,
    $statusCode,
    $headers,
    $responseBody,
    $requestData
);
```

#### Cache Operations

```php
use DevToolbar\DataCollectors\CacheCollector;

$toolbar = DevToolbar\DevToolbar::getInstance();
$collector = $toolbar->getCollector('cache');

// Track cache operations
$collector->trackOperation('get', 'user:123', $time, $value, $hit = true, $ttl);
$collector->trackOperation('set', 'session:abc', $time, $value, false, $ttl = 7200);
```

#### Timeline Events

```php
use DevToolbar\DataCollectors\TimelineCollector;

$toolbar = DevToolbar\DevToolbar::getInstance();
$collector = $toolbar->getCollector('timeline');

// Mark phases
$collector->startPhase('controller', 'Controller', 'controller');
// ... do work ...
$collector->endPhase('controller');

// Add aggregated data
$collector->addAggregatedData('Database Queries', 10, 150.5, 'database');
```

#### Exception Tracking

```php
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

#### Monolog Integration

```php
use DevToolbar\DataCollectors\MessageCollector;
use Monolog\Logger;

$logger = new Logger('app');

// Add DevToolbar handler in development
if (DevToolbar\Guard\DevToolbarGuard::isEnabled()) {
    $messageCollector = new MessageCollector();
    $logger->pushHandler($messageCollector);
}
```

## Usage

### Basic Usage

1. Load any page in development mode
2. Mini bar appears in bottom-right corner showing:
   - Environment badge (DEV/STAGING)
   - Request execution time
   - Peak memory usage
   - Number of database queries
   - Performance alert icon (if issues detected)
3. Click mini bar to expand full panel
4. Click tabs to switch between views
5. Click close button (▼) or press ESC to collapse panel

### Performance Analysis Workflow

#### Step 1: Check Mini Bar

Look for warning icon (⚠️) indicating performance issues

#### Step 2: Open Panel & Review Alerts

Performance alerts displayed at top showing:

- Issue type (slow request, high memory, excessive queries, etc.)
- Threshold vs. actual values
- Recommended actions

#### Step 3: Analyze Query Performance

**QUERIES Tab:**

- Review color-coded query list
- Check for N+1 patterns (automatically detected and highlighted)
- Review optimization suggestions
- Examine slow queries (>100ms)

#### Step 4: Check HTTP Requests

**HTTP Tab:**

- Review outgoing API calls
- Identify slow external requests (>500ms marked red)
- Check for excessive external dependencies

#### Step 5: Monitor Cache Performance

**CACHE Tab:**

- Review hit/miss rate (aim for >70%)
- Check cache operation times
- Identify cache misses causing database queries

#### Step 6: Visualize Request Lifecycle

**TIMELINE Tab:**

- Identify bottlenecks (phases taking >50% of time)
- Review time distribution across components
- Analyze sub-events within each phase

### N+1 Query Detection

When N+1 patterns are detected, they're automatically displayed in the QUERIES
tab:

**Example Detection:**

```
⚠️ N+1 Query Detected!

Pattern: SELECT * FROM posts WHERE user_id = ?
Executed: 15 times with different parameters
Total Time: 187ms

Called from: PostRepository.php:78

💡 Suggestion:
Use a WHERE IN clause to fetch all records in a single query:
SELECT * FROM posts WHERE user_id IN (1, 2, 3, ...)
```

### Request History

Navigate through recent requests:

```php
use DevToolbar\Storage\RequestStore;

// Get all stored requests (last 20)
$requests = RequestStore::getAll();

// Get specific request
$request = RequestStore::get($requestId);

// Get statistics
$stats = RequestStore::getStatistics();
// Returns: total_requests, avg_time, avg_memory, avg_queries, slowest_time, fastest_time

// Filter requests
$filtered = RequestStore::filter([
    'method' => 'POST',
    'status' => '4',        // 4xx codes
    'uri' => 'api/users',   // Contains 'api/users'
]);

// Get trends for charts
$trends = RequestStore::getTrends(20);
```

## Security

### Sensitive Data Protection

The toolbar automatically filters sensitive data in:

- Request POST data
- HTTP request/response bodies
- Headers (Authorization, Cookie, etc.)
- Session data
- Cache values

**Filtered patterns include:**

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

### Content Security Policy (CSP)

DevToolbar is CSP-compliant with built-in nonce support:

- Self-contained nonce generation (cryptographically secure)
- Automatic nonce injection in all inline `<script>` and `<style>` tags
- Optional integration with host project's CSP nonce
- Compatible with strict CSP policies (`'strict-dynamic'`)

## Performance Thresholds

Default thresholds for performance alerts:

| Metric             | Warning | Critical |
| ------------------ | ------- | -------- |
| Request Time       | 700ms   | 1000ms   |
| Memory Usage       | 40MB    | 50MB     |
| Query Count        | 50      | -        |
| Query Time Total   | 500ms   | -        |
| HTTP Request Count | 10      | -        |
| HTTP Time Total    | 1000ms  | -        |
| Cache Hit Rate     | <70%    | <50%     |

## Troubleshooting

### Toolbar Not Appearing

1. Check environment: `echo $APP_ENV` (should be `development`)
2. Check flag: `echo $ENABLE_DEV_TOOLBAR` (should be `true` or unset)
3. Verify page contains `</body>` tag (required for injection)
4. Check browser console for JavaScript errors
5. Verify output buffering is started before rendering

### Queries Not Showing

1. Verify `QueryCollector::trackQuery()` is being called
2. Ensure toolbar is booted before queries execute
3. Check that collector is started (automatic on boot)

### Messages Not Showing

1. Verify Monolog is configured with `MessageCollector` handler
2. Check log level threshold
3. Ensure messages are logged after toolbar boot

### HTTP Requests Not Tracked

1. Manually track requests using `HttpClientCollector::trackRequest()`
2. Ensure collector is retrieved from toolbar instance
3. Wrapper methods (`wrapCurlExec`, `wrapFileGetContents`) available but require
   manual implementation

### Performance Alerts Not Showing

1. Check if request exceeds thresholds
2. Verify all collectors are providing data
3. Ensure `PerformanceAnalyzer` is called (automatic in panel rendering)

### CSP Violations

1. Verify nonce is set via `$toolbar->setNonce()` if using external CSP
2. Check that CSP header includes nonce for `script-src` and `style-src`
3. Ensure DevToolbar's nonce matches your project's CSP nonce

## Technical Details

### Architecture

- **Singleton Pattern**: Main toolbar and some collectors use singleton
- **Collector Pattern**: Modular data collection via `CollectorInterface`
- **Analyzer Pattern**: Separate analysis logic (N+1 detection, performance)
- **Output Buffering**: Injects HTML before `</body>` tag
- **Self-Contained**: All CSS and JS inline - no external dependencies
- **Session Storage**: Request history stored in PHP session

### File Structure

```
src/php/DevToolbar/
├── DevToolbar.php              # Main singleton
├── Guard/
│   └── DevToolbarGuard.php     # Enable/disable logic
├── Middleware/
│   └── DevToolbarMiddleware.php # HTML injection
├── DataCollectors/
│   ├── CollectorInterface.php
│   ├── RequestCollector.php
│   ├── QueryCollector.php
│   ├── MessageCollector.php
│   ├── ExceptionCollector.php
│   ├── HttpClientCollector.php
│   ├── CacheCollector.php
│   └── TimelineCollector.php
├── Analyzers/
│   ├── QueryAnalyzer.php       # N+1 detection
│   └── PerformanceAnalyzer.php # Performance alerts
├── Renderers/
│   ├── RendererInterface.php
│   ├── MiniBarRenderer.php
│   ├── PanelRenderer.php
│   └── AssetsRenderer.php
├── Security/
│   └── NonceHelper.php         # CSP nonce support
└── Storage/
    └── RequestStore.php        # Request history
```

### Performance Impact

**Development Mode:**

- Memory: ~2-5MB per request (plus request history storage)
- Execution Time: ~5-20ms per request
- Session Storage: ~100KB per request (last 20 requests)

**Production Mode:**

- Memory: 0 (not loaded)
- Execution Time: 0 (not loaded)

### Browser Compatibility

Tested and supported in:

- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+

### API Reference

#### DevToolbar

```php
$toolbar = DevToolbar\DevToolbar::getInstance();
$toolbar->boot();                    // Start collecting data
$toolbar->render();                  // Inject HTML into response
$toolbar->setNonce(string $nonce);   // Set CSP nonce
$toolbar->getNonce(): string;        // Get current nonce
$toolbar->getCollector(string $name): ?CollectorInterface;
```

#### QueryAnalyzer

```php
QueryAnalyzer::detectNPlusOne(array $queries): array;
QueryAnalyzer::detectSlowQueries(array $queries, float $threshold = 100): array;
QueryAnalyzer::getStatistics(array $queries): array;
```

#### PerformanceAnalyzer

```php
PerformanceAnalyzer::analyze(array $collectorData): array;
PerformanceAnalyzer::getSummary(array $collectorData): array;
PerformanceAnalyzer::hasIssues(array $collectorData): bool;
```

#### RequestStore

```php
RequestStore::store(string $requestId, array $data): void;
RequestStore::getAll(): array;
RequestStore::get(string $requestId): ?array;
RequestStore::clear(): void;
RequestStore::getStatistics(): array;
RequestStore::filter(array $filters): array;
RequestStore::getTrends(int $limit = 20): array;
RequestStore::generateId(): string;
RequestStore::timeAgo(int $timestamp): string;
RequestStore::getStatusDisplay(int $statusCode): array;
```

## Future Enhancements

Planned features:

- XDebug profiler integration
- Node.js backend integration
- Resizable panel
- Persistent panel state across requests
- Export request data to HAR format
- Real-time WebSocket updates
- Custom threshold configuration
- Plugin system for custom collectors

## Support

For issues or feature requests, please create a ticket in the project's issue
tracker or contact the development team.
