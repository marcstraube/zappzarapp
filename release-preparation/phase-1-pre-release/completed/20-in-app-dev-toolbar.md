# 20: In-App Developer Toolbar (Phase 1 - MVP)

## Status

🟡 **Planned** - Awaiting implementation approval

## Goal

Implement a minimal, non-intrusive developer toolbar that provides essential
debugging information directly on the page during development. Enhances
developer experience by surfacing critical metrics (execution time, memory,
queries, exceptions) without context switching to external tools.

**Core Principle:** Security-by-Design, Zero-Config

## Value Proposition

### Why Build Custom vs. External Library?

**Security-by-Design:**

- Full control over what code runs in development environment
- No external dependencies with potential security vulnerabilities
- Clean separation dev/production (no foreign code in production images)
- Custom filtering for sensitive data (aligned with zappzarapp standards)

**Zero-Config:**

- Developers get consistent DX out of the box
- `make setup` → toolbar automatically available
- No manual installation or configuration needed per developer
- Part of the platform experience

**Integration:**

- Seamlessly integrates with existing DevDashboard
- Shared services (HealthCheck, SystemInfo, etc.)
- Consistent UI/UX across development tools
- Toolbar → Click → Opens relevant DevDashboard page

### Target Users

- Developers building applications on zappzarapp
- Teams debugging performance issues
- Anyone needing quick request/query insights

## Phase 1 Scope (MVP)

### Features Included

#### 1. Floating Mini Bar

Always-visible widget in bottom-right corner:

```
┌─────────────────────────────────────────┐
│ ⚡ dev │ 127ms │ 2.3MB │ 8 queries │ ↗ │
└─────────────────────────────────────────┘
```

**Displays:**

- Environment indicator (dev/staging - color coded)
- Request execution time (ms)
- Memory peak usage (MB)
- Database query count (if DB enabled)
- Expand button

**Interaction:**

- Click → Expands to full panel
- Hover → Slight elevation (visual feedback)
- Minimal screen real estate (<100px width)

#### 2. Expandable Panel (Slide-Up from Bottom)

Full-width panel with tabbed interface:

```
┌──────────────────────────────────────────────────────┐
│ REQUEST   MESSAGES   QUERIES   EXCEPTIONS   ▼ CLOSE │
├──────────────────────────────────────────────────────┤
│ [Tab Content - See below]                            │
│                                                       │
│ Height: 400px, Scrollable, Resizable (future)       │
└──────────────────────────────────────────────────────┘
```

#### 3. Panel Tabs

**Tab 1: REQUEST**

Display:

- HTTP Method + URI + Status Code
- Controller/Route matched (if available)
- Request Headers (collapsible sections)
- Request Body (POST/PUT - with sensitive data filtering)
- Response Headers
- Session Data (filtered)
- Cookies (filtered)

Action:

- "View in DevDashboard" button → Opens `/_dev` with request details

**Tab 2: MESSAGES**

Display:

- Monolog log messages from current request
- Log levels: debug/info/warning/error (color coded)
- Timestamp + Message
- Context data (collapsible JSON)
- Filter by log level

Implementation:

- Custom `DevToolbarHandler` extends Monolog `AbstractProcessingHandler`
- Collects messages in memory during request lifecycle
- Attached to Monolog logger in development mode only

**Tab 3: QUERIES**

Display:

- All database queries executed in current request
- Execution time per query
- Query parameters (prepared statement bindings)
- Stack trace (file + line where query was called)
- Performance indicators:
  - Fast (<100ms): green
  - Slow (100-500ms): orange
  - Very slow (>500ms): red + bold

Features:

- Collapsible query details
- Copy query button (for testing in DB client)
- Total queries count + total time

Implementation:

- PDO wrapper/proxy class
- Tracks: SQL, bindings, execution time, backtrace

**Tab 4: EXCEPTIONS**

Display:

- All exceptions thrown during request (even if caught/handled)
- Exception class name
- Exception message
- Stack trace (collapsible)
- File + Line number (clickable if IDE integration available)

Color coding:

- Handled exceptions: yellow background
- Unhandled exceptions: red background

Implementation:

- Custom exception handler
- Registers with `set_exception_handler()` and `set_error_handler()`
- Tracks all exceptions in memory

## Architecture

### Directory Structure

