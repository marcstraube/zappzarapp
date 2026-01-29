# 17: Developer Toolbar Phase 3 - Advanced Features

## Status

🔮 **Phase 3 (Future Backlog)** - Long-term vision

## Goal

Transform the Developer Toolbar into a comprehensive development platform with
advanced profiling, debugging, testing integration, and collaboration features.
Provides enterprise-grade developer experience comparable to mature frameworks
while maintaining zappzarapp's security-first principles.

## Prerequisites

- [ ] Phase 1 Developer Toolbar implemented and stable
- [ ] Phase 2 enhancements deployed and tested
- [ ] User feedback collected and analyzed
- [ ] Performance baseline established

## Phase 3 Scope

### Advanced Features

#### 1. Memory Profiler

**Purpose:** Detailed memory allocation analysis per function/class

**Display:**

```
┌────────────────────────────────────────────────────────────┐
│ Memory Profiler (Peak: 55.3MB, Delta: +32.1MB)            │
├────────────────────────────────────────────────────────────┤
│                                                             │
│ Memory Allocation by Function:                             │
│                                                             │
│ 1. UserRepository::findAll()          15.2MB  ████████    │
│    Called: 1 time                                          │
│    File: UserRepository.php:45                             │
│    [View Memory Snapshot]                                  │
│                                                             │
│ 2. PostService::loadWithComments()     8.7MB  █████       │
│    Called: 15 times                                        │
│    Average: 0.58MB per call                                │
│    [Potential Memory Leak] ⚠️                              │
│                                                             │
│ 3. Twig::render()                      6.3MB  ███          │
│    Called: 1 time                                          │
│    File: TwigService.php:78                                │
│                                                             │
│ 4. Other functions                     2.9MB  █            │
│                                                             │
│ Top Memory Consumers by Class:                             │
│ - User: 12.3MB (247 instances)                            │
│ - Post: 8.7MB (135 instances)                             │
│ - Comment: 4.2MB (428 instances)                           │
│                                                             │
│ 💡 Recommendations:                                        │
│ - Implement lazy loading for User->posts relationship      │
│ - Use pagination for PostService::loadWithComments()       │
│ - Consider DTO instead of full entity hydration            │
│                                                             │
└────────────────────────────────────────────────────────────┘
```

**Features:**

- Memory allocation per function
- Object instance tracking
- Memory leak detection (increasing memory over repeated calls)
- Memory snapshots (before/after comparison)
- Flame graph visualization
- Recommendations for optimization

**Implementation:**

```php
// Requires Xdebug or Tideways extension
// src/php/DevToolbar/Profilers/MemoryProfiler.php

class MemoryProfiler
{
    private array $snapshots = [];
    private array $allocations = [];

    public function startProfiling(): void
    {
        if (!extension_loaded('xdebug')) {
            throw new RuntimeException('Xdebug extension required for memory profiling');
        }

        xdebug_start_trace('/tmp/xdebug-trace', XDEBUG_TRACE_COMPUTERIZED);
        $this->snapshots['start'] = [
            'memory' => memory_get_usage(),
            'time' => microtime(true),
        ];
    }

    public function stopProfiling(): void
    {
        xdebug_stop_trace();
        $this->snapshots['end'] = [
            'memory' => memory_get_usage(),
            'time' => microtime(true),
        ];

        $this->analyzeTrace();
    }

    private function analyzeTrace(): void
    {
        $traceFile = '/tmp/xdebug-trace.xt';
        if (!file_exists($traceFile)) {
            return;
        }

        // Parse Xdebug trace file
        $handle = fopen($traceFile, 'r');
        while (($line = fgets($handle)) !== false) {
            // Parse trace format: level, function, file, line, memory
            $parts = explode("\t", $line);
            if (count($parts) < 5) {
                continue;
            }

            [$level, $function, $file, $lineNo, $memory] = $parts;

            if (!isset($this->allocations[$function])) {
                $this->allocations[$function] = [
                    'calls' => 0,
                    'total_memory' => 0,
                    'max_memory' => 0,
                    'locations' => [],
                ];
            }

            $this->allocations[$function]['calls']++;
            $memoryUsed = (int)$memory;
            $this->allocations[$function]['total_memory'] += $memoryUsed;
            $this->allocations[$function]['max_memory'] = max(
                $this->allocations[$function]['max_memory'],
                $memoryUsed
            );
            $this->allocations[$function]['locations'][] = "$file:$lineNo";
        }
        fclose($handle);

        // Sort by total memory
        uasort($this->allocations, fn($a, $b) => $b['total_memory'] <=> $a['total_memory']);
    }

    public function getTopConsumers(int $limit = 10): array
    {
        return array_slice($this->allocations, 0, $limit, true);
    }

    public function detectMemoryLeaks(): array
    {
        $leaks = [];

        foreach ($this->allocations as $function => $data) {
            if ($data['calls'] > 1) {
                $avgMemory = $data['total_memory'] / $data['calls'];
                // If average is high and called many times, potential leak
                if ($avgMemory > 1024 * 1024 && $data['calls'] > 10) {
                    $leaks[] = [
                        'function' => $function,
                        'calls' => $data['calls'],
                        'avg_memory' => $avgMemory,
                        'total_memory' => $data['total_memory'],
                    ];
                }
            }
        }

        return $leaks;
    }
}
```

