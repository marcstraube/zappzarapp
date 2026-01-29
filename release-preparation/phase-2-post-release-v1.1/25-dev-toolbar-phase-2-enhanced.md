# 25: Developer Toolbar Phase 2 - Enhanced Features

## Status

🔵 **Phase 2 (v1.1)** - Depends on Phase 1 completion

## Goal

Extend the Developer Toolbar with advanced debugging capabilities: HTTP client
tracking, cache operations monitoring, visual timeline, request history, and
intelligent N+1 query detection. Transforms the toolbar from a basic debugging
tool into a comprehensive performance analysis platform.

## Prerequisites

- [ ] Phase 1 Developer Toolbar implemented and merged
- [ ] DevDashboard integration endpoints available
- [ ] Request storage mechanism functional

## Phase 2 Scope

### New Features

#### 1. HTTP Client Tracking (Tab 5: HTTP)

**Purpose:** Track all outgoing HTTP requests (cURL, Guzzle, file_get_contents)

**Display:**

```
┌────────────────────────────────────────────────────────────┐
│ HTTP Client (3 requests, 245ms total)                      │
├────────────────────────────────────────────────────────────┤
│                                                             │
│ 🟢 GET https://api.example.com/users  (127ms)             │
│    Status: 200 OK                                          │
│    Headers: Content-Type: application/json                 │
│    Response: {"users": [...]}  [View Full Response]       │
│                                                             │
│ 🟡 POST https://api.example.com/auth  (95ms)              │
│    Status: 201 Created                                     │
│    Request Body: {"email": "[FILTERED]", ...}             │
│    [Show Request] [Show Response]                          │
│                                                             │
│ 🔴 GET https://slow-api.com/data  (523ms) ⚠️ SLOW         │
│    Status: 200 OK                                          │
│    Called at: ApiClient.php:45                             │
│    [Copy cURL Command]                                     │
│                                                             │
└────────────────────────────────────────────────────────────┘
```

**Features:**

- All HTTP calls logged with timing
- Request/Response headers and body
- Status code + reason phrase
- Performance indicators (<200ms: green, 200-500ms: yellow, >500ms: red)
- Stack trace showing where call was made
- "Copy as cURL" button for debugging
- Sensitive data filtering (passwords, tokens)

**Implementation:**

```php
// src/php/DevToolbar/DataCollectors/HttpClientCollector.php

class HttpClientCollector implements CollectorInterface
{
    private array $requests = [];
    private bool $collecting = false;

    // Wrapper for file_get_contents
    public function wrapFileGetContents(string $url, ...$args): string|false
    {
        if (!$this->collecting) {
            return file_get_contents($url, ...$args);
        }

        $start = hrtime(true);
        $result = file_get_contents($url, ...$args);
        $time = (hrtime(true) - $start) / 1_000_000;

        $this->requests[] = [
            'method' => 'GET',
            'url' => $url,
            'time' => round($time, 2),
            'status' => $this->parseHttpStatus($http_response_header ?? []),
            'headers' => $http_response_header ?? [],
            'body' => $result,
            'backtrace' => $this->getRelevantBacktrace(),
        ];

        return $result;
    }

    // Wrapper for cURL
    public function wrapCurlExec($ch): string|bool
    {
        if (!$this->collecting) {
            return curl_exec($ch);
        }

        $start = hrtime(true);
        $result = curl_exec($ch);
        $time = (hrtime(true) - $start) / 1_000_000;

        $info = curl_getinfo($ch);

        $this->requests[] = [
            'method' => $info['http_method'] ?? 'GET',
            'url' => $info['url'],
            'time' => round($time, 2),
            'status' => $info['http_code'],
            'headers' => $this->parseCurlHeaders($ch),
            'body' => $result,
            'backtrace' => $this->getRelevantBacktrace(),
        ];

        return $result;
    }
}
```

**Integration:**

- Override `file_get_contents()` via stream wrapper (development mode only)
- Provide `DevToolbarCurlHandle` wrapper class
- Auto-detect Guzzle and wrap HTTP client

#### 2. Cache Operations (Tab 6: CACHE)

**Purpose:** Monitor Redis/Cache operations (get, set, delete, hits, misses)

**Display:**