```
src/php/DevToolbar/
├── DevToolbar.php                      # Main singleton class
├── Guard/
│   └── DevToolbarGuard.php             # Security: checks if enabled
├── DataCollectors/
│   ├── CollectorInterface.php          # Interface for all collectors
│   ├── RequestCollector.php            # Request/Response/Session data
│   ├── QueryCollector.php              # Database queries (PDO wrapper)
│   ├── MessageCollector.php            # Monolog handler for log messages
│   └── ExceptionCollector.php          # Exception tracking
├── Middleware/
│   └── DevToolbarMiddleware.php        # Injects toolbar HTML into response
├── Renderers/
│   ├── RendererInterface.php           # Interface for renderers
│   ├── MiniBarRenderer.php             # Renders mini bar HTML
│   └── PanelRenderer.php               # Renders expandable panel HTML
└── Storage/
    └── RequestStore.php                # Stores request data (for DevDashboard integration)

resources/dev-toolbar/
├── toolbar.js                          # JavaScript for UI interactions
└── toolbar.css                         # CSS styles (extends app.css variables)

templates/dev-toolbar/
├── mini-bar.html.twig                  # Mini bar template
├── panel.html.twig                     # Panel container template
└── tabs/
    ├── request.html.twig               # REQUEST tab content
    ├── messages.html.twig              # MESSAGES tab content
    ├── queries.html.twig               # QUERIES tab content
    └── exceptions.html.twig            # EXCEPTIONS tab content

tests/php/DevToolbar/
├── DevToolbarTest.php
├── Guard/
│   └── DevToolbarGuardTest.php
├── DataCollectors/
│   ├── RequestCollectorTest.php
│   ├── QueryCollectorTest.php
│   ├── MessageCollectorTest.php
│   └── ExceptionCollectorTest.php
└── Middleware/
    └── DevToolbarMiddlewareTest.php
```

### Class Design

#### DevToolbar (Main Class)

```php
<?php

namespace DevToolbar;

class DevToolbar
{
    private static ?self $instance = null;
    private array $collectors = [];
    private bool $booted = false;

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        // Register collectors
        $this->registerCollectors();

        // Start collecting
        foreach ($this->collectors as $collector) {
            $collector->start();
        }

        $this->booted = true;
    }

    public function render(): void
    {
        if (!DevToolbarGuard::isEnabled()) {
            return;
        }

        // Stop all collectors
        foreach ($this->collectors as $collector) {
            $collector->stop();
        }

        // Inject toolbar HTML before </body>
        $middleware = new DevToolbarMiddleware($this->collectors);
        $middleware->inject();
    }

    private function registerCollectors(): void
    {
        $this->collectors['request'] = new RequestCollector();
        $this->collectors['queries'] = new QueryCollector();
        $this->collectors['messages'] = new MessageCollector();
        $this->collectors['exceptions'] = new ExceptionCollector();
    }
}
```

#### DevToolbarGuard (Security)

```php
<?php

namespace DevToolbar\Guard;

class DevToolbarGuard
{
    public static function isEnabled(): bool
    {
        // Production safety
        if (getenv('APP_ENV') === 'production') {
            return false;
        }

        // Explicit disable
        if (getenv('ENABLE_DEV_TOOLBAR') === 'false') {
            return false;
        }

        // Never load in CLI
        if (PHP_SAPI === 'cli') {
            return false;
        }

        // Optional: Skip for AJAX requests
        if (self::isAjaxRequest()) {
            return false;
        }

        return true;
    }

    private static function isAjaxRequest(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}
```

#### CollectorInterface

```php
<?php

namespace DevToolbar\DataCollectors;

interface CollectorInterface
{
    /**
     * Start collecting data
     */
    public function start(): void;

    /**
     * Stop collecting data
     */
    public function stop(): void;

    /**
     * Get collected data
     *
     * @return array<string, mixed>
     */
    public function getData(): array;

    /**
     * Get collector name (for tab label)
     */
    public function getName(): string;
}
```

#### QueryCollector (Example)

```php
<?php

namespace DevToolbar\DataCollectors;

use PDO;
use PDOStatement;

class QueryCollector extends PDO implements CollectorInterface
{
    private array $queries = [];
    private bool $collecting = false;

    public function start(): void
    {
        $this->collecting = true;
    }

    public function stop(): void
    {
        $this->collecting = false;
    }

    public function query($statement, $mode = PDO::ATTR_DEFAULT_FETCH_MODE, ...$fetch_mode_args): PDOStatement|false
    {
        if (!$this->collecting) {
            return parent::query($statement, $mode, ...$fetch_mode_args);
        }

        $start = hrtime(true);
        $result = parent::query($statement, $mode, ...$fetch_mode_args);
        $time = (hrtime(true) - $start) / 1_000_000; // Convert to ms

        $this->queries[] = [
            'sql' => $statement,
            'bindings' => [],
            'time' => round($time, 2),
            'backtrace' => $this->getRelevantBacktrace(),
        ];

        return $result;
    }

    public function getData(): array
    {
        return [
            'queries' => $this->queries,
            'count' => count($this->queries),
            'total_time' => round(array_sum(array_column($this->queries, 'time')), 2),
        ];
    }

    public function getName(): string
    {
        return 'queries';
    }

    private function getRelevantBacktrace(): array
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);

        // Filter out DevToolbar and PDO internals
        return array_filter($trace, function($frame) {
            return !str_contains($frame['file'] ?? '', 'DevToolbar')
                && !str_contains($frame['file'] ?? '', 'PDO');
        });
    }
}
```

