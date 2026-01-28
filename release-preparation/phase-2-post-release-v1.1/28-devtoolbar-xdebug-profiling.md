# DevToolbar: Xdebug Profiling Integration

**Status:** 🔴 Not Started
**Priority:** Medium
**Effort:** Medium (8-12h)
**Category:** Developer Experience
**Dependencies:** Requires Xdebug extension with profiling enabled

## Context

The DevToolbar currently provides basic performance metrics (execution time, memory usage, query count). Xdebug's profiling feature (Cachegrind format) provides granular function-level performance data that would significantly enhance debugging capabilities.

**Related Features:**
- ✅ Phase 1: Xdebug Step Debugging Quick Controls (implemented in feature/dev-toolbar)
- 🔴 This task: Xdebug Profiling Visualization
- 🔴 Task 29: Xdebug Function Trace Integration

## Problem

Developers currently have limited insight into where execution time is spent:
- **Current state:** Overall execution time (e.g., "270ms") without breakdown
- **What's missing:** Which functions consume the most time/memory/calls
- **Manual workflow:** Enable Xdebug profiling → reload page → download cachegrind file → open in external tool (KCachegrind, Webgrind)
- **Pain point:** Context switching between IDE, browser, and profiling tools

## Proposed Solution

Add a "Profiling" tab (or integrate into Timeline tab) that visualizes Xdebug cachegrind data.

### Implementation Approach

#### 1. Profiling Data Collection
```php
class ProfilingCollector implements CollectorInterface
{
    public function collect(): void
    {
        if (!extension_loaded('xdebug')) {
            $this->data = ['enabled' => false];
            return;
        }

        // Check if profiling is active
        $profilingEnabled = ini_get('xdebug.profiler_enable') === '1'
            || ini_get('xdebug.mode') === 'profile';

        if (!$profilingEnabled) {
            $this->data = ['enabled' => true, 'active' => false];
            return;
        }

        // Get cachegrind file path
        $filename = xdebug_get_profiler_filename();

        if (!$filename || !file_exists($filename)) {
            $this->data = ['enabled' => true, 'active' => true, 'error' => 'No profile data'];
            return;
        }

        // Parse cachegrind file
        $parser = new CachegrindParser();
        $profileData = $parser->parse($filename);

        $this->data = [
            'enabled' => true,
            'active' => true,
            'file' => $filename,
            'functions' => $profileData['functions'],
            'total_time' => $profileData['summary']['total_time'],
            'total_calls' => $profileData['summary']['total_calls'],
        ];
    }
}
```

#### 2. Cachegrind Parser
Parse cachegrind file format and extract:
- Function names with namespace
- Inclusive/exclusive time (microseconds)
- Call counts
- Memory usage (if available)
- Caller/callee relationships

