# TODO: Umstellung auf flexible PHP/Node.js Struktur

**Ziel:** Flexibles, sicheres Boilerplate für PHP und/oder Node.js mit moderner Toolchain

**Status:** In Bearbeitung
**Erstellt:** 2025-12-17

---

## Übersicht

Dieses Projekt wird umstrukturiert zu einem flexiblen Boilerplate, das folgende Modi unterstützt:

1. **PHP Backend + Frontend Assets** (Standard)
2. **Node.js Backend + Frontend Assets**
3. **Fullstack (PHP + Node.js)**
4. **API-only (kein Frontend)**

### Node.js Modi:
- **Development:** Vite HMR (Hot Module Replacement) für schnelle Entwicklung
- **asset-server (Production):** Idle Container, Assets werden während Build-Zeit kompiliert
- **app-server (Production):** Node.js Runtime-Server (Express, Fastify, etc.)

---

## Projektstruktur (Ziel)

```
/
├── src/
│   ├── php/              # PHP Backend Code
│   │   ├── Http/
│   │   ├── Domain/
│   │   └── Infrastructure/
│   │
│   └── node/             # Node.js Backend Code (optional)
│       ├── routes/
│       ├── controllers/
│       ├── services/
│       └── server.ts
│
├── resources/            # Frontend Source (Browser)
│   ├── js/
│   │   ├── app.js
│   │   └── components/
│   ├── css/
│   │   ├── app.css
│   │   └── components/
│   └── images/
│
├── public/               # Web Root (Nginx)
│   ├── index.php
│   ├── health.php
│   └── build/           # Vite Output (auto-generated)
│
├── tests/               # PHPUnit Tests
│   ├── Unit/
│   └── Feature/
│
├── config/              # App Configuration (optional)
├── templates/           # PHP Templates (optional, Twig/Plates)
├── storage/             # Runtime Data (uploads, cache)
├── logs/                # Application Logs
├── docker/              # Docker Config
└── vendor/              # Composer (gitignore)
```

---

## Phase 1: Ordnerstruktur erstellen

### 1.1 Verzeichnisse anlegen

```bash
# Bereits erledigt (verifizieren):
ls -la src/php src/node

# Neu erstellen:
mkdir -p resources/{js/components,css/components,images,fonts}
mkdir -p public/build
mkdir -p src/php/{Http/{Controller,Middleware},Domain,Infrastructure/{Database,Cache}}
mkdir -p src/node/{routes,controllers,services,middleware}
mkdir -p tests/{Unit,Feature}
mkdir -p config
mkdir -p templates
mkdir -p storage/{app/{uploads,generated},cache,sessions}

# Permissions
chmod 770 storage -R
```

**Status:** [X] Erledigt

---

## Phase 2: Konfigurationsdateien erstellen

### 2.1 package.json

**Pfad:** `/home/mst/Code/docker-webdev/package.json`

```json
{
  "name": "docker-webdev",
  "version": "1.0.0",
  "description": "Flexible Docker boilerplate for PHP and Node.js development",
  "type": "module",
  "private": true,
  "scripts": {
    "dev": "vite",
    "build": "vite build",
    "preview": "vite preview",
    "type-check": "tsc --noEmit"
  },
  "devDependencies": {
    "@types/node": "^22.10.5",
    "autoprefixer": "^10.4.23",
    "postcss": "^8.5.6",
    "typescript": "^5.9.3",
    "vite": "^6.0.6"
  },
  "engines": {
    "node": ">=24.0.0",
    "pnpm": ">=9.0.0"
  },
  "packageManager": "pnpm@9.15.1"
}
```

**Status:** [X] Erstellt
**Hinweis:** Nach Erstellung `pnpm install` im Node-Container ausführen

@Info: Install muss noch durchgeführt werden
---

### 2.2 vite.config.js

**Pfad:** `/home/mst/Code/docker-webdev/vite.config.js`

```javascript
import { defineConfig } from 'vite';
import { resolve } from 'path';

export default defineConfig({
  // Root directory for source files
  root: 'resources',

  // Public base path
  base: '/build/',

  // Build configuration
  build: {
    // Output directory relative to project root
    outDir: '../public/build',

    // Empty output directory before building
    emptyOutDir: true,

    // Generate manifest.json for cache busting
    manifest: true,

    // Rollup options
    rollupOptions: {
      input: {
        // Main entry points
        app: resolve(__dirname, 'resources/js/app.js'),
        // Add more entry points as needed:
        // admin: resolve(__dirname, 'resources/js/admin.js'),
      },
    },

    // Minification
    minify: 'esbuild',

    // Source maps for debugging
    sourcemap: process.env.NODE_ENV !== 'production',

    // Chunk size warnings
    chunkSizeWarningLimit: 1000,
  },

  // Development server configuration
  server: {
    // Port for Vite dev server (HMR)
    port: 5173,

    // Allow external access (required for Docker)
    host: '0.0.0.0',

    // Watch options
    watch: {
      usePolling: true,
      interval: 100,
    },

    // CORS
    cors: true,

    // HMR configuration
    hmr: {
      host: 'localhost',
      port: 5173,
      protocol: 'ws',
    },

    // Proxy API requests to PHP backend
    proxy: {
      '/api': {
        target: 'http://nginx:8080',
        changeOrigin: true,
      },
    },
  },

  // CSS configuration
  css: {
    // PostCSS configuration
    postcss: {
      plugins: [
        require('autoprefixer'),
      ],
    },

    // Preprocessor options
    preprocessorOptions: {
      scss: {
        // Additional SCSS data (variables, mixins)
        // additionalData: `@import "@/css/variables.scss";`,
      },
    },

    // Dev source maps
    devSourcemap: true,
  },

  // Path aliases
  resolve: {
    alias: {
      '@': resolve(__dirname, 'resources'),
      '@js': resolve(__dirname, 'resources/js'),
      '@css': resolve(__dirname, 'resources/css'),
      '@img': resolve(__dirname, 'resources/images'),
    },
  },

  // Optimize dependencies
  optimizeDeps: {
    include: [],
  },
});
```

**Status:** [X] Erstellt

---

### 2.3 tsconfig.json (Node.js Backend)

**Pfad:** `/home/mst/Code/docker-webdev/tsconfig.json`

```json
{
  "compilerOptions": {
    /* Language and Environment */
    "target": "ES2022",
    "lib": ["ES2022"],
    "module": "NodeNext",
    "moduleResolution": "NodeNext",

    /* Emit */
    "outDir": "./dist",
    "rootDir": "./src/node",
    "removeComments": true,
    "sourceMap": true,
    "declaration": true,
    "declarationMap": true,

    /* Type Checking */
    "strict": true,
    "noUnusedLocals": true,
    "noUnusedParameters": true,
    "noImplicitReturns": true,
    "noFallthroughCasesInSwitch": true,
    "noUncheckedIndexedAccess": true,
    "allowUnusedLabels": false,
    "allowUnreachableCode": false,

    /* Interop Constraints */
    "esModuleInterop": true,
    "allowSyntheticDefaultImports": true,
    "forceConsistentCasingInFileNames": true,
    "isolatedModules": true,

    /* Completeness */
    "skipLibCheck": true,

    /* Advanced */
    "resolveJsonModule": true
  },
  "include": [
    "src/node/**/*"
  ],
  "exclude": [
    "node_modules",
    "dist",
    "vendor"
  ]
}
```

**Status:** [X] Erstellt
**Hinweis:** Nur relevant, wenn Node.js Backend (app-server) genutzt wird

---

### 2.4 postcss.config.js

**Pfad:** `/home/mst/Code/docker-webdev/postcss.config.js`

```javascript
export default {
  plugins: {
    autoprefixer: {
      overrideBrowserslist: ['last 2 versions', '> 1%', 'not dead'],
    },
  },
};
```

**Status:** [X] Erstellt

---

### 2.5 .gitignore Ergänzungen

**Pfad:** `/home/mst/Code/docker-webdev/.gitignore`

**Hinzufügen:**
```gitignore
# Build outputs
/public/build/*
!/public/build/.gitkeep
/dist/

# Dependencies
/node_modules/
/vendor/

# Environment
.env
.env.local

# Logs
/logs/*
!/logs/.gitkeep

# Storage
/storage/app/*
!/storage/app/.gitkeep
/storage/cache/*
!/storage/cache/.gitkeep
/storage/sessions/*
!/storage/sessions/.gitkeep

# IDE
.idea/
.vscode/
*.swp
*.swo

# OS
.DS_Store
Thumbs.db

# Testing
/build/coverage/
.phpunit.result.cache
```

**Status:** [X] Aktualisiert

@Info: Mit leichten Änderungen durchgeführt.

---

## Phase 3: Boilerplate-Dateien erstellen

### 3.1 PHP Example Class

**Pfad:** `/home/mst/Code/docker-webdev/src/php/Http/Controller/ExampleController.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Controller;

/**
 * Example Controller
 *
 * Demonstrates basic PHP MVC pattern.
 */
class ExampleController
{
    public function index(): void
    {
        header('Content-Type: application/json');
        echo json_encode([
            'message' => 'Hello from PHP!',
            'timestamp' => time(),
            'php_version' => PHP_VERSION,
            'server' => 'PHP-FPM 8.4',
        ], JSON_THROW_ON_ERROR);
    }

    public function health(): void
    {
        header('Content-Type: application/json');
        http_response_code(200);
        echo json_encode([
            'status' => 'ok',
            'service' => 'php-backend',
            'timestamp' => date('c'),
        ], JSON_THROW_ON_ERROR);
    }
}
```

**Status:** [X] Erstellt

---

### 3.2 PHP Router (Simple)

**Pfad:** `/home/mst/Code/docker-webdev/src/php/Http/Router.php`

```php
<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Simple Router
 *
 * Basic routing for demo purposes.
 * For production, use Symfony Router, FastRoute, or similar.
 */
class Router
{
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }

    private function addRoute(string $method, string $path, callable $handler): void
    {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler,
        ];
    }

    public function dispatch(): void
    {
        $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

        foreach ($this->routes as $route) {
            if ($route['method'] === $requestMethod && $this->matchPath($route['path'], $requestPath)) {
                call_user_func($route['handler']);
                return;
            }
        }

        // 404 Not Found
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Not Found', 'path' => $requestPath], JSON_THROW_ON_ERROR);
    }

    private function matchPath(string $routePath, string $requestPath): bool
    {
        return $routePath === $requestPath;
    }
}
```

**Status:** [X] Erstellt

---

### 3.3 public/index.php (Updated)

**Pfad:** `/home/mst/Code/docker-webdev/public/index.php`

**Änderungen:**
- ❌ Error Handling entfernt → Wird in docker/php/php.ini und development.ini konfiguriert
- ❌ Security Headers entfernt → Werden in docker/nginx/conf.d/default.conf gesetzt
- ❌ CORS entfernt → Wird als Beispiel in Nginx Config ergänzt (Phase 4.3)
- ✅ CSP Nonce-Template hinzugefügt → Ausführliches, kommentiertes Template (optional aktivierbar)
- ✅ Fokus auf Routing → Schlank und klar strukturiert

**Finale Version:**

```php
<?php

declare(strict_types=1);

/**
 * Application Entry Point
 *
 * This file is the entry point for all HTTP requests routed through Nginx.
 */

// Load Composer Autoloader
require_once __DIR__ . '/../vendor/autoload.php';

/**
 * ============================================================================
 * CONTENT SECURITY POLICY (CSP) - NONCE-BASED (OPTIONAL)
 * ============================================================================
 *
 * CURRENT STATE:
 * A basic CSP is configured in docker/nginx/conf.d/default.conf with
 * 'unsafe-inline' for immediate compatibility.
 *
 * FOR MAXIMUM SECURITY (Production):
 * 1. REMOVE the static CSP header from Nginx config
 * 2. UNCOMMENT the code below (lines 27-47)
 * 3. Use CSP_NONCE constant in your inline <script> and <style> tags:
 *    <script nonce="<?= CSP_NONCE ?>">...</script>
 *    <style nonce="<?= CSP_NONCE ?>">...</style>
 *
 * BENEFITS:
 * - Blocks XSS attacks by only allowing scripts/styles with valid nonce
 * - 'strict-dynamic' allows dynamically loaded scripts from trusted sources
 * - No need to maintain a whitelist of script sources
 *
 * MORE INFO:
 * https://developer.mozilla.org/en-US/docs/Web/HTTP/CSP
 * https://web.dev/articles/csp
 */

/*
// 1. Generate Cryptographically Secure Nonce
$nonce = base64_encode(random_bytes(16));

// 2. Build CSP Header
$csp_directives = [
    "default-src 'self'",
    "script-src 'self' 'nonce-{$nonce}' 'strict-dynamic'",
    "style-src 'self' 'nonce-{$nonce}'",
    "img-src 'self' data: https:",
    "font-src 'self'",
    "connect-src 'self'",
    "frame-ancestors 'self'",
    "base-uri 'self'",
    "form-action 'self'",
];
$csp_header = implode('; ', $csp_directives);

// 3. Send CSP Header (before any output!)
header("Content-Security-Policy: {$csp_header}");

// 4. Define Constant for Template Access
define('CSP_NONCE', $nonce);
*/

// Simple Routing Example
use App\Http\Router;
use App\Http\Controller\ExampleController;

$router = new Router();
$controller = new ExampleController();

// Routes
$router->get('/', [$controller, 'index']);
$router->get('/api/health', [$controller, 'health']);

// Dispatch
$router->dispatch();
```

**Status:** [X] Aktualisiert

---

### 3.4 Frontend: resources/js/app.js

**Pfad:** `/home/mst/Code/docker-webdev/resources/js/app.js`

```javascript
/**
 * Main JavaScript Entry Point
 *
 * This file runs in the browser and is bundled by Vite.
 * Output: public/build/assets/app-[hash].js
 */

// Import main CSS
import '../css/app.css';

// Example: Fetch API data
async function fetchHealth() {
  try {
    const response = await fetch('/api/health');
    const data = await response.json();
    console.log('Health check:', data);

    // Update DOM
    const healthEl = document.getElementById('health-status');
    if (healthEl) {
      healthEl.textContent = JSON.stringify(data, null, 2);
      healthEl.className = 'health-ok';
    }
  } catch (error) {
    console.error('Health check failed:', error);

    const healthEl = document.getElementById('health-status');
    if (healthEl) {
      healthEl.textContent = 'Error: ' + error.message;
      healthEl.className = 'health-error';
    }
  }
}

// DOM Ready
document.addEventListener('DOMContentLoaded', () => {
  console.log('App loaded!');
  console.log('Environment:', import.meta.env.MODE);
  console.log('Vite HMR:', import.meta.hot ? 'Enabled' : 'Disabled');

  // Fetch health status
  fetchHealth();

  // Example: Button click
  const testBtn = document.getElementById('test-btn');
  if (testBtn) {
    testBtn.addEventListener('click', () => {
      alert('Button clicked! HMR is working if this updates without page reload.');
    });
  }
});

// Hot Module Replacement (HMR)
if (import.meta.hot) {
  import.meta.hot.accept(() => {
    console.log('HMR update received');
  });
}
```

**Status:** [X] Erstellt

---

### 3.5 Frontend: resources/css/app.css

**Pfad:** `/home/mst/Code/docker-webdev/resources/css/app.css`

```css
/**
 * Main Stylesheet
 *
 * This file is imported by resources/js/app.js and bundled by Vite.
 * Output: public/build/assets/app-[hash].css
 */

/* CSS Custom Properties (Variables) */
:root {
  --color-primary: #3b82f6;
  --color-secondary: #8b5cf6;
  --color-success: #10b981;
  --color-danger: #ef4444;
  --color-warning: #f59e0b;
  --color-info: #06b6d4;

  --font-sans: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
  --font-mono: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, 'Courier New', monospace;

  --spacing-xs: 0.25rem;
  --spacing-sm: 0.5rem;
  --spacing-md: 1rem;
  --spacing-lg: 1.5rem;
  --spacing-xl: 2rem;
  --spacing-2xl: 3rem;

  --radius-sm: 0.25rem;
  --radius-md: 0.5rem;
  --radius-lg: 1rem;

  --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
  --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
  --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
}

/* Reset */
*,
*::before,
*::after {
  box-sizing: border-box;
  margin: 0;
  padding: 0;
}

/* Base Styles */
html {
  font-size: 16px;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}

body {
  font-family: var(--font-sans);
  line-height: 1.6;
  color: #1f2937;
  background-color: #f9fafb;
  min-height: 100vh;
}

/* Container */
.container {
  max-width: 1200px;
  margin: 0 auto;
  padding: var(--spacing-md);
}

/* Typography */
h1 {
  font-size: 2.5rem;
  font-weight: 700;
  margin-bottom: var(--spacing-lg);
  color: #111827;
}

h2 {
  font-size: 2rem;
  font-weight: 600;
  margin-bottom: var(--spacing-md);
  color: #374151;
}

p {
  margin-bottom: var(--spacing-md);
}

/* Button */
.btn {
  display: inline-block;
  padding: var(--spacing-sm) var(--spacing-lg);
  background-color: var(--color-primary);
  color: white;
  border: none;
  border-radius: var(--radius-md);
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s ease;
  text-decoration: none;
}

.btn:hover {
  background-color: #2563eb;
  transform: translateY(-1px);
  box-shadow: var(--shadow-md);
}

.btn:active {
  transform: translateY(0);
}

/* Card */
.card {
  background-color: white;
  border-radius: var(--radius-lg);
  padding: var(--spacing-xl);
  box-shadow: var(--shadow-sm);
  margin-bottom: var(--spacing-lg);
}

/* Health Status Display */
#health-status {
  font-family: var(--font-mono);
  font-size: 0.875rem;
  padding: var(--spacing-md);
  border-radius: var(--radius-md);
  background-color: #f3f4f6;
  white-space: pre-wrap;
  word-break: break-all;
  max-height: 300px;
  overflow-y: auto;
}

#health-status.health-ok {
  background-color: #d1fae5;
  border-left: 4px solid var(--color-success);
}

#health-status.health-error {
  background-color: #fee2e2;
  border-left: 4px solid var(--color-danger);
  color: var(--color-danger);
}

/* Utility Classes */
.text-center {
  text-align: center;
}

.mt-4 {
  margin-top: var(--spacing-lg);
}

.mb-4 {
  margin-bottom: var(--spacing-lg);
}

.flex {
  display: flex;
}

.flex-col {
  flex-direction: column;
}

.items-center {
  align-items: center;
}

.justify-center {
  justify-content: center;
}

.gap-4 {
  gap: var(--spacing-lg);
}
```