### Integration Points

#### public/index.php

```php
<?php

declare(strict_types=1);

// Bootstrap application
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

#### Monolog Integration

```php
<?php

// config/logging.php or wherever Monolog is configured

use DevToolbar\DataCollectors\MessageCollector;
use Monolog\Logger;

$logger = new Logger('app');

// Add DevToolbar handler in development
if (DevToolbar\Guard\DevToolbarGuard::isEnabled()) {
    $logger->pushHandler(new MessageCollector());
}

// ... other handlers ...
```

## CSS Implementation (No Tailwind)

```css
/* resources/dev-toolbar/toolbar.css */

/* CSS Variables (extends app.css) */
:root {
    --toolbar-bg-dark: rgba(17, 24, 39, 0.95);
    --toolbar-bg-light: #ffffff;
    --toolbar-border: #e5e7eb;
    --toolbar-shadow: 0 -4px 20px rgba(0, 0, 0, 0.15);
    --toolbar-transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* === MINI BAR === */

.dev-toolbar-mini {
    position: fixed;
    bottom: 10px;
    right: 10px;
    background: var(--toolbar-bg-dark);
    color: white;
    padding: 8px 16px;
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-lg);
    font-family: var(--font-mono);
    font-size: 0.875rem;
    z-index: 9999;
    cursor: pointer;
    transition: var(--toolbar-transition);
    display: flex;
    align-items: center;
    gap: 12px;
}

.dev-toolbar-mini:hover {
    transform: translateY(-2px);
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);
}

.dev-toolbar-mini-env {
    background: var(--color-success);
    padding: 2px 8px;
    border-radius: var(--radius-sm);
    font-weight: 600;
    font-size: 0.75rem;
    text-transform: uppercase;
}

.dev-toolbar-mini-env.staging {
    background: var(--color-warning);
}

.dev-toolbar-mini-env.production {
    background: var(--color-danger);
}

.dev-toolbar-mini-metric {
    display: flex;
    align-items: center;
    gap: 4px;
}

.dev-toolbar-mini-expand {
    margin-left: 8px;
    font-size: 1.2rem;
    transition: transform 0.2s ease;
}

.dev-toolbar-mini:hover .dev-toolbar-mini-expand {
    transform: translateY(-2px);
}

/* === EXPANDABLE PANEL === */

.dev-toolbar-panel {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    height: 400px;
    background: var(--toolbar-bg-light);
    box-shadow: var(--toolbar-shadow);
    z-index: 9998;
    transform: translateY(100%);
    transition: var(--toolbar-transition);
    display: flex;
    flex-direction: column;
}

.dev-toolbar-panel.open {
    transform: translateY(0);
}

/* Panel Header */
.dev-toolbar-panel-header {
    display: flex;
    border-bottom: 2px solid var(--toolbar-border);
    background: #f9fafb;
    padding: 0;
}

.dev-toolbar-panel-tab {
    padding: 12px 24px;
    background: transparent;
    border: none;
    font-family: var(--font-sans);
    font-size: 0.875rem;
    font-weight: 500;
    color: #6b7280;
    cursor: pointer;
    transition: all 0.2s ease;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
}

.dev-toolbar-panel-tab:hover {
    color: #111827;
    background: rgba(59, 130, 246, 0.05);
}

.dev-toolbar-panel-tab.active {
    color: var(--color-primary);
    border-bottom-color: var(--color-primary);
    background: white;
}

.dev-toolbar-panel-tab-badge {
    display: inline-block;
    margin-left: 6px;
    padding: 2px 8px;
    background: #e5e7eb;
    border-radius: var(--radius-sm);
    font-size: 0.75rem;
    font-weight: 600;
}

.dev-toolbar-panel-tab.active .dev-toolbar-panel-tab-badge {
    background: var(--color-primary);
    color: white;
}

.dev-toolbar-panel-close {
    margin-left: auto;
    padding: 12px 24px;
    background: transparent;
    border: none;
    font-size: 1.2rem;
    cursor: pointer;
    color: #6b7280;
    transition: color 0.2s ease;
}

.dev-toolbar-panel-close:hover {
    color: var(--color-danger);
}

/* Panel Content */
.dev-toolbar-panel-content {
    flex: 1;
    overflow-y: auto;
    padding: 16px;
}