#### 2. XDebug Integration

**Purpose:** Full debugging capabilities (breakpoints, step-through, variable inspection)

**Display:**

```
┌────────────────────────────────────────────────────────────┐
│ XDebug Debugger (Active Session)                          │
├────────────────────────────────────────────────────────────┤
│                                                             │
│ Status: ⏸️  Paused at Breakpoint                           │
│ File: UserRepository.php:45                                │
│ Function: findById()                                       │
│                                                             │
│ Call Stack:                                                │
│ 1. UserController::show()        UserController.php:23    │
│ 2. UserService::getUser()        UserService.php:67       │
│ 3. UserRepository::findById()    UserRepository.php:45    │
│                                                             │
│ Local Variables:                                           │
│ $id = 123                                                  │
│ $query = "SELECT * FROM users WHERE id = ?"               │
│ $stmt = PDOStatement {...}                                │
│                                                             │
│ [▶️ Continue] [⏭️ Step Over] [⏬ Step Into] [⏫ Step Out] │
│                                                             │
│ Watch Expressions:                                         │
│ $user->email = "john@example.com"                         │
│ count($user->posts) = 15                                   │
│                                                             │
│ Breakpoints:                                               │
│ ✅ UserRepository.php:45                                   │
│ ⭕ PostService.php:78 (disabled)                           │
│                                                             │
└────────────────────────────────────────────────────────────┘
```

**Features:**

- Set breakpoints from toolbar
- Step-through debugging
- Variable inspection
- Watch expressions
- Call stack navigation
- Conditional breakpoints
- Remote debugging (IDE integration)

**Implementation:**

```php
// src/php/DevToolbar/Debug/XDebugClient.php

class XDebugClient
{
    private string $ideKey;
    private int $port;

    public function __construct()
    {
        $this->ideKey = $_ENV['XDEBUG_IDE_KEY'] ?? 'PHPSTORM';
        $this->port = (int)($_ENV['XDEBUG_PORT'] ?? 9003);
    }

    public function startDebugSession(): void
    {
        if (!extension_loaded('xdebug')) {
            throw new RuntimeException('XDebug extension not loaded');
        }

        xdebug_break();
    }

    public function setBreakpoint(string $file, int $line): void
    {
        // Store breakpoints in session for XDebug to pick up
        $_SESSION['xdebug_breakpoints'][] = [
            'file' => $file,
            'line' => $line,
            'condition' => null,
        ];
    }

    public function getCallStack(): array
    {
        return xdebug_get_function_stack();
    }

    public function inspectVariable(string $varName): mixed
    {
        // Use xdebug_debug_zval to get variable info
        return xdebug_debug_zval($varName);
    }
}
```

#### 3. Route Inspector

**Purpose:** Comprehensive view of all application routes