```
┌────────────────────────────────────────────────────────────┐
│ Cache Operations (12 operations, 75% hit rate)             │
├────────────────────────────────────────────────────────────┤
│                                                             │
│ Hit Rate: ████████████░░░░ 75% (9 hits, 3 misses)         │
│ Total Time: 23ms                                           │
│                                                             │
│ Operations:                                                 │
│                                                             │
│ 🟢 HIT   get('user:123')               2.1ms              │
│    Value: {"id": 123, "name": "John"}                     │
│    TTL: 3600s remaining                                    │
│                                                             │
│ 🔴 MISS  get('user:999')               1.8ms              │
│    Key not found                                           │
│    Called at: UserRepository.php:67                        │
│                                                             │
│ ⚙️  SET   set('session:abc', ...)      3.2ms              │
│    TTL: 7200s                                              │
│    Value Size: 2.3KB                                       │
│                                                             │
│ 🗑️  DEL   delete('old_cache:*')        5.5ms              │
│    Pattern matched 15 keys                                 │
│                                                             │
└────────────────────────────────────────────────────────────┘
```

**Features:**

- All cache operations logged
- Hit/miss rate visualization
- Time per operation
- Key patterns and TTL information
- Value size for SET operations
- Stack trace for cache calls
- Filtered sensitive data in values

**Implementation:**

```php
// src/php/DevToolbar/DataCollectors/CacheCollector.php

class CacheCollector implements CollectorInterface
{
    private array $operations = [];
    private int $hits = 0;
    private int $misses = 0;

    // Wrap Redis operations
    public function wrapRedisGet(Redis $redis, string $key): mixed
    {
        $start = hrtime(true);
        $result = $redis->get($key);
        $time = (hrtime(true) - $start) / 1_000_000;

        $isHit = $result !== false;

        if ($isHit) {
            $this->hits++;
        } else {
            $this->misses++;
        }

        $this->operations[] = [
            'type' => 'get',
            'key' => $key,
            'hit' => $isHit,
            'time' => round($time, 2),
            'value' => $this->filterValue($result),
            'ttl' => $redis->ttl($key),
            'backtrace' => $this->getRelevantBacktrace(),
        ];

        return $result;
    }

    public function getData(): array
    {
        $total = $this->hits + $this->misses;
        $hitRate = $total > 0 ? round(($this->hits / $total) * 100, 1) : 0;

        return [
            'operations' => $this->operations,
            'hits' => $this->hits,
            'misses' => $this->misses,
            'hit_rate' => $hitRate,
            'total_time' => round(array_sum(array_column($this->operations, 'time')), 2),
        ];
    }
}
```

**Integration:**

- Extend existing `RedisCache` class in `src/php/App/Infrastructure/Cache/`
- Wrap all Redis operations in development mode
- Auto-inject into DI container when toolbar enabled

#### 3. Timeline Visualization (Tab 7: TIMELINE)

**Purpose:** Visual representation of request lifecycle

**Display:**

```
┌────────────────────────────────────────────────────────────┐
│ Request Timeline (Total: 245ms)                            │
├────────────────────────────────────────────────────────────┤
│                                                             │
│ Bootstrap               ██ 15ms                            │
│ Middleware              █ 8ms                              │
│ ├─ AuthMiddleware       █ 5ms                              │
│ └─ CorsMiddleware       █ 3ms                              │
│ Controller              ██████████ 127ms                   │
│ ├─ Database Queries     ████████ 98ms (8 queries)         │
│ │  ├─ Query #1          ██ 23ms                            │
│ │  ├─ Query #2          █████ 45ms ⚠️                     │
│ │  └─ ...               ███ 30ms                           │
│ ├─ HTTP Clients         ██ 25ms (2 requests)              │
│ └─ Cache Operations     █ 4ms (5 ops)                      │
│ View Rendering          ████ 45ms                          │
│ Response                █ 10ms                             │
│                                                             │
│ ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ │
│ 0ms                                                 245ms  │
└────────────────────────────────────────────────────────────┘
```

**Features:**

- Visual bar chart of time distribution
- Hierarchical breakdown (bootstrap → middleware → controller → view)
- Database queries aggregated
- HTTP calls aggregated
- Cache operations aggregated
- Highlights bottlenecks (red if >50% of total time)
- Hover shows exact timing

**Implementation:**