.dev-toolbar-panel-tab-pane {
    display: none;
}

.dev-toolbar-panel-tab-pane.active {
    display: block;
}

/* === TAB CONTENT STYLES === */

/* Query Item */
.dev-toolbar-query {
    margin-bottom: 16px;
    padding: 12px;
    background: #f9fafb;
    border-radius: var(--radius-md);
    border-left: 3px solid var(--color-success);
}

.dev-toolbar-query.slow {
    border-left-color: var(--color-warning);
}

.dev-toolbar-query.very-slow {
    border-left-color: var(--color-danger);
    background: #fef2f2;
}

.dev-toolbar-query-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.dev-toolbar-query-time {
    font-family: var(--font-mono);
    font-size: 0.875rem;
    font-weight: 600;
}

.dev-toolbar-query-time.fast {
    color: var(--color-success);
}

.dev-toolbar-query-time.slow {
    color: var(--color-warning);
}

.dev-toolbar-query-time.very-slow {
    color: var(--color-danger);
}

.dev-toolbar-query-sql {
    font-family: var(--font-mono);
    font-size: 0.875rem;
    background: #1f2937;
    color: #e5e7eb;
    padding: 12px;
    border-radius: var(--radius-sm);
    overflow-x: auto;
    margin-bottom: 8px;
}

.dev-toolbar-query-bindings {
    font-family: var(--font-mono);
    font-size: 0.75rem;
    color: #6b7280;
    margin-bottom: 8px;
}

.dev-toolbar-query-trace {
    font-family: var(--font-mono);
    font-size: 0.75rem;
    color: #6b7280;
}

/* Log Message */
.dev-toolbar-message {
    margin-bottom: 12px;
    padding: 10px 12px;
    border-radius: var(--radius-sm);
    border-left: 3px solid #9ca3af;
    background: #f9fafb;
}

.dev-toolbar-message.debug {
    border-left-color: #9ca3af;
}

.dev-toolbar-message.info {
    border-left-color: var(--color-info);
    background: #ecfeff;
}

.dev-toolbar-message.warning {
    border-left-color: var(--color-warning);
    background: #fffbeb;
}

.dev-toolbar-message.error {
    border-left-color: var(--color-danger);
    background: #fef2f2;
}

.dev-toolbar-message-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 4px;
}

.dev-toolbar-message-level {
    font-family: var(--font-mono);
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.dev-toolbar-message-time {
    font-family: var(--font-mono);
    font-size: 0.75rem;
    color: #6b7280;
}

.dev-toolbar-message-text {
    font-size: 0.875rem;
    margin-bottom: 4px;
}

.dev-toolbar-message-context {
    font-family: var(--font-mono);
    font-size: 0.75rem;
    color: #6b7280;
    background: white;
    padding: 8px;
    border-radius: var(--radius-sm);
    overflow-x: auto;
}

/* Exception Item */
.dev-toolbar-exception {
    margin-bottom: 16px;
    padding: 12px;
    border-radius: var(--radius-md);
    border-left: 3px solid var(--color-danger);
    background: #fef2f2;
}

.dev-toolbar-exception.handled {
    border-left-color: var(--color-warning);
    background: #fffbeb;
}

.dev-toolbar-exception-class {
    font-family: var(--font-mono);
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--color-danger);
    margin-bottom: 4px;
}

.dev-toolbar-exception.handled .dev-toolbar-exception-class {
    color: var(--color-warning);
}

.dev-toolbar-exception-message {
    font-size: 0.875rem;
    margin-bottom: 8px;
}

.dev-toolbar-exception-location {
    font-family: var(--font-mono);
    font-size: 0.75rem;
    color: #6b7280;
    margin-bottom: 8px;
}

.dev-toolbar-exception-trace {
    font-family: var(--font-mono);
    font-size: 0.75rem;
    background: #1f2937;
    color: #e5e7eb;
    padding: 12px;
    border-radius: var(--radius-sm);
    overflow-x: auto;
    max-height: 200px;
    overflow-y: auto;
}

/* === UTILITY CLASSES === */

.dev-toolbar-section {
    margin-bottom: 24px;
}

.dev-toolbar-section-title {
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 12px;
    color: #111827;
}

.dev-toolbar-kv-table {
    width: 100%;
    font-size: 0.875rem;
}

.dev-toolbar-kv-table td {
    padding: 6px 12px;
    border-bottom: 1px solid var(--toolbar-border);
}

.dev-toolbar-kv-table td:first-child {
    font-family: var(--font-mono);
    font-weight: 600;
    color: #6b7280;
    width: 200px;
}

.dev-toolbar-kv-table td:last-child {
    word-break: break-all;
}