**Display:**

```
┌────────────────────────────────────────────────────────────┐
│ Route Inspector (42 routes registered)                     │
├────────────────────────────────────────────────────────────┤
│                                                             │
│ Filter: [All] [GET] [POST] [PUT] [DELETE]                 │
│ Search: [____________________] 🔍                          │
│                                                             │
│ GET    /                                                   │
│        Controller: WelcomeController::index()              │
│        Middleware: []                                      │
│        [Test Route]                                        │
│                                                             │
│ GET    /api/users                                          │
│        Controller: UserController::index()                 │
│        Middleware: [AuthMiddleware, CorsMiddleware]        │
│        Parameters: page (optional), limit (optional)       │
│        [Test Route]                                        │
│                                                             │
│ POST   /api/users                                          │
│        Controller: UserController::create()                │
│        Middleware: [AuthMiddleware, CorsMiddleware]        │
│        Validation: CreateUserRequest                       │
│        [Test Route]                                        │
│                                                             │
│ GET    /api/users/{id}                                     │
│        Controller: UserController::show()                  │
│        Middleware: [AuthMiddleware, CorsMiddleware]        │
│        Parameters: id (required, integer)                  │
│        [Test Route]                                        │
│                                                             │
│ [Export to Postman] [Export to OpenAPI]                   │
│                                                             │
└────────────────────────────────────────────────────────────┘
```

**Features:**

- List all registered routes
- Filter by HTTP method
- Search by URL pattern or controller
- View middleware stack per route
- View route parameters and validation rules
- "Test Route" button (opens request builder)
- Export to Postman collection
- Export to OpenAPI/Swagger specification

**Implementation:**

```php
// src/php/DevToolbar/Inspectors/RouteInspector.php

class RouteInspector
{
    private Router $router;

    public function __construct(Router $router)
    {
        $this->router = $router;
    }

    public function getAllRoutes(): array
    {
        $routes = [];

        // Assuming router has a method to get all registered routes
        foreach ($this->router->getRoutes() as $route) {
            $routes[] = [
                'method' => $route->getMethod(),
                'path' => $route->getPath(),
                'controller' => $route->getController(),
                'action' => $route->getAction(),
                'middleware' => $route->getMiddleware(),
                'parameters' => $route->getParameters(),
                'validation' => $route->getValidationRules(),
            ];
        }

        return $routes;
    }

    public function exportToPostman(): array
    {
        $collection = [
            'info' => [
                'name' => 'zappzarapp API',
                'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
            ],
            'item' => [],
        ];

        foreach ($this->getAllRoutes() as $route) {
            $collection['item'][] = [
                'name' => "{$route['method']} {$route['path']}",
                'request' => [
                    'method' => $route['method'],
                    'header' => [],
                    'url' => [
                        'raw' => '{{base_url}}' . $route['path'],
                        'host' => ['{{base_url}}'],
                        'path' => explode('/', trim($route['path'], '/')),
                    ],
                ],
            ];
        }

        return $collection;
    }

    public function exportToOpenAPI(): array
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'zappzarapp API',
                'version' => '1.0.0',
            ],
            'paths' => [],
        ];

        foreach ($this->getAllRoutes() as $route) {
            $path = $route['path'];
            $method = strtolower($route['method']);

            if (!isset($spec['paths'][$path])) {
                $spec['paths'][$path] = [];
            }

            $spec['paths'][$path][$method] = [
                'summary' => "{$route['method']} {$path}",
                'operationId' => str_replace(['Controller', '::'], ['', '_'], $route['controller']),
                'parameters' => $this->formatOpenAPIParameters($route['parameters']),
                'responses' => [
                    '200' => ['description' => 'Successful response'],
                ],
            ];
        }

        return $spec;
    }

    private function formatOpenAPIParameters(array $parameters): array
    {
        $formatted = [];

        foreach ($parameters as $name => $config) {
            $formatted[] = [
                'name' => $name,
                'in' => $config['in'] ?? 'path',
                'required' => $config['required'] ?? false,
                'schema' => [
                    'type' => $config['type'] ?? 'string',
                ],
            ];
        }

        return $formatted;
    }
}
```

