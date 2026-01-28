# DevToolbar: Xdebug Function Trace Integration

**Status:** 🔴 Not Started
**Priority:** Low
**Effort:** Medium (6-10h)
**Category:** Developer Experience
**Dependencies:** Requires Xdebug extension with function trace enabled

## Context

Xdebug's function trace feature records every function call during request execution, including parameters, return values, and call hierarchy. This complements profiling (which focuses on performance) by providing a complete execution audit trail.

**Related Features:**
- ✅ Phase 1: Xdebug Step Debugging Quick Controls (implemented in feature/dev-toolbar)
- 🔴 Task 28: Xdebug Profiling Integration (performance-focused)
- 🔴 This task: Xdebug Function Trace (execution flow-focused)

## Problem

Debugging complex logic requires understanding execution flow:
- **Current limitation:** Can't see which functions were called and in what order
- **Missing data:** Function parameters and return values during execution
- **Use case:** Debugging unexpected behavior where step debugging is too slow
- **Manual workflow:** Enable trace → reload → download .xt file → parse manually or use external viewer

## Proposed Solution

Add a "Trace" tab that displays a filterable, interactive function call tree with parameters and return values.

### Implementation Approach

#### 1. Trace Data Collection
```php
class TraceCollector implements CollectorInterface
{
    public function collect(): void
    {
        if (!extension_loaded('xdebug')) {
            $this->data = ['enabled' => false];
            return;
        }

        // Check if tracing is active
        $traceEnabled = ini_get('xdebug.trace_enable_trigger') === '1'
            || in_array('trace', explode(',', ini_get('xdebug.mode') ?? ''));

        if (!$traceEnabled) {
            $this->data = ['enabled' => true, 'active' => false];
            return;
        }

        // Get trace file path (Xdebug 3.x: xdebug.output_dir + filename pattern)
        $traceDir = ini_get('xdebug.output_dir') ?: sys_get_temp_dir();
        $traceFile = $this->findLatestTraceFile($traceDir);

        if (!$traceFile) {
            $this->data = ['enabled' => true, 'active' => true, 'error' => 'No trace file found'];
            return;
        }

        // Parse trace file
        $parser = new XdebugTraceParser();
        $traceData = $parser->parse($traceFile, $maxLines = 1000); // Limit for performance

        $this->data = [
            'enabled' => true,
            'active' => true,
            'file' => $traceFile,
            'calls' => $traceData['calls'],
            'total_calls' => $traceData['total_calls'],
            'truncated' => $traceData['truncated'],
        ];
    }

    private function findLatestTraceFile(string $dir): ?string
    {
        $pattern = $dir . '/trace.*.xt';
        $files = glob($pattern);

        if (empty($files)) {
            return null;
        }

        // Get most recent file
        usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));
        return $files[0];
    }
}
```

#### 2. Trace File Parser

Xdebug trace file format (human-readable mode):
```
TRACE START [2024-01-28 10:23:45.123456]
    0.0001     123456   -> App\Controller\HomeController->index() /app/src/Controller/HomeController.php:15
    0.0002     123512     -> App\Service\UserService->getUser() /app/src/Service/UserService.php:23
    0.0003     123568       -> PDO->query() :0
    0.0004     123612       <- PDO->query() = resource(5)
    0.0005     123656     <- App\Service\UserService->getUser() = array(5)
    0.0006     123700   <- App\Controller\HomeController->index() = Response
TRACE END [2024-01-28 10:23:45.234567]
```

**Parser Implementation:**
```php
class XdebugTraceParser
{
    public function parse(string $file, int $maxLines = 1000): array
    {
        $calls = [];
        $stack = [];
        $lineCount = 0;

        $handle = fopen($file, 'r');

        while (($line = fgets($handle)) !== false && $lineCount < $maxLines) {
            if (preg_match('/^\s+(\d+\.\d+)\s+(\d+)\s+(->|<-)\s+(.+)$/', $line, $m)) {
                $time = (float)$m[1];
                $memory = (int)$m[2];
                $direction = $m[3];
                $functionInfo = $m[4];

                if ($direction === '->') {
                    // Function entry
                    $call = $this->parseEntry($functionInfo);
                    $call['time_start'] = $time;
                    $call['memory_start'] = $memory;
                    $call['depth'] = count($stack);

                    $stack[] = count($calls);
                    $calls[] = $call;
                } else {
                    // Function exit
                    if (!empty($stack)) {
                        $callIndex = array_pop($stack);
                        $calls[$callIndex]['time_end'] = $time;
                        $calls[$callIndex]['memory_end'] = $memory;
                        $calls[$callIndex]['duration'] = $time - $calls[$callIndex]['time_start'];
                        $calls[$callIndex]['return_value'] = $this->parseReturnValue($functionInfo);
                    }
                }

                $lineCount++;
            }
        }

        fclose($handle);

        return [
            'calls' => $calls,
            'total_calls' => $lineCount,
            'truncated' => $lineCount >= $maxLines,
        ];
    }

    private function parseEntry(string $info): array
    {
        // Parse: "App\Service\UserService->getUser() /path/file.php:23"
        // Extract: class, method, file, line
        // ...
    }
}
```

#### 3. UI Components

**Call Tree View:**
```
┌─────────────────────────────────────────────────────────────────────┐
│ Function Trace (1,234 calls, truncated to 1,000)                     │
├─────────────────────────────────────────────────────────────────────┤
│ Filters: [x] Hide vendor  [ ] Show internals  Min duration: 1ms     │
├─────────────────────────────────────────────────────────────────────┤
│ ▼ App\Controller\HomeController->index()                     180ms  │
│   ├─ ▼ App\Service\UserService->getUser()                     45ms  │
│   │   ├─ PDO->query("SELECT * FROM users WHERE id = 1")       42ms  │
│   │   │   Returns: PDOStatement                                     │
│   │   └─ PDOStatement->fetchAll()                              3ms  │
│   │       Returns: array(5 items)                                   │
│   ├─ Twig\Template::render(['user' => ...])                   30ms  │
│   └─ Response->send()                                          5ms  │
└─────────────────────────────────────────────────────────────────────┘
```

