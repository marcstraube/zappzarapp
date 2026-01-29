# HistoryTabManager Unit Tests (Optional)

**Status:** Pending
**Priority:** Low
**Estimated Effort:** 1-2h

## Context

80 UI tests were written for DevToolbar, covering StorageManager, TabManager, DevToolbarUI, and RequestSwitcher. However, HistoryTabManager was skipped due to complexity.

## Current Test Coverage

### ✅ Tested Components

| Component | Tests | Status |
|-----------|-------|--------|
| StorageManager | 20 | ✅ Pass |
| TabManager | 20 | ✅ Pass |
| DevToolbarUI | 29 | ✅ Pass |
| RequestSwitcher | 29 | ✅ Pass |

### ⚠️ Not Tested

| Component | Reason |
|-----------|--------|
| HistoryTabManager | Complex DOM rendering, filtering, export logic |

## Why HistoryTabManager is Complex

1. **Heavy DOM Manipulation**
   - Renders request list from metadata
   - Dynamic sparkline generation
   - Statistics calculation and display
   - Filter UI interactions

2. **Multiple Responsibilities**
   - Filtering (method, status, URI, time)
   - Export (JSON, CSV)
   - Statistics calculation
   - Sparkline rendering
   - Individual request export
   - Clear history with confirmation

3. **Mock Requirements**
   - Many DOM elements to mock
   - File download simulation
   - Dialog interaction
   - Complex HTML structure

## Proposed Tests

### 1. Initialization Tests

```typescript
describe('HistoryTabManager - Initialization', () => {
  it('should initialize only once', () => {
    const manager = new HistoryTabManager();
    manager.init();
    manager.init(); // Should skip
    // Verify initialization happened only once
  });

  it('should render request list from storage', () => {
    // Setup: Store 3 requests
    // Call: manager.init()
    // Verify: 3 request items rendered
  });

  it('should calculate and display statistics', () => {
    // Setup: Store requests with known metrics
    // Call: manager.init()
    // Verify: avg_time, avg_memory, etc. are correct
  });

  it('should render sparkline trends', () => {
    // Setup: Store requests with time values
    // Call: manager.init()
    // Verify: sparkline element contains visualization
  });
});
```

### 2. Filtering Tests

```typescript
describe('HistoryTabManager - Filtering', () => {
  it('should filter by HTTP method', () => {
    // Setup: Render 3 requests (GET, POST, PUT)
    // Filter: Select "GET"
    // Verify: Only GET request visible
  });

  it('should filter by status code', () => {
    // Setup: Render requests with 200, 404, 500
    // Filter: Select "4xx"
    // Verify: Only 404 visible
  });

  it('should filter by URI substring', () => {
    // Setup: Render /api/users, /api/posts, /home
    // Filter: Enter "api"
    // Verify: Only /api/* visible
  });

  it('should filter by minimum execution time', () => {
    // Setup: Render requests with 50ms, 150ms, 500ms
    // Filter: Set min time to 100
    // Verify: Only 150ms and 500ms visible
  });

  it('should combine multiple filters', () => {
    // Setup: Render mixed requests
    // Filter: method=GET AND status=2xx AND uri contains "api"
    // Verify: Only matching requests visible
  });

  it('should reset filters', () => {
    // Setup: Apply filters
    // Call: Reset button
    // Verify: All requests visible again
  });
});
```

### 3. Export Tests

```typescript
describe('HistoryTabManager - Export', () => {
  it('should export visible history as JSON', () => {
    // Setup: Render and filter requests
    // Mock: downloadFile()
    // Call: Export JSON button
    // Verify: Correct JSON structure downloaded
  });

  it('should export visible history as CSV', () => {
    // Setup: Render requests
    // Mock: downloadFile()
    // Call: Export CSV button
    // Verify: Correct CSV format
  });

  it('should export individual request', () => {
    // Setup: Render request with raw_data
    // Mock: downloadFile()
    // Call: Export button on specific request
    // Verify: Request data exported
  });

  it('should show alert for legacy request without raw_data', () => {
    // Setup: Render old request (no raw_data)
    // Mock: alert()
    // Call: Export button
    // Verify: Alert shown with migration message
  });
});
```

### 4. Clear History Tests

```typescript
describe('HistoryTabManager - Clear History', () => {
  it('should show confirmation dialog before clearing', () => {
    // Setup: Render requests
    // Mock: ClearHistoryDialog
    // Call: Clear history button
    // Verify: Dialog opened
  });

  it('should clear history and reload on confirmation', () => {
    // Setup: Render requests
    // Mock: window.location.reload
    // Call: Confirm clear
    // Verify: StorageManager.clear() called
    // Verify: Page reload triggered
  });

  it('should not clear history on cancel', () => {
    // Setup: Render requests
    // Mock: Dialog cancel
    // Call: Clear button, then cancel
    // Verify: Requests still present
  });
});
```

## Implementation Challenges

1. **DOM Mocking Complexity**
   - Need to mock many nested elements
   - Filter inputs, sparkline div, stats elements
   - Request list container with many items

2. **File Download Mocking**
   - downloadFile() creates download links
   - Hard to verify without real browser

3. **Dialog Interaction**
   - ClearHistoryDialog is a separate component
   - Need to mock open() and callback

4. **Sparkline Rendering**
   - generateSparkline() uses Unicode characters
   - Visual representation hard to test

## Workarounds

1. **Minimal DOM Mocking**
   - Mock only essential elements
   - Use `document.createElement()` for dynamic elements
   - Don't test visual appearance (just data)

2. **Spy on Functions**
   - Spy on `downloadFile()` to verify calls
   - Don't test actual file creation

3. **Mock Dialog**
   - Mock `ClearHistoryDialog.open()` to immediately call callback
   - Skip UI interaction testing

4. **Skip Sparkline Visuals**
   - Test that sparkline element exists
   - Don't test Unicode character accuracy

## Acceptance Criteria

- [ ] At least 15 tests for HistoryTabManager
- [ ] Tests cover initialization, filtering, export, clear
- [ ] Tests use minimal DOM mocking
- [ ] All tests pass
- [ ] Focus on logic, not visual rendering

## Decision: Skip or Implement?

**Arguments for Skipping:**
- Other components have good test coverage (80 tests)
- HistoryTabManager is mostly UI rendering (hard to test)
- Manual testing is sufficient for v1.0
- Time better spent on other tasks

**Arguments for Implementing:**
- Filtering logic is complex (multiple conditions)
- Export logic handles data transformation
- Tests would catch regression bugs
- Good practice for comprehensive coverage

**Recommendation:** **Skip for v1.0**, add in v1.1 if issues arise.