#### 4. Node.js Integration

**Purpose:** Unified view of PHP and Node.js backend metrics

**Unified Mini Bar:**

```
┌───────────────────────────────────────────────────┐
│ ⚡ dev │ PHP: 127ms │ Node: 45ms │ Total: 172ms │
└───────────────────────────────────────────────────┘
```

**Unified Panel:**

```
┌────────────────────────────────────────────────────────────┐
│ REQUEST (PHP+Node) │ QUERIES │ NODE API │ TIMELINE │     │
├────────────────────────────────────────────────────────────┤
│                                                             │
│ PHP Backend (127ms, 2.3MB, 8 queries)                     │
│ └─ Timeline: Bootstrap → Middleware → Controller           │
│                                                             │
│ Node Backend (45ms, 15.7MB)                                │
│ ├─ Express Middleware: 5ms                                 │
│ ├─ Route Handler: 35ms                                     │
│ └─ Response: 5ms                                            │
│                                                             │
│ Inter-Service Communication:                               │
│ PHP → Node: POST /api/process (45ms)                      │
│                                                             │
└────────────────────────────────────────────────────────────┘
```

**Features:**

- Unified metrics across PHP and Node
- Inter-service communication tracking
- Combined timeline
- Node.js memory profiling
- Node.js query tracking (if using DB from Node)

**Implementation:**

```javascript
// src/node/dev-toolbar/middleware.js

import { DevToolbarClient } from './client.js';

export function devToolbarMiddleware(req, res, next) {
    if (process.env.ENABLE_DEV_TOOLBAR !== 'true') {
        return next();
    }

    const start = process.hrtime.bigint();
    const startMemory = process.memoryUsage();

    // Collect Node metrics
    res.on('finish', () => {
        const duration = Number(process.hrtime.bigint() - start) / 1_000_000; // ms
        const memoryUsed = process.memoryUsage().heapUsed - startMemory.heapUsed;

        const metrics = {
            service: 'node',
            method: req.method,
            path: req.path,
            status: res.statusCode,
            duration: Math.round(duration),
            memory: memoryUsed,
            timestamp: Date.now(),
        };

        // Send to PHP backend via HTTP
        DevToolbarClient.sendMetrics(metrics);
    });

    next();
}
```

```php
// src/php/DevToolbar/DataCollectors/NodeCollector.php

class NodeCollector implements CollectorInterface
{
    private array $nodeMetrics = [];

    public function receiveMetrics(array $metrics): void
    {
        $this->nodeMetrics[] = $metrics;
    }

    public function getData(): array
    {
        return [
            'node_requests' => $this->nodeMetrics,
            'total_time' => array_sum(array_column($this->nodeMetrics, 'duration')),
            'total_memory' => array_sum(array_column($this->nodeMetrics, 'memory')),
        ];
    }
}
```

#### 5. Request Sharing

**Purpose:** Share specific requests with team members for debugging

**Display:**

```
┌────────────────────────────────────────────────────────────┐
│ Share Request #42                                          │
├────────────────────────────────────────────────────────────┤
│                                                             │
│ Request: GET /api/users/123                                │
│ Status: 200 OK                                             │
│ Time: 127ms                                                │
│                                                             │
│ Share Options:                                             │
│ ⚪ Public (expires in 24 hours)                            │
│ ⚪ Team only (requires authentication)                     │
│ ⚪ Private (password protected)                            │
│                                                             │
│ Password (optional): [___________]                         │
│                                                             │
│ [Generate Share Link]                                      │
│                                                             │
│ Share Link:                                                │
│ https://localhost:8443/_dev/shared/abc123def456            │
│ [Copy Link] [Send via Email]                              │
│                                                             │
│ Expires: 2026-01-27 14:30 (24 hours)                      │
│                                                             │
└────────────────────────────────────────────────────────────┘
```

**Features:**

- Generate shareable link for request
- Expiration (1h, 24h, 7 days, never)
- Password protection
- View count tracking
- Revoke link
- Export to JSON/HAR format