```php
// src/php/DevToolbar/DataCollectors/TimelineCollector.php

class TimelineCollector implements CollectorInterface
{
    private array $events = [];
    private float $requestStart;

    public function start(): void
    {
        $this->requestStart = $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true);
        $this->addEvent('request_start', 'Request Start');
    }

    public function addEvent(string $key, string $label): void
    {
        $this->events[$key] = [
            'label' => $label,
            'time' => microtime(true),
        ];
    }

    public function getData(): array
    {
        $totalTime = (microtime(true) - $this->requestStart) * 1000;

        // Build timeline from events
        $timeline = [];
        $prevTime = $this->requestStart;

        foreach ($this->events as $key => $event) {
            $duration = ($event['time'] - $prevTime) * 1000;
            $timeline[] = [
                'label' => $event['label'],
                'duration' => round($duration, 2),
                'percentage' => round(($duration / $totalTime) * 100, 1),
            ];
            $prevTime = $event['time'];
        }

        return [
            'timeline' => $timeline,
            'total_time' => round($totalTime, 2),
        ];
    }
}
```

**Integration:**

- Add timeline events throughout application lifecycle
- Collect from other collectors (queries, HTTP, cache)
- Render as HTML progress bar with percentages

#### 4. Request History

**Purpose:** Store and navigate recent requests

**Display:**

```
┌────────────────────────────────────────────────────────────┐
│ Request History (Last 20 requests)                         │
├────────────────────────────────────────────────────────────┤
│                                                             │
│ 🟢 #42 GET  /api/users         200  127ms  5s ago         │
│ 🟢 #41 POST /api/auth          201   95ms  12s ago        │
│ 🟡 #40 GET  /api/slow          200  523ms  25s ago ⚠️     │
│ 🔴 #39 POST /api/invalid       422   45ms  1m ago         │
│ 🟢 #38 GET  /                  200   78ms  2m ago         │
│                                                             │
│ [< Previous] [Next >] [Clear History]                      │
│                                                             │
│ Click on request to view details in toolbar                │
└────────────────────────────────────────────────────────────┘
```

**Features:**

- Last 20 requests stored in session
- Navigate between requests without reloading
- Status code color coding (green: 2xx, yellow: 3xx/4xx, red: 5xx)
- Click request to load its data into toolbar
- "Clear History" button
- Persist across page loads (session storage)

**Implementation:**

```php
// src/php/DevToolbar/Storage/RequestStore.php

class RequestStore
{
    private const MAX_REQUESTS = 20;
    private const SESSION_KEY = 'dev_toolbar_requests';

    public static function store(string $requestId, array $data): void
    {
        if (!isset($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = [];
        }

        $_SESSION[self::SESSION_KEY][$requestId] = [
            'id' => $requestId,
            'method' => $data['method'],
            'uri' => $data['uri'],
            'status' => $data['status'],
            'time' => $data['time'],
            'memory' => $data['memory'],
            'queries' => count($data['queries'] ?? []),
            'timestamp' => time(),
            'data' => $data, // Full collector data
        ];

        // Keep only last 20
        if (count($_SESSION[self::SESSION_KEY]) > self::MAX_REQUESTS) {
            array_shift($_SESSION[self::SESSION_KEY]);
        }
    }

    public static function getAll(): array
    {
        return array_reverse($_SESSION[self::SESSION_KEY] ?? []);
    }

    public static function get(string $requestId): ?array
    {
        return $_SESSION[self::SESSION_KEY][$requestId] ?? null;
    }

    public static function clear(): void
    {
        unset($_SESSION[self::SESSION_KEY]);
    }
}
```

**DevDashboard Integration:**

```
New DevDashboard page: /_dev/toolbar-history

- Full request history table
- Filter by method, status, URL pattern
- Sort by time, memory, queries
- Export to JSON/CSV
- Performance graphs (time/memory over time)
```

#### 5. N+1 Query Detection

**Purpose:** Automatically detect and highlight N+1 query problems

**Display:**

```
┌────────────────────────────────────────────────────────────┐
│ ⚠️  N+1 Query Detected!                                    │
├────────────────────────────────────────────────────────────┤
│                                                             │
│ Pattern: SELECT * FROM posts WHERE user_id = ?             │
│ Executed: 15 times with different parameters               │
│ Total Time: 187ms                                          │
│                                                             │
│ Called from: PostRepository.php:78                         │
│                                                             │
│ Instances:                                                  │
│ 1. SELECT ... WHERE user_id = 1  (12ms)                   │
│ 2. SELECT ... WHERE user_id = 2  (13ms)                   │
│ 3. SELECT ... WHERE user_id = 3  (11ms)                   │
│ ... (12 more)                                              │
│                                                             │
│ 💡 Suggestion:                                             │
│ Use JOIN or WHERE IN clause to fetch all posts in         │
│ a single query:                                            │
│ SELECT * FROM posts WHERE user_id IN (1, 2, 3, ...)       │
│                                                             │
└────────────────────────────────────────────────────────────┘
```