Reference: [Valgrind Cachegrind Format](https://valgrind.org/docs/manual/cl-format.html)

#### 3. UI Components

**Top Functions Table:**
```
┌─────────────────────────────────────────────────────────────────┐
│ Top 20 Slowest Functions                                         │
├─────────────────────────────────┬─────────┬───────┬─────────────┤
│ Function                        │ Time    │ Calls │ Time/Call   │
├─────────────────────────────────┼─────────┼───────┼─────────────┤
│ App\Service\Heavy::process()    │ 180ms   │ 1     │ 180ms       │
│ PDOStatement::execute()         │  45ms   │ 12    │  3.75ms     │
│ Twig\Template::render()         │  30ms   │ 1     │  30ms       │
└─────────────────────────────────┴─────────┴───────┴─────────────┘
```

**Visual Breakdown (Flame Graph or Bar Chart):**
- Show time distribution across functions
- Click function to see call hierarchy
- Filter by namespace/package

**Controls:**
- Sort by: Time (inclusive), Time (exclusive), Calls, Time/Call
- Filter: Vendor code, Application code, PHP internals
- Threshold: Hide functions < 1ms

#### 4. Performance Considerations

**Storage Strategy:**
- Cachegrind files can be large (5-50MB for complex apps)
- **Don't** store in localStorage
- **Option A:** Parse on-demand, cache parsed summary in memory
- **Option B:** Generate summary during request, store only top N functions

**Recommended:**
```php
// Store only essential summary
$summary = [
    'top_20_functions' => array_slice($functions, 0, 20),
    'summary' => ['total_time' => X, 'total_calls' => Y],
    'file_path' => $filename, // For full analysis link
];
```

#### 5. Configuration

Allow developers to enable/disable profiling via DevToolbar:
```html
<button data-action="enable-profiling">
    📊 Enable Profiling (next request)
</button>
```

Sets `XDEBUG_PROFILE=1` trigger or cookie.

### User Workflow

1. **Enable Profiling:**
   - Click "Enable Profiling" in DevToolbar
   - Reload page to generate profile data

2. **View Results:**
   - Open "Profiling" tab
   - See top 20 slowest functions
   - Identify performance bottlenecks

3. **Drill Down:**
   - Click function to see call stack
   - See which function called it and how many times
   - Export full cachegrind file for external tools

4. **Disable:**
   - Click "Disable Profiling" when done
   - (Profiling has ~30% performance overhead)

## Technical Challenges

### Challenge 1: Cachegrind File Parsing
**Problem:** Cachegrind format is complex, line-based, with compressed function references.

**Solution:**
- Use existing PHP library: [`clue/graph-composer`](https://github.com/clue/graph-composer) has cachegrind parser
- Or implement minimal parser (focus on function summary, skip call graph for simplicity)

### Challenge 2: Large Files
**Problem:** Cachegrind files can be 10-50MB for complex applications.

**Solution:**
- Set `xdebug.profiler_output_name` to unique per-request filename
- Parse incrementally (stream-based parsing)
- Store only top N functions in DevToolbar data
- Provide download link for full analysis

### Challenge 3: IDE Integration
**Problem:** Developers may want to jump to function definition from profiling results.

**Solution (Future Enhancement):**
- Add file:line links to function entries
- Use PhpStorm/VSCode URL handler protocol
- Example: `phpstorm://open?file=/path/to/file.php&line=42`

## Implementation Steps

**Phase 1: Basic Integration (6-8h)**
1. Create `ProfilingCollector`
2. Implement basic cachegrind parser (top N functions only)
3. Add "Profiling" tab with table view
4. Add enable/disable profiling controls

**Phase 2: Enhanced Visualization (4-6h)**
5. Add flame graph or bar chart visualization
6. Implement call hierarchy drill-down
7. Add filtering/sorting controls
8. Export functionality

**Phase 3: Polish (2h)**
9. Performance optimization (large file handling)
10. Documentation and examples

## Alternative: Third-Party Integration

Instead of building custom parser, integrate existing tools:
- **Webgrind:** Embed iframe to Webgrind instance
- **Blackfire.io:** Link to Blackfire profile (if available)
- **Tideways:** Link to Tideways UI

**Trade-off:** Requires external service/tool setup vs. self-contained solution.

## Success Criteria

- [ ] DevToolbar shows top 20 slowest functions with time/calls
- [ ] Developers can enable/disable profiling via toolbar
- [ ] Performance overhead: Parsing adds < 100ms to page load
- [ ] Works with Xdebug 3.x profiling mode
- [ ] Handles large cachegrind files (> 10MB) gracefully
- [ ] Documentation includes setup instructions for Xdebug profiling

## References

- [Xdebug Profiling Documentation](https://xdebug.org/docs/profiler)
- [Cachegrind Format Specification](https://valgrind.org/docs/manual/cl-format.html)
- [Webgrind (Web-based Profiler Viewer)](https://github.com/jokkedk/webgrind)
- [KCachegrind (Desktop Tool)](https://kcachegrind.github.io/)

## Related Tasks

- Task 19: Debug Command (phase-2, implements debug mode infrastructure)
- Task 25: DevToolbar Phase 2 Enhanced (this task extends Phase 2)
- Task 29: Xdebug Function Trace Integration (complementary feature)