**Implementation:**

```php
// src/php/DevToolbar/Sharing/RequestSharer.php

class RequestSharer
{
    private const STORAGE_PATH = __DIR__ . '/../../../../storage/toolbar/shared/';

    public function shareRequest(string $requestId, array $options): string
    {
        $shareId = $this->generateShareId();
        $request = RequestStore::get($requestId);

        if (!$request) {
            throw new RuntimeException("Request $requestId not found");
        }

        $shareData = [
            'id' => $shareId,
            'request_id' => $requestId,
            'data' => $request,
            'created_at' => time(),
            'expires_at' => time() + ($options['ttl'] ?? 86400), // 24h default
            'password' => $options['password'] ?? null,
            'view_count' => 0,
            'max_views' => $options['max_views'] ?? null,
        ];

        // Store share data
        $this->ensureStorageDirectory();
        file_put_contents(
            self::STORAGE_PATH . $shareId . '.json',
            json_encode($shareData, JSON_PRETTY_PRINT)
        );

        return $shareId;
    }

    public function getSharedRequest(string $shareId, ?string $password = null): ?array
    {
        $filePath = self::STORAGE_PATH . $shareId . '.json';

        if (!file_exists($filePath)) {
            return null;
        }

        $shareData = json_decode(file_get_contents($filePath), true);

        // Check expiration
        if ($shareData['expires_at'] < time()) {
            unlink($filePath);
            return null;
        }

        // Check password
        if ($shareData['password'] && !password_verify($password ?? '', $shareData['password'])) {
            return null;
        }

        // Check max views
        if ($shareData['max_views'] && $shareData['view_count'] >= $shareData['max_views']) {
            return null;
        }

        // Increment view count
        $shareData['view_count']++;
        file_put_contents($filePath, json_encode($shareData, JSON_PRETTY_PRINT));

        return $shareData['data'];
    }

    private function generateShareId(): string
    {
        return bin2hex(random_bytes(16));
    }

    private function ensureStorageDirectory(): void
    {
        if (!is_dir(self::STORAGE_PATH)) {
            mkdir(self::STORAGE_PATH, 0755, true);
        }
    }
}
```

#### 6. Performance Baseline

**Purpose:** Track performance over time and compare against baseline

**Display:**

```
┌────────────────────────────────────────────────────────────┐
│ Performance Baseline Comparison                            │
├────────────────────────────────────────────────────────────┤
│                                                             │
│ Current Request: GET /api/users                            │
│                                                             │
│ Metric         Current  Baseline  Diff      Status         │
│ ──────────────────────────────────────────────────────────│
│ Time           127ms    95ms      +32ms     🟡 Slower      │
│ Memory         2.3MB    1.8MB     +0.5MB    🟢 OK          │
│ Queries        8        6         +2        🟡 More        │
│ HTTP Calls     2        2         0         🟢 Same        │
│                                                             │
│ Baseline: v1.0.0 (2026-01-15)                              │
│ [Update Baseline] [View History]                          │
│                                                             │
│ Performance Trend (Last 7 days):                           │
│ Time:    ────────▄▄▄▄▄▄▄▄▄▄▄ (increasing ⚠️)              │
│ Memory:  ──────────────────── (stable ✅)                  │
│ Queries: ────────────▄▄────── (fluctuating ⚠️)             │
│                                                             │
└────────────────────────────────────────────────────────────┘
```

**Features:**

- Store baseline metrics per route
- Compare current vs. baseline
- Visual diff indicators
- Performance trend graphs
- Version tagging (baseline per release)
- Regression alerts

**Implementation:**