**Status:** [X] Erstellt

---

### 3.6 Test HTML Page

**Pfad:** `/home/mst/Code/docker-webdev/public/test.html`

**Hinweis:** Da dies ein Development-Boilerplate ist, ist **Dev-Mode mit HMR als Default aktiviert**.

```html
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Docker WebDev - Test Page</title>

    <!-- Vite Development Mode (Default - HMR Active) -->
    <!--
    Hinweis: HMR ist standardmäßig aktiviert für Development.
    - Container starten: make up
    - Vite Dev Server starten: make node-dev
    - Browser öffnen: http://localhost:8080/test.html
    - Änderungen an resources/css/app.css oder resources/js/app.js werden live reloaded
    -->
    <script type="module" src="http://localhost:5173/@vite/client"></script>
    <script type="module" src="http://localhost:5173/js/app.js"></script>

    <!-- Vite Production Mode (Uncomment for Production Build) -->
    <!--
    Hinweis: Für Production Build (ohne HMR):
    1. Build ausführen: make node-build
    2. Diese Tags verwenden (obige auskommentieren):
    <link rel="stylesheet" href="/build/assets/app.css">
    <script type="module" src="/build/assets/app.js"></script>
    -->
</head>
<body>
    <div class="container">
        <div class="card text-center">
            <h1>🚀 Docker WebDev Boilerplate</h1>
            <p>Flexibles Setup für PHP und Node.js Entwicklung</p>

            <div class="flex flex-col items-center gap-4 mt-4">
                <button id="test-btn" class="btn">Test Button (HMR)</button>

                <div style="width: 100%; max-width: 600px;">
                    <h2>API Health Check</h2>
                    <pre id="health-status">Loading...</pre>
                </div>
            </div>
        </div>

        <div class="card">
            <h2>Setup Modes</h2>
            <ul style="list-style: none; padding: 0;">
                <li>✅ <strong>Development:</strong> Vite HMR auf Port 5173</li>
                <li>✅ <strong>Production (asset-server):</strong> Statische Assets</li>
                <li>✅ <strong>Production (app-server):</strong> Node.js Backend</li>
            </ul>
        </div>
    </div>
</body>
</html>
```

**Status:** [X] Erstellt

---

### 3.7 Node.js Backend Example

**Pfad:** `/home/mst/Code/docker-webdev/src/node/server.ts`

```typescript
/**
 * Node.js Backend Server
 *
 * This is an example Express.js server.
 * Only used when NODE_TARGET=app-server in docker-compose.
 *
 * Build output: dist/server.js
 * Runtime: Node.js container on port 3000
 */

import express, { Request, Response, NextFunction } from 'express';
import { createServer } from 'http';

const app = express();
const PORT = process.env.PORT || 3000;
const NODE_ENV = process.env.NODE_ENV || 'production';

// Middleware
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// Request logging
app.use((req: Request, res: Response, next: NextFunction) => {
  const timestamp = new Date().toISOString();
  console.log(`[${timestamp}] ${req.method} ${req.path}`);
  next();
});

// CORS (if needed)
app.use((req: Request, res: Response, next: NextFunction) => {
  res.header('Access-Control-Allow-Origin', '*');
  res.header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
  res.header('Access-Control-Allow-Headers', 'Content-Type, Authorization');

  if (req.method === 'OPTIONS') {
    return res.sendStatus(200);
  }

  next();
});

// Security Headers
app.use((req: Request, res: Response, next: NextFunction) => {
  res.setHeader('X-Content-Type-Options', 'nosniff');
  res.setHeader('X-Frame-Options', 'SAMEORIGIN');
  res.setHeader('X-XSS-Protection', '1; mode=block');
  next();
});

// Routes
app.get('/health', (req: Request, res: Response) => {
  res.json({
    status: 'ok',
    service: 'node-backend',
    timestamp: new Date().toISOString(),
    uptime: process.uptime(),
    node_version: process.version,
    environment: NODE_ENV,
  });
});

app.get('/api/hello', (req: Request, res: Response) => {
  const name = req.query.name || 'World';
  res.json({
    message: `Hello, ${name}!`,
    timestamp: new Date().toISOString(),
    server: 'Node.js + Express',
  });
});

// Example POST endpoint
app.post('/api/echo', (req: Request, res: Response) => {
  res.json({
    echo: req.body,
    timestamp: new Date().toISOString(),
  });
});

// 404 Handler
app.use((req: Request, res: Response) => {
  res.status(404).json({
    error: 'Not Found',
    path: req.path,
    method: req.method,
  });
});

// Error Handler
app.use((err: Error, req: Request, res: Response, next: NextFunction) => {
  console.error('Error:', err);

  res.status(500).json({
    error: NODE_ENV === 'development' ? err.message : 'Internal Server Error',
    timestamp: new Date().toISOString(),
  });
});

// Start Server
const server = createServer(app);

server.listen(PORT, () => {
  console.log('==========================================');
  console.log(`🚀 Node.js server running`);
  console.log(`   Port: ${PORT}`);
  console.log(`   Environment: ${NODE_ENV}`);
  console.log(`   Health: http://localhost:${PORT}/health`);
  console.log('==========================================');
});

// Graceful Shutdown
const shutdown = () => {
  console.log('\nShutting down gracefully...');
  server.close(() => {
    console.log('Server closed');
    process.exit(0);
  });

  // Force shutdown after 10s
  setTimeout(() => {
    console.error('Forced shutdown');
    process.exit(1);
  }, 10000);
};

process.on('SIGTERM', shutdown);
process.on('SIGINT', shutdown);

export default app;
```

**Status:** [X] Erstellt

**Hinweis:** Benötigt `pnpm add express @types/express` im Container

---

### 3.8 Node.js package.json (mit Backend Dependencies)

**Pfad:** `/home/mst/Code/docker-webdev/package.json` (Erweitert)

```json
{
  "name": "docker-webdev",
  "version": "1.0.0",
  "description": "Flexible Docker boilerplate for PHP and Node.js development",
  "type": "module",
  "private": true,
  "scripts": {
    "dev": "vite",
    "build": "vite build",
    "preview": "vite preview",
    "type-check": "tsc --noEmit",
    "server:build": "tsc --project tsconfig.json",
    "server:dev": "tsx watch src/node/server.ts",
    "server:start": "node dist/server.js"
  },
  "dependencies": {
    "express": "^4.21.2"
  },
  "devDependencies": {
    "@types/express": "^5.0.6",
    "@types/node": "^22.10.5",
    "autoprefixer": "^10.4.23",
    "postcss": "^8.5.6",
    "tsx": "^4.21.0",
    "typescript": "^5.9.3",
    "vite": "^6.0.6"
  },
  "engines": {
    "node": ">=24.0.0",
    "pnpm": ">=9.0.0"
  },
  "packageManager": "pnpm@9.15.1"
}
```

**Status:** [X] Aktualisiert

---

## Phase 4: Docker & Compose Anpassungen

### 4.1 Nginx Vite HMR Config (Development only)

**Pfad:** `/home/mst/Code/docker-webdev/docker/nginx/conf.d/vite-hmr.conf`

**Neue Datei erstellen:**

```nginx
# Vite HMR Proxy Configuration
# This file is ONLY loaded in Development mode via compose.override.yaml
# DO NOT include this in Production!

# Vite HMR WebSocket
location /@vite/client {
    proxy_pass http://node:5173;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
    proxy_cache_bypass $http_upgrade;
}

# Vite Dev Server (JS/CSS/Images)
location ~ ^/(js|css|images)/ {
    proxy_pass http://node:5173;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
    proxy_cache_bypass $http_upgrade;
}
```

**Status:** [X] Integriert in default.conf

**Änderung (2025-12-19):** Separate Datei entfernt und direkt in `default.conf` integriert
- **Grund:** Nginx verlangt `location` Direktiven innerhalb eines `server` Blocks
- **Lösung:** HMR Locations in `docker/nginx/conf.d/default.conf` (Zeilen 45-71)
- **Vorteil:** Production-safe, keine Docker-Mount-Probleme, einfachere Wartung

---

### 4.2 Nginx Config - CORS Beispiele & Brotli

**Pfad:** `/home/mst/Code/docker-webdev/docker/nginx/conf.d/default.conf`

**Aktion:** Kommentierte CORS-Beispiele nach Security Headers hinzufügen

```nginx
    # CORS Headers (Optional - Uncomment if needed for API)
    # add_header Access-Control-Allow-Origin "*" always;
    # add_header Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS" always;
    # add_header Access-Control-Allow-Headers "Content-Type, Authorization, X-Requested-With" always;
    # add_header Access-Control-Allow-Credentials "true" always;
    # add_header Access-Control-Max-Age "3600" always;

    # Handle OPTIONS requests for CORS preflight
    # if ($request_method = 'OPTIONS') {
    #     return 204;
    # }
```

**Pfad:** `/home/mst/Code/docker-webdev/docker/nginx/nginx.conf`

**Aktion:** Brotli Compression als Alternative zu Gzip hinzufügen (kommentiert)

```nginx
    # Gzip Compression (Default - Active)
    gzip on;
    gzip_vary on;
    gzip_proxied any;
    gzip_comp_level 6;
    gzip_types text/plain text/css text/xml text/javascript
               application/json application/javascript application/xml+rss
               application/rss+xml font/truetype font/opentype
               application/vnd.ms-fontobject image/svg+xml;

    # Brotli Compression (Alternative - Better compression, requires nginx-module-brotli)
    # To enable Brotli: Uncomment lines below, install module in Dockerfile, comment out gzip
    # brotli on;
    # brotli_comp_level 6;
    # brotli_types text/plain text/css text/xml text/javascript
    #              application/json application/javascript application/xml+rss
    #              application/rss+xml font/truetype font/opentype
    #              application/vnd.ms-fontobject image/svg+xml;
```

**Pfad:** `/home/mst/Code/docker-webdev/docker/nginx/Dockerfile`

**Aktion:** Brotli Module installieren (kommentiert)

```dockerfile
# --- 1. BASE STAGE (Shared) ---
FROM nginx:${NGINX_VERSION}-alpine${ALPINE_VERSION} AS base

# Install Brotli Module (Optional - Uncomment if you want to use Brotli instead of Gzip)
# RUN apk add --no-cache nginx-mod-http-brotli

# Setup Permissions & PID File
RUN mkdir -p /var/cache/nginx /var/log/nginx /tmp/nginx && \
```

**Status:** [X] Ergänzt

---

### 4.3 Nginx Rate Limiting - Separate Zonen

**Pfad:** `/home/mst/Code/docker-webdev/docker/nginx/nginx.conf`

**Aktion:** Erweitern um separate Zonen für API, Assets und General

```nginx
    # Rate Limiting Zones (Separate zones for different resource types)
    #
    # Zone 'api': Strict limit for API endpoints (5 requests/sec)
    # Zone 'assets': Generous limit for static files (50 requests/sec)
    # Zone 'general': Default limit for everything else (10 requests/sec)
    #
    # To apply: Use 'limit_req zone=api' in location blocks
    # Burst allows temporary spike, nodelay processes burst immediately

    limit_req_zone $binary_remote_addr zone=api:10m rate=5r/s;
    limit_req_zone $binary_remote_addr zone=assets:10m rate=50r/s;
    limit_req_zone $binary_remote_addr zone=general:10m rate=10r/s;
    limit_req_status 429;

    # Connection Limiting Zone (Max 10 concurrent connections per IP)
    limit_conn_zone $binary_remote_addr zone=addr:10m;
```

**Pfad:** `/home/mst/Code/docker-webdev/docker/nginx/conf.d/default.conf`

**Aktion:** default.conf anpassen für zone-spezifisches Rate Limiting

```nginx
    # Enable Rate Limiting (General Zone - default for all locations)
    limit_req zone=general burst=20 nodelay;
    limit_conn addr 10;

    # Standard Location
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # API Endpoints - Stricter Rate Limiting
    location ~ ^/api/ {
        limit_req zone=api burst=10 nodelay;
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Static files caching - More generous rate limiting
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
        limit_req zone=assets burst=100 nodelay;
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
        try_files $uri =404;
    }
```

**Status:** [X] Aktualisiert

---

### 4.4 docker/node/Dockerfile - Production optimieren

**Pfad:** `/home/mst/Code/docker-webdev/docker/node/Dockerfile`

**Aktion:** Production Stages optimieren - Nur notwendige Dateien kopieren (analog php/Dockerfile)

**Problem:** Aktuell wird `COPY . .` verwendet (Zeile 61), was das gesamte Verzeichnis kopiert.

**Lösung:** Selektives Kopieren wie im php/Dockerfile.

**Hinweis zur Development Stage & app-server Kompatibilität:**
Die Development Stage (Zeile 18-39) ist mit beiden Modi kompatibel:
- **asset-server (Default):** Container läuft mit `CMD ["sleep", "infinity"]` → Idle, für Vite Dev Server via `make node-dev`
- **app-server:** Container wird mit `NODE_TARGET=app-server` gebaut → Dev startet Server manuell via `docker compose exec node pnpm run server:dev`

Für app-server Development ist der manuelle Start gewollt, da:
1. Dev hat volle Kontrolle über Restart/Watch-Mode
2. Logs sind direkt sichtbar (nicht in Docker Logs versteckt)
3. Konsistent mit dem PHP-Workflow (manuelles `make dev-deps`)

#### 4.4.1 Make Befehl für Dev App-Server (✅ Erledigt)

**Antwort:** Makefile wurde um `make node-server-dev` erweitert.

**Verwendung:**
```bash
# Node.js Backend in Development Watch Mode starten
make node-server-dev
# Führt aus: docker compose exec node pnpm run server:dev
# tsx watch src/node/server.ts
```

**Warum manueller Start?**
Der manuelle Start via `make node-server-dev` ist bewusst gewählt:
1. ✅ **Dev-Kontrolle:** Volle Kontrolle über Restart/Watch-Mode
2. ✅ **Logs sichtbar:** Direkt im Terminal (nicht in Docker Logs versteckt)
3. ✅ **Konsistent:** Gleicher Workflow wie PHP (`make dev-deps` auch manuell)

**Development Workflow (app-server):**
```bash
# 1. Container starten (NODE_TARGET=asset-server, default)
make up

# 2. Dependencies installieren
make node-install

# 3. Backend manuell starten
make node-server-dev
# → Server läuft auf http://localhost:3000
# → tsx watch sorgt für Auto-Reload bei Änderungen
```

**Siehe auch:** Makefile:237-240

---

#### 4.4.2 Image Slimming für Production (❌ Noch implementieren!)

**Antwort:** Image Slimming ist **weiterhin notwendig und möglich**!

**Problem (Aktuell):**
Die Production Stages (build/app-server) kopieren **alle** node_modules, inklusive DevDependencies:
- TypeScript Compiler (typescript)
- Type Definitions (@types/*)
- tsx (Development Runtime)
- Vite (Build Tool)

**Lösung:** `pnpm prune --prod` im Build Stage

**Erwartete Einsparung:** 40-60% der Image-Größe

**Implementierung:**

```dockerfile
# --- 3. PRODUCTION BUILD STAGE (Secure Asset Build) ---
FROM base AS build

# ... (User Setup) ...

# Copy dependency files
COPY --chown=node:node package.json pnpm-lock.yaml ./

# Install ALL dependencies (including devDependencies for build)
RUN pnpm install --frozen-lockfile

# Copy ONLY necessary source files
COPY --chown=node:node vite.config.js ./
COPY --chown=node:node tsconfig.json ./
COPY --chown=node:node postcss.config.js ./
COPY --chown=node:node resources/ ./resources/
COPY --chown=node:node src/node/ ./src/node/

# Execute the build command
RUN pnpm run build

# ⭐ NEU: Image Slimming - Entferne DevDependencies
RUN pnpm prune --prod

# --- 4. PRODUCTION ASSET SERVER STAGE ---
FROM base AS asset-server

# ... (bleibt unverändert) ...

# --- 5. PRODUCTION APP SERVER STAGE ---
FROM base AS app-server

# ... (Health Check) ...

# Copy production dependencies (now without dev deps!) and built application
COPY --from=build --chown=node:node /app/node_modules /app/node_modules
COPY --from=build --chown=node:node /app/dist /app/dist
COPY --from=build --chown=node:node /app/package.json /app/package.json

USER node

