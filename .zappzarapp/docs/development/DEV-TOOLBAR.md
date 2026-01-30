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

#### HISTORY Tab (Phase 2.1)

- **Request History**: Browse last N requests (configurable, default: 20)
- **Filtering**: Filter by HTTP method, status code, URI pattern, minimum
  execution time
- **Statistics**: Total requests, average time/memory/queries, fastest/slowest
  requests
- **Trends**: ASCII sparkline visualization of response time trends
- **Export**: Export visible requests as JSON or CSV
- **Clear History**: Remove all stored requests from session

### Request Navigation (Phase 2.1)

**Request Switcher Dropdown:**

- Navigate between current and recent requests without page reload
- AJAX-based loading of historical request data
- Shows last 5 requests in dropdown
- Server-rendered HTML for consistency (no duplicate rendering logic)
- CSRF-protected endpoints for security

**Export Options:**

- Export current request data (all tabs)
- Export filtered history as JSON or CSV
- Timestamped exports for debugging sessions

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

### Request History Size (Phase 2.1)

Configure the maximum number of requests to store in session:

```bash
# .env.local
DEV_TOOLBAR_MAX_REQUESTS=10   # Default: 10, Min: 1, Max: 50
```

**Considerations:**

- **Two-Tier Storage System:**
  - Lightweight metadata for all requests (~10KB each)
  - Full collector data only for last 3 requests (~5-10MB each)
- Recommended: 10-20 requests for typical development
- Use 50 for extensive history tracking (only metadata, lightweight)
- **Request Switcher shows only the 3 most recent requests** (with full data for
  AJAX)
- Older requests visible in HISTORY tab but cannot be loaded via AJAX

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
    $toolbar->setNonce(\Zappzarapp\Security\Csp\Nonce\NonceRegistry::get());
}
```

DevToolbar uses `NonceRegistry` from the `zappzarapp/security` library for
consistent nonce handling across the application.

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

### Request History (Phase 2.1)

#### UI Features

**HISTORY Tab:**

1. **Filters** - Refine the request list:
   - Method: GET, POST, PUT, DELETE, PATCH
   - Status: 2xx, 3xx, 4xx, 5xx
   - URI Contains: Text search in request paths
   - Min Time: Show only requests slower than threshold
   - Reset button to clear all filters

2. **Statistics Dashboard:**
   - Total Requests: Count of stored requests
   - Avg Time: Average execution time
   - Avg Memory: Average peak memory usage
   - Avg Queries: Average database queries per request
   - Fastest: Quickest request time
   - Slowest: Longest request time

3. **Trend Visualization:**
   - ASCII sparkline showing response time trends
   - Visual pattern recognition for performance issues

4. **Request List:**
   - Color-coded by performance (green <200ms, yellow 200-500ms, red >500ms)
   - Click to view details (future: AJAX load)
   - Shows method, URI, status, time, memory, queries
   - Relative timestamps ("5s ago", "2m ago")

5. **Actions:**
   - Export JSON: Download filtered requests with metadata
   - Export CSV: Spreadsheet format for analysis
   - Clear History: Remove all requests (confirmation required)

**Request Switcher (Panel Header):**

- Dropdown showing current + last 5 requests
- Click to load historical request data via AJAX
- "View All History →" link to HISTORY tab
- Current request badge for context

#### Programmatic Access

```php
use DevToolbar\Storage\RequestStore;

// Get configurable max requests
$maxRequests = RequestStore::getMaxRequests(); // Respects DEV_TOOLBAR_MAX_REQUESTS

// Get all stored requests (newest first)
$requests = RequestStore::getAll();

// Get specific request
$request = RequestStore::get($requestId);

// Get statistics
$stats = RequestStore::getStatistics();
// Returns: total_requests, avg_time, avg_memory, avg_queries, slowest_time, fastest_time

// Filter requests (server-side, but UI uses client-side)
$filtered = RequestStore::filter([
    'method' => 'POST',
    'status' => '4',        // 4xx codes
    'uri' => 'api/users',   // Contains 'api/users'
    'min_time' => 200,      // Requests > 200ms
]);

// Get trends for visualization
$trends = RequestStore::getTrends(20);
// Returns: ['labels' => [...], 'time' => [...], 'memory' => [...], 'queries' => [...]]

// Clear all history
RequestStore::clear();
```

#### AJAX Endpoints

**Load Historical Request** (GET):

```
?dev_toolbar_action=load_request&request_id=<id>&token=<csrf_token>
```

Returns: Server-rendered HTML for all tabs

**Clear History** (POST):

```
?dev_toolbar_action=clear_history
```

Returns: JSON `{"success": true}`

**Security:**

- CSRF token validation using HMAC-SHA256
- Session-based secret key
- Automatic token generation for each request

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

## TypeScript Implementation (Phase 2.1+)

The DevToolbar frontend has been migrated to TypeScript for improved
maintainability, type safety, and testability.

### Architecture

**Modular Structure:**

```
src/node/backend/DevToolbar/
├── types/          # TypeScript interfaces and type definitions
├── storage/        # localStorage persistence with LRU eviction
├── utils/          # Time formatting, sparklines, export utilities
├── ui/             # UI controllers (TabManager, RequestSwitcher, etc.)
├── index.ts        # Browser entry point
└── devtoolbar.build.ts  # esbuild build configuration
```

**Build Process:**

- Source: `src/node/backend/DevToolbar/` (TypeScript modules)
- Build: Integrated into `make node-server-build`
- Output: `src/php/DevToolbar/assets/devtoolbar.js` (IIFE bundle, 38KB)
- Format: ES2020 browser-compatible bundle with sourcemaps

**Testing:**

- Framework: Vitest with happy-dom environment
- Coverage: 88% overall (storage: 73%, utils: 100%, ui: 91%)
- Tests: 69 unit tests (all passing)
- Location: `tests/node/backend/unit/DevToolbar/`

**Key Modules:**

- **StorageManager**: localStorage + in-memory fallback, quota management, LRU
  eviction
- **TabManager**: Tab switching with localStorage persistence
- **RequestSwitcher**: Historical request navigation from localStorage
- **XdebugControls**: Dynamic Xdebug status and IDE session controls
- **DevToolbarUI**: Main controller orchestrating all components

**Features:**

- Type-safe data structures
- Comprehensive unit tests
- localStorage persistence (50 metadata entries, 20 full requests)
- Automatic migration from sessionStorage
- Memory fallback for private browsing mode
- Sourcemap support for debugging

**Development Workflow:**

1. Edit TypeScript sources in `src/node/backend/DevToolbar/`
2. Run tests: `make test-node`
3. Build bundle: `make node-server-build`
4. Bundle auto-generated at `src/php/DevToolbar/assets/devtoolbar.js`

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