```php
// src/php/DevToolbar/Performance/BaselineManager.php

class BaselineManager
{
    private const BASELINE_FILE = __DIR__ . '/../../../../storage/toolbar/baseline.json';

    public function setBaseline(string $route, array $metrics, string $version): void
    {
        $baselines = $this->loadBaselines();

        $baselines[$route] = [
            'version' => $version,
            'timestamp' => time(),
            'metrics' => $metrics,
        ];

        $this->saveBaselines($baselines);
    }

    public function compare(string $route, array $currentMetrics): array
    {
        $baselines = $this->loadBaselines();

        if (!isset($baselines[$route])) {
            return [
                'has_baseline' => false,
                'message' => 'No baseline set for this route',
            ];
        }

        $baseline = $baselines[$route]['metrics'];
        $diff = [];

        foreach (['time', 'memory', 'queries'] as $metric) {
            $current = $currentMetrics[$metric] ?? 0;
            $base = $baseline[$metric] ?? 0;
            $delta = $current - $base;
            $percentage = $base > 0 ? round(($delta / $base) * 100, 1) : 0;

            $status = 'same';
            if ($delta > 0) {
                $status = $percentage > 20 ? 'worse' : 'slightly_worse';
            } elseif ($delta < 0) {
                $status = 'better';
            }

            $diff[$metric] = [
                'current' => $current,
                'baseline' => $base,
                'delta' => $delta,
                'percentage' => $percentage,
                'status' => $status,
            ];
        }

        return [
            'has_baseline' => true,
            'version' => $baselines[$route]['version'],
            'timestamp' => $baselines[$route]['timestamp'],
            'diff' => $diff,
        ];
    }

    private function loadBaselines(): array
    {
        if (!file_exists(self::BASELINE_FILE)) {
            return [];
        }

        return json_decode(file_get_contents(self::BASELINE_FILE), true) ?? [];
    }

    private function saveBaselines(array $baselines): void
    {
        $dir = dirname(self::BASELINE_FILE);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(self::BASELINE_FILE, json_encode($baselines, JSON_PRETTY_PRINT));
    }
}
```

#### 7. Testing Integration

**Purpose:** Integrate toolbar data into automated tests

**PHPUnit Integration:**

```php
// tests/php/App/Http/PerformanceTest.php

use DevToolbar\Testing\Assertions;

class PerformanceTest extends TestCase
{
    use Assertions;

    public function testUserListPerformance(): void
    {
        $response = $this->get('/api/users');

        $response->assertStatus(200);

        // DevToolbar assertions
        $this->assertRequestFasterThan(200); // ms
        $this->assertMemoryLessThan(10); // MB
        $this->assertQueryCountLessThan(5);
        $this->assertNoNPlusOne();
        $this->assertNoCacheM isses();
    }

    public function testNoSlowQueries(): void
    {
        $response = $this->get('/api/posts');

        $this->assertNoSlowQueries(100); // All queries < 100ms
    }
}
```

**Test Report:**

```
PHPUnit 12.0.0

Performance Tests:
  ✅ testUserListPerformance
     Time: 127ms (< 200ms ✓)
     Memory: 2.3MB (< 10MB ✓)
     Queries: 3 (< 5 ✓)
     N+1: None ✓
     Cache Misses: 0 ✓

  ❌ testNoSlowQueries
     Failed: Query #4 took 523ms (> 100ms)
     Query: SELECT * FROM posts WHERE user_id = ?
     Location: PostRepository.php:78

Tests: 2, Assertions: 7, Failures: 1.
```

**Implementation:**

```php
// src/php/DevToolbar/Testing/Assertions.php

trait Assertions
{
    protected function assertRequestFasterThan(int $maxMs): void
    {
        $toolbar = DevToolbar::getInstance();
        $time = $toolbar->getCollector('request')->getData()['time'];

        $this->assertLessThan(
            $maxMs,
            $time,
            "Request took {$time}ms, expected < {$maxMs}ms"
        );
    }

    protected function assertQueryCountLessThan(int $maxQueries): void
    {
        $toolbar = DevToolbar::getInstance();
        $count = $toolbar->getCollector('queries')->getData()['count'];

        $this->assertLessThan(
            $maxQueries,
            $count,
            "Query count: {$count}, expected < {$maxQueries}"
        );
    }

    protected function assertNoNPlusOne(): void
    {
        $toolbar = DevToolbar::getInstance();
        $queries = $toolbar->getCollector('queries')->getData()['queries'];
        $nPlusOnes = QueryAnalyzer::detectNPlusOne($queries);

        $this->assertEmpty(
            $nPlusOnes,
            sprintf('N+1 query detected: %s', json_encode($nPlusOnes))
        );
    }

    protected function assertNoSlowQueries(int $thresholdMs): void
    {
        $toolbar = DevToolbar::getInstance();
        $queries = $toolbar->getCollector('queries')->getData()['queries'];

        $slowQueries = array_filter($queries, fn($q) => $q['time'] > $thresholdMs);

        $this->assertEmpty(
            $slowQueries,
            sprintf('Slow queries detected: %s', json_encode($slowQueries))
        );
    }
}
```