CMD ["node", "dist/server.js"]
```

**Warum `pnpm prune --prod`?**
- Entfernt alle devDependencies aus node_modules
- Behält nur Production Dependencies (z.B. express)
- Muss **nach** dem Build erfolgen (Build benötigt typescript, vite, tsx)
- Muss **vor** dem COPY nach app-server erfolgen

**Vergleich (Beispiel):**
```bash
# Vorher (mit DevDeps):
node_modules: ~250MB
  - express: 5MB
  - typescript: 50MB ❌
  - vite: 80MB ❌
  - @types/*: 20MB ❌
  - tsx: 15MB ❌
  - ...

# Nachher (nur ProdDeps):
node_modules: ~100MB (60% kleiner!)
  - express: 5MB ✅
  - (andere Runtime-Dependencies)
```

**Status:** [X] Aktualisiert

**Optimierte Stages:**

```dockerfile
# --- 3. PRODUCTION BUILD STAGE (Secure Asset Build) ---
FROM base AS build

# Using a high, non-conflicting UID for maximum security in production.
RUN apk add --no-cache --virtual .user-deps shadow && \
        usermod -u 50000 node && \
        groupmod -g 50000 node && \
        apk del .user-deps && \
    chown -R node:node /home/node /app /usr/local/bin/pnpm

USER node

# Copy dependency files
COPY --chown=node:node package.json pnpm-lock.yaml ./

# Install ALL dependencies (including devDependencies for build)
RUN pnpm install --frozen-lockfile

# Copy ONLY necessary source files (not entire directory)
COPY --chown=node:node vite.config.js ./
COPY --chown=node:node tsconfig.json ./
COPY --chown=node:node postcss.config.js ./
COPY --chown=node:node resources/ ./resources/
COPY --chown=node:node src/node/ ./src/node/

# Execute the build command
RUN pnpm run build

# --- 4. PRODUCTION ASSET SERVER STAGE ---
FROM base AS asset-server

# Copy user/group from build stage
COPY --from=build /etc/passwd /etc/group /etc/

# Health Check
HEALTHCHECK --interval=30s --timeout=5s --start-period=5s --retries=3 \
    CMD test -f /app/package.json

# Copy only build artifacts
COPY --from=build --chown=node:node /app/public/build /app/public/build
COPY --from=build --chown=node:node /app/package.json /app/package.json

USER node

CMD ["sleep", "infinity"]

# --- 5. PRODUCTION APP SERVER STAGE ---
FROM base AS app-server

# Copy user/group from build stage
COPY --from=build /etc/passwd /etc/group /etc/

# Health Check
HEALTHCHECK --interval=15s --timeout=3s --start-period=10s --retries=3 \
    CMD curl --fail http://localhost:3000/health || exit 1

# Copy production dependencies and built application
COPY --from=build --chown=node:node /app/node_modules /app/node_modules
COPY --from=build --chown=node:node /app/dist /app/dist
COPY --from=build --chown=node:node /app/package.json /app/package.json

USER node

CMD ["node", "dist/server.js"]
```

**Status:** [X] Aktualisiert

---

### 4.5 Makefile - Detaillierte Ordnerstruktur erweitern

**Pfad:** `/home/mst/Code/docker-webdev/Makefile`

**Aktion:** 'setup' Target erweitern um detaillierte Ordnerstruktur

**Aktuell (Zeile 77):**
```bash
mkdir -p $${STORAGE_DIR:-./storage} $${LOG_DIR:-./logs}/{app,nginx,php} src/{php,node} resources/{js,css,images} public/build tests vendor
```

**Neu (erweitert):**
```bash
# Base directories
mkdir -p $${STORAGE_DIR:-./storage} $${LOG_DIR:-./logs}/{app,nginx,php} vendor

# Source directories
mkdir -p src/php/{Http/{Controller,Middleware},Domain,Infrastructure/{Database,Cache}}
mkdir -p src/node/{routes,controllers,services,middleware}

# Resources directories (Frontend source)
mkdir -p resources/{js/components,css/components,images,fonts}

# Public directory (Web root)
mkdir -p public/build

# Tests
mkdir -p tests/{Unit,Feature}

# Config & Templates
mkdir -p config templates

# Storage (Runtime data)
mkdir -p storage/{app/{uploads,generated},cache,sessions}
```

**Vollständiger 'setup' Target:**
```makefile
setup: ## Create directories, install dev dependencies and ensure structure
	@if [ ! -f .env ]; then \
		echo -e "\033[0;31mError: .env file not found. Please run 'make init' first.\033[0m"; \
		exit 1; \
	fi
	@echo -e "\033[0;33mCreating project structure...\033[0m"
	@. ./.env && mkdir -p $${LOG_DIR:-./logs}/{app,nginx,php} vendor

	# Source directories
	@mkdir -p src/php/{Http/{Controller,Middleware},Domain,Infrastructure/{Database,Cache}}
	@mkdir -p src/node/{routes,controllers,services,middleware}

	# Resources directories (Frontend source)
	@mkdir -p resources/{js/components,css/components,images,fonts}

	# Public directory (Web root)
	@mkdir -p public/build

	# Tests
	@mkdir -p tests/{Unit,Feature}

	# Config & Templates
	@mkdir -p config templates

	# Storage (Runtime data) - Set permissions
	@. ./.env && mkdir -p $${STORAGE_DIR:-./storage}/{app/{uploads,generated},cache,sessions}
	@. ./.env && chmod 770 $${STORAGE_DIR:-./storage} -R

	@echo -e "\033[0;32mProject structure created!\033[0m"
	@$(MAKE) --silent dev-deps
	@echo -e "\033[0;32mSetup completed (directories + dependencies)!\033[0m"
```

**Status:** [X] Aktualisiert

---

### 4.6 Makefile - Docker-First Strategie für Dependencies

**Pfad:** `/home/mst/Code/docker-webdev/Makefile`

**Problem:** Aktuelles Makefile nutzt lokale Tools (composer/pnpm) als Default → Versionsdifferenzen möglich

**Lösung:** Docker als Default, lokale Tools als Opt-in

**Änderungen:**

```makefile
##@ Setup

dev-deps: ## Install/update Composer dependencies (Docker - guaranteed consistency)
	@echo -e "\033[0;33mManaging Composer dependencies (Docker)...\033[0m"
	@if [ ! -f "vendor/autoload.php" ]; then \
		XDEBUG_MODE=off docker compose run --rm --no-TTY php composer install --prefer-dist --no-interaction; \
	else \
		XDEBUG_MODE=off docker compose run --rm --no-TTY php composer install --prefer-dist --no-interaction --no-scripts; \
	fi
	@echo -e "\033[0;32mDependencies ready!\033[0m"

dev-deps-local: ## Install/update Composer dependencies (Local - faster, but version may differ)
	@if command -v composer >/dev/null 2>&1; then \
		echo -e "\033[0;33m⚠️  Using local Composer (version may differ from Docker).\033[0m"; \
		echo -e "\033[0;34mFor guaranteed consistency, use 'make dev-deps' instead.\033[0m"; \
		if [ ! -f "vendor/autoload.php" ]; then \
			composer install --prefer-dist --no-interaction; \
		else \
			composer install --prefer-dist --no-interaction --no-scripts; \
		fi; \
		echo -e "\033[0;32mDependencies installed!\033[0m"; \
	else \
		echo -e "\033[0;31mError: Local Composer not found. Use 'make dev-deps' instead.\033[0m"; \
		exit 1; \
	fi

##@ Node Commands

node-install: ## Install Node.js dependencies (Docker - guaranteed consistency)
	@echo -e "\033[0;33mInstalling Node.js dependencies (Docker)...\033[0m"
	@docker compose exec node pnpm install
	@echo -e "\033[0;32mDependencies installed!\033[0m"

node-install-local: ## Install Node.js dependencies (Local - faster, but version may differ)
	@if command -v pnpm >/dev/null 2>&1; then \
		echo -e "\033[0;33m⚠️  Using local pnpm (version may differ from Docker).\033[0m"; \
		echo -e "\033[0;34mFor guaranteed consistency, use 'make node-install' instead.\033[0m"; \
		pnpm install; \
		echo -e "\033[0;32mDependencies installed!\033[0m"; \
	else \
		echo -e "\033[0;31mError: Local pnpm not found. Use 'make node-install' instead.\033[0m"; \
		exit 1; \
	fi

# Ad-hoc Commands auf laufenden Containern (schneller als run --rm)
composer: ## Execute Composer command in running container (e.g. make composer CMD="require vendor/package")
	@docker compose exec php composer $(CMD)

pnpm: ## Execute pnpm command in running container (e.g. make pnpm CMD="add vue")
	@docker compose exec node pnpm $(CMD)
```

**Rationale:**
- ✅ **Docker-First:** Garantiert identische Versionen (Composer 2.8.5, pnpm 9.15.1)
- ✅ **Konsistenz:** Lokale Setup = CI/CD Setup
- ✅ **Opt-in für Speed:** `-local` Targets mit klarem Warning
- ✅ **composer.json config.platform schützt nur vor PHP-Version-Differenzen, nicht vor Composer/pnpm-Versionsdifferenzen**
- ✅ **Ad-hoc Commands:** `make composer` / `make pnpm` für schnelle Iterations auf laufenden Containern

**Status:** [X] Aktualisiert

---

### 4.7 compose.override.yaml - HMR Config Mount erweitern

**Pfad:** `/home/mst/Code/docker-webdev/compose.override.yaml` (existiert bereits)

**Aktion:** Ergänzungen für Vite HMR Support

**Änderungen:**
1. **nginx Service:** Vite HMR Config mount hinzufügen
2. **node Service:** Vite Dev Server Port (5173) exposen
3. **node Service:** NODE_ENV=development setzen

**Zu ergänzen in der existierenden Datei:**

```yaml
services:
  nginx:
    build:
      target: development
      args:
        USER_ID: ${USER_ID:-101}
        GROUP_ID: ${GROUP_ID:-101}
    volumes:
      # Application
      - ./public:/var/www/html/public:ro

      # Nginx Configuration (Read-Only)
      - ./docker/nginx/nginx.conf:/etc/nginx/nginx.conf:ro
      - ./docker/nginx/conf.d:/etc/nginx/conf.d:ro
      # ⭐ NEU: Vite HMR Config (Development only!)
      - ./docker/nginx/conf.d/vite-hmr.conf:/etc/nginx/conf.d/vite-hmr.conf:ro

  php:
    # ... (bleibt unverändert)

  node:
    build:
      target: development
      args:
        USER_ID: ${USER_ID:-50000}
        GROUP_ID: ${GROUP_ID:-50000}
    # ⭐ NEU: Vite HMR Port exposen
    ports:
      - "${VITE_PORT:-5173}:5173"
    volumes:
      - .:/app
      - node_modules:/app/node_modules
    # ⭐ NEU: NODE_ENV setzen
    environment:
      - NODE_ENV=development
    tty: true

volumes:
  node_modules:
    driver: local
```

**Konkret hinzuzufügen:**

1. In `nginx.volumes` ergänzen:
```yaml
- ./docker/nginx/conf.d/vite-hmr.conf:/etc/nginx/conf.d/vite-hmr.conf:ro
```

2. In `node` Abschnitt `ports` hinzufügen (nach `args`, vor `volumes`):
```yaml
ports:
  - "${VITE_PORT:-5173}:5173"
```

3. In `node` Abschnitt `environment` hinzufügen (nach `volumes`):
```yaml
environment:
  - NODE_ENV=development
```

#### 4.7.1 NODE_ENV vs ENV - Single Source of Truth (✅ Erledigt)

**Antwort:** ENV ist die **Single Source of Truth**, NODE_ENV wird **davon abgeleitet** für Node.js Ecosystem-Kompatibilität.

**Warum beide Variablen?**

1. **ENV** (Projekt-Variable):
   - ✅ Zentrale Steuerung für das **gesamte Projekt** (PHP, Nginx, Node.js)
   - ✅ Wird in `.env` definiert und steuert Docker Compose-Verhalten
   - ✅ Bestimmt welche compose-Dateien geladen werden
   ```bash
   # .env
   ENV=development  # oder production
   ```

2. **NODE_ENV** (Node.js Ecosystem-Variable):
   - ✅ **Erforderlich** für Node.js npm packages und Tools
   - ✅ Viele Libraries erwarten explizit `NODE_ENV`:
     - Express: Optimierungen in Production
     - Vite: Build-Modus (development vs production)
     - TypeScript Compiler: Source Maps
     - Logging Libraries: Log-Level
   - ✅ **Best Practice** im Node.js Ecosystem

**Pattern: ENV → NODE_ENV (Ableitung)**

```yaml
# compose.override.yaml (Development)
services:
  node:
    environment:
      # ENV=development ist implizit (aus .env)
      # NODE_ENV wird explizit für Node.js Ecosystem gesetzt
      - NODE_ENV=development
```

```yaml
# compose.prod.yaml (Production)
services:
  node:
    environment:
      # ENV=production ist implizit (aus .env)
      # NODE_ENV wird explizit für Node.js Ecosystem gesetzt
      - NODE_ENV=production
```

**Vorteile dieser Strategie:**

1. ✅ **Single Source of Truth:** `ENV` in `.env` steuert alles
2. ✅ **Explicit is better than implicit:** NODE_ENV wird bewusst gesetzt
3. ✅ **Ecosystem-Kompatibilität:** Node.js Tools funktionieren out-of-the-box
4. ✅ **Flexibilität:** Theoretisch könnte `ENV=staging` mit `NODE_ENV=production` kombiniert werden

**Beispiel: Was passiert, wenn NODE_ENV fehlt?**

```javascript
// src/node/server.ts
const NODE_ENV = process.env.NODE_ENV || 'production';  // ❌ Fallback nötig!

// Mit explizitem NODE_ENV:
const NODE_ENV = process.env.NODE_ENV;  // ✅ Immer definiert

// Vite (vite.config.js)
sourcemap: process.env.NODE_ENV !== 'production',  // ✅ Funktioniert

// Express
if (NODE_ENV === 'development') {
  // ✅ Development-spezifische Middleware
}
```

**Zusammenfassung:**
- **ENV**: Master-Variable für **Projekt-Konfiguration** (Docker Compose)
- **NODE_ENV**: **Abgeleitete** Variable für **Node.js Ecosystem-Kompatibilität**
- **Warum nicht nur ENV?** Node.js Tools und Libraries erwarten explizit `NODE_ENV`

**Siehe auch:** compose.override.yaml:1684-1685, compose.prod.yaml (NODE_ENV Zuweisung)

**Status:** [X] Ergänzt

---

## Phase 4 - Zusammenfassung der Änderungen

| Datei | Aktion | Beschreibung |
|-------|--------|-------------|
| **4.1** `docker/nginx/conf.d/vite-hmr.conf` | **Neu** | Vite HMR Proxy (nur Development) |
| **4.2** `docker/nginx/conf.d/default.conf` | **Ergänzen** | CORS Beispiele (kommentiert) |
| **4.2** `docker/nginx/nginx.conf` | **Ergänzen** | Brotli Compression (kommentiert) |
| **4.2** `docker/nginx/Dockerfile` | **Ergänzen** | Brotli Module Installation (kommentiert) |
| **4.3** `docker/nginx/conf.d/default.conf` | **Erweitern** | Rate Limiting Zonen (API/Assets/General) |
| **4.3** `docker/nginx/nginx.conf` | **Erweitern** | Rate Limiting Zonen Definition |
| **4.4** `docker/node/Dockerfile` | **Optimieren** | Selektives Kopieren in Production Stages |
| **4.5** `Makefile` | **Erweitern** | Detaillierte Ordnerstruktur in 'setup' |
| **4.6** `Makefile` | **Umstellen** | Docker-First Strategie (dev-deps, node-install) |
| **4.7** `compose.override.yaml` | **Ergänzen** | HMR Config Mount, Vite Port, NODE_ENV |

**Hinweise:**
- `public/index.php` (Phase 3.3) wurde bereits mit CSP Template und aufgeräumt erstellt
- `package.json` Versionen (Phase 2.1 & 3.8) auf stabile Releases aktualisiert (Option A)

---

## Phase 4 - Node.js Commands (bereits vorhanden, keine Änderung)

### Makefile - Node.js Commands erweitern

**Status:** ✅ Bereits implementiert im aktuellen Makefile (Zeile 193-211)

*(Keine Änderungen erforderlich)*

---

## Phase 5: .gitkeep Dateien mit Dokumentation

### 5.1 Alle benötigten .gitkeep Dateien

**Erstellen:**

```bash
# src/php/
cat > src/php/.gitkeep << 'EOF'
# PHP Backend Code
#
# This directory contains server-side PHP code that runs in PHP-FPM.
#
# Example structure:
#   src/php/
#   ├── Http/
#   │   ├── Controller/
#   │   └── Middleware/
#   ├── Domain/
#   └── Infrastructure/
#
# Namespace: App\
# Autoloading: PSR-4 via composer.json
#
# If you're not using PHP, delete this directory and remove
# PHP service from compose.yaml.
EOF

# src/node/
cat > src/node/.gitkeep << 'EOF'
# Node.js Backend Code
#
# This directory contains server-side Node.js code.
# Only used when NODE_TARGET=app-server in docker-compose.
#
# Example structure:
#   src/node/
#   ├── routes/
#   ├── controllers/
#   ├── services/
#   └── server.ts
#
# Build output: dist/
# Runtime: Node.js container (port 3000)
#
# Usage:
#   - Set NODE_TARGET=app-server in .env
#   - make node-server-build
#   - make node-app-server-up
#
# If you're only using Node.js for frontend builds,
# delete this directory.
EOF

# resources/js/
cat > resources/js/.gitkeep << 'EOF'
# Client-Side JavaScript
#
# JavaScript that runs in the user's browser.
#
# Build tool: Vite
# Build output: public/build/
#
# Development: make node-dev (HMR on port 5173)
# Production: make node-build
#
# Supports: Vue, React, Alpine.js, Svelte, Vanilla JS
EOF

# resources/css/
cat > resources/css/.gitkeep << 'EOF'
# Stylesheets
#
# CSS/SCSS/SASS files for the frontend.
#
# Build tool: Vite + PostCSS
# Build output: public/build/
#
# Supports:
#   - Plain CSS
#   - SCSS/SASS
#   - PostCSS (autoprefixer, nesting)
#   - Tailwind CSS
EOF

# resources/images/
cat > resources/images/.gitkeep << 'EOF'
# Images
#
# Source images for the frontend.
# Processed by Vite during build.
EOF

# public/build/
cat > public/build/.gitkeep << 'EOF'
# Build Output
#
# Auto-generated by Vite.
# DO NOT commit files in this directory!
#
# This .gitkeep keeps the directory structure in git.
EOF

# config/
cat > config/.gitkeep << 'EOF'
# Application Configuration
#
# Environment-specific configuration files.
#
# Examples:
#   - database.php
#   - app.php
#   - services.php
EOF

# templates/
cat > templates/.gitkeep << 'EOF'
# PHP Templates
#
# Server-side templates (Twig, Plates, Blade).
#
# Example structure:
#   templates/
#   ├── layout/
#   │   └── base.html.twig
#   └── pages/
#       └── home.html.twig
#
# Only relevant for server-side rendering.
# For SPAs, delete this directory.
EOF

# storage/
cat > storage/app/.gitkeep << 'EOF'
# Application Storage
#
# Runtime data generated by the application.
#
# Examples:
#   - User uploads
#   - Generated files (PDFs, images)
#
# Mounted as read-write in Docker.
EOF

cat > storage/cache/.gitkeep << 'EOF'
# Application Cache
#
# Cached data (views, routes, config).
#
# Can be safely deleted.
EOF

cat > storage/sessions/.gitkeep << 'EOF'
# Session Storage
#
# PHP session files (if using file-based sessions).
EOF

# tests/
cat > tests/Unit/.gitkeep << 'EOF'
# Unit Tests
#
# PHPUnit unit tests for isolated components.
EOF

cat > tests/Feature/.gitkeep << 'EOF'
# Feature Tests
#
# PHPUnit feature/integration tests.
EOF
```

**Status:** [X] Erstellt

---

## Phase 6: Testing & Validation

### 6.1 PHP Testing

**Befehle:**

```bash
# 1. Composer Autoloader testen
docker compose exec php php -r "require 'vendor/autoload.php'; echo 'Autoloader OK';"

# 2. Klasse laden testen
docker compose exec php php -r "
require 'vendor/autoload.php';
\$controller = new \App\Http\Controller\ExampleController();
\$controller->health();
"

# 3. PHPStan ausführen
make analyse

# 4. PHP-CS-Fixer ausführen
make cs-check

# 5. Webserver testen
curl http://localhost:8080/
curl http://localhost:8080/api/health
```

**Status:** [X] Getestet

**Ergebnis:** ✅ Alle Tests erfolgreich
- Autoloader: OK
- PHPStan: No errors (2 files analyzed)
- PHP-CS-Fixer: 0 errors in 4 files
- Web: http://localhost:8080/ → {"message":"Hello from PHP!"}
- API: http://localhost:8080/api/health → {"status":"ok"}

---

### 6.2 Node.js Frontend Testing (HMR)

**Befehle:**

```bash
# 1. Dependencies installieren
make node-install

# 2. Vite Dev Server starten (HMR)
make node-dev
# Sollte auf Port 5173 laufen

# 3. Browser öffnen
# http://localhost:8080/test.html
# Ändere resources/css/app.css -> sollte live reloaden

# 4. Production Build testen
make node-build
# Prüfe public/build/manifest.json
ls -la public/build/
```

**Status:** [X] Getestet

**Ergebnis:** ✅ Alle Tests erfolgreich (siehe Changelog 2.5)

**Bugfixes während Testing (2025-12-19):**

1. **Named Volume Permissions (node_modules)**
   - **Problem:** Docker erstellt named volumes mit root:root, User `node` (UID 1000) konnte nicht schreiben
   - **Lösung:** `make node-install` angepasst - startet mit `--user root`, fixt Permissions, dann `su node`
   - **Datei:** `Makefile` Zeile 237
   - **Warum named volume?** Windows/Mac Performance (Linux könnte bind mount nutzen, aber Konsistenz wichtiger)

2. **Vite 6 ESM Compatibility**
   - **Problem:** `vite.config.js` verwendete `require('autoprefixer')` (CommonJS) in ESM-Kontext
   - **Fehler:** `Dynamic require of "..." is not supported`
   - **Lösung:** Geändert zu ESM-Import (`import autoprefixer from 'autoprefixer'`)
   - **Datei:** `vite.config.js` Zeile 3 & 81

3. **Content Security Policy (CSP) blockierte Vite HMR**
   - **Problem:** Nginx CSP erlaubte nur `script-src 'self'`, Vite läuft auf Port 5173
   - **Symptom:** Scripts/Styles in Browser mit CSP-Fehler markiert, API Health Check bleibt auf "Loading..."
   - **Lösung:** CSP für Development erweitert um `http://localhost:5173` und WebSocket (`ws://localhost:5173`)
   - **Datei:** `docker/nginx/conf.d/default.conf` Zeile 35
   - **Wichtig:** Production CSP muss stricter sein (siehe Kommentar in Datei)

4. **Vite Base Path**
   - **Problem:** `test.html` verwendete Pfade ohne `/build/` Prefix
   - **Lösung:** Pfade angepasst auf `http://localhost:5173/build/@vite/client` und `.../build/js/app.js`
   - **Datei:** `public/test.html` Zeile 16-17

---

### 6.3 Node.js Backend Testing (app-server)

**Befehle (Korrigierter Workflow):**

```bash
# 1. Container starten (nginx + php)
make up

# 2. Node-Container starten
make node-up

# 3. Dependencies installieren (falls noch nicht geschehen)
make node-install

# 4. Node.js Backend starten (Development Watch Mode)
make node-server-dev

# 5. In einem neuen Terminal testen:
curl http://localhost:3000/health
curl http://localhost:3000/api/hello?name=Docker
```

**Alternative (Production Build):**

```bash
# 1. TypeScript kompilieren
make node-server-build
# Prüfe dist/server.js

# 2. App-Server starten (Production Mode)
make node-app-server-up

# 3. Health Check
curl http://localhost:3000/health
curl http://localhost:3000/api/hello?name=Docker

# 4. Logs ansehen
make node-app-server-logs
```

**Status:** [X] Getestet

**Ergebnis:** ✅ Alle Tests erfolgreich

**Testergebnisse (2025-12-19):**

1. **Node.js Health Check:**
   ```json
   {
     "status": "ok",
     "service": "node-backend",
     "timestamp": "2025-12-19T11:34:38.442Z",
     "uptime": 392.06156866,
     "node_version": "v24.12.0",
     "environment": "development"
   }
   ```

2. **Node.js API Endpoint:**
   ```json
   {
     "message": "Hello, Docker!",
     "timestamp": "2025-12-19T11:34:39.541Z",
     "server": "Node.js + Express"
   }
   ```

**Wichtige Erkenntnis - Nginx Chicken-Egg-Problem:**

Bei `make up` (nur nginx + php) tritt ein Problem auf:
- Nginx versucht zu starten, bevor Node-Container läuft
- Nginx-Config enthält `upstream node` für Vite HMR Proxy (default.conf:58)
- DNS-Auflösung schlägt fehl: `nginx: [emerg] host not found in upstream "node"`
- Nginx crash-loopt, bis Node-Container gestartet wird

**Lösungsoptionen:**
1. **Einfach:** Immer `make up SERVICES="node"` verwenden (startet alle Container)
2. **Optional:** `nginx.depends_on: node` in compose.yaml hinzufügen
3. **Production-Ready:** Nginx-Config mit `resolver` erweitern für dynamische DNS-Auflösung

**Aktueller Workaround:**
- `make up` (nginx crasht anfangs)
- `make node-up` (nginx erholt sich automatisch)
- Funktioniert, aber nicht elegant

---

## Phase 7: Security & Best Practices

### 7.1 Security Checklist

- [ ] **CSP Headers:** Content Security Policy konfiguriert (Nginx + PHP)
- [ ] **HTTPS:** In Production HTTPS nutzen (via Reverse Proxy)
- [ ] **Secrets:** Keine Secrets in .env committen
- [ ] **User Permissions:** Non-root user in allen Containern (bereits konfiguriert)
- [ ] **Read-only Filesystem:** Production Container read-only (bereits konfiguriert)
- [ ] **Input Validation:** Alle User-Inputs validieren
- [ ] **SQL Injection:** Prepared Statements nutzen (bei DB-Nutzung)
- [ ] **XSS Protection:** Output escaping in Templates
- [ ] **CSRF Protection:** CSRF Tokens für Forms
- [ ] **Rate Limiting:** Nginx rate limiting konfigurieren (bereits basic vorhanden)

@Answered:
- CSP: Bereits gut konfiguriert! ✅ Wird in index.php mit ausführlicherem Nonce-Template erweitert (Phase 4.4)
- HTTPS: Korrekt, DevOps-Sache. Wird nur in README erwähnt ✅
- Secrets/Input Validation/SQL Injection/XSS: Vollkommen korrekt! Reine Dev-Sache, nur README-Erwähnung ✅
- CSRF: Keine universellen Server-Defaults möglich ohne Framework/Library. Nur README-Hinweise ✅
- Rate Limiting: Wird verbessert! ✅ Separate Zonen für API/Assets/General (siehe Phase 4.5)
  - API: Streng (5r/s)
  - Assets: Großzügig (50r/s)
  - General: Default (10r/s)

**Status:** [ ] Geprüft

---

### 7.2 Performance Best Practices

- [ ] **OPcache:** Aktiviert in Production (bereits konfiguriert)
- [ ] **Gzip/Brotli:** Nginx compression aktiviert (bereits konfiguriert)
- [ ] **Asset Caching:** Long-term caching für statische Assets (bereits konfiguriert)
- [ ] **CDN:** Für Production erwägen
- [ ] **Database Indexes:** Bei DB-Nutzung
- [ ] **Query Optimization:** N+1 Queries vermeiden
- [ ] **Lazy Loading:** Für Bilder und Components
- [ ] **Code Splitting:** Vite code splitting nutzen

@Answered:
- Vollkommen richtig! Die meisten Punkte sind Dev-Sache.
- Bereits im Boilerplate konfiguriert: ✅
  - OPcache: Aktiv in docker/php/php.ini
  - Gzip: Aktiv in docker/nginx/nginx.conf (Zeile 37-45)
  - Brotli: Wird zusätzlich installiert mit Umschalt-Kommentar (siehe Phase 4.3)
  - Asset Caching: Konfiguriert in default.conf (Zeile 50-55)
- Nur README-Erwähnung (Dev-Sache): ℹ️
  - CDN, Database Indexes, Query Optimization
  - Lazy Loading (HTML/JS-Technik, Framework-spezifisch)
  - Code Splitting (Vite macht dies automatisch, Dev kann anpassen)

**Status:** [ ] Geprüft

---

## Phase 8: Dokumentation

### 8.1 README.md neu schreiben

**Status:** [ ] Nach erfolgreichem Testing

**Inhalt sollte umfassen:**
- Setup-Optionen (PHP-only, Node-only, Fullstack)
- Quick Start Guide
- Development Workflow
- Production Deployment
- Makefile Commands
- Troubleshooting
- **Tool-Versionen & Konsistenz**

#### Abschnitt: Tool-Versionen (wichtig!)

```markdown
## Tool-Versionen & Konsistenz

### Empfohlene Versionen

Für optimale Kompatibilität verwenden die Docker-Container:
- **Node.js:** 24.x (LTS)
- **pnpm:** 9.15.1 (via `corepack enable`)
- **Composer:** 2.8.5
- **PHP:** 8.4

### Docker-First Strategie

Dieses Boilerplate nutzt **Docker-First** für Dependencies:
- ✅ `make dev-deps` → Docker Composer (garantiert identisch zu CI/CD)
- ✅ `make node-install` → Docker pnpm (garantiert identisch zu CI/CD)

**Opt-in für lokale Tools (schneller, aber versionsspezifisch):**
- ⚡ `make dev-deps-local` → Nutzt lokalen Composer (mit Warning)
- ⚡ `make node-install-local` → Nutzt lokalen pnpm (mit Warning)

### Warum Docker-First?

**Problem mit lokalen Tools:**
```bash
# Szenario:
Dev A: Composer 2.7, pnpm 9.15 → composer.lock v1
Dev B: Composer 2.8, pnpm 10.0 → composer.lock v2 (Konflikt!)
CI:    Composer 2.8, pnpm 9.15 → Build fails!
```

**Lösung:**
- Docker garantiert **identische Tool-Versionen** für alle Devs + CI
- `composer.json` `config.platform` schützt nur vor **PHP-Versions-Differenzen**
- Es schützt **nicht** vor Composer/pnpm-Versionsdifferenzen!

### Ad-hoc Commands (auf laufenden Containern)

Für schnelle Iterationen ohne Container-Neustart:
```bash
make composer CMD="require symfony/cache"  # Schneller als docker compose run --rm
make pnpm CMD="add vue"                    # Auf laufendem Container
```

### Best Practices

1. **Setup & CI/CD:** Nutze Docker-Commands (`make dev-deps`, `make node-install`)
2. **Lokale Entwicklung:** Optional `-local` Targets für Speed (auf eigene Gefahr)
3. **Lockfiles committen:** `composer.lock` und `pnpm-lock.yaml` immer mit Docker generieren
4. **Version-Pinning:** `packageManager` in package.json und Composer-Version in Dockerfile
```

---

## Appendix: Kommando-Referenz

### Entwicklung starten (PHP + Frontend HMR)

```bash
# 1. Projekt initialisieren
make init
make setup

# 2. Container bauen
make build

# 3. Container starten
make up

# 4. Node Dependencies installieren
make node-install

# 5. Vite HMR starten
make node-dev

# 6. Browser öffnen
# http://localhost:8080/test.html
```

### Production Build

```bash
# 1. Frontend Assets bauen
make node-build

# 2. Container bauen (Production)
ENV=production make build

# 3. Container starten (Production)
docker compose -f compose.yaml -f compose.prod.yaml up -d
```

### Node.js Backend (app-server)

```bash
# 1. Backend kompilieren
make node-server-build

# 2. App-Server starten
make node-app-server-up

# 3. Testen
curl http://localhost:3000/health
```

---

## Status-Übersicht

| Phase | Status | Notizen |
|-------|--------|---------|
| 1. Ordnerstruktur | [X] | ✅ Bereits erledigt |
| 2. Konfigurationsdateien | [ ] | package.json (V2.2), vite.config.js, tsconfig.json, postcss.config.js |
| 3. Boilerplate-Code | [ ] | PHP (inkl. CSP), Router, Frontend (JS/CSS), Node Server, test.html |
| 4. Docker/Compose | [ ] | **7 Abschnitte** (HMR, CORS, Brotli, Rate Limiting, Node, 2x Makefile, Compose) |
| 5. .gitkeep Dateien | [ ] | Dokumentation in allen Ordnern |
| 6. Testing | [ ] | PHP, Frontend HMR, Node Backend |
| 7. Security | ✅ | Bereits konfiguriert + CSP in Phase 3.3 |
| 8. Documentation | [ ] | README.md nach erfolgreichem Testing (inkl. Tool-Versionen Abschnitt) |

---

## Wichtige Erkenntnisse & Entscheidungen

### ✅ Konsistent und korrekt (keine Änderung nötig):
- **Dependencies Management:** Production = automatisch, Development = manuell (PHP & Node identisch)
- **Gzip Compression:** Bereits aktiv in nginx.conf
- **Rate Limiting:** Bereits vorhanden, wird in Phase 4.3 mit Zonen erweitert
- **Security Headers:** Bereits in Nginx konfiguriert
- **PHP Error Handling:** Bereits in php.ini/development.ini konfiguriert

### 🔧 Wird verbessert:
- **package.json:** Versionen aktualisiert auf stabile Releases (Phase 2.1 & 3.8)
- **index.php:** Vereinfacht + CSP Nonce-Template hinzugefügt (Phase 3.3)
- **test.html:** Dev-Mode mit HMR als Default aktiviert (Phase 3.6)
- **Nginx HMR Config:** Separate Datei nur für Development (Phase 4.1)
- **Brotli:** Wird als Option hinzugefügt (Phase 4.2)
- **CORS:** Kommentierte Beispiele in Nginx Config (Phase 4.2)
- **Rate Limiting:** Separate Zonen für API/Assets/General (Phase 4.3)
- **Node Dockerfile:** Production optimiert mit selektivem Kopieren (Phase 4.4)
- **Makefile:** Detaillierte Ordnerstruktur (Phase 4.5)
- **Makefile:** Docker-First Strategie für Dependencies (Phase 4.6)
- **compose.override.yaml:** HMR Config Mount, Vite Port, NODE_ENV (Phase 4.7)

### ℹ️ Nur in README (Dev-Sache):
- HTTPS Setup (DevOps)
- Input Validation, XSS, SQL Injection (Dev)
- CSRF Protection (Framework-abhängig)
- CDN, Database Optimization
- Lazy Loading (Frontend-Technik)
- Code Splitting (Vite macht dies bereits)

---

**Erstellt:** 2025-12-19
**Letzte Aktualisierung:** 2025-12-28 (Makefile Konsistenz und Formatierung)
**Version:** 2.10

---

## Changelog

### Version 2.11 (2025-12-28)
- ✅ **PHP Code Quality Improvements: PSR-4 Compliance und Dependency Management**
  - **Problem:** IDE-Warnungen und fehlende Extension-Deklarationen
    - `ext-pdo` fehlte in composer.json, obwohl HealthCheck.php PDO verwendet
    - `ext-json` war implizit verwendet, aber nicht deklariert
    - IDE-Warnungen in ViteHelper.php: "Cannot resolve file/directory" für sprintf() Platzhalter
    - Unnötige Redundanz in Conditional-Checks (null + empty)
  - **Lösung 1: Composer Dependencies vervollständigt**
    - **ext-pdo hinzugefügt:** Erforderlich für PostgreSQL/MariaDB Verbindungen in HealthCheck
    - **ext-json hinzugefügt:** Verwendet in Router, ExampleController, StatusController (JSON_THROW_ON_ERROR)
    - **Best Practice:** Explizite Deklaration aller verwendeten Extensions verhindert Runtime-Fehler
  - **Lösung 2: IDE-Warnungen in ViteHelper.php behoben**
    - **@noinspection HtmlUnknownTarget Annotations hinzugefügt**
      - renderScriptTags() Zeile 108: Unterdrückt Warnung für dynamische Vite Dev Server URLs
      - renderScriptTags() Zeile 125: Unterdrückt Warnung für Production Build-Assets
      - renderCssTags() Zeile 148: Unterdrückt Warnung für CSS-Dateien aus Manifest
    - **Code-Redundanz entfernt:**
      - Zeile 142: `if (empty($cssUrls))` ersetzt `if ($cssUrls === null || empty($cssUrls))`
      - Grund: `empty()` prüft bereits auf null, array, und Leerheit
    - **Warum diese Warnungen auftraten:**
      - PHPStorm versucht, String-Formatierungen in sprintf() zu validieren
      - `%s` Platzhalter wurden als tatsächliche Dateipfade interpretiert
      - Warnungen waren harmlos, aber störend für Code-Quality-Metriken
  - **Lösung 3: Template-Variable-Dokumentation in welcome.php**
    - **Problem:** PhpStorm meldete "Undefined variable" für $vite, $env, $status
      - Variablen werden von WelcomeController via include übergeben
      - IDE konnte nicht erkennen, dass Variablen im Template-Scope verfügbar sind
    - **PHPDoc-Header hinzugefügt (Zeilen 1-12):**
      - `@var \App\Infrastructure\ViteHelper $vite` - Vite asset helper
      - `@var array $env` - Environment configuration
      - `@var array $status` - Service health status
      - `declare(strict_types=1)` für Type-Safety
    - **Vorteile:**
      - IDE-Autocomplete für Template-Variablen funktioniert
      - Type-Hinting für bessere Code-Navigation
      - Dokumentiert erwartete Variablen für Template-Engine-Integration
      - Best Practice für PHP-Template-Dateien
  - **Dateien geändert:**
    - `composer.json`: ext-pdo und ext-json hinzugefügt (Zeilen 16-17), license Kleinschreibung (Zeile 5)
    - `src/php/Infrastructure/ViteHelper.php`: @noinspection Annotations, Code-Cleanup (Zeilen 108, 125, 142, 148)
    - `templates/welcome.php`: PHPDoc-Header mit @var Annotations (Zeilen 1-12)
  - **Ergebnis:**
    - ✅ Alle IDE-Warnungen in src/php/* und templates/* behoben
    - ✅ Composer Dependencies vollständig deklariert
    - ✅ Code Quality verbessert (keine redundanten Checks)
    - ✅ Template-Variablen dokumentiert mit Type-Hints
    - ✅ PSR-4 Namespaces bereits korrekt (keine Änderungen nötig)
  - **Nächste Schritte:**
    - Autoloader bereits regeneriert (`composer dump-autoload -o`)
    - Bei anhaltenden Namespace-Warnungen: PhpStorm Cache invalidieren (`File` → `Invalidate Caches`)

### Version 2.10 (2025-12-23)
- ✅ **Multi-Database Support mit Docker Compose Profiles**
  - **PostgreSQL 17.7-alpine als Standard (empfohlen)**
    - Image: `postgres:17.7-alpine` (Minor-Version fixiert)
    - Profile: `["postgres"]`
    - Healthcheck mit `pg_isready`
    - Production-optimierte Settings (shared_buffers, max_connections, etc.)
  - **MariaDB 12.1 als optionale Alternative**
    - Image: `mariadb:12.1` (12.1.x-Debian, Minor-Version fixiert)
    - Profile: `["mariadb"]`
    - Healthcheck mit `healthcheck.sh --connect --innodb_initialized`
    - InnoDB-optimierte Settings
  - **MySQL entfernt**
    - Grund: Keine offizielle Alpine-Version verfügbar
    - MySQL hatte nur Debian-Images (gegen Alpine-Konsistenz)
  - **Percona entfernt**
    - Grund: Entwicklung eingestellt
  - **Konfiguration:**
    - `.env`: `DB_TYPE=postgres` oder `DB_TYPE=mariadb`
    - Automatische Profile-Aktivierung via `docker compose --profile ${DB_TYPE}`
    - Makefile erweitert mit DB-spezifischen Commands
  - **Best Practices - Konsistente Minor-Version Pinning:**
    - **Alle Images mit fixen Minor-Versionen** (keine `latest`- oder Major-only-Tags)
    - **PostgreSQL:** `17.7-alpine` (Minor fixiert, erlaubt automatische Patch-Updates 17.7.x)
    - **MariaDB:** `12.1` (Minor fixiert, erlaubt automatische Patch-Updates 12.1.x)
    - **Redis:** `7.4-alpine` (Minor fixiert, erlaubt automatische Patch-Updates 7.4.x)
    - **Vorteil:** Balance zwischen Sicherheit (automatische Patches) und Stabilität (keine Breaking Changes)
    - **Verhindert:** Unerwartete Minor-Updates mit Breaking Changes (z.B. 17.0 → 17.1)
  - **Dateien geändert:**
    - `compose.yaml`: PostgreSQL 17.7-alpine, MariaDB 12.1, Redis 7.4-alpine (alle Minor-fixiert)
    - `compose.prod.yaml`: Production-Optimierungen für beide DBs
    - `compose.override.yaml`: Development-Settings (verbose logging, exposed ports)
    - `.env.example`: `DB_TYPE`, Datenbank-URLs, Port-Konfigurationen
    - `.env`: `DB_TYPE=postgres` als Standard
    - `Makefile`: `up-core` mit Profile-Support, DB-spezifische CLI-Commands
  - **Getestet mit aktuellen Versionen:**
    - PostgreSQL 17.7: Konnektivität, Tabellen-Erstellung, CRUD-Operationen ✅
    - MariaDB 12.1.2: Konnektivität, Tabellen-Erstellung, CRUD-Operationen ✅
    - Redis 7.4.7: PING/PONG, GET/SET Operationen ✅
    - Node.js Backend mit PostgreSQL ✅
    - Alle Services healthy und voll funktionsfähig ✅

- ✅ **Granulare Service-Aktivierung mit ENABLE_* Flags**
  - **Problem:** Bisherige Architektur startete immer alle Services (PHP, Node, Redis)
    - Verschwendung von Ressourcen für ungenutzte Services
    - Keine Flexibilität für unterschiedliche Stack-Typen (Pure PHP, Pure Node.js, Static)
    - Nginx war der einzige wirklich essenzielle Service
  - **Lösung:** Docker Compose Profiles für jeden Service
    - PHP: `profiles: ["php"]`, aktivierbar via `ENABLE_PHP=true`
    - Node: `profiles: ["node"]`, aktivierbar via `ENABLE_NODE=true`
    - Redis: `profiles: ["redis"]`, aktivierbar via `ENABLE_REDIS=true`
    - Nginx: Immer aktiv (Entry Point, ohne Profile)
    - Database: Weiterhin via `DB_TYPE` gesteuert (postgres/mariadb)
  - **Konfiguration in .env:**
    ```bash
    ENABLE_PHP=true      # PHP-FPM Service
    ENABLE_NODE=true     # Node.js (Vite + Backend)
    ENABLE_REDIS=true    # Redis Cache/Sessions
    DB_TYPE=postgres     # Database Selection
    ```
  - **Vordefinierte Presets in .env.example:**
    - **Full-Stack** (Default): PHP + Node.js + Redis + Database
    - **Pure PHP Stack**: PHP + Redis + Database (kein Node.js)
    - **Pure Node.js Stack**: Node.js + Redis + Database (kein PHP)
    - **Static/JAMstack**: Nur Nginx (keine Backend-Services)
    - **Minimal Node.js**: Nur Node.js (kein Database/Redis)
    - **Custom**: Beliebige Kombination
  - **Makefile-Integration:**
    - `make up` liest `.env` und aktiviert nur gewählte Services
    - Dynamischer Profil-Aufbau: `--profile postgres --profile php --profile node --profile redis`
    - Output zeigt aktive Services: `Active services: nginx php node redis`
  - **Zukunftssicherheit:**
    - Einfache Erweiterung für neue Services (z.B. ENABLE_RABBITMQ, ENABLE_ELASTICSEARCH)
    - Skaliert linear: N Services = N Variablen (statt N! Kombinationen)
    - Microservice-Prinzip: Jeder Service einzeln steuerbar
  - **Dateien geändert:**
    - `compose.yaml`: Profiles für php, node, redis hinzugefügt; depends_on auf `required: false`
    - `.env`: `ENABLE_PHP`, `ENABLE_NODE`, `ENABLE_REDIS` hinzugefügt (alle true)
    - `.env.example`: Ausführliche Dokumentation + 5 vordefinierte Presets
    - `Makefile`: `up-core` dynamische Profile-Aktivierung basierend auf ENABLE_* Flags
  - **Getestet:**
    - Full-Stack (PHP + Node + Redis + PostgreSQL): ✅ Alle Services gestartet
    - Node-only (nur Node.js + Nginx): ✅ PHP und Redis nicht gestartet
    - Static/JAMstack (nur Nginx): ✅ Alle Backend-Services deaktiviert
    - Service-Kombinationen funktionieren wie erwartet ✅

- ✅ **NODE_MODE Auto-Start Implementation mit Entrypoint-Script**
  - **Problem:** Node.js Container führte NODE_MODE nicht aus
    - Container startete nur mit `sleep infinity` (development stage)
    - NODE_MODE-Variable (`full-stack`, `vite-only`, `backend-only`, `none`) war in .env dokumentiert, aber nicht implementiert
    - PM2-Config (`ecosystem.config.cjs`) und npm-Scripts existierten, wurden aber nie ausgeführt
    - Vite Dev Server lief nicht → **CORS-Fehler** bei HMR (localhost:5173 nicht erreichbar)
    - Regression des in Version 2.9 behobenen CORS-Problems
  - **Root Cause:**
    - Dockerfile development stage hatte kein ENTRYPOINT, nur `CMD ["sleep", "infinity"]`
    - Keine Logik für Dependency-Installation (`pnpm install`)
    - Keine Logik für automatischen Service-Start basierend auf NODE_MODE
  - **Lösung - Entrypoint Script erstellt:** `docker/node/entrypoint.sh`
    - **Dependency Installation:**
      - Prüft ob `node_modules` existiert oder leer ist
      - Führt `pnpm install --frozen-lockfile` aus wenn nötig
      - Überspringt Installation wenn Dependencies bereits vorhanden (Performance)
    - **Service-Start basierend auf NODE_MODE:**
      - `NODE_MODE=full-stack` → `pnpm run dev:full` (PM2 mit Vite + Express)
      - `NODE_MODE=vite-only` → `pnpm run dev:frontend` (PM2 nur Vite)
      - `NODE_MODE=backend-only` → `pnpm run dev:backend` (PM2 nur Express)
      - `NODE_MODE=none` → `sleep infinity` (Idle Container für manuelle Commands)
    - **Logging:** Debug-Output für Startup-Status
  - **Dockerfile-Änderungen:**
    - Entrypoint-Script kopiert: `COPY --chown=node:node docker/node/entrypoint.sh /usr/local/bin/`
    - Ausführbar gemacht: `RUN chmod +x /usr/local/bin/entrypoint.sh`
    - Als ENTRYPOINT gesetzt: `ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]`
    - Ersetzt bisheriges `CMD ["sleep", "infinity"]`
  - **compose.override.yaml erweitert:**
    - `NODE_MODE=${NODE_MODE:-full-stack}` Environment-Variable hinzugefügt
    - Wird aus .env gelesen und an Container übergeben
  - **PM2 Prozess-Management (via ecosystem.config.cjs):**
    - **vite:** Läuft auf Port 5173 mit `--host 0.0.0.0` für Docker-Zugriff
    - **backend:** Express Server auf Port 3000 via `tsx` (TypeScript-Execution)
    - Beide mit Auto-Restart, Watch-Mode, Graceful Shutdown
    - JSON-Logs für strukturiertes Logging
  - **Dateien geändert:**
    - `docker/node/entrypoint.sh`: Neu erstellt (36 Zeilen)
    - `docker/node/Dockerfile`: ENTRYPOINT hinzugefügt (Zeilen 33-44)
    - `compose.override.yaml`: NODE_MODE env-var hinzugefügt (Zeile 68)
  - **Testing & Verification:**
    - Container-Rebuild: `docker compose build node` ✅
    - Container-Start: `docker compose up -d node` ✅
    - pnpm install: Erfolgreich (Dependencies in 1.2s installiert) ✅
    - PM2 Status: Beide Prozesse online (`vite:0`, `backend:1`) ✅
    - Vite Dev Server: Läuft auf http://localhost:5173 ✅
    - Express Backend: Läuft auf http://localhost:3000/health ✅
    - HMR funktioniert: Vite Client erreichbar (`/@vite/client` liefert JS) ✅
    - CORS korrekt konfiguriert: `origin: '*'` in vite.config.js ✅
    - test.php zeigt HMR-Modus: Script-Tags verweisen auf localhost:5173 ✅
  - **Erwartetes Verhalten bei verschiedenen Modi:**
    ```bash
    # Full-Stack Mode (Default)
    NODE_MODE=full-stack → PM2 startet Vite (5173) + Express (3000)

    # Vite-Only Mode (nur Frontend-Entwicklung)
    NODE_MODE=vite-only → PM2 startet nur Vite (5173)

    # Backend-Only Mode (nur API-Entwicklung)
    NODE_MODE=backend-only → PM2 startet nur Express (3000)

    # Idle Mode (manuelles Exec)
    NODE_MODE=none → Container läuft idle, manuelle Commands via docker compose exec
    ```
  - **Vorteile:**
    - Automatischer Start ohne manuelle Eingriffe
    - Zero-Config HMR für Frontend-Entwicklung
    - Konsistente Entwicklungsumgebung (Dev-Parity)
    - Flexible Modi für unterschiedliche Entwicklungs-Workflows
    - Transparente Logs für Debugging

- ✅ **PHP Infrastruktur-Refactoring: ViteHelper und HealthCheck Klassen**
  - **Problem:** `public/vite-helper.php` lag außerhalb der Codebase-Struktur
    - Keine Nutzung des Composer Autoloaders (PSR-4)
    - Keine Trennung von Public-Dateien und Business-Logik
    - Keine zentrale Service-Status-Prüfung
    - Developer musste Routing/Framework selbst implementieren
  - **Lösung 1: ViteHelper als Infrastructure-Klasse**
    - **Verschoben:** `public/vite-helper.php` → `src/php/Infrastructure/ViteHelper.php`
    - **Namespace:** `App\Infrastructure\ViteHelper`
    - **Autoloading:** Via Composer PSR-4 (`"App\\": "src/php/"`)
    - **Manifest-Path angepasst:** `__DIR__ . '/../../../public/build/.vite/manifest.json'`
    - **Alle Funktionen erhalten:** Development HMR, Production Assets, CORS-Config
  - **Lösung 2: HealthCheck-Klasse für Service-Monitoring**
    - **Neue Klasse:** `src/php/Infrastructure/HealthCheck.php`
    - **Features:**
      - Prüft alle Services: PHP-FPM, Node Backend, Redis, Database (PostgreSQL/MariaDB)
      - Liest ENV-Variablen: `ENABLE_PHP`, `ENABLE_NODE`, `ENABLE_REDIS`, `DB_TYPE`, `NODE_MODE`
      - Gibt Gesamtstatus zurück: `ok`, `degraded`, `error`
      - Zeigt Service-Versionen: PHP 8.4.16, Node v24.12.0, PostgreSQL 17.7, etc.
    - **Service-Checks:**
      - **PHP-FPM:** Immer OK (Code läuft bereits)
      - **Node Backend:** HTTP-Request zu `http://node:3000/health` (JSON-Parsing)
      - **Redis:** Verbindung + PING-Test via PHP Redis Extension
      - **Database:** PDO-Verbindung + Version-Query (postgres/mariadb)
    - **Error Handling:** Bei fehlenden Extensions (Redis, PDO) wird Status als "error" mit Message zurückgegeben
  - **Lösung 3: MVC-Controller für Routing**
    - **Neue Controller:**
      - `src/php/Http/Controller/WelcomeController.php` - Landing Page mit Service-Dashboard
      - `src/php/Http/Controller/StatusController.php` - JSON Health Endpoint
    - **Template:** `templates/welcome.php` - HTML-Template für Dashboard
    - **Routes in `public/index.php`:**
      - `GET /` → WelcomeController (Dashboard)
      - `GET /welcome` → WelcomeController (Alias)
      - `GET /status` → StatusController (JSON Health Check)
      - `GET /api/health` → ExampleController (Legacy Endpoint)
    - **Hybrid-Ansatz:** Router + direkte Dateien
      - `/` via Router (Clean URLs)
      - `/welcome.php` direkt (HMR Demo ohne Router)
  - **Lösung 4: test.php → welcome.php umbenennen**
    - **Verschoben:** `public/test.php` → `public/welcome.php`
    - **Aktualisiert:** Nutzt nun `App\Infrastructure\ViteHelper` via Autoloader
    - **Funktion:** HMR-Demo-Page mit direktem File-Access (ohne Router)
  - **compose.override.yaml erweitert:**
    - PHP-Container erhält alle ENV-Variablen für HealthCheck:
      ```yaml
      environment:
        - ENV=${ENV:-development}
        - ENABLE_PHP=${ENABLE_PHP:-true}
        - ENABLE_NODE=${ENABLE_NODE:-true}
        - ENABLE_REDIS=${ENABLE_REDIS:-true}
        - NODE_MODE=${NODE_MODE:-full-stack}
        - DB_TYPE=${DB_TYPE:-postgres}
        - DB_NAME=${DB_NAME:-app}
        - DB_USER=${DB_USER:-app}
        - DB_PASSWORD=${DB_PASSWORD:-secret}
      ```
  - **Dateien geändert/erstellt:**
    - **Erstellt:** `src/php/Infrastructure/ViteHelper.php` (159 Zeilen)
    - **Erstellt:** `src/php/Infrastructure/HealthCheck.php` (310 Zeilen)
    - **Erstellt:** `src/php/Http/Controller/WelcomeController.php` (28 Zeilen)
    - **Erstellt:** `src/php/Http/Controller/StatusController.php` (24 Zeilen)
    - **Erstellt:** `templates/welcome.php` (136 Zeilen)
    - **Geändert:** `public/index.php` (Routes erweitert)
    - **Verschoben:** `public/test.php` → `public/welcome.php` (aktualisiert)
    - **Gelöscht:** `public/vite-helper.php` (alte Version)
    - **Geändert:** `compose.override.yaml` (PHP ENV-Variablen hinzugefügt, Zeilen 51-60)
  - **Testing & Verification:**
    - Composer Autoloader regeneriert: `composer dump-autoload -o` ✅
    - PHP-Container neu erstellt: `docker compose up -d php` ✅
    - ENV-Variablen korrekt geladen: `printenv | grep ENABLE` ✅
    - **Endpoint-Tests:**
      - `GET /` - Dashboard mit Service-Tabelle ✅
      - `GET /welcome` - Alias für / ✅
      - `GET /status` - JSON mit allen Services ✅
      - `GET /api/health` - Legacy JSON ✅
      - `GET /welcome.php` - HMR Demo direkt ✅
    - **Service Status (Vor Extension-Installation):**
      - PHP-FPM: ✅ OK (v8.4.16)
      - Node Backend: ✅ OK (v24.12.0, uptime 6800s, mode: full-stack)
      - Redis: ⚠️ Error (Extension nicht installiert)
      - Database: ⚠️ Error (PDO Extension nicht installiert)
    - **Overall Status:** `degraded` (wegen fehlender Extensions)
    - **Hinweis:** Extensions wurden später hinzugefügt (siehe nächster Changelog-Eintrag)
  - **Vorteile:**
    - **Framework-Agnostic:** Dev kann später Laravel, Symfony, Slim, etc. nutzen
    - **Clean Architecture:** Business-Logik in `src/`, Public-Dateien in `public/`
    - **PSR-4 Autoloading:** Kein manuelles `require_once` mehr
    - **Testbar:** Klassen können via PHPUnit getestet werden
    - **Monitoring:** Zentraler HealthCheck für Docker Healthchecks und Uptime-Monitoring
    - **Transparency:** Dashboard zeigt alle aktiven Features und Service-Status
    - **Hybrid Routing:** Dev kann Router nutzen oder direkte Dateien (maximale Flexibilität)

- ✅ **PHP Extensions installiert: Redis, PDO PostgreSQL, PDO MySQL**
  - **Problem:** HealthCheck zeigte "degraded" Status
    - Redis Extension fehlte → HealthCheck-Fehler: "Redis PHP extension not installed"
    - PDO PostgreSQL Extension fehlte → HealthCheck-Fehler: "PDO PostgreSQL extension not installed"
    - health.php war kaputt → Leere weiße Seite (Bug: prüfte REQUEST_URI === '/health' statt '/health.php')
    - User-Frustration: "Warum ist Status degraded wenn Services laufen?"
  - **User-Feedback:**
    - "Sollten wir REDIS und PDO nicht, wie z.B. auch GraphicsMagick, bereits vorinstallieren?"
    - "Damit sofort alles lauffähig ist und ein Dev nicht erst herausfinden muss, wie man die Extension installiert"
  - **Entscheidung:** Extensions vorinstallieren (wie GraphicsMagick)
    - **Philosophie:** Boilerplate sollte "out of the box" funktionieren
    - Dev kann später Extensions entfernen (einfach), aber hinzufügen ist frustrierend
    - Wenn Services (Redis, PostgreSQL) verfügbar sind, sollten Extensions auch da sein
  - **Lösung 1: Redis Extension via PECL**
    - Im `php-builder` Stage: `pecl install redis`
    - Extension aktiviert: `docker-php-ext-enable redis`
    - Keine zusätzlichen System-Dependencies nötig
  - **Lösung 2: PDO PostgreSQL Extension**
    - Build-Dependencies: `postgresql-dev` (Compiler-Headers)
    - Runtime-Dependencies: `postgresql-libs` (Shared Libraries)
    - Installation: `docker-php-ext-install pdo_pgsql`
  - **Lösung 3: PDO MySQL Extension**
    - Keine zusätzlichen Dependencies (Built-in in PHP)
    - Installation: `docker-php-ext-install pdo_mysql`
  - **Lösung 4: health.php repariert**
    - **Problem:** `if ($_SERVER['REQUEST_URI'] === '/health')` prüfte falsche URI
    - **Aufruf war:** `http://localhost:8080/health.php`
    - **Geprüft wurde:** `/health` (nie true → leere Seite)
    - **Fix:** Bedingung entfernt, direktes JSON-Output
    - **Zweck:** Minimal-Simple Health Check für Docker HEALTHCHECK
    - **Format:** `{"status":"ok","service":"php-fpm","timestamp":"2025-12-24T13:55:35+01:00"}`
  - **Dateien geändert:**
    - `docker/php/Dockerfile` (Zeilen 36-40, 48-49, 68):
      - `postgresql-dev postgresql-libs` hinzugefügt
      - `pdo_pgsql pdo_mysql` in docker-php-ext-install
      - `pecl install redis && docker-php-ext-enable redis`
    - `public/health.php` (Zeilen 1-19): URI-Check entfernt, direktes JSON-Output
  - **Testing & Verification:**
    - PHP Container neu gebaut: `docker compose build php` ✅
    - Extensions geladen: `php -m | grep -E "redis|pdo_pgsql|pdo_mysql"` ✅
    - **Service Status:**
      - PHP-FPM: ✅ OK (v8.4.16)
      - Node Backend: ✅ OK (v24.12.0, full-stack mode)
      - Redis: ✅ OK (v7.4.7) - **JETZT GRÜN!**
      - PostgreSQL: ✅ OK (PostgreSQL 17.7) - **JETZT GRÜN!**
    - **Overall Status:** `"ok"` (vorher "degraded") ✅
    - Dashboard zeigt grünes "OK" ✅
    - `/health.php` gibt JSON zurück ✅
    - `/status` gibt vollständigen Service-Status ✅
  - **Vorteile:**
    - **Zero-Config:** Alle Services sofort nutzbar ohne Extension-Installation
    - **Better DX:** Developer muss nicht nach Dockerfile-Anleitung suchen
    - **Consistency:** Wenn Service verfügbar ist, ist Extension auch da
    - **Production-Ready:** Image kann direkt deployed werden
  - **Image-Size Impact:** ~3 MB (Redis ~1 MB, PDO PostgreSQL ~2 MB - vernachlässigbar)

- ✅ **Redundante welcome.php entfernt und Endpoint-Dokumentation verbessert**
  - **Problem:** Verwirrende Redundanz bei Endpoints
    - `GET /` (Router) → Dashboard ✅
    - `GET /welcome` (Router) → Selbes Dashboard ✅
    - `GET /welcome.php` (direkte Datei) → Ähnlicher Inhalt ❌ **REDUNDANT**
    - User-Verwirrung: "Welchen Endpoint soll ich nutzen?"
    - health.php als "Legacy" bezeichnet, obwohl perfekt für Docker HEALTHCHECK
  - **User-Feedback:**
    - "welcome.php scheint mir jetzt ziemlich redundant zu sein"
    - "health.php ist auf der Startseite als Legacy beschrieben, ggf. Hinweis, das für Docker HEALTHCHECK genutzt"
  - **Lösung 1: welcome.php gelöscht**
    - Redundanz eliminiert
    - Nur noch Clean URLs via Router: `/` und `/welcome`
    - `health.php` bleibt als Beispiel für "direkte PHP-Datei ohne Router"
  - **Lösung 2: Endpoint-Dokumentation im Dashboard verbessert**
    - **Vorher (verwirrend):**
      - `GET /health.php` - Legacy PHP health check (direct file)
      - `GET /welcome.php` - HMR Demo Page (direct file)
    - **Nachher (klar):**
      - `GET /` - Service dashboard with live status (via Router)
      - `GET /welcome` - Alias for / (via Router)
      - `GET /status` - Detailed JSON health check (all services)
      - `GET /api/health` - Simple JSON health (PHP-FPM only)
      - `GET /health.php` - Minimal health check for Docker HEALTHCHECK
    - **Tip hinzugefügt:**
      - "Use `/health.php` for Docker HEALTHCHECK (minimal overhead)"
      - "Use `/status` for monitoring dashboards (detailed service info)"
  - **Dateien geändert:**
    - **Gelöscht:** `public/welcome.php` (redundant)
    - **Geändert:** `templates/welcome.php` (Zeilen 79-95): Endpoint-Liste neu strukturiert, Tip hinzugefügt
  - **Testing & Verification:**
    - `GET /` → Dashboard ✅
    - `GET /welcome` → Dashboard (Alias) ✅
    - `GET /welcome.php` → 404 Not Found ✅ (wie erwartet)
    - `GET /health.php` → Minimal JSON ✅
    - `GET /status` → Detailed JSON ✅
  - **Vorteile:**
    - **Clarity:** Jeder Endpoint hat klaren Zweck, keine Redundanz
    - **Best Practices:** Dokumentation zeigt wann welcher Endpoint genutzt werden sollte
    - **Developer Experience:** Keine Verwirrung mehr über "welchen Endpoint nutze ich?"
    - **Clean:** Weniger Dateien = weniger Maintenance

- ✅ **TypeScript Fehler in src/node/server.ts behoben**
  - **Problem:** Implizite `any`-Types und Import-Probleme
    - `pino-http` CommonJS/ESM Interop-Fehler: `TS2349: This expression is not callable`
    - Implizite `any` in Callback-Parametern (customLogLevel, customSuccessMessage, etc.)
    - `server: any` ohne korrekte Typisierung
    - Unused default export
  - **Lösung:**
    - **pino-http Import-Fix:** `import pinoHttpImport from 'pino-http'` + Workaround
      ```typescript
      const pinoHttp = pinoHttpImport as unknown as typeof pinoHttpImport.default;
      ```
    - **Explizite Types für Callbacks:**
      - `customLogLevel: (_req: Request, res: Response, err?: Error) => {...}`
      - `customSuccessMessage: (req: Request, res: Response) => {...}`
      - `customErrorMessage: (_req: Request, _res: Response, err: Error) => {...}`
    - **Server Type:** `const server: Server = createServer(app);` (statt `any`)
    - **Label Type:** `level: (label: string) => {...}` in Pino formatter
    - **Logger-Referenz:** `req.log.error` → `logger.error` im Error Handler
    - **Unused Export entfernt:** `export default app` gelöscht
  - **Dateien geändert:**
    - `src/node/server.ts` (Zeilen 11-17, 34, 42-55, 138, 125)
  - **Verification:**
    - TypeScript Compilation: ✅ Keine Fehler (`pnpm run type-check`)
    - Server läuft: ✅ API antwortet korrekt auf `/api/node/health`
  - **Hinweis:** IDE-Diagnostics (TS2307, TS2580) sind normal - node_modules nur im Container

- ✅ **SCSS/SASS Support implementiert**
  - **Dependency hinzugefügt:**
    - `sass@^1.97.1` in `devDependencies`
    - Installiert via `pnpm add -D sass`
  - **Vite Config:** Bereits vorbereitet mit `preprocessorOptions.scss` (Zeile 92-96)
  - **Test-SCSS erstellt:** `resources/css/test.scss`
    - **Moderne SASS-Modules:**
      - `@use 'sass:math'` für `math.div()` (statt deprecated `/`)
      - `@use 'sass:color'` für `color.adjust()` (statt deprecated `darken()`, `lighten()`)
    - **Features demonstriert:**
      - Variablen: `$primary-color`, `$secondary-color`, `$spacing`, etc.
      - Nesting: `.scss-test__header`, `.scss-test__content`, `.scss-test__footer`
      - Mixins: `@mixin flex-center`, `@mixin card-shadow($opacity)`
      - Color-Funktionen: `color.adjust($primary-color, $lightness: -10%)`
      - Math-Funktionen: `math.div($spacing, 2)`
      - Media Queries: `@media (max-width: 768px)`
  - **Integration:** `resources/js/app.js` importiert `import '../css/test.scss'`
  - **Build-Output:**
    - Kompiliertes CSS: `public/build/assets/app-ByQwGoR5.css` (3.06 kB)
    - Keine Deprecation-Warnings ✅
    - Vite Manifest: CSS korrekt verlinkt
  - **Dateien geändert:**
    - `package.json`: `sass@^1.97.1` hinzugefügt
    - `resources/css/test.scss`: Neue Test-Datei mit SCSS-Features
    - `resources/js/app.js`: SCSS-Import hinzugefügt (Zeile 12)
  - **Getestet:**
    - Build: ✅ Erfolgreich ohne Warnings (`pnpm run build`)
    - Dev-Server: ✅ HMR funktioniert mit SCSS
    - CSS-Output: ✅ Alle SCSS-Features korrekt kompiliert

- ✅ **Node.js Environment Support erweitert**
  - **Jetzt unterstützt:**
    - CSS (native)
    - PostCSS mit Autoprefixer
    - **SCSS/SASS** mit allen modernen Features (neu!)
  - **Vite HMR:** Hot Module Replacement für alle CSS/SCSS-Dateien

- ✅ **Dokumentation und UX-Verbesserungen (5 Punkte vor Commit)**
  - **1. Quick Start Optimierung in Welcome-Dashboard**
    - **Problem:** Quick Start zeigte nur generische make-Commands ohne Kontext
      - Kein Unterschied zwischen "erstem Setup" und "täglicher Entwicklung"
      - User musste selbst herausfinden welche Commands wann relevant sind
      - Verwirrung für neue Developer: "Was muss ich als erstes tun?"
      - **Inkonsistenz:** Zeigte `cp .env.example .env` statt `make init`
    - **Lösung:** Quick Start in zwei Abschnitte unterteilt mit konsistenten Commands
      - **🚀 Initial Setup (First Time):**
        ```bash
        make init    # Initialize project (copy .env.example to .env)
        # Edit .env: Set ENV=development
        make setup   # Create project structure (directories, dependencies)
        make fresh   # Build and start all services
        ```
      - **💻 Daily Development:**
        ```bash
        make up      # Start services
        make down    # Stop services
        make build   # Rebuild images
        ```
    - **Dateien geändert:**
      - `templates/welcome.php` (Zeilen 100-114): Quick Start neu strukturiert mit 4-Schritt-Flow
      - `.env.example` (Zeilen 7-11): Quick Start Header aktualisiert
      - `.env` (Zeilen 7-11): Quick Start Header aktualisiert
    - **Vorteile:**
      - Klare Trennung: Einmaliges Setup vs tägliche Nutzung
      - Konsistente make-Commands (keine direkten bash-Befehle)
      - Neue Developer wissen sofort was zu tun ist
      - Reduziert Support-Anfragen und Onboarding-Zeit

  - **2. make commands Konsistenz (statt docker exec)**
    - **Problem:** Dokumentation zeigte inkonsistente Commands
      - README.md: Mix aus `make` und `docker compose exec`
      - entrypoint.sh: Nur `docker compose exec` in Hilfe-Texten
      - User musste beide Syntaxen kennen
      - Verwirrung: "Welche Methode soll ich nutzen?"
    - **Lösung:** Überall make commands als primäre Methode
      - **README.md aktualisiert:** `make php-exec CMD="php -m | grep xdebug"`
        - Mit Fallback: `# Or: docker compose exec php php -m | grep xdebug`
      - **entrypoint.sh aktualisiert:** Hilfe-Text zeigt make commands
        ```bash
        echo "[entrypoint]   - Via make: 'make node-exec CMD=\"pnpm run <command>\"'"
        echo "[entrypoint]   - Direct:   'docker compose exec node pnpm run <command>'"
        ```
    - **Dateien geändert:**
      - `README.md` (Zeilen 399-407): make commands als Primär-Methode
      - `docker/node/entrypoint.sh` (Zeilen 37-38): make-Command-Hinweise
    - **Vorteile:**
      - Konsistente Developer Experience
      - make abstrahiert Docker-Komplexität
      - Einfacher für Anfänger
      - Weniger kognitive Last (nur eine Methode merken)

  - **3. Emoji-Symbole in .env Dateien korrigiert**
    - **Problem:** PhpStorm zeigte Emojis falsch an
      - Nummerierung mit 1️⃣ 2️⃣ 3️⃣ (Emoji Keycap Digits)
      - PhpStorm-Rendering: Falsche Darstellung oder Boxen
      - User-Feedback: "bessere Symbole bei Nummerierung in .env.example nutzen"
    - **Lösung:** ASCII-Formatierung mit Brackets
      - `1️⃣` → `[1]`
      - `2️⃣` → `[2]`
      - `3️⃣` → `[3]`
      - etc.
    - **Dateien geändert:**
      - `.env.example` (Zeilen 44-68): Alle Preset-Nummerierungen
      - `.env` (Zeilen 44-68): Alle Preset-Nummerierungen
    - **Vorteile:**
      - Universelle Kompatibilität (alle IDEs und Editoren)
      - Bessere Lesbarkeit in PhpStorm
      - ASCII-only (keine Unicode-Probleme)

  - **4. Redis Session Handler Konfiguration hinzugefügt**
    - **Problem:** Redis für Sessions nicht dokumentiert
      - User fragte: "Ist Redis ready2go oder erfordert es weitere Anpassungen?"
      - Unklar ob zwischen Redis und file-based Sessions gewechselt werden kann
      - Keine Anleitung wie Redis-Sessions aktiviert werden
      - **Falsche Platzierung:** development.ini würde nur in Development ENV geladen
    - **Lösung:** Dokumentierte Konfiguration in **php.ini** (Base-Config für alle Environments)
      - **Default:** File-based Sessions (kein Code-Change nötig)
      - **Optional:** Redis Sessions (auskommentiert mit Anleitung)
      - **Warnung hinzugefügt:** Redis erfordert Code-Anpassungen:
        - Session-Daten müssen serializable sein
        - Kein File-Locking (Redis Transactions nutzen)
        - Memory-Policy in redis.conf setzen (maxmemory)
      - **Beispiele für Production und Development:**
        ```ini
        ; Production (mit Auth):
        ; session.save_path = "tcp://redis:6379?auth=your_redis_password&timeout=2.5&database=0"

        ; Development (ohne Auth):
        ; session.save_path = "tcp://redis:6379?timeout=2.5&database=0"
        ```
    - **Dateien geändert:**
      - `docker/php/php.ini` (Zeilen 28-44): Redis Session-Handler Dokumentation hinzugefügt
      - `docker/php/conf.d/development.ini`: Redis-Config entfernt (war falsche Stelle)
    - **Vorteile:**
      - **Richtige Platzierung:** php.ini gilt für alle Environments (development + production)
      - Transparenz: User weiß was Redis erfordert
      - Quick-Switch: Zeilen auskommentieren für Redis-Sessions
      - Best Practices: Production mit Auth, Development ohne
      - Warnung verhindert Frustration bei Session-Problemen

  - **5. Alpine-Versionen in Compose-Dateien fixiert**
    - **Problem:** Inkonsistente Versionierung bei Docker Images
      - `redis:7.4-alpine` - Alpine-Version nicht fixiert (könnte 3.19, 3.20, 3.21, 3.22 sein)
      - `postgres:17.7-alpine` - Alpine-Version nicht fixiert
      - Dockerfiles nutzten `alpine:3.22` (explizit)
      - Inkonsistenz: Build-Images mit 3.22, Runtime-Images mit variablem Alpine
      - Potenzial für Breaking Changes bei Alpine-Updates
    - **Lösung:** Explizite Alpine 3.22 Versionen **direkt in compose.yaml**
      - **Redis:** `redis:7.4-alpine` → `redis:7.4-alpine3.22`
      - **PostgreSQL:** `postgres:17.7-alpine` → `postgres:17.7-alpine3.22`
      - **MariaDB:** Keine Änderung (nutzt Debian/Ubuntu, nicht Alpine)
      - **Keine .env Variablen:** Versionen bleiben hardcoded in compose.yaml
        - Grund: Keine Wiederverwendung (jeder Image-String kommt nur 1x vor)
        - Dockerfiles nutzen ARG (DRY: `alpine:${ALPINE_VERSION}` mehrfach verwendet)
        - compose.yaml: Fixe Versionen (bessere Lesbarkeit, Renovate-Kompatibilität)
    - **Dateien geändert:**
      - `compose.yaml` (Zeile 64): redis Image auf `redis:7.4-alpine3.22`
      - `compose.yaml` (Zeile 88): postgres Image auf `postgres:17.7-alpine3.22`
    - **Vorteile:**
      - **Konsistenz:** Alle Services nutzen Alpine 3.22
      - **Vorhersagbarkeit:** Kein unerwartetes Alpine-Update von 3.22 → 3.23
      - **Reproduzierbarkeit:** Gleiche Builds in 6 Monaten
      - **Lesbarkeit:** `redis:7.4-alpine3.22` klarer als `redis:${REDIS_VERSION}-alpine${ALPINE_VERSION}`
      - **Renovate-Kompatibilität:** Dependency-Scanner können fixe Versionen direkt erkennen
      - **Best Practice:** ENV-Variablen für Konfiguration, nicht für Versionen

  - **6. Makefile Konsistenz und Formatierung verbessert**
    - **Problem:** Fehlende und inkonsistente Commands
      - **Inkonsistente Dependency-Installation:** PHP nutzte `dev-deps`, Node nutzte `node-install`
      - **Fehlende logs-* Commands:** logs-redis, logs-postgres, logs-mariadb existierten nicht
      - **Fehlende shell-* Commands:** shell-node, shell-redis, shell-postgres, shell-mariadb fehlten
      - **Inkonsistente Benennung:** `node-shell` statt `shell-node` (nicht konsistent mit shell-nginx, shell-php)
      - **Formatierung:** Command-Beschreibungen mit ungleichem Abstand (15 Zeichen zu kurz für längste Commands)
      - **Database-Emoji:** Falsches Symbol mit extra Leerzeichen in check-health
      - **Überflüssige Commands:** MySQL-Commands (mysql-cli, mysql-dump, mysql-restore) obwohl MySQL-Service entfernt wurde
    - **Lösung 1: MySQL Commands entfernt**
      - `mysql-cli`, `mysql-dump`, `mysql-restore` gelöscht
      - Grund: MySQL Service existiert nicht mehr (nur PostgreSQL und MariaDB)
      - MariaDB Commands beibehalten (Service existiert in compose.yaml)
    - **Lösung 2: Fehlende logs-* Commands hinzugefügt**
      - `logs-redis` (Zeile 199): Show Redis logs only
      - `logs-postgres` (Zeile 210): Show PostgreSQL logs only
      - `logs-mariadb` (Zeile 221): Show MariaDB logs only
      - Konsistente Implementierung wie logs-nginx, logs-php, logs-node
      - Unterstützt automatisch production/development ENV-Detection
    - **Lösung 3: Composer Commands mit Node.js konsistent benannt**
      - **Problem:** PHP nutzte `dev-deps` / `dev-deps-local`, Node nutzte `node-install` / `node-install-local`
      - **Umbenennung:**
        - `dev-deps` → `composer-install` (Zeile 32)
        - `dev-deps-local` → `composer-install-local` (Zeile 41)
      - **Alle Referenzen aktualisiert:**
        - `setup` Target: Ruft jetzt `composer-install` auf (Zeile 104)
        - Fehlermeldungen: Zeigen jetzt `make composer-install` (Zeilen 44, 52)
      - **Konsistentes Naming:** `<package-manager>-install` / `<package-manager>-install-local`
        - Composer: `composer-install` / `composer-install-local`
        - Node.js: `node-install` / `node-install-local`
    - **Lösung 4: Shell Commands konsistent gemacht**
      - **Neue Commands:**
        - `shell-node` (Zeile 247): Open shell in Node container
        - `shell-redis` (Zeile 250): Open shell in Redis container
        - `shell-postgres` (Zeile 253): Open shell in PostgreSQL container
        - `shell-mariadb` (Zeile 256): Open shell in MariaDB container
      - **Gelöscht:** `node-shell` (Zeile 289) → ersetzt durch `shell-node`
      - **Konsistentes Pattern:** Alle shell-* Commands in Docker-Sektion gruppiert
    - **Lösung 5: Formatierung verbessert**
      - Command-Breite von `%-15s` auf `%-20s` erhöht (Zeile 28)
      - Grund: Längste Commands sind 18 Zeichen (`node-install-local`, `node-app-server-up`)
      - Alle Beschreibungen jetzt perfekt ausgerichtet bei `make help`
    - **Lösung 6: Database-Emoji korrigiert**
      - Altes Symbol: `🗄️` (File Cabinet) mit extra Leerzeichen und falscher Breite
      - Neues Symbol: `💾` (Floppy Disk - klassisches Datenspeicher-Symbol)
      - Konsistente Breite und Abstand zu anderen Symbolen (📦 PHP-FPM, 🔴 Redis, 🌐 Nginx)
    - **Dateien geändert:**
      - `Makefile` (Zeile 28): help-Formatierung %-20s
      - `Makefile` (Zeilen 32, 41): dev-deps → composer-install, dev-deps-local → composer-install-local
      - `Makefile` (Zeilen 44, 52, 104): Alle dev-deps Referenzen auf composer-install aktualisiert
      - `Makefile` (Zeilen 199-230): logs-redis, logs-postgres, logs-mariadb hinzugefügt
      - `Makefile` (Zeilen 247-257): shell-node, shell-redis, shell-postgres, shell-mariadb hinzugefügt
      - `Makefile` (Zeile 365-381): mysql-cli, mysql-dump, mysql-restore entfernt
      - `Makefile` (Zeile 289): node-shell entfernt
      - `Makefile` (Zeile 436): Database-Emoji auf 💾 geändert
    - **Testing & Verification:**
      - `make help`: Alle Commands perfekt formatiert, composer-install und composer-install-local sichtbar ✅
      - `make composer-install`: Installiert Composer Dependencies im Container ✅
      - `make logs-redis`: Zeigt Redis-Logs ✅
      - `make shell-postgres`: Öffnet PostgreSQL-Shell ✅
      - `make check-health`: Database-Emoji 💾 konsistente Breite ✅
    - **Vorteile:**
      - **Vollständigkeit:** Alle Services haben logs-* und shell-* Commands
      - **Konsistenz:**
        - Einheitliches Naming-Schema (shell-*, logs-*, *-install)
        - Composer und Node.js nutzen gleiches Pattern: `<tool>-install` / `<tool>-install-local`
      - **Lesbarkeit:** Perfekt formatierte Help-Ausgabe
      - **Klarheit:** Keine überflüssigen Commands für nicht-existierende Services
      - **UX:** Developer findet jeden Command intuitiv ohne Dokumentation zu lesen

  - **7. compose.override.yaml ENV Variable auf development hardcoded**
    - **Problem:** Unnötige Fallback-Logik in Development-only File
      - `ENV=${ENV:-development}` in compose.override.yaml (Zeile 51)
      - compose.override.yaml wird NUR in Development verwendet (nie in Production)
      - Production nutzt: `docker compose -f compose.yaml -f compose.prod.yaml` (ohne override)
      - Inkonsistenz: NODE_ENV war bereits hardcoded (`NODE_ENV=development`), aber ENV hatte Fallback
    - **Lösung:** ENV auf development hardcoded (analog zu NODE_ENV)
      - `ENV=${ENV:-development}` → `ENV=development`
      - Konsistent mit `NODE_ENV=development` (Zeile 75)
    - **Begründung:**
      - compose.override.yaml ist Development-spezifisch (per Docker Compose Convention)
      - Fallback-Logik `${ENV:-development}` macht nur in Base-Files Sinn (compose.yaml)
      - Hardcoded values in Override-Files sind Best Practice
    - **Dateien geändert:**
      - `compose.override.yaml` (Zeile 51): ENV=development (hardcoded)
    - **Vorteile:**
      - **Klarheit:** Keine Verwirrung ob ENV dynamisch oder fix ist
      - **Konsistenz:** Beide ENV-Variablen (ENV, NODE_ENV) jetzt hardcoded
      - **Best Practice:** Override-Files sollten explizite Werte haben, keine Fallbacks
      - **Einfachheit:** Weniger Variablen-Substituierung = schnelleres Startup

### Version 2.9 (2025-12-19)
- ✅ **Vite HMR (Hot Module Replacement) CORS-Probleme behoben**
  - **Problem:** Browser blockierte Vite Dev Server mit CORS-Fehlern
    - `Cross-Origin Request blocked: CORS request failed`
    - `Module source URI is not allowed in this document`
    - Grund: Browser versuchte von `localhost:8080` (NGINX) auf `localhost:5173` (Vite) zuzugreifen
  - **Lösung 1: Explizite CORS-Konfiguration in Vite**
    - `vite.config.js:58-61`: CORS aktiviert mit `origin: '*'` und `credentials: true`
    - `vite.config.js:71`: `strictPort: true` hinzugefügt für stabilen Port
  - **Lösung 2: Dynamic base path für Development vs Production**
    - `vite.config.js:10`: `base: process.env.NODE_ENV === 'production' ? '/build/' : '/'`
    - Development: Root-Path `/` für direkte Vite-Server Zugriffe
    - Production: `/build/` für statische Assets
  - **Dateien geändert:**
    - `vite.config.js` (Zeilen 10, 58-61, 71)
    - `public/vite-helper.php` (Zeile 22: `viteDevServerUrl = 'http://localhost:5173'`)
  - **Ergebnis:** Vite HMR lädt jetzt korrekt mit CORS-Headern

- ✅ **ViteHelper.php Entry Points korrigiert**
  - **Problem:** Entry Points stimmten nicht mit Vite-Root überein
    - ViteHelper verwendete `resources/js/app.js`
    - Vite Config hat `root: 'resources'`, daher sollte es `js/app.js` sein
  - **Lösung:** Entry Points in allen Methoden angepasst
    - `public/vite-helper.php:98`: `renderScriptTags()` default: `'js/app.js'`
    - `public/vite-helper.php:126`: `renderCssTags()` default: `'js/app.js'`
    - Kommentare in `getAssetUrl()` und `getCssUrl()` aktualisiert
  - **Dateien geändert:**
    - `public/vite-helper.php` (Zeilen 64-65, 86, 98, 126)
  - **Ergebnis:** Assets werden jetzt korrekt geladen

- ✅ **node_modules Permission-Problem in Development Mode behoben**
  - **Problem:** `make node-install` schlug fehl mit `EACCES: permission denied, mkdir '/app/node_modules/.pnpm'`
    - Ursache: Docker Volume `node_modules` wurde mit `root:root` erstellt
    - Node User (UID 1000) hatte keine Schreibrechte
  - **Lösung:** Permissions-Fix vor pnpm install
    - `Makefile:246-247`: `chown -R node:node /app/node_modules` als root vor pnpm install
    - Ausgabe: "Fixing node_modules permissions..." für Transparenz
  - **Dateien geändert:**
    - `Makefile` (Zeilen 246-247)
  - **Ergebnis:** Dependencies installieren jetzt erfolgreich in Development Mode

- ✅ **Vollständige Test-Matrix: Alle 6 Szenarien erfolgreich**
  - **ENV=production:**
    - ✅ Test 1: PHP-only Mode (nginx + php)
    - ✅ Test 2: Asset-Server Mode (nginx + php + node:asset-server mit Build-Artefakten)
    - ✅ Test 3: App-Server Mode (nginx + php + node:app-server mit Backend auf Port 3000)
  - **ENV=development:**
    - ✅ Test 4: PHP-only Mode (nginx + php)
    - ✅ Test 5: Asset-Server Mode mit HMR (nginx + php + node + Vite Dev Server auf Port 5173)
    - ✅ Test 6: App-Server Mode (nginx + php + node + Backend in watch mode)
  - **Test-Befehle für Development:**
    ```bash
    # Test 5: ENV=development, asset-server + HMR
    make fresh
    make node-up
    make node-install  # Permissions werden automatisch korrigiert
    make node-dev      # Vite läuft auf Port 5173
    # Zugriff: http://localhost:8080/test.php

    # Test 6: ENV=development, app-server
    make fresh
    make node-app-server-up
    make node-install
    make node-server-dev  # Backend läuft auf Port 3000
    curl http://localhost:3000/health  # ✅ {"status":"ok"}
    ```
  - **Dynamische ENV-Erkennung funktioniert:**
    - `public/test.php` zeigt automatisch Development (🔧 Vite HMR) oder Production (🚀 Built Assets)
    - `public/vite-helper.php` lädt korrekt basierend auf `$_ENV['ENV']`

- 🔧 **Bekannte Einschränkungen:**
  - Vite HMR WebSocket muss von Browser zu `localhost:5173` direkt verbinden können
  - In Docker-Netzwerk-Setups ohne Port-Forwarding muss `hmr.host` angepasst werden
  - Production Mode erfordert `make build` vor `make up` (Build-Artefakte werden in Image kopiert)

### Version 2.8 (2025-12-19)
- ✅ **Container Logging auf 12-Factor App Best Practices umgestellt**
  - **Problem:** Production Mode schlug fehl
    - Nginx crashte mit "Permission denied" auf `/var/log/nginx/error.log`
    - Ursache: Read-only Filesystem in Production (`compose.prod.yaml:8`) verhinderte Schreibzugriff auf Log-Dateien
    - Anti-Pattern: Bind-Mounts für Logs (`./logs:/var/log/*`) skalieren nicht und funktionieren nicht mit read-only FS
  - **Lösung:** Umstellung auf stdout/stderr Logging (Industry Standard)
    1. **Nginx Logs:** `docker/nginx/nginx.conf`
       - `error_log stderr warn;` (Zeile 2)
       - `access_log /dev/stdout main;` (Zeile 18)
    2. **PHP Logs:**
       - `docker/php/php.ini`: `error_log = /proc/self/fd/2` (Zeile 41)
       - `docker/php/php-fpm.conf`: Alle Logs → `/proc/self/fd/2` (Zeilen 18-20)
    3. **Compose Cleanup:**
       - `compose.yaml`: Nginx Log Bind-Mount entfernt (Zeile 15)
       - `compose.override.yaml`: PHP Log Bind-Mounts entfernt (Zeilen 42-43)
       - `compose.prod.yaml`: tmpfs für `/var/log/app` und `/var/log/php` entfernt (Zeilen 40-41)
    4. **Makefile Cleanup:**
       - `LOG_DIR` Referenzen in `make setup` und `make up-core` entfernt
  - **Vorteile:**
    - ✅ Production Mode funktioniert jetzt (read-only FS kompatibel)
    - ✅ Einheitliches Logging zwischen Dev und Production
    - ✅ Logs via `make logs-nginx` / `make logs-php` / `docker compose logs` verfügbar
    - ✅ Automatische Log-Rotation via Docker JSON-File Driver (50MB/File, 5 Files)
    - ✅ Kompatibel mit Log-Aggregation (ELK, Loki, CloudWatch, etc.)
    - ✅ Skaliert auf Kubernetes/Swarm (keine Filesystem-Abhängigkeit)
  - **Dateien geändert:**
    - `docker/nginx/nginx.conf` (Zeilen 2, 18)
    - `docker/php/php.ini` (Zeile 41)
    - `docker/php/php-fpm.conf` (Zeilen 18-20)
    - `compose.yaml` (Zeile 15 entfernt)
    - `compose.override.yaml` (Zeilen 42-43 entfernt)
    - `compose.prod.yaml` (Zeilen 40-41 entfernt)
    - `Makefile` (LOG_DIR Referenzen entfernt)
  - **Verifikation:**
    ```bash
    # In .env: ENV=production
    make down && make build && make up
    docker compose ps               # nginx: healthy, php: healthy ✅
    curl http://localhost:8080      # ✅ Funktioniert
    make logs-nginx                 # ✅ Zeigt Access-Logs
    make logs-php                   # ✅ Zeigt PHP-FPM Logs
    ```

### Version 2.7 (2025-12-19)
- ✅ **Nginx Health-Check Fix**
  - **Problem:** Nginx Health-Check schlug fehl mit "Connection refused"
  - **Ursache:** `wget --spider http://localhost:8080/health` versuchte IPv6 (`[::1]`), aber nginx hört nur auf IPv4
  - **Lösung:** Health-Check URL von `localhost` → `127.0.0.1` geändert
  - **Datei:** `docker/nginx/Dockerfile` Zeile 30
  - **Ergebnis:** Health-Check funktioniert jetzt zuverlässig

- ✅ **Nginx Dynamic DNS Resolution für Node Container**
  - **Problem:** "Chicken-Egg Problem" - nginx startete nicht ohne node-Container
    - Fehler: `host not found in upstream "node" in /etc/nginx/conf.d/default.conf:58`
    - Grund: nginx löste DNS-Namen beim Start auf, scheiterte wenn node nicht existierte
    - Konflikt: `make up` (nur nginx+php) vs `make node-up` (alle Services)
  - **Anforderung:** nginx muss auch ohne node-Container starten und healthy sein
  - **Lösung:** Dynamic DNS Resolution mit Docker's internem DNS
    1. **Resolver hinzugefügt:** `resolver 127.0.0.11 valid=30s ipv6=off;`
       - `127.0.0.11` = Docker's interner DNS-Server
       - `valid=30s` = DNS-Cache-TTL (30 Sekunden)
       - `ipv6=off` = Deaktiviert IPv6-Auflösung (nur IPv4)
    2. **Variable für upstream:** `set $upstream_node node:5173;`
       - DNS-Auflösung erfolgt zur Laufzeit (bei Request), nicht beim Start
       - Ermöglicht Proxy-Requests auch wenn node später hinzugefügt wird
  - **Dateien:**
    - `docker/nginx/conf.d/default.conf` Zeile 6 (resolver)
    - `docker/nginx/conf.d/default.conf` Zeile 58, 69 (upstream variables)
  - **Ergebnis:**
    - ✅ `make up` → nginx + php → **nginx healthy** (ohne node)
    - ✅ `make node-up` → node hinzufügen → **alle Container healthy**
    - ✅ Keine Fehler mehr: "host not found in upstream"
    - ✅ Vite HMR Proxy funktioniert wenn node verfügbar ist

- 📋 **Technische Details: Nginx Variable vs. Statischer Upstream**
  - **Statisch:** `proxy_pass http://node:5173;`
    - DNS-Auflösung beim nginx-Start
    - Fehler wenn upstream nicht existiert → nginx startet nicht
  - **Dynamisch:** `set $upstream_node node:5173; proxy_pass http://$upstream_node;`
    - DNS-Auflösung bei jedem Request (mit Cache)
    - Fehler nur wenn upstream zum Request-Zeitpunkt nicht erreichbar
    - Erfordert `resolver` Direktive
  - **Vorteil:** Flexibler Workflow - node ist optional, nginx funktioniert in beiden Fällen

- ✅ **Workflow-Verifizierung**
  - **Szenario 1: Nur PHP Backend**
    ```bash
    make up              # nginx + php
    docker compose ps    # nginx: healthy, php: healthy
    curl localhost:8080  # ✅ PHP funktioniert
    ```
  - **Szenario 2: Mit Node.js Services**
    ```bash
    make up              # nginx + php
    make node-up         # node hinzufügen
    docker compose ps    # alle healthy
    make node-dev        # Vite HMR starten
    curl localhost:8080/@vite/client  # ✅ Proxy funktioniert
    ```

### Version 2.6 (2025-12-19)
- ✅ **Node.js Backend Port 3000 Fix**
  - Problem: `curl http://localhost:3000` fehlgeschlagen mit "Could not connect to server"
  - Ursache: Port 3000 war nur mit `expose:` konfiguriert (nur Docker-Netzwerk), nicht mit `ports:` (Host-Zugriff)
  - Lösung: Port 3000 in `compose.override.yaml` hinzugefügt (Zeile 63): `- "${NODE_PORT:-3000}:3000"`
  - Jetzt erreichbar: `curl http://localhost:3000/health` und `curl http://localhost:3000/api/hello?name=Docker`
  - Datei: `compose.override.yaml` Zeile 63
- ✅ **TypeScript-Fehler in server.ts behoben** (vom User bereits durchgeführt)
  - TS2742: Expliziter Typ für Express App (`const app: Express = express();`)
  - TS6133: Ungenutzte Parameter mit Unterstrich (`_req`, `_res`, `_next`)
  - TS7030: Expliziter `void` Rückgabetyp für Middleware-Funktionen
  - Datei: `src/node/server.ts` (bereits korrekt)
- ✅ **Makefile Review & Analyse**
  - Alle Pfade überprüft: Keine veralteten `/var/www/html/app/` Referenzen gefunden
  - Alle `docker compose exec`/`run` Befehle geprüft: Funktionieren korrekt
  - **Empfehlungen für zukünftige Optimierung:**
    1. `node-build` (Zeile 251-253): Verwendet `--build --target build`, was bei jedem Aufruf Image rebuildet → Ineffizient
       - Alternative: Im laufenden Container ausführen oder separates Build-Image-Konzept
    2. `node-up` / `node-app-server-up`: Verwirrende Befehle, könnten besser dokumentiert werden
       - `node-up`: Startet Container mit "sleep infinity" (asset-server) → Unklar, warum nötig in Development
       - `node-app-server-up`: Startet mit NODE_TARGET=app-server → Macht Sinn für Backend-Testing
    3. Fehlende Container-Status-Checks: `node-dev`, `node-server-dev` etc. scheitern, wenn Container nicht läuft
       - Lösung: `@docker compose ps -q node >/dev/null 2>&1 || { echo "Container not running"; exit 1; }` vor exec
  - **Aktueller Stand:** Alle Befehle funktionieren, keine veralteten Commands identifiziert
- 📝 **Testing-Status Update**
  - Phase 6.3 (Node.js Backend Testing) bereit für Tests nach Port-Fix
  - Workflow: `make up` → `make node-install` → `make node-server-dev` → `curl http://localhost:3000/health`

### Version 2.5 (2025-12-19)
- ✅ **Phase 6.2 Testing abgeschlossen:** Node.js Frontend & Vite HMR erfolgreich getestet
  - Dependencies: 109 packages installiert in 5.2s
  - Vite HMR: Ready in 407ms auf Port 5173
  - test.html: http://localhost:8080/test.html lädt erfolgreich
  - API Health Check: Zeigt JSON-Daten korrekt an
  - HMR: Live-Reload funktioniert bei Änderungen in `resources/css/app.css`
- ✅ **Bugfix: Named Volume Permissions (node_modules)**
  - Problem: Docker erstellt named volumes mit root:root Ownership, User `node` (UID 1000) konnte nicht schreiben → `EACCES: permission denied, mkdir '/app/node_modules/.pnpm'`
  - Lösung: `make node-install` (Makefile:237) angepasst - startet mit `--user root`, fixt Permissions via `chown`, dann `su node -s /bin/sh -c 'pnpm install'`
  - Begründung: Named volume nötig für Windows/Mac Performance (Linux könnte bind mount nutzen, aber Konsistenz wichtiger)
  - Datei: `Makefile` Zeile 237
- ✅ **Bugfix: Vite 6 ESM Compatibility**
  - Problem: `vite.config.js` verwendete `require('autoprefixer')` (CommonJS) in ESM-Kontext
  - Fehler: `Dynamic require of "file:///app/node_modules/.pnpm/autoprefixer@10.4.23_postcss@8.5.6/node_modules/autoprefixer/lib/autoprefixer.js" is not supported`
  - Lösung: Geändert zu ESM-Import - `import autoprefixer from 'autoprefixer'` (Zeile 3) und `plugins: [autoprefixer]` (Zeile 81)
  - Datei: `vite.config.js` Zeilen 3 & 81
- ✅ **Bugfix: Content Security Policy (CSP) blockierte Vite HMR**
  - Problem: Nginx CSP erlaubte nur `script-src 'self'`, aber Vite läuft auf Port 5173 → Scripts/Styles wurden im Browser mit CSP-Fehler markiert
  - Symptome:
    - Browser Console: CSP-Violations für `http://localhost:5173/build/@vite/client` und `/build/js/app.js`
    - API Health Check bleibt auf "Loading..." stecken (fetch blockiert)
    - Keine HMR WebSocket-Verbindung
  - Lösung: CSP für Development erweitert (Zeile 35):
    - `script-src 'self' 'unsafe-inline' http://localhost:5173` - Erlaubt Scripts von Vite Dev Server
    - `connect-src 'self' ws://localhost:5173 http://localhost:5173` - Erlaubt WebSocket für HMR und fetch zu Vite
  - Wichtig: **Production CSP muss stricter sein!** Entferne `localhost:5173` und nutze Nonce-basierte CSP (siehe index.php)
  - Datei: `docker/nginx/conf.d/default.conf` Zeile 35
- ✅ **Bugfix: Vite Base Path in test.html**
  - Problem: `test.html` verwendete Pfade ohne `/build/` Prefix → `http://localhost:5173/@vite/client` statt `http://localhost:5173/build/@vite/client`
  - Lösung: Pfade angepasst auf korrekte Base Path (Zeilen 16-17)
  - Datei: `public/test.html` Zeilen 16-17

### Version 2.4 (2025-12-19)
- ✅ **Phase 6.1 Testing abgeschlossen:** PHP Backend & QA Tools erfolgreich getestet
  - Nginx: Läuft stabil (Port 8080)
  - PHP API: http://localhost:8080/ und /api/health funktionieren
  - PHPStan: No errors (2 files analyzed)
  - PHP-CS-Fixer: 0 errors in 4 files
- ✅ **Bugfix: Nginx vite-hmr.conf Integration (Phase 4.1)**
  - Problem: Separate `vite-hmr.conf` verursachte "location directive not allowed" Fehler
  - Lösung: Vite HMR Locations direkt in `default.conf` integriert (Zeilen 45-71)
  - Begründung: Production-safe (Proxy schlägt harmlos fehl), keine separate Datei nötig
  - Entfernt: Separate `vite-hmr.conf` Datei und Mount aus `compose.override.yaml`
- ✅ **Bugfix: PHPStan Config**
  - Problem: "At least one path must be specified" - leeres `tests/` Verzeichnis
  - Lösung: `tests/` Pfad in `phpstan.neon` auskommentiert (Zeile 6)
  - Hinzugefügt: `phpstan.neon` Mount in `compose.override.yaml` (Zeile 37)
- ✅ **Bugfix: PHP-CS-Fixer Config**
  - Problem: Config nicht im Container verfügbar
  - Lösung: `.php-cs-fixer.dist.php` Mount in `compose.override.yaml` (Zeile 38)
  - Angepasst: Finder auf neue Struktur (`src/php`, `tests`, `public`)

### Version 2.3 (2025-12-18)
- ✅ **Makefile Kompatibilität:** Makefile auf Kompatibilität mit neuer Struktur geprüft (Phase 4)
  - Hinzugefügt: `make node-dev`, `make node-server-dev`, `make node-server-build`, `make logs-node`
  - Korrigiert: cs-fix-all Pfad von `/var/www/html/app/` zu `/var/www/html/src/php/`
- ✅ **TODO.md Antworten:** Rückfragen aus Phase 4.4 und 4.7 als Unterpunkte beantwortet
  - **4.4.1:** Make Befehl für Dev App-Server - `make node-server-dev` Verwendung und Workflow
  - **4.4.2:** Image Slimming für Production - `pnpm prune --prod` Implementierung mit Beispiel
  - **4.7.1:** NODE_ENV vs ENV - Single Source of Truth Pattern erklärt
  - Alle Antworten mit Code-Beispielen, Rationale und Referenzen dokumentiert

### Version 2.2 (2025-12-17)
- ✅ **package.json Versionen aktualisiert:** Stable Releases (Option A)
  - autoprefixer: 10.4.20 → 10.4.23
  - postcss: 8.4.49 → 8.5.6
  - typescript: 5.7.2 → 5.9.3
  - @types/express: 5.0.0 → 5.0.6
  - tsx: 4.19.2 → 4.21.0
  - Vite 6.x, Express 4.x, pnpm 9.x bleiben (stable LTS)
- ✅ **Makefile Docker-First:** Neue Strategie für Dependencies (Phase 4.6)
  - `make dev-deps` / `make node-install` → Docker (Default, garantiert konsistent)
  - `make dev-deps-local` / `make node-install-local` → Lokal (Opt-in, mit Warning)
  - `make composer` / `make pnpm` → Ad-hoc Commands auf laufenden Containern
- ✅ **README Tool-Versionen:** Ausführlicher Abschnitt zu Konsistenz (Phase 8.1)
  - Warum Docker-First
  - Problem mit lokalen Tools (Lockfile-Konflikte)
  - composer.json config.platform schützt nur vor PHP-Differenzen
- ✅ **Phase 4 Zusammenfassung:** Aktualisiert auf 7 Abschnitte (4.6 hinzugefügt)

### Version 2.1 (2025-12-17)
- ✅ **index.php zentralisiert:** Phase 3.3 enthält jetzt die finale Version mit CSP Template
- ✅ **test.html Dev-Mode:** Dev-Mode mit HMR als Default aktiviert (Phase 3.6)
- ✅ **compose.override.yaml:** Anpassungen für existierende Datei statt Neuerstellung (Phase 4.7)
- ✅ **Phase 4 optimiert:** Redundante Abschnitte entfernt, Nummern angepasst
- ✅ **Node Development Stage:** Kompatibilität mit app-server dokumentiert (Phase 4.4)
- ✅ **Zusammenfassung:** Aktualisiert auf 6 statt 11 Abschnitte durch Zentralisierung

### Version 2.0 (2025-12-17)
- Alle @Questions beantwortet
- Phase 4 erweitert mit 11 Abschnitten
- Wichtige Erkenntnisse & Entscheidungen dokumentiert
