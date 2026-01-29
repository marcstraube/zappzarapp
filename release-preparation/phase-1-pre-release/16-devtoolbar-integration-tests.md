# DevToolbar Integration Tests (Optional)

**Status:** Pending
**Priority:** Low (Nice-to-have)
**Estimated Effort:** 2-3h

## Context

Unit tests for DevToolbar UI components are complete (80 tests, all passing). However, integration tests for full user flows are missing.

## Current Test Coverage

### ✅ Completed (Unit Tests)

- StorageManager (quota management, LRU eviction, localStorage fallback)
- TabManager (tab switching, badge updates, persistence)
- DevToolbarUI (panel state, event handling, initialization)
- RequestSwitcher (request loading, dropdown population, restoration)

### ❌ Missing (Integration Tests)

- Full request capture → storage → display flow
- Panel open → tab switch → request switch → close flow
- Settings change → persistence → minibar update flow
- History filter → export → clear flow

## Proposed Integration Tests

### 1. End-to-End Request Flow

**Test:** Full lifecycle from PHP injection to localStorage

```typescript
it('should capture request, store in localStorage, and display in history', () => {
  // 1. Mock PHP-injected data
  window.__DEV_TOOLBAR_DATA__ = mockDevToolbarData();

  // 2. Initialize UI
  const ui = new DevToolbarUI();
  ui.init();

  // 3. Verify storage
  const stored = StorageManager.getRequest(mockData.id);
  expect(stored).toBeTruthy();

  // 4. Verify UI reflects data
  expect(document.querySelector('.dev-toolbar-mini')).toBeVisible();

  // 5. Open panel and check history
  const miniBar = document.querySelector('.dev-toolbar-mini');
  miniBar.click();

  const historyTab = document.querySelector('[data-tab="history"]');
  historyTab.click();

  // 6. Verify request appears in history list
  const historyItem = document.querySelector(`[data-request-id="${mockData.id}"]`);
  expect(historyItem).toBeTruthy();
});
```

### 2. Request Switching Flow

**Test:** Load historical request, verify UI updates, restore current

```typescript
it('should load historical request and restore current request', () => {
  // Setup: Store 2 requests
  const current = mockDevToolbarData({ id: 'current-1' });
  const historical = mockDevToolbarData({ id: 'historical-1' });

  StorageManager.storeRequest(historical.id, historical.metadata, historical.tabs);
  window.__DEV_TOOLBAR_DATA__ = current;

  const ui = new DevToolbarUI();
  ui.init();

  // Load historical request
  const switcher = document.querySelector('.dev-toolbar-request-switcher-item[data-request-id="historical-1"]');
  switcher.click();

  // Verify UI shows historical data
  expect(document.querySelector('.dev-toolbar-panel-content')).toContainHTML(historical.tabs.request);

  // Restore current request
  const currentLink = document.querySelector('.dev-toolbar-request-switcher-item[data-request-id="current"]');
  currentLink.click();

  // Verify UI shows current data again
  expect(document.querySelector('.dev-toolbar-panel-content')).toContainHTML(current.tabs.request);
});
```

### 3. Settings Persistence Flow

**Test:** Change settings, verify cookies, reload page, verify persistence

```typescript
it('should persist settings across page reloads', () => {
  const ui = new DevToolbarUI();
  ui.init();

  // Open settings
  const settingsBtn = document.querySelector('.dev-toolbar-panel-settings');
  settingsBtn.click();

  // Change minibar labels
  const branchCheckbox = document.querySelector('#minibar-label-branch') as HTMLInputElement;
  branchCheckbox.checked = true;

  // Save settings
  const saveBtn = document.querySelector('.settings-save-btn');
  saveBtn.click();

  // Verify localStorage
  const config = StorageManager.getConfig();
  expect(config.minibarLabels).toContain('branch');

  // Verify cookie set
  expect(document.cookie).toContain('devbar_labels');

  // Simulate page reload
  localStorage.clear();
  document.cookie = ''; // Clear cookies

  // ... simulate new page load with settings applied
});
```

## Implementation Plan

1. Create `tests/node/backend/integration/DevToolbar/` directory
2. Write integration test files:
   - `full-request-flow.test.ts`
   - `request-switching.test.ts`
   - `settings-persistence.test.ts`
   - `history-management.test.ts`
3. Use happy-dom for DOM simulation
4. Mock PHP window globals properly
5. Test full user journeys (not isolated units)

## Acceptance Criteria

- [ ] At least 3 integration test files created
- [ ] Tests cover complete user flows (not just units)
- [ ] All integration tests pass
- [ ] Tests use realistic scenarios (actual user behavior)
- [ ] Tests validate persistence across "page reloads"

## Notes

- **Priority: Low** - Unit tests already provide good coverage
- Integration tests are useful but not critical for v1.0 release
- Can be added in v1.1 if needed
- Focus on critical user flows first (request capture, switching)