## Architecture

### New Files

```
src/php/DevToolbar/
├── Profilers/
│   ├── MemoryProfiler.php
│   └── CpuProfiler.php
├── Debug/
│   ├── XDebugClient.php
│   └── BreakpointManager.php
├── Inspectors/
│   └── RouteInspector.php
├── Sharing/
│   ├── RequestSharer.php
│   └── ShareManager.php
├── Performance/
│   ├── BaselineManager.php
│   └── TrendAnalyzer.php
└── Testing/
    ├── Assertions.php
    └── TestReporter.php

src/node/dev-toolbar/
├── middleware.js
├── client.js
└── collectors/
    ├── query-collector.js
    └── memory-collector.js

templates/dev-toolbar/tabs/
├── profiler.html.twig
├── debugger.html.twig
├── routes.html.twig
├── node.html.twig
└── baseline.html.twig

storage/toolbar/
├── shared/           # Shared request files
├── baseline.json     # Performance baselines
└── trends/           # Historical performance data
```

## Security Considerations

### Shared Requests

- Sensitive data must be filtered before sharing
- Password protection for sensitive requests
- Expiration enforcement
- IP whitelist option
- Audit log for shared requests

### XDebug Remote Debugging

- Restrict to development IPs only
- IDE key authentication
- Disable in production (multiple layers)

### Performance Data

- No PII in performance logs
- Aggregate data only in metrics
- Clean up old data (retention policy)

## Success Criteria

Phase 3 complete when:

- [ ] Memory profiler functional with flame graphs
- [ ] XDebug integration working (breakpoints, step-through)
- [ ] Route inspector showing all routes
- [ ] Node.js integration displaying unified metrics
- [ ] Request sharing functional with expiration
- [ ] Performance baseline comparison working
- [ ] Testing assertions available in PHPUnit
- [ ] All features documented
- [ ] Security audit passed
- [ ] Performance impact < 20ms overhead

## Estimated Effort

**Total: 20-30 hours**

Breakdown:

- Memory Profiler: 4-6h
- XDebug Integration: 3-4h
- Route Inspector: 2-3h
- Node.js Integration: 4-5h
- Request Sharing: 2-3h
- Performance Baseline: 2-3h
- Testing Integration: 2-3h
- Documentation: 2-3h
- Testing & QA: 3-4h

## Priority

**Low** - Advanced features for mature projects

## Dependencies

- Phase 1 and Phase 2 complete
- Xdebug extension (optional, for profiling)
- Node.js backend (optional, for unified metrics)
- User feedback indicating need for advanced features

## Future Considerations

### Potential Phase 4 Features

- **AI-Powered Insights**: Use LLM to analyze performance issues and suggest optimizations
- **Distributed Tracing**: Integration with OpenTelemetry for microservices
- **Real-time Collaboration**: Multiple developers viewing same toolbar session
- **Custom Dashboards**: User-configurable layouts and widgets
- **Browser Extension**: Standalone toolbar as browser extension
- **Mobile Companion**: Mobile app for monitoring production issues

## Next Steps

1. Monitor Phase 1 and Phase 2 adoption
2. Collect feature requests from users
3. Prioritize Phase 3 features based on demand
4. Create detailed specs for high-priority features
5. Implement iteratively (one feature at a time)
6. Release as incremental updates (v1.2, v1.3, etc.)