**Features:**

- Analyze query patterns
- Detect identical queries with different parameters
- Show total count and time
- Display all instances
- Provide suggestions for optimization
- Visual alert in mini bar when detected (⚠️ icon)

**Implementation:**

```php
// src/php/DevToolbar/Analyzers/QueryAnalyzer.php

class QueryAnalyzer
{
    public static function detectNPlusOne(array $queries): array
    {
        $patterns = [];
        $nPlusOnes = [];

        foreach ($queries as $query) {
            // Normalize query (replace values with placeholders)
            $pattern = self::normalizeQuery($query['sql']);

            if (!isset($patterns[$pattern])) {
                $patterns[$pattern] = [
                    'pattern' => $pattern,
                    'original' => $query['sql'],
                    'instances' => [],
                    'total_time' => 0,
                ];
            }

            $patterns[$pattern]['instances'][] = $query;
            $patterns[$pattern]['total_time'] += $query['time'];
        }

        // Identify N+1 (same pattern executed 3+ times)
        foreach ($patterns as $pattern => $data) {
            if (count($data['instances']) >= 3) {
                $nPlusOnes[] = [
                    'pattern' => $pattern,
                    'count' => count($data['instances']),
                    'total_time' => $data['total_time'],
                    'instances' => $data['instances'],
                    'location' => $data['instances'][0]['backtrace'][0] ?? 'unknown',
                ];
            }
        }

        return $nPlusOnes;
    }

    private static function normalizeQuery(string $sql): string
    {
        // Replace numbers with ?
        $sql = preg_replace('/\b\d+\b/', '?', $sql);

        // Replace quoted strings with ?
        $sql = preg_replace("/'[^']*'/", '?', $sql);

        // Normalize whitespace
        $sql = preg_replace('/\s+/', ' ', $sql);

        return trim($sql);
    }
}
```

#### 6. Performance Alerts

**Purpose:** Proactive warnings for performance issues

**Mini Bar Display:**

```
┌────────────────────────────────────────┐
│ ⚠️  dev │ 1.2s │ 55MB │ 127 queries │  │
└────────────────────────────────────────┘
         ↑
   Alert icon when:
   - Time > 1s
   - Memory > 50MB
   - Queries > 50
```

**Panel Display:**

```
┌────────────────────────────────────────────────────────────┐
│ ⚠️  Performance Alerts (3 issues detected)                 │
├────────────────────────────────────────────────────────────┤
│                                                             │
│ 🔴 CRITICAL: Slow Request (1.2s)                           │
│    Threshold: 1000ms                                       │
│    Actual: 1245ms                                          │
│    Action: Review Timeline tab for bottlenecks             │
│                                                             │
│ 🟠 WARNING: High Memory Usage (55MB)                       │
│    Threshold: 50MB                                         │
│    Actual: 55.3MB                                          │
│    Action: Check for memory leaks or large datasets        │
│                                                             │
│ 🟠 WARNING: Excessive Queries (127)                        │
│    Threshold: 50 queries                                   │
│    Actual: 127 queries                                     │
│    Action: Review QUERIES tab for N+1 problems             │
│                                                             │
└────────────────────────────────────────────────────────────┘
```

**Implementation:**