**Features:**
- Collapsible call tree
- Show/hide function arguments and return values
- Duration and memory delta per call
- Filter by namespace/package
- Search by function name
- Color-coding by duration (red = slow, green = fast)

#### 4. Performance Considerations

**Trace Files Can Be Huge:**
- Simple request: 500-2,000 calls
- Complex request: 10,000-50,000 calls
- File size: 1-100MB

**Mitigation Strategies:**
1. **Limit parsed lines:** Parse only first 1,000 calls (configurable)
2. **Server-side filtering:** Filter vendor code during parsing
3. **On-demand loading:** Parse trace on button click, not during request
4. **Async processing:** Use background job for large traces

**Recommended Approach:**
```php
// Don't parse during request collection
public function collect(): void
{
    // Store only file path and basic info
    $this->data = [
        'trace_file' => $traceFile,
        'file_size' => filesize($traceFile),
        'estimated_calls' => $this->estimateCallCount($traceFile),
    ];
}
```

Then parse via AJAX when user clicks "Load Trace" button.

#### 5. Configuration

Enable/disable tracing via DevToolbar:
```html
<button data-action="enable-trace">
    📜 Enable Function Trace (next request)
</button>

<div class="warning">
    ⚠️ Function tracing has significant performance overhead (~10x slower).
    Only use for debugging specific issues.
</div>
```

Sets `XDEBUG_TRACE=1` trigger.

### User Workflow

1. **Enable Tracing:**
   - Click "Enable Trace" in DevToolbar
   - Reload page to generate trace file

2. **View Trace:**
   - Open "Trace" tab
   - Click "Load Trace" (lazy loading)
   - See function call tree

3. **Analyze:**
   - Expand/collapse call tree
   - Filter to application code only
   - Search for specific function
   - Click function to see parameters/return values

4. **Disable:**
   - Click "Disable Trace" when done
   - (Tracing has ~10x performance overhead)

## Technical Challenges

### Challenge 1: Large Trace Files
**Problem:** 50,000 function calls = 10MB+ file, too large for real-time parsing.

**Solution:**
- Parse asynchronously via AJAX endpoint
- Show loading indicator
- Implement pagination (load first 1,000 calls, "Load more" button)

### Challenge 2: Complex Call Trees
**Problem:** Deep nesting (20+ levels) is hard to visualize.

**Solution:**
- Limit depth to 10 levels by default (expand on demand)
- Add "Flatten" view option (list without hierarchy)
- Highlight current depth level

### Challenge 3: Parameter Serialization
**Problem:** Xdebug truncates large parameters, objects shown as "???"

**Solution:**
- Configure `xdebug.var_display_max_depth` and `xdebug.var_display_max_data`
- Show truncated indicator: "array(50 items) [truncated]"
- Provide tooltip with more details where available

## Implementation Steps

**Phase 1: Basic Integration (4-6h)**
1. Create `TraceCollector`
2. Implement basic trace file parser (limited lines)
3. Add "Trace" tab with simple list view
4. Add enable/disable trace controls

**Phase 2: Enhanced UI (4-6h)**
5. Implement collapsible call tree visualization
6. Add parameter/return value display
7. Implement filtering (vendor code, internals)
8. Add search functionality

**Phase 3: Performance & Polish (2h)**
9. Implement lazy loading / pagination
10. Add warning about performance overhead
11. Documentation

## Alternative: Link to External Tools

Instead of building custom viewer, provide download link to trace file and recommend external tools:
- **Xdebug Trace Viewer** (PhpStorm built-in)
- **Webgrind** (can also parse traces)
- **Custom CLI tool** (parse with grep/awk)

**Trade-off:** Simpler implementation but worse UX (context switching).

## Success Criteria

- [ ] DevToolbar shows function call tree with parameters/returns
- [ ] Developers can enable/disable tracing via toolbar
- [ ] Parsing performance: < 200ms for 1,000 calls
- [ ] UI handles deep nesting (10+ levels) gracefully
- [ ] Filter options: vendor code, PHP internals, min duration
- [ ] Works with Xdebug 3.x trace mode
- [ ] Documentation includes trace configuration examples

## Configuration Example

**docker/php/conf.d/xdebug.ini:**
```ini
[xdebug]
xdebug.mode = debug,trace
xdebug.start_with_request = trigger
xdebug.trace_enable_trigger = 1
xdebug.trace_output_dir = /tmp/xdebug-traces
xdebug.trace_format = 0  ; Human-readable format
xdebug.collect_params = 4  ; Full variable contents
xdebug.collect_return = 1  ; Collect return values
xdebug.var_display_max_depth = 5
xdebug.var_display_max_data = 256
```

## References

- [Xdebug Function Trace Documentation](https://xdebug.org/docs/trace)
- [Xdebug 3.x Trace Format](https://xdebug.org/docs/all_settings#trace_format)
- [PhpStorm Trace File Viewer](https://www.jetbrains.com/help/phpstorm/viewing-execution-traces.html)

## Related Tasks

- Task 19: Debug Command (phase-2, implements debug mode infrastructure)
- Task 25: DevToolbar Phase 2 Enhanced (this task extends Phase 2)
- Task 28: Xdebug Profiling Integration (complementary feature)