.dev-toolbar-filtered {
    color: #9ca3af;
    font-style: italic;
}

.dev-toolbar-btn {
    padding: 6px 12px;
    background: var(--color-primary);
    color: white;
    border: none;
    border-radius: var(--radius-sm);
    font-size: 0.875rem;
    cursor: pointer;
    transition: background 0.2s ease;
}

.dev-toolbar-btn:hover {
    background: #2563eb;
}

.dev-toolbar-btn-secondary {
    background: #e5e7eb;
    color: #374151;
}

.dev-toolbar-btn-secondary:hover {
    background: #d1d5db;
}
```

## JavaScript Implementation

```javascript
// resources/dev-toolbar/toolbar.js

(function() {
    'use strict';

    const DevToolbar = {
        miniBar: null,
        panel: null,
        currentTab: 'request',

        init() {
            this.miniBar = document.querySelector('.dev-toolbar-mini');
            this.panel = document.querySelector('.dev-toolbar-panel');

            if (!this.miniBar || !this.panel) {
                console.warn('DevToolbar: Elements not found');
                return;
            }

            this.attachEventListeners();
            this.setActiveTab(this.currentTab);
        },

        attachEventListeners() {
            // Mini bar click - toggle panel
            this.miniBar.addEventListener('click', () => {
                this.togglePanel();
            });

            // Tab clicks
            document.querySelectorAll('.dev-toolbar-panel-tab').forEach(tab => {
                tab.addEventListener('click', (e) => {
                    const tabName = e.target.dataset.tab;
                    this.setActiveTab(tabName);
                });
            });

            // Close button
            const closeBtn = document.querySelector('.dev-toolbar-panel-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', () => {
                    this.closePanel();
                });
            }

            // ESC key to close
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.panel.classList.contains('open')) {
                    this.closePanel();
                }
            });

            // Copy query buttons
            document.querySelectorAll('.dev-toolbar-copy-query').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const query = e.target.dataset.query;
                    this.copyToClipboard(query);
                    this.showCopyFeedback(e.target);
                });
            });
        },

        togglePanel() {
            if (this.panel.classList.contains('open')) {
                this.closePanel();
            } else {
                this.openPanel();
            }
        },

        openPanel() {
            this.panel.classList.add('open');
        },

        closePanel() {
            this.panel.classList.remove('open');
        },

        setActiveTab(tabName) {
            this.currentTab = tabName;

            // Update tab buttons
            document.querySelectorAll('.dev-toolbar-panel-tab').forEach(tab => {
                if (tab.dataset.tab === tabName) {
                    tab.classList.add('active');
                } else {
                    tab.classList.remove('active');
                }
            });

            // Update tab panes
            document.querySelectorAll('.dev-toolbar-panel-tab-pane').forEach(pane => {
                if (pane.dataset.tab === tabName) {
                    pane.classList.add('active');
                } else {
                    pane.classList.remove('active');
                }
            });
        },

        copyToClipboard(text) {
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text);
            } else {
                // Fallback
                const textarea = document.createElement('textarea');
                textarea.value = text;
                textarea.style.position = 'fixed';
                textarea.style.opacity = '0';
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
            }
        },

        showCopyFeedback(btn) {
            const originalText = btn.textContent;
            btn.textContent = 'Copied!';
            btn.disabled = true;

            setTimeout(() => {
                btn.textContent = originalText;
                btn.disabled = false;
            }, 2000);
        }
    };

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => DevToolbar.init());
    } else {
        DevToolbar.init();
    }
})();
```

## Security Considerations

### 1. Production Safety (Defense in Depth)

```php
// Multiple layers of protection

// Layer 1: Environment check
if (getenv('APP_ENV') === 'production') return false;

// Layer 2: Explicit disable flag
if (getenv('ENABLE_DEV_TOOLBAR') === 'false') return false;

// Layer 3: Development file check
if (!file_exists(__DIR__ . '/../../.env')) return false;

// Layer 4: CLI detection
if (PHP_SAPI === 'cli') return false;
```

### 2. Sensitive Data Filtering

```php
private const SENSITIVE_PATTERNS = [
    'password', 'passwd', 'pwd',
    'secret', 'token', 'api_key', 'apikey',
    'private_key', 'access_token', 'refresh_token',
    'session', 'cookie', 'authorization',
    'credit_card', 'cvv', 'ssn',
];