```php
// src/php/DevToolbar/Analyzers/PerformanceAnalyzer.php

class PerformanceAnalyzer
{
    private const THRESHOLDS = [
        'time_ms' => 1000,        // 1 second
        'memory_mb' => 50,        // 50 MB
        'query_count' => 50,      // 50 queries
    ];

    public static function analyze(array $collectorData): array
    {
        $alerts = [];

        // Check execution time
        if ($collectorData['request']['time'] > self::THRESHOLDS['time_ms']) {
            $alerts[] = [
                'level' => 'critical',
                'type' => 'slow_request',
                'message' => sprintf('Slow Request (%dms)', $collectorData['request']['time']),
                'threshold' => self::THRESHOLDS['time_ms'],
                'actual' => $collectorData['request']['time'],
                'action' => 'Review Timeline tab for bottlenecks',
            ];
        }

        // Check memory usage
        $memoryMb = $collectorData['request']['memory'] / 1024 / 1024;
        if ($memoryMb > self::THRESHOLDS['memory_mb']) {
            $alerts[] = [
                'level' => 'warning',
                'type' => 'high_memory',
                'message' => sprintf('High Memory Usage (%.1fMB)', $memoryMb),
                'threshold' => self::THRESHOLDS['memory_mb'],
                'actual' => round($memoryMb, 1),
                'action' => 'Check for memory leaks or large datasets',
            ];
        }

        // Check query count
        $queryCount = count($collectorData['queries']['queries'] ?? []);
        if ($queryCount > self::THRESHOLDS['query_count']) {
            $alerts[] = [
                'level' => 'warning',
                'type' => 'excessive_queries',
                'message' => sprintf('Excessive Queries (%d)', $queryCount),
                'threshold' => self::THRESHOLDS['query_count'],
                'actual' => $queryCount,
                'action' => 'Review QUERIES tab for N+1 problems',
            ];
        }

        return $alerts;
    }
}
```

### DevDashboard Integration Pages

#### New Page: /_dev/toolbar

Full-featured toolbar control panel:

```
Features:
- Request history table (sortable, filterable)
- Performance graphs (time, memory, queries over last 50 requests)
- N+1 detection summary
- Export request data to JSON
- Clear history
- Configure thresholds
```

#### New Page: /_dev/toolbar/request/{id}

Detailed request view:

```
Features:
- All tabs expanded (REQUEST, MESSAGES, QUERIES, EXCEPTIONS, HTTP, CACHE, TIMELINE)
- Performance alerts
- N+1 detection results
- Export to JSON
- "Replay Request" button (for testing)
```

## Architecture Changes

### New Files to Create

```
src/php/DevToolbar/
├── DataCollectors/
│   ├── HttpClientCollector.php       # HTTP client tracking
│   ├── CacheCollector.php            # Cache operations monitoring
│   └── TimelineCollector.php         # Timeline events
├── Analyzers/
│   ├── QueryAnalyzer.php             # N+1 detection
│   └── PerformanceAnalyzer.php       # Performance alerts
├── Wrappers/
│   ├── CurlWrapper.php               # cURL function wrapper
│   └── StreamWrapper.php             # file_get_contents wrapper
└── Storage/
    └── RequestStore.php              # Enhanced with history

templates/dev-toolbar/tabs/
├── http.html.twig                    # HTTP client tab
├── cache.html.twig                   # Cache operations tab
├── timeline.html.twig                # Timeline visualization
└── alerts.html.twig                  # Performance alerts

src/php/DevDashboard/Controllers/
└── ToolbarController.php             # New controller for toolbar pages

templates/dev-dashboard/
├── toolbar.html.twig                 # Toolbar history page
└── toolbar-request.html.twig         # Request detail page

tests/php/DevToolbar/
├── DataCollectors/
│   ├── HttpClientCollectorTest.php
│   ├── CacheCollectorTest.php
│   └── TimelineCollectorTest.php
└── Analyzers/
    ├── QueryAnalyzerTest.php
    └── PerformanceAnalyzerTest.php
```

## Testing Strategy

### Unit Tests

```php
// N+1 Detection
class QueryAnalyzerTest extends TestCase
{
    public function testDetectsNPlusOnePattern(): void
    {
        $queries = [
            ['sql' => 'SELECT * FROM posts WHERE user_id = 1', 'time' => 10],
            ['sql' => 'SELECT * FROM posts WHERE user_id = 2', 'time' => 11],
            ['sql' => 'SELECT * FROM posts WHERE user_id = 3', 'time' => 12],
        ];

        $nPlusOnes = QueryAnalyzer::detectNPlusOne($queries);

        $this->assertCount(1, $nPlusOnes);
        $this->assertEquals(3, $nPlusOnes[0]['count']);
    }

    public function testDoesNotFlagDifferentQueries(): void
    {
        $queries = [
            ['sql' => 'SELECT * FROM users WHERE id = 1', 'time' => 10],
            ['sql' => 'SELECT * FROM posts WHERE id = 1', 'time' => 11],
        ];

        $nPlusOnes = QueryAnalyzer::detectNPlusOne($queries);

        $this->assertCount(0, $nPlusOnes);
    }
}
```