public function filterSensitiveData(array $data): array
{
    $filtered = [];

    foreach ($data as $key => $value) {
        $keyLower = strtolower($key);
        $isSensitive = false;

        foreach (self::SENSITIVE_PATTERNS as $pattern) {
            if (str_contains($keyLower, $pattern)) {
                $isSensitive = true;
                break;
            }
        }

        if ($isSensitive) {
            $filtered[$key] = '[FILTERED]';
        } elseif (is_array($value)) {
            $filtered[$key] = $this->filterSensitiveData($value);
        } else {
            $filtered[$key] = $value;
        }
    }

    return $filtered;
}
```

### 3. XSS Protection

All output must be escaped:

```twig
{# templates/dev-toolbar/tabs/queries.html.twig #}

{# GOOD - Escaped #}
<div class="dev-toolbar-query-sql">{{ query.sql|e }}</div>

{# BAD - Never do this #}
<div class="dev-toolbar-query-sql">{{ query.sql|raw }}</div>
```

### 4. Docker Build Exclusion

```dockerfile
# docker/php/Dockerfile

ARG BUILD_ENV=production

# DevToolbar only copied in development builds
COPY --chown=www-data:www-data \
    $(if [ "$BUILD_ENV" = "development" ]; then echo "src/php/DevToolbar"; else echo "/dev/null"; fi) \
    /var/www/src/php/DevToolbar || true
```

### 5. No External Dependencies

- No external JavaScript libraries (pure vanilla JS)
- No external CSS frameworks (custom CSS using app.css variables)
- Minimizes attack surface and supply chain risks

## Testing Strategy

### Unit Tests

```php
// tests/php/DevToolbar/Guard/DevToolbarGuardTest.php

class DevToolbarGuardTest extends TestCase
{
    public function testDisabledInProduction(): void
    {
        putenv('APP_ENV=production');
        $this->assertFalse(DevToolbarGuard::isEnabled());
    }

    public function testEnabledInDevelopment(): void
    {
        putenv('APP_ENV=development');
        putenv('ENABLE_DEV_TOOLBAR=true');
        $this->assertTrue(DevToolbarGuard::isEnabled());
    }

    public function testExplicitDisable(): void
    {
        putenv('APP_ENV=development');
        putenv('ENABLE_DEV_TOOLBAR=false');
        $this->assertFalse(DevToolbarGuard::isEnabled());
    }

    public function testDisabledInCli(): void
    {
        // PHP_SAPI is 'cli' during PHPUnit tests
        $this->assertFalse(DevToolbarGuard::isEnabled());
    }
}
```

```php
// tests/php/DevToolbar/DataCollectors/RequestCollectorTest.php

class RequestCollectorTest extends TestCase
{
    private RequestCollector $collector;

    protected function setUp(): void
    {
        $this->collector = new RequestCollector();
    }

    public function testCollectsRequestMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->collector->start();
        $this->collector->stop();

        $data = $this->collector->getData();
        $this->assertEquals('POST', $data['method']);
    }

    public function testFiltersSensitivePostData(): void
    {
        $_POST = [
            'username' => 'john',
            'password' => 'secret123',
            'api_token' => 'abc123',
        ];

        $this->collector->start();
        $this->collector->stop();

        $data = $this->collector->getData();
        $this->assertEquals('john', $data['post']['username']);
        $this->assertEquals('[FILTERED]', $data['post']['password']);
        $this->assertEquals('[FILTERED]', $data['post']['api_token']);
    }
}
```

### Integration Tests

```php
// tests/php/DevToolbar/DevToolbarTest.php

class DevToolbarTest extends TestCase
{
    public function testToolbarInjectsIntoHtml(): void
    {
        putenv('APP_ENV=development');
        putenv('ENABLE_DEV_TOOLBAR=true');

        // Simulate request
        ob_start();

        $toolbar = DevToolbar::getInstance();
        $toolbar->boot();

        echo '<html><body><h1>Test</h1></body></html>';

        $toolbar->render();
        $output = ob_get_clean();

        $this->assertStringContainsString('dev-toolbar-mini', $output);
        $this->assertStringContainsString('dev-toolbar-panel', $output);
    }

    public function testToolbarNotInjectedInProduction(): void
    {
        putenv('APP_ENV=production');

        ob_start();

        $toolbar = DevToolbar::getInstance();
        $toolbar->boot();

        echo '<html><body><h1>Test</h1></body></html>';

        $toolbar->render();
        $output = ob_get_clean();

        $this->assertStringNotContainsString('dev-toolbar-mini', $output);
        $this->assertStringNotContainsString('dev-toolbar-panel', $output);
    }
}
```

### Manual Testing Checklist

- [ ] Mini bar appears in bottom-right corner
- [ ] Mini bar shows correct environment (dev/staging)
- [ ] Mini bar shows request time, memory, query count
- [ ] Click mini bar opens panel
- [ ] Panel slides up smoothly
- [ ] All tabs are clickable and switch content
- [ ] REQUEST tab shows method, URI, headers
- [ ] MESSAGES tab shows log messages with correct colors
- [ ] QUERIES tab shows all queries with timing
- [ ] Slow queries highlighted in orange/red
- [ ] EXCEPTIONS tab shows caught exceptions
- [ ] Close button closes panel
- [ ] ESC key closes panel
- [ ] Toolbar not visible in production (`APP_ENV=production`)
- [ ] Sensitive data filtered in all tabs
- [ ] Copy query button works
- [ ] Panel scrollable when content overflows
- [ ] Toolbar not shown for AJAX requests (optional)

## Documentation

### User Documentation

Create `.zappzarapp/docs/development/DEV-TOOLBAR.md`:

```markdown
# Developer Toolbar

## Overview

The Developer Toolbar provides real-time debugging information during
development, including request details, database queries, log messages,
and exceptions.

## Features

- **Always Visible Mini Bar**: Request time, memory usage, query count
- **Expandable Panel**: Detailed debugging information
- **REQUEST Tab**: HTTP method, headers, body, session, cookies
- **MESSAGES Tab**: Log messages from Monolog
- **QUERIES Tab**: Database queries with execution time and stack traces
- **EXCEPTIONS Tab**: All exceptions (even caught ones)

## Access

The toolbar automatically appears on all pages in development mode.

## Configuration

### Enable/Disable

```bash
# .env
ENABLE_DEV_TOOLBAR=true   # Default in development
ENABLE_DEV_TOOLBAR=false  # Disable explicitly
```

The toolbar is automatically disabled in production (`APP_ENV=production`).

### Disable for AJAX Requests

By default, the toolbar is not injected for AJAX requests
(`X-Requested-With: XMLHttpRequest` header).

## Usage

### Basic Usage

1. Load any page in development mode
2. Mini bar appears in bottom-right corner
3. Click mini bar to expand full panel
4. Click tabs to switch between views
5. Click close button or press ESC to collapse panel

### Query Analysis

1. Open toolbar
2. Click QUERIES tab
3. Review query list:
   - Green: Fast (<100ms)
   - Orange: Slow (100-500ms)
   - Red: Very slow (>500ms)
4. Click "Copy Query" to test in database client
5. Review stack trace to find where query was called

### Log Message Review

1. Open toolbar
2. Click MESSAGES tab
3. Messages color-coded by level:
   - Gray: DEBUG
   - Cyan: INFO
   - Yellow: WARNING
   - Red: ERROR
4. Expand context data to see additional information

## Security

### Sensitive Data Protection

The toolbar automatically filters sensitive data:

- Passwords (`password`, `passwd`, `pwd`)
- Secrets (`secret`, `token`, `api_key`)
- Private keys (`private_key`, `access_token`)
- Session data (`session`, `cookie`)

Filtered values show as `[FILTERED]`.

### Production Safety

The toolbar is protected by multiple security layers:

1. Disabled in production environment (`APP_ENV=production`)
2. Requires explicit enable flag (`ENABLE_DEV_TOOLBAR=true`)
3. Never loaded in CLI mode
4. Not included in production Docker images

## Troubleshooting

### Toolbar Not Appearing

1. Check environment: `echo $APP_ENV` (should be `development`)
2. Check flag: `echo $ENABLE_DEV_TOOLBAR` (should be `true` or unset)
3. Check browser console for JavaScript errors
4. Verify `</body>` tag exists in HTML

### Queries Not Showing

1. Verify database is enabled: `ENABLE_DATABASE=true`
2. Check that PDO is wrapped by `QueryCollector`
3. Ensure queries are executed after toolbar boot

### Messages Not Showing

1. Verify Monolog is configured with `MessageCollector` handler
2. Check log level (messages below threshold not collected)
3. Ensure messages logged after toolbar boot
```

### Developer Documentation

Add section to `.zappzarapp/docs/development/CONTRIBUTING.md`:

```markdown
## Developer Toolbar

### Adding Custom Collectors

To add custom data collectors:

1. Create collector class implementing `CollectorInterface`
2. Register in `DevToolbar::registerCollectors()`
3. Create Twig template in `templates/dev-toolbar/tabs/`
4. Add tab button in `templates/dev-toolbar/panel.html.twig`

Example:

```php
<?php

namespace DevToolbar\DataCollectors;

class MyCollector implements CollectorInterface
{
    private array $data = [];

    public function start(): void
    {
        // Start collecting
    }

    public function stop(): void
    {
        // Stop collecting
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getName(): string
    {
        return 'my_collector';
    }
}
```

### Sensitive Data Filtering

When collecting data, always filter sensitive information:

```php
use DevToolbar\DataCollectors\RequestCollector;

$filteredData = RequestCollector::filterSensitiveData($rawData);
```
```

## Success Criteria

### MVP (Phase 1) Complete When:

- [ ] Mini bar appears on all pages in development mode
- [ ] Mini bar shows correct metrics (time, memory, queries)
- [ ] Panel expands/collapses smoothly
- [ ] All 4 tabs implemented and functional (REQUEST, MESSAGES, QUERIES, EXCEPTIONS)
- [ ] Sensitive data automatically filtered
- [ ] Toolbar disabled in production
- [ ] Unit tests passing (>80% coverage)
- [ ] Integration tests passing
- [ ] Documentation complete
- [ ] Zero-config (works after `make setup`)
- [ ] No external dependencies
- [ ] Query performance indicators working (color coding)
- [ ] Stack traces visible for queries
- [ ] Copy query button functional
- [ ] ESC key closes panel
- [ ] Toolbar not shown for AJAX requests

## Dependencies

### Before Implementation

- [ ] None - self-contained feature

### Blocking Issues

- None identified

## Environment Variables

```bash
# .env

# Enable/disable Developer Toolbar
# Default: true in development, false in production
ENABLE_DEV_TOOLBAR=true
```

## Migration Path

### From Existing Setup

No migration needed - new feature.

### Rollback Plan

1. Set `ENABLE_DEV_TOOLBAR=false` in `.env`
2. Restart containers
3. Delete `src/php/DevToolbar/` directory (optional)

## Performance Impact

### Development Mode

- **Memory**: ~2-5MB per request (storing collected data)
- **Execution Time**: ~5-15ms per request (data collection + rendering)
- **Acceptable**: Negligible impact in development

### Production Mode

- **Memory**: 0 (not loaded)
- **Execution Time**: 0 (not loaded)
- **Impact**: None

## Future Enhancements (Not in Phase 1)

See Phase 2 and Phase 3 task files:

- HTTP Client tracking (cURL, Guzzle)
- Cache operations (Redis hits/misses)
- Timeline visualization
- Request history
- N+1 query detection
- XDebug integration
- Node.js integration

## Files to Create

```
src/php/DevToolbar/
├── DevToolbar.php
├── Guard/
│   └── DevToolbarGuard.php
├── DataCollectors/
│   ├── CollectorInterface.php
│   ├── RequestCollector.php
│   ├── QueryCollector.php
│   ├── MessageCollector.php
│   └── ExceptionCollector.php
├── Middleware/
│   └── DevToolbarMiddleware.php
├── Renderers/
│   ├── RendererInterface.php
│   ├── MiniBarRenderer.php
│   └── PanelRenderer.php
└── Storage/
    └── RequestStore.php

resources/dev-toolbar/
├── toolbar.js
└── toolbar.css

templates/dev-toolbar/
├── mini-bar.html.twig
├── panel.html.twig
└── tabs/
    ├── request.html.twig
    ├── messages.html.twig
    ├── queries.html.twig
    └── exceptions.html.twig

tests/php/DevToolbar/
├── DevToolbarTest.php
├── Guard/
│   └── DevToolbarGuardTest.php
├── DataCollectors/
│   ├── RequestCollectorTest.php
│   ├── QueryCollectorTest.php
│   ├── MessageCollectorTest.php
│   └── ExceptionCollectorTest.php
└── Middleware/
    └── DevToolbarMiddlewareTest.php

.zappzarapp/docs/development/
└── DEV-TOOLBAR.md
```

## Files to Modify

```
public/index.php
  - Add DevToolbar bootstrap code
  - Add shutdown handler for rendering

config/logging.php (or equivalent)
  - Add MessageCollector to Monolog handlers

.env.example
  - Add ENABLE_DEV_TOOLBAR=true

.zappzarapp/docs/development/CONTRIBUTING.md
  - Add section on custom collectors

README.md
  - Add link to DEV-TOOLBAR.md documentation
```

## Estimated Effort

**Total: 8-12 hours**

Breakdown:

- Architecture & Setup: 1-2h
- Core DevToolbar class + Guard: 1h
- Data Collectors (4 collectors): 2-3h
- Middleware & Rendering: 1-2h
- Templates (Twig): 1-2h
- CSS Styling: 1-2h
- JavaScript: 1h
- Testing (Unit + Integration): 2-3h
- Documentation: 1h

## Priority

**Medium-High** - Significant DX improvement, aligns with platform vision

## Next Steps

1. Review and approve this task specification
2. Create feature branch: `feature/dev-toolbar-phase-1`
3. Implement core architecture (DevToolbar + Guard)
4. Implement collectors one by one (TDD approach)
5. Implement rendering (templates + CSS)
6. Add JavaScript interactions
7. Write tests
8. Write documentation
9. Manual testing
10. Code review
11. Merge to develop