```php
// Performance Alerts
class PerformanceAnalyzerTest extends TestCase
{
    public function testDetectsSlowRequest(): void
    {
        $data = [
            'request' => ['time' => 1500, 'memory' => 10_000_000],
            'queries' => ['queries' => []],
        ];

        $alerts = PerformanceAnalyzer::analyze($data);

        $this->assertCount(1, $alerts);
        $this->assertEquals('slow_request', $alerts[0]['type']);
    }
}
```

### Integration Tests

```php
// Request History
class RequestStoreTest extends TestCase
{
    public function testStoresRequestInSession(): void
    {
        $_SESSION = [];

        RequestStore::store('req-1', [
            'method' => 'GET',
            'uri' => '/test',
            'status' => 200,
            'time' => 100,
            'memory' => 1000000,
        ]);

        $history = RequestStore::getAll();

        $this->assertCount(1, $history);
        $this->assertEquals('GET', $history[0]['method']);
    }

    public function testLimitsHistoryTo20Requests(): void
    {
        $_SESSION = [];

        for ($i = 0; $i < 25; $i++) {
            RequestStore::store("req-$i", [
                'method' => 'GET',
                'uri' => "/test-$i",
                'status' => 200,
                'time' => 100,
                'memory' => 1000000,
            ]);
        }

        $history = RequestStore::getAll();

        $this->assertCount(20, $history);
    }
}
```

## Documentation Updates

Update `.zappzarapp/docs/development/DEV-TOOLBAR.md`:

### Phase 2 Features Section

```markdown
## Phase 2 Features (v1.1)

### HTTP Client Tracking

Monitor all outgoing HTTP requests:

- cURL requests
- Guzzle HTTP client
- `file_get_contents()` with HTTP streams

View request/response details, timing, and performance.

### Cache Operations

Track Redis cache operations:

- GET (hits/misses)
- SET
- DELETE
- Patterns and TTL
- Hit rate visualization

### Timeline Visualization

Visual representation of request lifecycle:

- Bootstrap time
- Middleware execution
- Controller processing
- Database queries
- HTTP calls
- View rendering

Identify bottlenecks at a glance.

### Request History

Navigate through recent requests:

- Last 20 requests stored
- Click to view details
- Filter by status, method, URL
- Performance trends over time

### N+1 Query Detection

Automatic detection of N+1 query problems:

- Pattern matching
- Optimization suggestions
- Stack trace to source

### Performance Alerts

Proactive warnings:

- Slow requests (>1s)
- High memory (>50MB)
- Excessive queries (>50)
- Visual indicators in mini bar
```

## Success Criteria

Phase 2 complete when:

- [ ] HTTP Client tab implemented and tracking all HTTP calls
- [ ] Cache tab showing Redis operations with hit rate
- [ ] Timeline tab displaying visual breakdown
- [ ] Request history functional (store, navigate, clear)
- [ ] N+1 query detection working and displaying alerts
- [ ] Performance alerts showing in mini bar and panel
- [ ] DevDashboard pages created (`/_dev/toolbar` and `/_dev/toolbar/request/{id}`)
- [ ] Unit tests passing (>80% coverage for new code)
- [ ] Integration tests passing
- [ ] Documentation updated
- [ ] Manual testing checklist completed
- [ ] No performance regression (<10ms overhead)

## Estimated Effort

**Total: 12-16 hours**

Breakdown:

- HttpClientCollector: 2-3h
- CacheCollector: 2h
- TimelineCollector: 1-2h
- QueryAnalyzer (N+1 detection): 2-3h
- PerformanceAnalyzer: 1h
- RequestStore enhancements: 1h
- DevDashboard pages: 2-3h
- Templates (3 new tabs): 2h
- Testing: 2-3h
- Documentation: 1h

## Priority

**Medium** - Valuable enhancements after Phase 1 is stable

## Dependencies

- Phase 1 Developer Toolbar complete
- DevDashboard functional
- Session management working

## Next Steps

1. Wait for Phase 1 completion and merge
2. Gather user feedback from Phase 1
3. Create feature branch: `feature/dev-toolbar-phase-2`
4. Implement collectors (HTTP, Cache, Timeline)
5. Implement analyzers (N+1, Performance)
6. Build DevDashboard integration pages
7. Add new tabs to toolbar panel
8. Write tests
9. Update documentation
10. Code review
11. Merge to develop
