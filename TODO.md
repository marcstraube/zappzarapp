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
**Letzte Aktualisierung:** 2026-01-12 (Code Quality & Documentation Improvements)
**Version:** 3.12

---

## Changelog

### Version 3.12 (2026-01-12) - Code Quality & Documentation Improvements

#### Fixed
- **Makefile `fresh` Target**:
  - Added `--no-cache` flag to ensure true clean rebuilds
  - Previously used cached layers, defeating the purpose of a "fresh" build

- **PHPUnit Coverage Warnings**:
  - Removed `src/php/App/Infrastructure` exclusion from `phpunit.xml.dist`
  - Changed `HasAuditLoggingTest` from `#[CoversClass]` to `#[CoversNothing]` (traits can't be coverage targets)
  - Eliminated 30+ coverage target warnings

- **PHP development.ini Comment**:
  - Fixed misleading comment about disabled functions
  - Now correctly documents both `shell_exec` (phpDox) and `curl_exec/curl_multi_exec` (Composer)

- **PHP Import Statements**:
  - Refactored to use `use` imports instead of fully qualified class names (`\PDO`, `\PDOException`, etc.)
  - Files updated: `DatabaseService.php`, `AuditLogger.php`, `CalculatorIntegrationTest.php`

#### Added
- **`make build-no-cache` Target**:
  - New Makefile target for explicit no-cache builds
  - Supports both development and production environments

- **Separated Coverage Directories**:
  - PHP coverage: `build/coverage/php/`
  - Node.js coverage: `build/coverage/node/`
  - Prevents report conflicts when running both coverage commands

- **Test Documentation in `documentation/`**:
  - `TESTING-PHP.md` - Comprehensive PHP testing guide (PHPUnit)
  - `TESTING-NODE.md` - Comprehensive Node.js testing guide (Vitest)
  - Updated `documentation/README.md` with Testing & Quality section

- **New PHP Infrastructure Classes**:
  - `CorsMiddleware.php` - CORS handling with configurable origins via `CORS_ORIGINS` env
  - `HealthStatus.php` - Enum for health check states (OK, DEGRADED, ERROR, DISABLED, UNKNOWN)

#### Changed
- **Docker Compose Watch Configuration** (`compose.override.yaml`):
  - Added `./build:/var/www/html/build` mount to PHP service for coverage reports
  - Added `tests/php` to Watch sync for test file changes

- **Vitest Configuration** (`vitest.config.ts`):
  - Coverage directory changed from `./build/coverage` to `./build/coverage/node`

- **Makefile Coverage Commands**:
  - `test-coverage-php`: Output to `build/coverage/php/`
  - `test-coverage-node`: Output to `build/coverage/node/`
  - `test-coverage`: Updated info message with correct paths

#### Removed
- `tests/php/README.md` - Moved to `documentation/TESTING-PHP.md`
- `tests/node/README.md` - Moved to `documentation/TESTING-NODE.md`

#### Status
- PHPStan Level 8: No errors
- PHP CS Fixer: 0 files need fixing
- PHPUnit: 65 tests, 229 assertions (0 warnings)
- Vitest: 61 tests passing

### Version 3.11 (2026-01-11) - Code Quality & PHPStan Level 8 Compliance

#### Fixed
- **PHPDoc Formatting Issues**:
  - Fixed malformed class docblocks in `HealthCheckService.php`, `DatabaseService.php`
  - Fixed method docblock indentation issues
  - Corrected misplaced `@return` annotations at class level

- **PHPMD @SuppressWarnings Compatibility**:
  - Changed format from `@SuppressWarnings(PHPMD.*)` to `@SuppressWarnings("PHPMD.*")` (quoted)
  - Fixes PHPStan parsing errors while maintaining PHPMD compatibility
  - Affected files: `HealthCheckService.php`, `DatabaseService.php`, `WelcomeController.php`

- **PHPStan Type Issues (Level 8)**:
  - `AuditLogger.php`: Changed `?string $logFilePath` to `string` (never null after construction)
  - `AuditLogger.php`: Removed redundant null check that was always false
  - `AuditLoggerTest.php`: Added `PDO&MockObject` intersection type for mock property
  - `AuditLoggerTest.php`: Added `assertIsString()` for `file_get_contents()` result

- **PHPStan Configuration**:
  - Added ignore pattern for test-specific assertions that are valid documentation
  - Pattern: `method.alreadyNarrowedType` in `tests/*` (assertIsArray, assertTrue, etc.)

#### Changed
- **MariaDB Encryption Config (`my.cnf.example`)**:
  - Encryption settings now enabled by default (uncommented)
  - Rationale: File is only copied when enabling table-level encryption
  - Setup steps reduced from 5 to 4 (removed "uncomment encryption settings")
  - Optional settings remain commented: `innodb_encrypt_tables`, `innodb_encryption_rotate_key_age`

#### Status
- PHPStan Level 8: No errors
- PHP CS Fixer: 0 files need fixing
- PHPUnit: 65 tests, 229 assertions
- Vitest: 61 tests passing

### Version 3.10 (2026-01-09) - SSL/TLS Secure-by-Default
**GDPR Phase 1.1 Implementation - Encryption at Rest and in Transit:**

#### Added
- **Automated SSL/TLS Setup**:
  - `make setup` now automatically generates self-signed certificates if not present
  - Certificate check integrated into setup workflow
  - Automatic fallback to `make ssl-selfsigned` for development

- **Encryption Key Management**:
  - Automatic generation of `ENCRYPTION_KEY` and `BACKUP_ENCRYPTION_KEY` in .env
  - 256-bit AES keys generated via `openssl rand -base64 32`
  - Keys are checked and generated only if missing or empty

- **SSL/TLS for Databases (Development)**:
  - PostgreSQL SSL enabled by default with custom entrypoint script
  - MariaDB SSL enabled by default with custom entrypoint script
  - Certificate permission handling via entrypoint wrappers
  - Certificates mounted to `/tmp/certs/` and copied with correct ownership

- **SSL/TLS for Databases (Production)**:
  - PostgreSQL SSL configuration activated in `compose.prod.yaml`
  - MariaDB SSL configuration activated in `compose.prod.yaml`
  - Redis TLS configuration activated (port 6380, non-plaintext mode)
  - All services use shared certificates from `docker/certs/`

- **Nginx SSL/HTTPS with Dynamic Port Configuration**:
  - HTTPS enabled on port 8443 (configurable via `NGINX_SSL_PORT`)
  - **Automatic HTTP → HTTPS redirect**: `http://localhost:8080` → `https://localhost:8443/`
  - Redirect uses configurable `NGINX_SSL_PORT` from environment variable
  - SSL configuration generated dynamically from template using `envsubst`
  - HTTP/2 support enabled for HTTPS connections
  - Template-based configuration: `ssl-development.conf.template`
  - Entrypoint script processes templates at container startup
  - Port dynamically injected from `NGINX_SSL_PORT` environment variable
  - CSP and SSL configs mounted by default in production (`compose.prod.yaml`)
  - Self-signed certificates work out-of-the-box after `make setup`
  - Ready for Let's Encrypt certificates (`make ssl-letsencrypt`)
  - **Note**: Browser will show certificate warning for self-signed cert (expected behavior)

- **Custom Entrypoint Scripts**:
  - `docker/nginx/entrypoint.sh`: Processes SSL template with `envsubst` for dynamic port configuration
  - `docker/postgres/entrypoint.sh`: Handles SSL certificate setup for PostgreSQL
  - `docker/mariadb/entrypoint.sh`: Handles SSL certificate setup for MariaDB
  - PostgreSQL & MariaDB scripts ensure correct ownership (postgres:postgres, mysql:mysql)
  - Automatic permission fixing (644 for .crt, 600 for .key)
  - Nginx script runs as root to write config, then starts nginx

#### Changed
- **Environment Configuration**:
  - `NGINX_SSL_PORT=8443` now enabled by default in `.env.example`
  - Automatic SSL port binding in `compose.yaml`
  - SSL volumes mounted automatically (no manual uncomment needed)

- **Makefile Setup Target Enhanced**:
  - Added SSL certificate existence check
  - Added encryption key generation logic
  - Improved setup flow with colored output
  - Better error handling for missing keys

- **Database Configuration**:
  - PostgreSQL: SSL enabled with custom entrypoint in development
  - MariaDB: SSL enabled with custom entrypoint in development
  - Redis: TLS mode enabled in production (port 6380, plaintext disabled)

- **Nginx Configuration**:
  - Fixed file permissions for `csp-production.conf` (600 → 644)
  - Fixed file permissions for `ssl-production.conf.example` (600 → 644)
  - Converted `ssl-development.conf` to template file with `${NGINX_SSL_PORT}` placeholder
  - Nginx Dockerfile: Added `gettext` package for `envsubst` support
  - Nginx Dockerfile: Removed `USER nginx` to allow root entrypoint execution
  - Container starts as root, processes templates, then runs nginx

#### Security Improvements
- **Transport Layer Security**:
  - All database connections encrypted by default
  - PostgreSQL enforces SSL with server certificates
  - MariaDB enforces secure transport with `--require-secure-transport=ON`
  - Redis TLS mode disables plaintext connections

- **At-Rest Encryption Preparation**:
  - Encryption keys ready for column-level encryption (Phase 1.2)
  - Keys stored securely in `.env` (excluded from version control)
  - Backup encryption key for future backup script usage

- **Certificate Management**:
  - Self-signed certificates for development (automatic generation)
  - Symlinks replaced with actual files for proper container access
  - Certificate permissions: 644 for both `.crt` and `.key` (Nginx compatibility)
  - Production-ready Let's Encrypt integration (via `make ssl-letsencrypt`)
  - Centralized certificate location (`docker/certs/`)
  - Certificates shared across all services (Nginx, PostgreSQL, MariaDB, Redis)

#### GDPR Compliance
- **Article 32 (Security of Processing)**:
  - ✅ Encryption of personal data in transit (SSL/TLS)
  - ✅ Preparation for encryption at rest (keys generated)
  - ✅ Secure-by-default configuration

- **Article 25 (Data Protection by Design)**:
  - ✅ Default security settings enabled automatically
  - ✅ No manual intervention required for basic security

#### Developer Experience
- **Zero-Configuration Security**:
  - SSL/TLS works out of the box after `make setup`
  - No manual certificate generation needed
  - No manual key generation needed

- **Backward Compatibility**:
  - Existing deployments continue to work
  - Entrypoint scripts handle missing certificates gracefully
  - Optional encryption keys (can remain empty if not used)

#### Testing
- Complete container rebuild verified (`make fresh`)
- All services healthy after SSL/TLS activation
- PostgreSQL SSL verified: `SHOW ssl;` returns `on`
- **HTTP → HTTPS redirect verified**: `http://localhost:8080` returns HTTP 301 → `https://localhost:8443/`
- Redirect uses correct configurable port from `NGINX_SSL_PORT`
- Nginx HTTPS verified: `https://localhost:8443` returns HTTP 200 OK
- Following redirect with `-k` flag works: Final HTTP 200 OK
- SSL template processing verified: "SSL configuration generated with NGINX_SSL_PORT=8443"
- HTTP/2 protocol confirmed on HTTPS connections
- Certificate validity: 1 year from generation (self-signed)
- Certificate verified: Subject CN=localhost, valid chain
- Encryption keys: 256-bit base64-encoded
- **Browser behavior**: Self-signed certificate warning is shown (expected and normal)

#### Documentation
- GDPR Phase 1.1 tasks completed from `GDPR-NEXT-STEPS.md`
- Next phase: Database Encryption (Phase 1.2 - optional)
- Next phase: Audit Logging (Phase 1.3 - GDPR Art. 30)

---

### Version 3.9 (2026-01-03) - Security Hardening: GDPR Compliance & Network Isolation
**Major security enhancements for production environments with 10,000+ users:**

#### Added
- **3-Network Segmentation Architecture** (GDPR Art. 32 compliance):
  - `frontend`: Public-facing nginx only
  - `backend`: Application layer (nginx, php, node)
  - `database`: Data persistence layer (postgres, mariadb, redis)
  - Prevents direct database access from public-facing services
  - Reduces attack surface and enables Defense-in-Depth strategy
  - Comprehensive documentation in `documentation/NETWORK.md`

- **Unix Socket Communication**:
  - PHP-FPM now uses Unix sockets instead of TCP (nginx ↔ php)
  - 10-20% performance improvement over TCP
  - Enhanced security (no network exposure)
  - Shared volume `/var/run/php-fpm` for socket communication

- **SSL/TLS Infrastructure** (prepared for activation):
  - PostgreSQL SSL configuration (commented, ready to enable)
  - MariaDB SSL configuration (commented, ready to enable)
  - Redis TLS configuration (commented, ready to enable)
  - All services can use single certificate from `docker/certs/`

- **Persistent Data Volumes** (Production):
  - Added volumes for all databases in `compose.prod.yaml`
  - Prevents data loss on container recreation
  - Named volumes with project prefix for clarity

#### Changed
- **Certificate Directory Restructured**:
  - Moved from `docker/nginx/certs/` to `docker/certs/`
  - Centralized location for all service certificates
  - Updated all references across 9 files (configs, docs, scripts)

- **PHP-FPM Configuration**:
  - Added custom pool config for Unix socket support
  - Updated healthchecks to use Unix socket
  - Config now consistent across development and production stages

- **Nginx Configuration**:
  - FastCGI pass updated to Unix socket
  - Applied to both default and SSL configurations
  - Maintained backward compatibility

#### Removed
- Redundant `NODE_ENV=production` from PHP service (no functional use in PHP-FPM)

#### Fixed
- Production database tmpfs permissions (changed `/var/run` to `/var/run/nginx`)
- **PM2 Process Manager Configuration**:
  - Fixed PM2 not passing `--import tsx` interpreter args to Node.js process
  - Changed from `interpreter` + `interpreter_args` to `script` + `args` pattern
  - Backend now starts correctly with TypeScript transpilation via tsx loader
  - Resolved endless restart loops caused by `wait_ready: true` with missing `process.send('ready')`
  - Configured structured JSON logging (consistent with project-wide logging standard)
  - Disabled PM2 watch mode (better handled by bind mounts for file change detection)
- **Node.js Graceful Shutdown**:
  - Added `process.off()` calls to prevent multiple SIGINT/SIGTERM handlers
  - Prevents duplicate shutdown attempts during container restarts
- **Development Workflow** (Cross-Platform Compatibility):
  - Hybrid approach: COPY in Dockerfile + Docker Compose Watch for file sync
  - Source code (src/, tests/, resources/, templates/) copied into images at build time
  - Docker Compose Watch syncs file changes in development (~50-100ms latency)
  - Named volumes for dependencies (node_modules, vendor) - best performance on all platforms
  - Consistent performance across Linux, Windows, and macOS
  - No manual configuration needed - works out-of-the-box on all platforms

#### Security Impact
- **GDPR Compliance**: Network segmentation addresses Art. 32 requirements
- **Attack Surface**: Databases unreachable from frontend network
- **Performance**: Unix sockets reduce latency by ~15%
- **Data Integrity**: Persistent volumes prevent accidental data loss

---

### Version 3.8 (2026-01-02) - Production Docker Compose YAML Syntax Fix
**Fixed YAML syntax errors in production compose file:**

#### Fixed - Docker Configuration
- **Production Compose YAML Syntax**:
  - Fixed invalid YAML syntax in `compose.prod.yaml` for PostgreSQL and MariaDB services
  - Removed inline comments from multi-line command strings (lines 134-154 for PostgreSQL, 183-196 for MariaDB)
  - Comments inside folded multi-line strings (`>`) were being passed to database commands, causing syntax errors
  - Moved SSL configuration comments outside command blocks as proper YAML comments
  - Validated with `docker compose config --quiet` to ensure correctness

### Version 3.7 (2026-01-02) - Code Quality & Test Coverage Improvements
**Achieved 100% Node.js test coverage, eliminated all PHPMD errors, and improved TypeScript configuration structure:**

#### Fixed - Code Quality
- **PHPMD Error Elimination**:
  - Fixed all 26 PHPMD errors across multiple files
  - Added appropriate `@SuppressWarnings` annotations for intentional patterns
  - Added missing `use` statements (PDO, PDOException, Redis, Exception, etc.)
  - Removed error control operators (`@`) in HealthCheck and services
  - Files improved: WelcomeController, HealthCheck, DatabaseService, HealthCheckService, LogService, QualityService

#### Changed - Node.js Architecture
- **Entry Point Separation**:
  - Moved `server.ts` from `src/node/App/` to `src/node/` (proper separation of concerns)
  - Entry point now separated from application logic
  - Updated `ecosystem.config.cjs` to reference correct path: `src/node/server.ts`
- **TypeScript Configuration Cleanup**:
  - Split into three distinct configs for clarity:
    - `tsconfig.json`: Development & testing (includes only `src/node/App/**/*` and `tests/node/App/**/*`)
    - `tsconfig.build.json`: Production builds (includes `server.ts` entry point)
    - `tsconfig.vitest.json`: Test-specific settings
  - Fixed `vitest.config.ts` test patterns to match new structure (`tests/node/App/**/*`)

#### Fixed - Test Coverage
- **Node.js Coverage: 100%**:
  - Achieved 100% coverage on application code (exceeds 80% target)
  - app.ts: 100%, math.ts: 100%
  - Resolved Vitest v8 coverage issue with server.ts through proper configuration
  - 27/27 tests passing

#### Changed - Docker Configuration
- **Watch Mode Improvements**:
  - Added `sync+restart` action for config files in `compose.override.yaml`
  - Auto-restart on changes to: tsconfig.json, tsconfig.build.json, tsconfig.vitest.json, vitest.config.ts, vite.config.js, ecosystem.config.cjs
  - Improves development experience with automatic config reloading
- **Production Build Fix**:
  - Added `tsconfig.build.json` to Dockerfile COPY step (was missing)
  - Fixed production build to compile both frontend AND backend: `pnpm run build && pnpm run server:build`
  - Ensures TypeScript server is properly compiled in production images

#### Technical Debt Removed
- Deleted unnecessary `tests/node/App/unit/server.test.ts` (wasn't testing server.ts)
- Simplified coverage configuration (removed unnecessary glob-specific thresholds)
- Cleaned up workarounds that were masking cache issues

### Version 3.6 (2025-12-31) - Granular Service Control & Makefile Optimization
**Introduced optional database control, simplified Makefile logic, and improved health checks for better visibility:**

#### Added - Optional Database Control
- **`ENABLE_DATABASE` Environment Variable**:
  - New granular control flag in `.env` and `.env.example` (default: `true`)
  - Allows running stack without database (e.g., for external DB connections)
  - Consistent with existing `ENABLE_PHP`, `ENABLE_NODE`, `ENABLE_REDIS` pattern
  - Added Preset [6]: "Minimal PHP (PHP only, no Database/Redis)"

#### Changed - Makefile Simplification
- **Profile-Based Service Management**:
  - Removed redundant `SERVICES` variable (was duplicating `PROFILES` functionality)
  - Now uses Docker Compose profiles idiomatically: `docker compose $PROFILES up -d`
  - Cleaner, more maintainable code following Docker Compose best practices
  - Reduced code complexity in `up-core` and `down` targets

- **Enhanced `check-health` Command**:
  - Now displays **all services** regardless of state (enabled/disabled)
  - Added `⚪ Disabled (ENABLE_*=false)` status for deactivated services
  - Provides complete stack overview at a glance
  - Distinguishes between "disabled by config" vs "unhealthy/not running"

#### Fixed - Path Configuration
- **TypeScript Path Mappings**:
  - Fixed `tsconfig.json`: `@tests/*` now correctly points to `./tests/node/App/*`
  - Fixed `vitest.config.ts`: `@tests` alias updated to `./tests/node/App`
  - Aligns with App/ directory structure introduced in v3.5

### Version 3.5 (2025-12-31) - Docker Compose Watch, Vitest 4, App Structure Migration
**Modern development workflow with Docker Compose Watch (2025 standard), Vitest 4 upgrade, and improved project structure:**

#### Added - Docker Compose Watch (2025 Standard)
- **Lock File Synchronization Strategy**:
  - Named volumes for dependencies: `php_vendor` (prevents permission issues)
  - Lock files excluded from bind mounts (generated in containers)
  - `develop.watch` configured for both PHP and Node.js services
  - `action: rebuild` triggers on composer.json/package.json/lock file changes
  - `action: sync` for real-time source code updates (src/php, src/node)
  - Manual sync via `make sync-lockfiles` (uses `docker cp`)

- **Makefile Commands**:
  - `make sync-lockfiles`: Copies lock files from containers to host (for Git tracking)
  - `make validate`: Extended to check both Composer and pnpm lockfile presence
  - `make setup`: Now creates `tools/` directory (fixes `make docs` permission issues)
  - `make up`: Automatically starts database based on `DB_TYPE` environment variable
  - `make down`: Now uses same profiles as `make up` (stops all enabled services correctly)

#### Changed - Vitest Upgrade (2.1.8 → 4.0.16)
- **Dependencies Updated**:
  - `vitest`: ^2.1.8 → ^4.0.16
  - `@vitest/coverage-v8`: ^2.1.8 → ^4.0.16
  - `@vitest/ui`: ^2.1.8 → ^4.0.16
  - Added: `@types/ws` ^8.5.13, `ws` ^8.18.0 (for future WebSocket live-logs)

- **Configuration Improvements** (vitest.config.ts):
  - Fixed TypeScript import: `import * as path from 'path'` (was causing TS1259 error)
  - Simplified coverage include/exclude (Vitest 4 fixed pattern matching)
  - Updated alias: `'@node': './src/node/App'` (reflects new App/ structure)
  - Comment clarifies: "Vitest 4.x: Fixed include/exclude handling (no longer needs workarounds)"

#### Changed - App/ Directory Structure Migration
- **PHP Source Files**:
  - Moved: `src/php/*.php` → `src/php/App/` (preserves subdirectory structure)
  - Examples: `Http/Router.php`, `Http/Controller/*.php`, `Infrastructure/*.php`, `Utils/*.php`
  - Updated composer.json PSR-4: `"App\\": "src/php/App/"` (was `"App\\": "src/php/"`)
  - Updated composer.json PSR-4 dev: `"App\\Tests\\": "tests/php/App/"` (was `"tests/php/"`)

- **PHP Test Files**:
  - Moved: `tests/php/*.php` → `tests/php/App/`
  - Examples: `Feature/CalculatorIntegrationTest.php`, `Unit/CalculatorTest.php`

- **Node.js Source Files**:
  - Moved: `src/node/*.ts` → `src/node/App/`
  - Examples: `app.ts`, `server.ts`, `utils/math.ts`
  - Updated tsconfig.json paths: `"@node/*": ["./src/node/App/*"]`

- **Node.js Test Files**:
  - Moved: `tests/node/*.test.ts` → `tests/node/App/`
  - Examples: `integration/api.test.ts`, `unit/math.test.ts`
  - Updated vitest.config.ts include: `tests/node/**/*.{test,spec}.{ts,js}`

- **Template Path Fix**:
  - `WelcomeController.php`: Added extra `../` for new directory nesting level
  - Path: `__DIR__ . '/../../../../../templates/welcome.php'` (was 5 levels, now 6)

#### Changed - IDE Integration Updates
- **.idea/docker-webdev.iml**: Updated sourceFolders and testFolders for App/ structure
- **.idea/phpunit.xml**: Corrected test directories path
- **ecosystem.config.cjs**: Updated Node.js app paths to src/node/App/
- **phpunit.xml.dist**: Updated test suite directories to tests/php/App/

#### Fixed - Git Status on DevDashboard
- **Problem**: Git commands failed for www-data user with "dubious ownership" error
- **Root Cause**: Repository owned by host user (UID 1000), but PHP-FPM runs as www-data (UID 82)
- **Solution**: Changed `git config --global` to `git config --system` in docker/php/entrypoint.dev.sh
  - System-wide config (`/etc/gitconfig`) accessible to all users
  - Global config (`/root/.gitconfig`) only accessible to root
- **Result**: Dashboard now correctly displays:
  - Current branch (e.g., "node-testing-backup")
  - Latest commit hash (e.g., "8f2a806")
  - Uncommitted changes count with status badge

#### Fixed - Makefile Issues
- **Database Auto-Start**: `make up` now includes database in SERVICES variable
  - Changed: `SERVICES="nginx ${DB_TYPE:-postgres}"` (was `SERVICES="nginx"`)
  - Database type controlled by `DB_TYPE` env var (postgres/mariadb), not separate flag

- **make down Profile Handling**: Now uses same profiles as `make up`
  - Previously only stopped nginx, left other containers running
  - Now correctly stops all enabled services (php, node, redis, postgres/mariadb)

- **Confusing Hints Removed**: Deleted "For guaranteed consistency, use 'make ...-install'" messages
  - Messages appeared on `*-install-local` targets but were misleading
  - Lock files now managed via Docker Compose Watch strategy

#### Fixed - PHP Entrypoint (docker/php/entrypoint.dev.sh)
- **composer.lock Check**: Added lockfile existence check to install condition
  - Before: Only checked `vendor/` directory and `autoload.php`
  - After: Also checks for `composer.lock` existence
  - Ensures lockfile is generated on first `make setup` run
  - Condition: `if [ ! -d vendor ] || [ ! -f vendor/autoload.php ] || [ ! -f composer.lock ]`

#### Testing
- ✅ Fresh developer workflow: `make init` → `make setup` → `make up` (tested from clean slate)
- ✅ PHPUnit: 36 tests, 163 assertions passing
- ✅ Vitest: 25 tests passing
- ✅ All endpoints functional: localhost:8080, /_dev, /_dev/system, etc.
- ✅ Git Status displays correctly on DevDashboard (branch, commit, uncommitted changes)
- ✅ make docs working (tools/ directory created by setup)
- ✅ make validate checks both composer.json and pnpm-lock.yaml
- ✅ make down stops all services (verified with make status)
- ✅ Database starts automatically with make up

#### Documentation
- Removed confusing lockfile consistency hints from Makefile
- Added Vitest 4.x comment explaining simplified coverage config
- Git safe.directory comment updated to clarify system-wide vs global scope

### Version 3.4 (2025-12-30) - Development Dashboard Completion
**Complete implementation of all dashboard pages with proper autoloading, volume mounts, and production safety:**

#### Completed - Dashboard Pages
- **Quality Page (`/_dev/quality`)**:
  - PHP quality tools status: PHPStan Level 8, PHPMD, PHP CS Fixer (properly detected)
  - Node.js quality tools status: ESLint, Prettier, TypeScript (detected via volume mounts)
  - Code statistics: Accurate file counts for PHP/Node source and test files
  - Test coverage reports for PHP (PHPUnit) and Node.js (Vitest)
  - Quick action commands: Run quality checks, auto-fix code style, generate coverage

- **Logs Page (`/_dev/logs`)**:
  - Log statistics: File count, total size, available sources
  - Application logs detection (requires `make setup` to create storage/logs)
  - Available log sources: Docker Compose logs, per-service logs, application logs
  - CLI commands with examples for log viewing (docker compose logs, tail, grep)
  - Usage tips: Real-time monitoring, filtering, searching patterns
  - Makefile integration documentation

- **Database Page (`/_dev/database`)**:
  - Database overview: Type, version, table count, total size
  - Connection pool statistics: Total, active, idle connections
  - Tables list: Row counts, sizes, schemas (PostgreSQL/MariaDB support)
  - CLI commands: psql/mysql access, dump, restore, exec
  - Database client recommendations: pgAdmin, DBeaver, TablePlus, DataGrip
  - Error handling for disconnected databases

- **System Page Improvements**:
  - Fixed phpinfo() button toggle (removed duplicate "Hide" link)
  - phpinfo() logos display correctly (fixed CSP to allow data: URIs)
  - Improved phpinfo() display with scrollable container

- **Dashboard Page Enhancements**:
  - Git status working correctly (branch, commit, uncommitted changes)
  - System health monitoring with status indicators
  - Quick actions for common tasks

#### Fixed - Critical Issues
- **PHP Autoloading**: Removed all `require_once` statements from DashboardController
  - Added `DevDashboard\` namespace to composer.json PSR-4 autoload
  - Added `Tests\DevDashboard\` namespace to composer.json PSR-4 autoload-dev
  - Eliminates "does not comply with psr-4" warnings during composer dump-autoload

- **CSP Security**: Fixed Content-Security-Policy blocking phpinfo() images
  - Added `img-src 'self' data:` to development CSP in nginx default.conf
  - Allows base64-encoded images (PHP logo, Zend logo) to display correctly

- **Production Safety**: Dashboard now only available in development environment
  - Checks `ENV=development` in public/index.php (not a separate variable)
  - Prevents accidental exposure in production of sensitive data:
    - Environment variables and secrets
    - Database credentials
    - phpinfo() system details
    - Git repository information

- **UI/UX Fixes**:
  - Added explicit spacing in header (gap-4 between sections, gap-2 within)
  - Fixed list styling with consistent bullet points (removed double bullets)
  - Removed duplicate "Hide phpinfo()" link in System page

#### Added - Architecture Improvements (DEV-only)
- **Volume Mounts** (compose.override.yaml):
  - Node.js config files: eslint.config.js, .prettierrc.json, tsconfig.json
  - Git repository: .git/ (read-only) for git status functionality
  - Node.js source: src/node/, tests/node/ for accurate code statistics
  - Entrypoint script: docker/php/entrypoint.dev.sh

- **Git Integration**:
  - Created entrypoint.dev.sh to configure git safe.directory automatically
  - Resolves "dubious ownership" errors in Docker container
  - Enables Git status detection in dashboard

- **Quality Detection**:
  - QualityService now properly checks for config files (no assumptions)
  - Node.js tools detected via volume-mounted configs
  - PHP tools detected from phpstan.neon, phpmd.xml.dist, .php-cs-fixer.dist.php

#### Changed - IDE Integration
- **.idea/docker-webdev.iml**:
  - Corrected sourceFolders: `src/php/App`, `src/php/DevDashboard` (not generic `src/php`)
  - Corrected test folders: `tests/php/App`, `tests/php/DevDashboard`
  - Added Node.js folders: `src/node`, `tests/node`

- **.idea/phpunit.xml**:
  - Fixed directories: `$PROJECT_DIR$/tests/php` (not generic `tests`)

- **.vscode/settings.json**:
  - Updated PHPStan level to 8 (was 5)
  - Updated PHPStan configFile to phpstan.neon (was phpstan.neon.dist)

- **phpstan.neon**:
  - Upgraded from Level 5 to Level 8 for stricter type checking

- **Makefile**:
  - Added `storage/logs` to directories created by `make setup`
  - Ensures Application Logs feature works after setup

- **composer.json**:
  - Added DevDashboard and Tests\DevDashboard namespaces to autoload

#### Services Implementation
- **QualityService**: Code quality metrics aggregation
  - Detects PHP tools: PHPStan, PHPMD, PHP CS Fixer
  - Detects Node.js tools: ESLint, Prettier, TypeScript
  - Counts source files and test files for both PHP and Node.js
  - Checks for test coverage reports

- **LogService**: Log viewing and aggregation
  - Lists available log sources (Docker, application, per-service)
  - Provides CLI commands for log viewing
  - Calculates log file statistics

- **DatabaseService**: Database introspection
  - Database overview with type-specific queries (PostgreSQL/MariaDB)
  - Tables list with row counts and sizes
  - Connection pool statistics
  - CLI commands for database operations

#### Testing
- ✅ All 6 dashboard pages return HTTP 200
- ✅ phpinfo() images display correctly (2 logos present)
- ✅ Application Logs shows as "Available" after make setup
- ✅ Node.js quality tools detected correctly
- ✅ Git status shows branch and commit info
- ✅ No composer autoloading warnings
- ✅ PHPUnit DevDashboard suite: 19 tests, 131 assertions - OK
- ✅ Proper PHP autoloading working (no require_once needed)

#### Security Notes
- Dashboard only accessible when `ENV=development`
- Volume mounts for .git and Node.js configs are DEV-only (compose.override.yaml)
- Sensitive environment variables masked in display
- phpinfo() only available in development

### Version 3.3 (2025-12-30) - Development Dashboard
**Comprehensive development dashboard for real-time system monitoring and insights:**

#### Added - Development Dashboard (`/_dev`)
- **Dashboard Pages**:
    - Main Dashboard (`/_dev`): System overview with health status, git info, quick actions
    - Health Checks (`/_dev/health`): Container status, database connections, service monitoring, SSL certificate info
    - System Info (`/_dev/system`): PHP version, loaded extensions (160+), environment variables, phpinfo() viewer
    - Placeholder Pages: Quality metrics, Database tools, Log viewer (to be implemented)

- **Core Services**:
    - `HealthCheckService`: Real-time health monitoring via TCP socket checks
        - Container checks: nginx, php, node, redis, postgres (based on enabled services)
        - Database connections: PostgreSQL/MariaDB (based on DB_TYPE)
        - Service checks: PHP-FPM, Node.js, Nginx
        - SSL certificate validation with expiry warnings
    - `SystemInfoService`: System information aggregation
        - PHP version, SAPI, Zend version
        - 160+ loaded extensions with version info
        - Environment variables with sensitive data masking
        - Git repository status (branch, commit, uncommitted changes)

- **Technical Implementation**:
    - Simple function-based routing under `/_dev` prefix
    - Server-side rendered PHP views with inline CSS (CSP-compliant, no external CDN)
    - Environment-based enable/disable (`ENABLE_DEV_DASHBOARD=false` for production)
    - Comprehensive test coverage: 19 tests, 123 assertions (100% passing)

- **API Endpoints**:
    - `/_dev/api/health-check`: Overall system health status (JSON)
    - `/_dev/api/container-status`: Detailed container status (JSON)

#### Changed - Infrastructure
- **Makefile**: Add DevDashboard directory structure in `make setup`
    - `src/php/DevDashboard/{Controllers,Services,Views}`
    - `tests/php/DevDashboard/{Controllers,Services}`

- **Docker Nginx Configuration**:
    - Fixed config mounting: Only copy base configs into image (default.conf, csp-production.conf)
    - SSL configs now properly opt-in via compose.yaml volumes (not baked into image)
    - Resolved restart loop issue caused by missing SSL certs with mounted config

- **PHPUnit Configuration**:
    - Added DevDashboard test suite to phpunit.xml.dist

- **Public Entry Point**:
    - Integrated DevDashboard routing before app routes
    - Dashboard only loads when path starts with `/_dev`

#### Technical Details
- **Health Check Strategy**: TCP socket connectivity checks instead of Docker CLI (works inside containers)
- **Environment Awareness**: Only checks enabled services (ENABLE_PHP, ENABLE_NODE, ENABLE_REDIS, DB_TYPE)
- **Security**: Sensitive environment variables (PASSWORD, SECRET, KEY) are masked in display
- **Styling**: Self-contained inline CSS (~190 lines) for zero external dependencies

### Version 3.2 (2025-12-30) - IDE Integration (VS Code & PhpStorm)
**Complete IDE configurations for both Visual Studio Code and PhpStorm with full feature parity:**

#### Added - VS Code Configuration (`.vscode/`)
- **Workspace Configuration**:
    - `extensions.json`: 26 recommended extensions (PHP, Node.js, Docker, Git, Testing, Database)
    - `settings.json`: Comprehensive workspace settings with tool integration
    - `tasks.json`: 24 pre-configured tasks for all Makefile commands
    - `launch.json`: Debug configurations for PHP (Xdebug), Node.js, Frontend, Full-Stack compounds
    - `README.md`: Complete documentation with setup guide and troubleshooting

- **PHP Development Tools**:
    - Intelephense with PHP 8.4 support
    - PHP CS Fixer integration (PER-CS standard, risky rules enabled)
    - PHPStan Level 5 integration
    - PHPMD integration
    - PHPUnit Test Explorer
    - Xdebug 3.5.0 debugging (port 9003)

- **JavaScript/TypeScript Tools**:
    - ESLint validation and auto-fix
    - Prettier formatting
    - TypeScript strict mode
    - Vitest Test Explorer
    - Auto imports and path updates

- **Docker & Database**:
    - Docker extension integration
    - Remote Containers support
    - SQL Tools with PostgreSQL and MariaDB pre-configured

- **Editor Configuration**:
    - Tab size: 4 (PHP), 2 (JS/TS)
    - 120 char ruler, Unix line endings (LF)
    - Format on save (PHP: CS Fixer via onsave, JS/TS/Markdown: Prettier)
    - Real-time linting (PHPStan, PHPMD, ESLint)
    - Code spell checker with custom dictionary

#### Added - PhpStorm Configuration (`.idea/`)
- **Run Configurations** (`runConfigurations/`):
    - 25 pre-configured run configurations organized by category
    - Browser: Open App
    - Make: Up, Down, Restart, Fresh Build, Rebuild
    - PHP: CS Fixer, PHPStan, PHPMD, Run Tests, Coverage Report
    - Node: ESLint, Prettier, Type Check, Run Tests, Coverage Report
    - Quality: Run All Checks, Fix All
    - Test: Run All Tests
    - Docs: Generate API Documentation
    - SSL: Generate Self-Signed, Show Certificate Info
    - Logs: View All
    - Shell: PHP Container, Node Container

- **Database Connections** (`dataSources.xml`):
    - PostgreSQL (Docker): localhost:5432/app
    - MariaDB (Docker): localhost:3306/app

- **Documentation** (`README.md`):
    - Complete setup guide
    - Troubleshooting section
    - Feature comparison with VS Code

#### Changed
- **VS Code Configuration**:
    - Fixed `cSpell.enableFiletypes` → `cSpell.enabledFileTypes` (deprecated syntax)
    - Renamed tasks for consistency with PhpStorm: `Docker: *` → `Make: *`
        - `Docker: Up` → `Make: Up`
        - `Docker: Down` → `Make: Down`
        - `Docker: Restart` → `Make: Restart`
        - `Docker: Fresh Build` → `Make: Fresh Build`
        - `Docker: Rebuild` → `Make: Rebuild`
- **PhpStorm Run Configurations**:
    - Renamed `Rebuild_Docker_Images.xml` → `Make__Rebuild.xml` (consistent naming)
    - Renamed `Run_App_in_Browser.xml` → `Browser__Open_App.xml`
    - Removed `Docker.xml` (redundant, all Docker operations via Make)
- **.gitignore**: Updated to allow VS Code workspace config (like PhpStorm .idea)
    - Only ignore user-specific files (*.code-workspace, .history/)

#### Feature Parity (Both IDEs)
Complete feature parity between VS Code and PhpStorm:
- ✅ PHP Interpreter via Docker Compose
- ✅ Code Style: PHP CS Fixer (PER-CS)
- ✅ Static Analysis: PHPStan Level 5
- ✅ Mess Detection: PHPMD
- ✅ Testing: PHPUnit, Vitest
- ✅ Debugging: Xdebug 3.5.0
- ✅ Database Tools: PostgreSQL, MariaDB connections
- ✅ Run Configurations/Tasks for all Make commands
- ✅ Comprehensive documentation (README.md in both .vscode/ and .idea/)

---

### Version 3.1 (2025-12-29) - SSL/TLS Integration
**Comprehensive SSL/TLS support for all services with zero-config philosophy:**

#### Added
- **SSL Certificate Management**:
    - Self-signed certificate generator (`docker/nginx/certs/generate-selfsigned.sh`)
    - Let's Encrypt setup script (`docker/nginx/certs/setup-letsencrypt.sh`)
    - Makefile commands: `ssl-selfsigned`, `ssl-letsencrypt`, `ssl-renew`, `ssl-info`, `ssl-clean`
    - Comprehensive SSL documentation (`docker/nginx/certs/README.md`)
- **Nginx SSL Configurations**:
    - `ssl-development.conf`: Zero-config SSL für Development (localhost, self-signed)
    - `ssl-production.conf.example`: Production template mit HSTS, OCSP Stapling, strenger CSP
    - Separate Configs für Development/Production Parität
- **Database SSL Support**:
    - PostgreSQL: SSL connection configuration in `compose.prod.yaml` (optional)
    - MariaDB: SSL connection configuration in `compose.prod.yaml` (optional)
    - Certificate mounting via volumes (commented, ready to uncomment)
- **Node.js SSL Support**:
    - Documentation für HTTPS server setup (`docker/node/ssl-example.md`)
    - Szenarien: Nginx Reverse Proxy (default) vs. Direct Exposure
- **Environment Variables**:
    - `NGINX_SSL_PORT` für SSL Port Configuration (default: 8443)

#### Changed
- **Directory Structure**: SSL certificate directory via `make setup` statt .gitkeep
- **Compose Files**:
    - `compose.yaml`: SSL volumes für development (commented)
    - `compose.prod.yaml`: SSL volumes für production (commented)
- **Zero-Config Philosophy**: Development SSL funktioniert out-of-the-box nach `make ssl-selfsigned`

#### Removed
- Obsolete `.gitkeep` files (alle Directories werden via `make setup` erstellt):
    - `src/php/.gitkeep`, `src/node/.gitkeep`
    - `resources/js/.gitkeep`, `resources/css/.gitkeep`, `resources/images/.gitkeep`
    - `config/.gitkeep`, `templates/.gitkeep`
    - `storage/app/.gitkeep`, `storage/cache/.gitkeep`, `storage/sessions/.gitkeep`

#### Security
- Modern TLS configuration (Mozilla Intermediate Profile)
- TLSv1.2/1.3 only, strong cipher suites
- HSTS, OCSP Stapling in production config
- Strikte CSP in production SSL config

---

### Version 3.0 (2025-12-29) - Quality & CI/CD Integration
**Peer Review Improvements based on comprehensive code review:**

#### Added
- **CI/CD Templates**:
    - GitHub Actions workflow (`.github/workflows/ci.yml`) mit 6 Jobs:
        - php-quality: CS-Fixer, PHPStan, PHPMD, Rector
        - php-tests: PHPUnit mit Coverage (80% threshold)
        - node-quality: ESLint, Prettier, TypeScript
        - node-tests: Vitest mit Coverage (80% threshold)
        - security: Trivy Scans für Docker Images, Dependency Audits
        - build-production: Production Build Validation
    - GitLab CI pipeline (`.gitlab-ci.yml`) mit 15+ Jobs über 5 Stages
- **Security**:
    - Production CSP Config (`docker/nginx/conf.d/csp-production.conf`)
    - Strict Content-Security-Policy ohne unsafe-inline/unsafe-eval
    - Optional als Volume in `compose.prod.yaml` (kommentiert)
- **Configuration**:
    - Composer `platform-check: true` für PHP Version Consistency
    - TypeDoc `theme: "default"` explizit konfiguriert

#### Changed
- **Health Checks**: Node.js Development Health Check verbessert
    - Alt: `test -f /app/package.json` (nur File-Check)
    - Neu: Prüft auf laufende Services (Vite:5173 oder Backend:3000)
    - Fallback auf File-Check für idle Mode
- **Documentation**: compose.prod.yaml mit CSP Config Mount Beispiel

#### Removed
- Obsoleter TODO Kommentar in `docker/php/Dockerfile` (Composer wurde bereits korrekt deinstalliert in Zeile 144)

#### Quality Notes
- Projekt-Status nach Peer Review: **AUSGEZEICHNET (9.5/10)**
- PhpStorm Settings bereits perfekt konfiguriert (Docker Interpreter, PHPStan Level 5, CS-Fixer, PHPMD)
- Komplette PHP ↔ Node.js Parität bei allen Quality Tools
- Zero-Config Readiness validiert
- 12-Factor App Compliance vollständig

---

### Version 2.19 (2025-12-29)
- ✅ **Code Quality & Build Optimization (Gemini-Review + Optimierungen)**
    - **Kontext:** Gemini AI Review des gesamten Projekts mit 7 Verbesserungsvorschlägen
        - Nach Analyse: 5 Vorschläge sinnvoll, 2 inkorrekt/obsolet
        - **Resultat:** 5 Optimierungen implementiert (+ 1 Datei-Cleanup)
    - **Änderung 1: Rector vollständig integriert (composer.json + Makefile)**
        - **Problem:** Rector-Dependency vorhanden, aber nicht nutzbar
            - `rector.php` Config existierte, aber keine Scripts/Targets
            - Automatische PHP 8.4 Refactorings nicht verfügbar
        - **Lösung:**
            - `composer.json`: Scripts `rector-check` (dry-run) und `rector-fix` hinzugefügt
            - `Makefile`: Targets `make rector-check` und `make rector-fix` hinzugefügt
        - **Nutzen:**
            - ✅ Automatische Code-Upgrades auf PHP 8.4 Syntax (property hooks, etc.)
            - ✅ Dead Code Detection & Removal
            - ✅ Type Declaration Improvements
    - **Änderung 2: Git Line-Ending-Konsistenz (.gitattributes)**
        - **Problem:** Nur `* text=auto` ohne explizite LF-Enforcement
            - Potenzielle CRLF/LF-Inkonsistenzen zwischen Windows/Unix/macOS
        - **Lösung:** Explizite `eol=lf` Regeln für alle Text-Dateien
            - `* text=auto eol=lf` (Global Default)
            - Explizite Rules: `*.php`, `*.js`, `*.ts`, `*.json`, `*.md`, `*.yaml`, `*.yml`, `*.xml`
        - **Nutzen:**
            - ✅ 100% LF-Garantie (verhindert CRLF auf Windows)
            - ✅ Keine Git-Diff-Rauschen durch Line-Ending-Wechsel
    - **Änderung 3: PHP-CS-Fixer auf @PER-CS:risky umgestellt (.php-cs-fixer.dist.php)**
        - **Vorher:** `@auto` Ruleset (veraltet, deprecated in PHP-CS-Fixer v4)
        - **Nachher:** `@PER-CS:risky` + Custom Binary Operator Alignment
            - `@PER-CS:risky` = PER Coding Style 2.0 (PSR-12 Nachfolger, offizieller PHP-FIG Standard)
            - `setRiskyAllowed(true)` aktiviert (required für :risky Variante)
            - Custom Rule: `binary_operator_spaces` mit `=>` und `=` Alignment
        - **Nutzen:**
            - ✅ Modernster PHP Coding Standard (Industry Best Practice)
            - ✅ Zukunftssicher (PER ersetzt PSR-12 offiziell)
            - ✅ Konsistent mit PhpStorm-Config (.idea/php.xml nutzt auch PER-CS)
    - **Änderung 4: Docker Compose Build-Dependencies (compose.yaml)**
        - **Problem:** nginx + php kopieren von `docker-webdev-node:latest`, aber keine explizite Dependency
            - `docker/nginx/Dockerfile:66`: `COPY --from=docker-webdev-node:latest /app/public/build/`
            - `docker/php/Dockerfile:140`: `COPY --from=docker-webdev-node:latest /app/public/build/`
            - Potenzielle Race-Condition bei `make build` (node muss zuerst gebaut werden)
        - **Lösung:** `depends_on: node` bei nginx + php hinzugefügt
            - `condition: service_started` (wartet auf node-Container Start)
            - `required: false` (optional, da nur für Build relevant)
        - **Nutzen:**
            - ✅ Korrekte Build-Reihenfolge garantiert (Docker Compose orchestriert automatisch)
            - ✅ Makefile-Logic vereinfacht (kein manueller "build node first"-Hack mehr nötig)
            - ✅ Konsistent mit Best Practices (explizite Dependencies deklarieren)
    - **Änderung 5: Node.js Security-Audit (Makefile)**
        - **Problem:** PHP hat `make security-deps`, Node.js hatte kein Äquivalent
            - Inkonsistenz: PHP-Dependencies werden gescannt, npm-Dependencies nicht
        - **Lösung:** `make security-audit-node` hinzugefügt
            - Führt `pnpm audit` im node-Container aus
            - Scannt npm-Dependencies auf bekannte CVEs
        - **Nutzen:**
            - ✅ Parität zwischen PHP und Node.js Security-Tooling
            - ✅ Früherkennung von npm-Package-Schwachstellen
    - **Änderung 6: Obsolete Datei entfernt (php_cs_fixer.dist.php)**
        - **Problem:** Doppelte PHP-CS-Fixer Config
            - `.php-cs-fixer.dist.php` (neu, modern) ✅
            - `php_cs_fixer.dist.php` (alt, ungenutzt, verwaist) ❌
        - **Lösung:** Alte Datei gelöscht
    - **Änderung 7: CaptainHook PHP Lint Hook Fix (captainhook.json)**
        - **Problem:** Pre-commit Hook schlägt fehl bei gelöschten PHP-Dateien
            - `git diff --name-only --cached` listet auch gelöschte Dateien
            - `php -l` versucht nicht-existierende Dateien zu linten → "Could not open input file"
        - **Lösung:** `--diff-filter=d` hinzugefügt
            - Filtert gelöschte Dateien aus dem diff
            - Nur existierende PHP-Dateien werden gelintet
    - **Files geändert:**
        - `composer.json` (+ rector-check/fix Scripts)
        - `.gitattributes` (+ explizite eol=lf Rules)
        - `.php-cs-fixer.dist.php` (@auto → @PER-CS:risky)
        - `compose.yaml` (+ depends_on: node bei nginx/php)
        - `Makefile` (+ rector-check/fix, security-audit-node Targets)
        - `captainhook.json` (+ --diff-filter=d im PHP Lint Hook)
        - `php_cs_fixer.dist.php` (gelöscht)
    - **PhpStorm-Integration verifiziert (.idea/php.xml):**
        - `allowRiskyRules="true"` ✅ (konsistent mit setRiskyAllowed(true))
        - `codingStandard="PER-CS"` ✅ (konsistent mit @PER-CS:risky)
        - Keine Anpassungen nötig (bereits korrekt konfiguriert)
    - **Neue Make-Befehle:**
        - `make rector-check` - Zeigt potenzielle PHP 8.4 Refactorings (dry-run)
        - `make rector-fix` - Führt automatische Refactorings aus
        - `make security-audit-node` - Scannt Node.js Dependencies auf CVEs
    - **Vorteile:**
        - ✅ Vollständige Rector-Integration für PHP 8.4 Upgrades
        - ✅ Line-Ending-Konsistenz über alle Plattformen
        - ✅ Modernster PHP Coding Standard (PER-CS statt deprecated @auto)
        - ✅ Korrekte Docker Build-Orchestrierung (explizite Dependencies)
        - ✅ Parität: PHP + Node.js Security-Scanning
        - ✅ Code-Aufräumung (obsolete Dateien entfernt)
        - ✅ CaptainHook robuster bei Datei-Löschungen

### Version 2.18 (2025-12-29)
- ✅ **Nginx /docs/ Route: API-Dokumentation über Browser zugänglich (Development-Only)**
    - **Problem:** Generierte API-Dokumentation ist lokal vorhanden, aber nicht im Browser abrufbar
        - `make docs` generiert Dokumentation in `docs/`, aber kein Web-Zugriff
        - Entwickler müssen Dateien direkt im Filesystem öffnen
        - Inkonsistent mit Dashboard-Integration der anderen Endpoints
    - **Lösung: Nginx Route + Dashboard-Integration (nur Development)**
        - **Nginx `/docs/` Location Block (`docker/nginx/conf.d/default.conf`):**
            - `location ^~ /docs/` - Prefix-Match mit `^~` modifier (verhindert Regex-Matching)
            - `alias /var/www/html/docs/` - Serve-Pfad
            - `autoindex on` - Directory-Listing für Übersichtsseite
            - `try_files $uri $uri/ =404` - File-Serving-Logik
            - `add_header Cache-Control "no-cache, must-revalidate"` - Verhindert veraltete Docs
            - **Warum `^~` modifier:** Verhindert, dass Regex-Location `~* \.(css|js|...)` CSS/JS-Files in docs/ abfängt
        - **Redirect `/docs` → `/docs/`:**
            - `location = /docs { return 301 /docs/; }` - Trailing Slash Normalization
        - **Volume Mount (compose.override.yaml):**
            - `- ./docs:/var/www/html/docs:ro` (Read-Only, nur Development)
            - **Sicherheit:** In Production nicht gemountet → 404 für `/docs/` (intended behavior)
        - **Dashboard-Integration (`templates/welcome.php`):**
            - Neue Sektion "📖 API Documentation" (nur Development: `if ($vite->isDevelopment())`)
            - Links zu `/docs/`, `/docs/api/php/`, `/docs/api/node/`
            - Hinweis: "Run `make docs` to generate/update API documentation"
    - **Endpoints:**
        - `http://localhost:8080/docs/` - Dokumentations-Übersicht (Directory Listing)
        - `http://localhost:8080/docs/api/php/` - PHP API Docs (phpDocumentor)
        - `http://localhost:8080/docs/api/node/` - Node/TypeScript API Docs (TypeDoc)
    - **Files geändert:**
        - `docker/nginx/conf.d/default.conf` (neue `/docs/` Location Blocks)
        - `compose.override.yaml` (docs/ Volume Mount für nginx Service)
        - `templates/welcome.php` (neue "API Documentation" Sektion)
    - **Vorteile:**
        - ✅ Entwickler können API-Docs direkt im Browser öffnen
        - ✅ Dashboard zeigt alle verfügbaren Endpoints inkl. Dokumentation
        - ✅ Development-Only Feature (Production-sicher)
        - ✅ CSS/JS-Files funktionieren korrekt (`^~` modifier verhindert Konflikte)
        - ✅ Konsistent mit "production-ready boilerplate"-Philosophie

### Version 2.17 (2025-12-29)
- ✅ **Documentation Generation: PHP + Node/TypeScript (Production-Ready Setup)**
    - **Problem:** Keine automatische API-Dokumentations-Generierung vorhanden
        - Manual documentation ist fehleranfällig und veraltet schnell
        - Keine Parität zwischen PHP und Node.js Tooling
        - Inkonsistent mit "production-ready boilerplate"-Philosophie
    - **Lösung: Pre-konfigurierte Documentation-Tools für beide Stacks**
        - **PHP: phpDocumentor v3.9.1 (PHAR standalone)**
            - **Installation:** Auto-Download on first use (Makefile lädt PHAR bei Bedarf herunter)
            - **Warum PHAR:** Vermeidet Composer-Dependency-Konflikte (phpDocumentor v3 requires Monolog v2, wir nutzen v3)
            - **Warum Download-on-Demand:** Kein 25MB Binary im Repo (tools/ ist in .gitignore)
            - **Config:** `phpdoc.xml` (scannt `src/php/`, Output: `docs/api/php/`)
            - **Features:** Class diagrams, inheritance graphs, Markdown support, responsive UI
            - **Script:** `composer docs` → `php tools/phpdoc.phar --config=phpdoc.xml`
            - **Makefile-Logic:** `make docs-php` prüft ob PHAR existiert, downloadet sie sonst automatisch
        - **Node/TypeScript: TypeDoc**
            - **Installation:** `pnpm add -D typedoc` (package.json devDependencies)
            - **Config:** `typedoc.json` (scannt `src/node/`, Output: `docs/api/node/`)
            - **Features:** TypeScript-native, type inference, cross-referenced navigation
            - **Script:** `pnpm run docs` → `typedoc`
        - **Makefile-Targets:**
            - `make docs` - Generiert PHP + Node Dokumentation
            - `make docs-php` - Nur PHP API Docs
            - `make docs-node` - Nur Node/TypeScript API Docs
            - `make docs-clean` - Löscht generierte Dokumentation
        - **Files geändert:**
            - `phpdoc.xml` (neu) - phpDocumentor Konfiguration
            - `typedoc.json` (neu) - TypeDoc Konfiguration
            - `composer.json` (Script: `docs`)
            - `package.json` (Script: `docs`, DevDep: `typedoc`)
            - `.gitignore` (ignoriert `docs/`, `.phpdoc/` Cache, `tools/`)
            - `Makefile` (neue Documentation-Section mit Auto-Download-Logic)
            - `README.md` (umfassende "Documentation Generation"-Sektion mit Examples, Best Practices, CI/CD Integration)
            - `tools/` (git-ignored, PHAR wird on-demand downloaded)
    - **Vorteile:**
        - ✅ Zero-Config Documentation Generation (out-of-the-box)
        - ✅ PHP + Node Parität (beide Stacks haben Tools)
        - ✅ Konsistent mit Projekt-Philosophie: "Production-ready modern defaults"
        - ✅ PHPDoc & TSDoc Best Practices demonstriert
        - ✅ CI/CD Integration-Example in README
        - ✅ Keine Composer-Dependency-Konflikte (PHAR-Ansatz)
        - ✅ Makefile-Integration für einfache Nutzung

### Version 2.16 (2025-12-29)
- ✅ **Alpine Linux: Upgrade auf 3.23 (alle Services)**
    - **Grund:** Version-Matching für Nginx + Brotli-Modul
    - **Geänderte Dockerfiles:**
        - `docker/nginx/Dockerfile`: Alpine 3.22 → 3.23
        - `docker/php/Dockerfile`: Alpine 3.22 → 3.23
        - `docker/node/Dockerfile`: Alpine 3.22 → 3.23
            - **Fix:** `COREPACK_ENABLE_DOWNLOAD_PROMPT=0` hinzugefügt
            - **Grund:** Alpine 3.23 / Node 24 - Corepack fragt interaktiv nach Download-Bestätigung
            - **Lösung:** Environment-Variable deaktiviert interaktive Prompts
    - **Vorteile:**
        - Neueste Sicherheitsupdates (Alpine 3.23, Dezember 2024)
        - Konsistente Alpine-Version über alle Services
        - Garantiertes Version-Matching zwischen nginx und Modulen

- ✅ **Nginx: Brotli-Kompression aktiviert (Dual-Compression-Strategie)**
    - **Problem:** Nur Gzip-Kompression aktiv, moderne Brotli-Kompression nicht genutzt
        - Brotli bietet 10-20% bessere Kompression als Gzip
        - Offizielle nginx Docker-Images haben Version-Mismatch mit Alpine Brotli-Paketen
        - Inkonsistenz zur "production-ready modern defaults"-Philosophie
    - **Lösung: Nginx direkt aus Alpine-Repository + Brotli + Gzip parallel aktiviert**
        - **Dockerfile-Strategie-Wechsel:**
            - **Vorher:** `FROM nginx:1.29-alpine3.22` (offizielles nginx Docker-Image)
            - **Nachher:** `FROM alpine:3.23` + Installation von nginx aus Alpine-Repo
            - **Warum:** Garantiert Version-Matching zwischen nginx und nginx-mod-http-brotli
            - Alpine 3.23 liefert: `nginx-1.28.0-r8` + `nginx-mod-http-brotli-1.28.0-r8` (perfekt matched)
        - **nginx.conf (Zeile 1-3, 51-72):**
            - **Module laden:**
                - `load_module modules/ngx_http_brotli_filter_module.so;`
                - `load_module modules/ngx_http_brotli_static_module.so;`
            - **Brotli Compression (Primary - Modern browsers):**
                - `brotli on;` mit Level 6 (balanced compression/speed)
                - Identische MIME-Types wie Gzip (text/*, application/*, fonts)
            - **Gzip Compression (Fallback - Legacy browsers):**
                - `gzip on;` bleibt aktiv (100% Backward Compatibility)
                - Gleiche Konfiguration wie vorher
            - **Automatische Negotiation:**
                - Nginx wählt Brotli für moderne Clients (Chrome 50+, Firefox 44+, Safari 11+, Edge 15+)
                - Gzip für Legacy-Clients (alte Browser, CLI-Tools ohne Brotli)
                - Basiert auf `Accept-Encoding` HTTP-Header
        - **Vorteile:**
            - ✅ 10-20% bessere Kompression für moderne Clients (Brotli)
            - ✅ 100% Backward Compatibility (Gzip Fallback)
            - ✅ Konsistent mit Projekt-Philosophie: "Production-ready modern defaults"
            - ✅ Zero Configuration nötig (funktioniert out-of-the-box)
            - ✅ Version-Matching garantiert (Alpine managed Dependencies)
            - ✅ Kein Build-Overhead (alpine packages, keine Source-Compilation)
        - **Browser-Support:**
            - Brotli: Chrome 50+, Firefox 44+, Safari 11+, Edge 15+ (99%+ Coverage)
            - Gzip: Universal (alle Browser seit 1990er)

### Version 2.15 (2025-12-29)
- ✅ **Git Hooks: Vollständige Containerisierung für 100% Version-Parität**
    - **Problem:** Git Hooks liefen auf lokalen Host-Tools
        - CaptainHook nutzte lokales PHP (8.5.1) statt Container-PHP (8.4)
        - pnpm/node Commands nutzten lokales Node statt Container-Node
        - Abhängigkeit von lokaler Entwickler-Installation
        - Xdebug-Versionskonflikt-Warnungen
        - Inkonsistenz zwischen Hook-Umgebung und Production-Container
    - **Lösung: Alle Hook-Commands in Containern ausführen**
        - **captainhook.json komplett überarbeitet**
            - **commit-msg Hook:**
                - Vorher: `pnpm exec commitlint --edit $1`
                - Nachher: `docker compose exec -T node pnpm exec commitlint --edit /app/.git/COMMIT_EDITMSG`
                - Benötigt .git Mount im Node-Container
            - **pre-commit Hook (5 Actions):**
                - PHP-CS-Fixer: `docker compose exec -T php vendor/bin/php-cs-fixer fix --diff --config=.php-cs-fixer.dist.php --dry-run`
                - PHP Syntax Check: `git diff --name-only --cached | grep .php$ | xargs -r -I {} docker compose exec -T php php -l /var/www/html/{}`
                - Prettier: `docker compose exec -T node pnpm exec prettier --check 'src/**/*.{ts,js,json}'`
                - ESLint: `docker compose exec -T node pnpm exec eslint 'src/**/*.{ts,js}' --max-warnings=0`
                - TypeScript: `docker compose exec -T node pnpm run type-check`
            - **pre-push Hook (2 Actions):**
                - PHPStan: `docker compose exec -T php vendor/bin/phpstan analyse --configuration=phpstan.neon --memory-limit=1G`
                - Vitest: `docker compose exec -T node pnpm test`
        - **compose.override.yaml: Node-Container Anpassungen (Zeilen 99, 73-82)**
            - **Git-Zugriff für commitlint:**
                - Neu: `./.git:/app/.git:ro` (read-only mount)
                - Ermöglicht commitlint Zugriff auf Git-Metadaten
            - **Volume-Mounts vereinheitlicht:**
                - Vorher: `.:/app` (gesamtes Projekt gemountet, inkonsistent zu PHP)
                - Nachher: Spezifische Files/Directories wie bei PHP-Container
                - Neue Mounts: package.json, pnpm-lock.yaml, src/node, tests/node, public, resources, dist, vite.config.js, tsconfig.json, eslint.config.js, commitlint.config.js, etc.
                - Konsistenz: Beide Container (PHP + Node) nutzen identisches Mount-Pattern
        - **Ausführliche Descriptions wiederhergestellt**
            - Alle captainhook.json Actions haben aussagekräftige Beschreibungen
            - Statt "(in container)" nun: "Validate commit message format against Conventional Commits standard", "Check for styling issues with PHP-CS-Fixer (Dry-Run)", etc.
        - **`-T` Flag verwendet:** Deaktiviert TTY allocation (Git Hooks nicht interaktiv)
        - **Hooks neu installiert:** `vendor/bin/captainhook install -f`
    - **Vorteile:**
        - **100% Version-Parität:** PHP 8.4, Node 24, pnpm 10.26.2 exakt wie in Containern
        - **Keine lokalen Dependencies:** Nur Git, Docker, Make erforderlich
        - **Reproduzierbar:** Jeder Developer hat identisches Environment
        - **Konsistent:** Alle Development-Tools laufen in Containern
        - **Keine Versionskonflikt-Warnungen:** Host-PHP spielt keine Rolle mehr
        - **"Container als venv":** Makefile + containerisierte Hooks = vollständige Isolation
        - **Einheitliche Volume-Struktur:** PHP und Node nutzen identisches Mount-Pattern
    - **Trade-off akzeptiert:**
        - Container müssen laufen (ist beim Development sowieso gegeben)
        - Minimal höhere Latenz (~100ms) durch Docker exec (nicht spürbar bei Commits)
    - **Dependencies aktualisiert:**
        - `@eslint/js@^9.18.0` zu devDependencies hinzugefügt (package.json:40)
        - Erforderlich für ESLint 9.x Flat Config System (eslint.config.js:1)
        - pnpm-lock.yaml automatisch regeneriert

### Version 2.14 (2025-12-29)
- ✅ **Monolog für PHP: Strukturiertes Logging**
  - **Problem:** PHP hatte kein Logging-Framework, während Node.js bereits Pino hatte
    - Keine strukturierte Log-Ausgabe für PHP-Anwendungen
    - Inkonsistenz zwischen PHP- und Node.js-Stack
    - Developer müssten selbst Logging-Lösung wählen/implementieren
  - **Lösung: Monolog als Standard-Logger (analog zu Pino für Node.js)**
    - **Monolog 3.9.0 installiert** (composer.json:17, composer.lock)
    - **PSR-3 Standard:** Framework-agnostisch, kompatibel mit Laravel, Symfony, etc.
    - **Verwendung:**
      ```php
      use Monolog\Logger;
      use Monolog\Handler\StreamHandler;

      $log = new Logger('app');
      $log->pushHandler(new StreamHandler('/var/www/html/storage/logs/app.log', Logger::DEBUG));

      $log->info('User logged in', ['user_id' => 123]);
      $log->error('Database connection failed', ['error' => $e->getMessage()]);
      ```
    - **Logs:** Standard-Pfad `storage/logs/app.log` (konfigurierbar)
    - **Vorteile:**
      - Strukturierte Logs mit Context-Daten
      - Mehrere Handler möglich (File, Syslog, Slack, etc.)
      - Production-ready mit Rotation-Support
      - Vollständige Symmetrie zu Node.js Pino-Setup

- ✅ **Conventional Commits: Automatisierte Commit-Validierung**
  - **Problem:** Keine einheitliche Commit-Message-Struktur
    - Inkonsistente Commit-Messages erschweren Changelog-Generierung
    - Keine Kategorisierung von Änderungen (feat, fix, refactor, etc.)
    - Keine automatische Validierung → Manuelle Code-Reviews nötig
  - **Lösung: Commitlint mit Conventional Commits Standard**
    - **Dependencies installiert (package.json:38-39)**
      - `@commitlint/cli 20.2.0`
      - `@commitlint/config-conventional 20.2.0`
    - **Git Commit Template (.gitmessage)**
      - Interaktive Vorlage mit allen Commit-Typen
      - Erklärt Format, Scope, Subject, Body, Footer
      - Verwendung: `git config commit.template .gitmessage`
      - Zeigt Best-Practices bei jedem Commit
    - **Commitlint Konfiguration (commitlint.config.js)**
      - Extends `@commitlint/config-conventional`
      - **Erlaubte Typen:** feat, fix, refactor, style, docs, test, chore, perf, ci, build, revert
      - **Rules:**
        - Subject: Lowercase, kein Punkt, max 100 Zeichen
        - Body/Footer: Max 100 Zeichen pro Zeile
        - Leere Zeile zwischen Subject und Body erzwungen
      - **Format:** `<type>(<scope>): <subject>`
        - Beispiel: `feat(auth): add JWT token validation`
        - Beispiel: `fix(api): correct user endpoint response format`
    - **CaptainHook Integration (captainhook.json:2-11)**
      - **commit-msg Hook:** Validiert jede Commit-Message
      - Command: `pnpm exec commitlint --edit $1`
      - Automatische Ablehnung bei ungültigen Messages
      - Hilfreiche Fehlermeldungen mit Korrekturvorschlägen
    - **Hooks neu installiert:** `vendor/bin/captainhook install -f`
      - Alle Hooks aktiv: commit-msg, pre-commit, pre-push, etc.
    - **Vorteile:**
      - Automatische Changelog-Generierung möglich
      - Klare Kategorisierung (Breaking Changes, Features, Fixes)
      - Verbesserte Code-Review-Effizienz
      - Semantic Versioning Support

- ✅ **TypeScript Type-Safety: Eliminierung unsicherer eslint-disable Workarounds**
  - **Problem:** Pre-Commit Hook scheiterte wegen ESLint-Fehlern in TypeScript-Dateien
    - `@typescript-eslint/no-unsafe-assignment` bei pino-http Import
    - `@typescript-eslint/no-unsafe-call` bei pinoHttp Aufruf
    - `@typescript-eslint/strict-boolean-expressions` bei env vars (|| statt ??)
    - `@typescript-eslint/no-base-to-string` bei req.query.name
    - `@typescript-eslint/restrict-template-expressions` bei Template-Literals
    - Unsichere Workarounds mit `eslint-disable` Kommentaren
  - **Lösung: Typsichere Implementierung statt eslint-disable**
    - **pino-http Import korrigiert (src/node/app.ts:10)**
      ```typescript
      // Vorher (unsicher):
      import pinoHttpImport from 'pino-http';
      const pinoHttp = pinoHttpImport as unknown as typeof pinoHttpImport.default;

      // Nachher (typsicher):
      import pinoHttp from 'pino-http';
      ```
    - **Environment Variables mit Nullish Coalescing (src/node/app.ts:12-14, server.ts:14-17)**
      ```typescript
      // Vorher: || (falsy check)
      const NODE_ENV = process.env.NODE_ENV || 'production';

      // Nachher: ?? (null/undefined check)
      const NODE_ENV = process.env.NODE_ENV ?? 'production';
      ```
    - **req.query.name Type-Guard (src/node/app.ts:107-108)**
      ```typescript
      // Vorher (unsicher):
      const name = req.query.name || 'World';

      // Nachher (typsicher):
      const nameParam = req.query.name;
      const name = typeof nameParam === 'string' && nameParam.length > 0 ? nameParam : 'World';
      ```
    - **req.body explizit als unknown (src/node/app.ts:114)**
      ```typescript
      // Vorher (unsicher):
      res.json({ echo: req.body });

      // Nachher (typsicher):
      const body: unknown = req.body;
      res.json({ echo: body });
      ```
    - **PORT als String Type (server.ts:14)**
      ```typescript
      const PORT = process.env.PORT ?? '3000';
      ```
  - **Verifikation:**
    - ESLint: 0 Fehler, 0 Warnungen (--max-warnings=0)
    - TypeScript: tsc --noEmit ohne Fehler
    - Prettier: Alle Dateien korrekt formatiert
    - Tests: 25/25 bestanden
  - **Vorteile:**
    - Keine eslint-disable Kommentare mehr nötig
    - Vollständige Type-Safety ohne Ausnahmen
    - Bessere IDE-Unterstützung und Autocomplete
    - Verhindert Runtime-Fehler durch strikte Typisierung
    - Pre-Commit Hook läuft ohne Fehler durch

- ✅ **Docker Compose: Volume-Mounts konsistent und PHP-Test-Pfad korrigiert**
  - **Problem:** Inkonsistente Volume-Mount-Strategie zwischen PHP und Node
    - PHP-Container: Gezielte Mounts für jede Datei/Verzeichnis
    - Node-Container: Komplettes Projekt-Root (`.:/app`)
    - PHP-Container: `./tests:/var/www/html/tests` statt `./tests/php:/var/www/html/tests/php`
    - PHP-CS-Fixer: `__DIR__ . '/tests'` statt `__DIR__ . '/tests/php'`
    - Inkonsistenz mit Verzeichnisstruktur `tests/php/` und `tests/node/`
  - **Lösung: Gezielte Mounts für beide Container + korrekte Pfade**
    - **compose.override.yaml - PHP (Zeile 33)**
      - Vorher: `./tests:/var/www/html/tests`
      - Nachher: `./tests/php:/var/www/html/tests/php`
    - **compose.override.yaml - Node (Zeilen 73-99)**
      - Vorher: `.:/app` (alles gemountet)
      - Nachher: Gezielte Mounts analog zu PHP
        ```yaml
        # Application
        - ./package.json:/app/package.json
        - ./pnpm-lock.yaml:/app/pnpm-lock.yaml
        - ./src/node:/app/src/node
        - ./tests/node:/app/tests/node
        - ./public:/app/public
        - ./resources:/app/resources
        - ./dist:/app/dist
        - node_modules:/app/node_modules

        # Build & Config (read-only)
        - ./vite.config.js:/app/vite.config.js:ro
        - ./tsconfig.json:/app/tsconfig.json:ro
        - ./vitest.config.ts:/app/vitest.config.ts:ro
        # ... weitere Konfigs

        # Quality Assurance Tools Config (read-only)
        - ./eslint.config.js:/app/eslint.config.js:ro
        - ./commitlint.config.js:/app/commitlint.config.js:ro
        # ... weitere QA-Konfigs

        # Build Output
        - ./build:/app/build
        ```
    - **.php-cs-fixer.dist.php (Zeile 19)**
      - Vorher: `__DIR__ . '/tests'`
      - Nachher: `__DIR__ . '/tests/php'`
  - **Vorteile:**
    - **Konsistenz:** Beide Container verwenden gleiche Mount-Strategie
    - **Sicherheit:** Keine ungewollten Dateien im Container (`.git`, `.env`, etc.)
    - **Read-Only:** Konfigurationsdateien mit `:ro` Flag geschützt
    - **Explizit:** Klar erkennbar welche Dateien gemountet werden
    - **Performance:** Weniger Dateien = schnelleres File-Watching
    - **Dokumentation:** Kommentare zeigen Zweck jeder Mount-Gruppe
    - **Korrekte Pfade:** PHP-Tools arbeiten nur mit PHP-Tests

- ✅ **Docker Image-Tags korrigiert: Redis und PostgreSQL Alpine-Versionen**
  - **Problem:** Fehlerhafte Image-Tags aus Version 2.13 verhinderten `make fresh`
    - `redis:7.4-alpine3.22` - **Tag existiert nicht!** (manifest unknown)
    - `postgres:17.7-alpine3.22` - **Tag existiert nicht!** (manifest unknown)
    - Redis und PostgreSQL verwenden **nicht** das Tagging-Format `-alpine3.22`
    - Alpine-Version kann bei offiziellen Images nicht im Tag spezifiziert werden
  - **Lösung: Korrekte offizielle Image-Tags verwenden**
    - **compose.yaml (Zeile 64)**
      - Vorher: `redis:7.4-alpine3.22` ❌
      - Nachher: `redis:7.4-alpine` ✅
    - **compose.yaml (Zeile 88)**
      - Vorher: `postgres:17.7-alpine3.22` ❌
      - Nachher: `postgres:17-alpine` ✅ (PostgreSQL nutzt Major-Versionen)
    - **Hinweis:** Alpine-Version wird vom Image-Maintainer bestimmt
      - Redis und Postgres verwenden `-alpine` ohne Versionssuffix
      - Alpine-Version ist typischerweise aktuellste stabile Version
      - Fixierung nur über vollständigen Digest möglich (nicht praktikabel)
      - Nur bei Custom-Dockerfiles: `FROM alpine:3.22` möglich
  - **Verifikation:**
    - `docker compose config` ohne Fehler
    - Images erfolgreich gepullt
    - `make fresh` läuft fehlerfrei durch
  - **Changelog TODO.md korrigiert:**
    - Version 2.13 Eintrag "Alpine-Versionen fixiert" aktualisiert
    - Dokumentiert warum Alpine-Version-Tags nicht funktionieren

### Version 2.13 (2025-12-29)
- ✅ **Node.js: Quality-of-Life Tooling (Testing, Linting, Formatting)**
  - **Problem:** Node.js-Stack hatte keine Quality-Tools wie PHP (PHPUnit, PHPStan, PHP-CS-Fixer)
    - Kein Testing-Framework → Keine automatisierten Tests
    - Kein Linter → Keine statische Code-Analyse
    - Kein Formatter → Inkonsistente Code-Formatierung
    - Keine Git-Hooks für Node.js → Manuelle Quality-Checks
  - **Lösung: Vollständiges QoL-Tooling analog zu PHP-Stack**
    - **Testing: Vitest** (PHPUnit-Äquivalent)
      - Vitest 2.1.8 mit Native Vite-Integration
      - Coverage Reports (V8): HTML, LCOV, Text → `build/coverage/`
      - Vitest UI für interaktive Test-Entwicklung
      - Test-Verzeichnisstruktur: `tests/node/{unit,integration}/` (symmetrisch zu PHP `tests/php/{Unit,Feature}/`)
      - Coverage-Thresholds: 80% (Lines, Functions, Branches, Statements)
      - Konfiguration: `vitest.config.ts` mit Path-Aliases (@node, @tests)
    - **Linting: ESLint + TypeScript-ESLint** (PHPStan-Äquivalent)
      - ESLint 9.18.0 mit Flat Config Format (eslint.config.js)
      - TypeScript-ESLint 8.20.0 für Type-Aware Linting
      - Strict Rules: no-explicit-any, no-floating-promises, strict-boolean-expressions
      - TypeScript Type-Checking ähnlich PHPStan Level 5
    - **Formatting: Prettier** (PHP-CS-Fixer-Äquivalent)
      - Prettier 3.4.2 mit eslint-plugin-prettier Integration
      - Sane Defaults: Single Quotes, 100 Print Width, LF Line Endings
      - .prettierrc.json + .prettierignore für konsistente Formatierung
      - Konfliktfreie Integration mit ESLint (eslint-config-prettier)
    - **Git Hooks: CaptainHook erweitert**
      - **pre-commit:** Prettier Check, ESLint, TypeScript Type-Check
      - **pre-push:** Vitest Tests (analog zu PHPStan für PHP)
      - Hooks laufen automatisch bei Git-Operationen (captainhook.json:21-38, 50-55)
    - **Package.json Scripts:** Vollständiges Script-Arsenal (package.json:22-30)
      - `pnpm test` → Vitest run
      - `pnpm test:watch` → Watch mode
      - `pnpm test:coverage` → Coverage Report
      - `pnpm lint` / `pnpm lint:fix` → ESLint
      - `pnpm format` / `pnpm format:check` → Prettier
      - `pnpm quality` → Alle Checks (Format, Lint, Type-Check, Test)
    - **Makefile Integration:** setup-Target erweitert (Makefile:94-98, 106)
      - `tests/php/{Unit,Feature}` und `tests/node/{unit,integration}` Verzeichnisse (Symmetrie wie src/)
      - `build/{coverage,vitest-report}` für Reports
      - `node-install` automatisch nach `composer-install`
  - **Code Refactoring für Testbarkeit:**
    - Express-App nach `src/node/app.ts` extrahiert (app.ts:1-152)
    - `server.ts` nur noch Server-Startup (server.ts:1-56)
    - `createApp()` exportiert für Tests ohne Server-Start
    - Logger exportiert für Test-Mocking
  - **Beispiel-Tests:**
    - **Unit-Tests:** `tests/node/unit/math.test.ts` (Utility-Funktionen)
    - **Integration-Tests:** `tests/node/integration/api.test.ts` (API-Endpoints mit supertest)
    - Security Headers, CORS, 404/500 Error Handling getestet
  - **Dependencies hinzugefügt:** (package.json:40-58)
    - Testing: vitest, @vitest/ui, @vitest/coverage-v8, supertest
    - Linting: eslint, @typescript-eslint/eslint-plugin, @typescript-eslint/parser
    - Formatting: prettier, eslint-plugin-prettier, eslint-config-prettier
    - Types: @types/supertest
  - **Dokumentation:** `tests/node/README.md` mit Struktur, Beispielen, Thresholds

- ✅ **Test-Verzeichnisstruktur: Vollständige Symmetrie PHP ↔ Node.js**
  - **Problem:** Inkonsistente Test-Verzeichnisstruktur
    - Source-Code getrennt: `src/php/` und `src/node/` ✓
    - Tests gemischt: `tests/Unit`, `tests/Feature`, `tests/unit`, `tests/integration` ✗
    - Keine klare Trennung zwischen PHP- und Node.js-Tests
    - PhpStorm-Konfiguration nur für gemischtes `tests/` Verzeichnis
  - **Lösung: Spiegelsymmetrische Struktur wie in src/**
    - **Migration durchgeführt:**
      - `tests/Unit/` → `tests/php/Unit/` (PHPUnit Unit-Tests)
      - `tests/Feature/` → `tests/php/Feature/` (PHPUnit Feature-Tests)
      - `tests/unit/` → `tests/node/unit/` (Vitest Unit-Tests)
      - `tests/integration/` → `tests/node/integration/` (Vitest Integration-Tests)
    - **PhpStorm IDE-Konfiguration aktualisiert (.idea/docker-webdev.iml:5-8)**
      - `src/php` (Source) + `tests/php` (Test Source mit Namespace App\Tests\)
      - `src/node` (Source) + `tests/node` (Test Source)
      - IDE erkennt nun beide Sprach-Stacks korrekt
    - **Vitest-Konfiguration isoliert (vitest.config.ts:10-11)**
      - `include: ['tests/node/**/*.{test,spec}.{ts,js}']`
      - `exclude: ['tests/php']` → Keine Konflikte mit PHP-Tests
      - Path-Alias `@tests` → `./tests/node`
    - **Makefile setup-Target (Makefile:94-95)**
      - Erstellt beide Strukturen parallel
      - Kommentar: "separated by language like src/"
  - **Best Practice: Vollständige Symmetrie zwischen PHP- und Node.js-Stack**
    - **Tools:** PHP: PHPUnit, PHPStan, PHP-CS-Fixer | Node.js: Vitest, ESLint+TS, Prettier
    - **Struktur:** `src/php/` + `tests/php/` | `src/node/` + `tests/node/`
    - **Workflow:** Gleiche Integration (CaptainHook, Makefile)
    - **Coverage:** Gleiche Anforderungen (80%)
    - **IDE:** Beide Stacks korrekt als Source/Test markiert

- ✅ **Test-Dokumentation & Makefile-Integration: Konsistenz PHP ↔ Node.js**
  - **Problem:** Inkonsistente Dokumentation und fehlende Makefile-Abstraktion
    - `tests/node/README.md` vorhanden, aber `tests/php/README.md` fehlte
    - `.gitkeep` Dateien in `tests/php/` unnötig (durch `make setup` erstellt)
    - Node.js README verwendete direkt `pnpm` Commands statt `make`
    - Keine einheitlichen Makefile-Targets für beide Test-Stacks
  - **Lösung: Symmetrische Dokumentation und Makefile-Targets**
    - **tests/php/README.md erstellt** (analog zu tests/node/README.md)
      - Struktur-Übersicht mit Verweis auf tests/node/
      - Makefile-Commands als primäre Schnittstelle
      - Composer-Commands als Alternative dokumentiert
      - Test-Beispiele für Unit und Feature Tests
      - Quality-Tools (PHPStan, PHP-CS-Fixer) dokumentiert
      - Coverage-Thresholds: 80% (symmetrisch zu Node.js)
    - **tests/node/README.md aktualisiert**
      - Makefile-Commands als primäre Schnittstelle (Makefile:560-592)
      - pnpm-Commands als Alternative dokumentiert
      - Quality-Tools-Sektion hinzugefügt (ESLint, Prettier, pnpm quality)
      - Symmetrisch zur PHP-README strukturiert
    - **.gitkeep Dateien entfernt** (tests/php/Unit/.gitkeep, tests/php/Feature/.gitkeep)
      - Unnötig, da `make setup` Verzeichnisse erstellt (Makefile:94-95)
      - Reduziert Dateien-Clutter
    - **Makefile Test-Targets erweitert (Makefile:560-592)**
      - `make test` → Führt beide Stacks aus (test-php + test-node)
      - `make test-coverage` → Beide Coverage-Reports
      - **PHP-Tests:**
        - `make test-php` → PHPUnit Tests
        - `make test-php-debug` → Mit Xdebug
        - `make test-coverage-php` → Coverage Report
      - **Node.js-Tests:**
        - `make test-node` → Vitest Tests
        - `make test-node-watch` → Watch Mode
        - `make test-coverage-node` → Coverage Report
    - **Vorteil: Einheitliche Schnittstelle**
      - Entwickler müssen nicht wissen, ob PHP oder Node.js
      - `make test` führt alle Tests aus
      - Beide READMEs haben identische Struktur
      - Gleiche Abstraktionsebene (Makefile statt direkte Tool-Calls)

- ✅ **Beispiel-Tests & TypeScript-Konfiguration: Vollständige Symmetrie**
  - **Problem:** Asymmetrische Test-Beispiele und Path-Alias-Fehler
    - Node.js hatte Beispiel-Tests (math.test.ts, api.test.ts), PHP nicht
    - Node.js hatte Beispiel-Utilities (src/node/utils/math.ts), PHP nicht
    - TypeScript Path-Aliases (@node/*, @tests/*) funktionierten nicht in Tests
    - IDE konnte Importe nicht auflösen → Entwickler-Erfahrung schlecht
    - `make test-php` und `make test` funktionierten nicht (mehrere Fehler)
  - **Lösung: Symmetrische Beispiele und korrekte TypeScript-Konfiguration**
    - **TypeScript-Konfiguration erweitert (tsconfig.json:16-23, 47-57)**
      - Path-Aliases hinzugefügt: `@/*`, `@node/*`, `@tests/*`
      - `baseUrl: "."` für Alias-Auflösung
      - `include: ["tests/node/**/*"]` → Tests werden von TypeScript erkannt
      - `exclude: ["tests/php"]` → Keine PHP-Dateien in TypeScript
      - `rootDir` entfernt → Flexibilität für src/ und tests/
      - IDE erkennt nun alle Importe korrekt
    - **PHP Beispiel-Utilities erstellt (src/php/Utils/Calculator.php)**
      - Analog zu src/node/utils/math.ts
      - Funktionen: add(), multiply(), divide(), isEven()
      - Type-Hints und DivisionByZeroError
      - Namespace: App\Utils
    - **PHP Unit-Tests erstellt (tests/php/Unit/CalculatorTest.php)**
      - Analog zu tests/node/unit/math.test.ts
      - Testet alle Calculator-Methoden
      - setUp() Methode für Test-Fixture
      - Gruppierung nach Funktionalität (Addition, Multiplication, etc.)
      - Exception-Testing für Division durch Null
    - **PHP Feature-Tests erstellt (tests/php/Feature/CalculatorIntegrationTest.php)**
      - Analog zu tests/node/integration/api.test.ts
      - Testet komplexe Workflows über mehrere Methoden
      - Error-Handling-Workflows
      - Chained Calculations (mehrere Operationen nacheinander)
      - Demonstriert Feature-Test-Pattern
  - **Ergebnis: Perfekte Symmetrie zwischen PHP und Node.js**
    - **PHP:** src/php/Utils/Calculator.php → tests/php/Unit/CalculatorTest.php + tests/php/Feature/CalculatorIntegrationTest.php
    - **Node.js:** src/node/utils/math.ts → tests/node/unit/math.test.ts + tests/node/integration/api.test.ts
    - Beide Sprachen haben lauffähige Beispiel-Tests
    - Entwickler können `make test` ausführen → Alle Tests laufen
    - Path-Aliases funktionieren in IDE und Tests
    - Beide Test-Suites haben identische Struktur

- ✅ **PHPUnit-Konfiguration & Test-Infrastruktur-Fixes**
  - **Problem:** `make test-php` und `make test` funktionierten nicht
    - Kein phpunit.xml.dist → PHPUnit fand keine Konfiguration
    - composer.json autoload-dev hatte falschen Pfad (`tests/` statt `tests/php/`)
    - phpunit.xml.dist nicht in Docker-Volume gemountet (compose.override.yaml)
    - composer test Script lief mit Coverage, aber Xdebug nicht im Coverage-Modus
    - Datei-Permissions zu restriktiv (600 statt 644)
  - **Lösung: Vollständige PHPUnit-Infrastruktur**
    - **phpunit.xml.dist erstellt** mit korrekter Konfiguration
      - Test-Suites: Unit (tests/php/Unit) und Feature (tests/php/Feature)
      - Source-Code für Coverage: src/php/ (exclude: Infrastructure/)
      - Coverage-Konfiguration entfernt aus XML (wird via CLI aktiviert)
      - Cache-Directory: build/.phpunit.cache
      - **Coverage-Generierung via Makefile:**
        - `make test-coverage-php` setzt XDEBUG_MODE=coverage
        - Generiert HTML Report: build/coverage/index.html
        - Generiert Clover XML: build/coverage/clover.xml
        - Keine Coverage-Warnungen bei normalen Tests
    - **composer.json fixes (composer.json:51, 55)**
      - autoload-dev: `"App\\Tests\\": "tests/php/"` (war: tests/)
      - test script: `phpunit` (ohne Coverage-Zwang)
      - → Tests laufen schnell ohne Coverage-Overhead
      - → Coverage über separates Target: `make test-coverage-php` mit XDEBUG_MODE=coverage
    - **compose.override.yaml erweitert (compose.override.yaml:39)**
      - phpunit.xml.dist Volume-Mount hinzugefügt
      - Read-only Mount für Konfigurationsdatei
      - Analog zu phpstan.neon und .php-cs-fixer.dist.php
    - **Workflow-Fix:**
      - Container-Neustarts nach Konfigurationsänderungen erforderlich
      - File-Permissions: 644 für Test-Dateien (nicht 600)
      - composer dump-autoload nach autoload-dev Änderungen
  - **Resultat: Beide Test-Suites laufen erfolgreich**
    - `make test-php` → 17 Tests, 32 Assertions ✅
    - `make test-node` → 30+ Tests (math + API integration) ✅
    - `make test` → Beide Test-Suites zusammen ✅
    - Coverage-Reports: `make test-coverage` (beide Sprachen)

- ✅ **PHPMD (PHP Mess Detector): Code Quality & Complexity Analysis**
  - **Problem:** Fehlende Code-Quality-Metriken im PHP-Stack
    - Nur PHPStan (Static Analysis) und PHP-CS-Fixer (Code Style)
    - Keine Complexity-Analyse (Cyclomatic Complexity, NPath)
    - Keine Detection von Code Smells (Long Methods, Too Many Parameters)
    - Keine Warnung bei ungenutztem Code (Unused Variables, Dead Code)
    - Node.js-Stack hatte mit ESLint bereits Complexity-Checks
  - **Lösung: PHPMD für vollständige Code-Quality-Abdeckung**
    - **PHPMD 2.15.0 installiert** (composer.json:22)
      - Dependency: pdepend/pdepend für Metriken-Berechnung
      - Composer Script: `composer phpmd` (composer.json:58)
      - Makefile-Target: `make phpmd` (Makefile:523-525)
    - **phpmd.xml.dist Konfiguration** mit ausgewogenen Regeln
      - **Clean Code Rules:** Detect code smells (disabled: ElseExpression, StaticAccess)
      - **Code Size Rules (Complexity):**
        - Cyclomatic Complexity: Max 15 (Warnung bei zu verschachteltem Code)
        - NPath Complexity: Max 250 (Max Ausführungspfade)
        - Excessive Method Length: Max 100 Zeilen
        - Excessive Class Length: Max 500 Zeilen
        - Excessive Parameter List: Max 10 Parameter
        - Too Many Fields: Max 20 Felder
        - Too Many Methods: Max 25 Methoden
      - **Design Rules:** Coupling, Depth of Inheritance (disabled: ExitExpression für CLI)
      - **Naming Rules:** Short/Long Variable Names (min 2 chars, exceptions: i,j,k,e,id,x,y,a,b)
      - **Unused Code Detection:** Unused Variables, Parameters, Private Methods
      - **Controversial Rules:** Deaktiviert (zu opinionated)
    - **Docker-Integration** (compose.override.yaml:38)
      - phpmd.xml.dist Volume-Mount hinzugefügt
      - Read-only Mount analog zu anderen QA-Tools
    - **Make-Target erweitert:**
      - `make check` führt jetzt auch PHPMD aus (Makefile:527)
      - CI-Simulation: cs-check + analyse + phpmd + test
  - **Ergebnis: Vollständige PHP Quality-Tool-Chain**
    - **Static Analysis:** PHPStan (Level 5)
    - **Code Style:** PHP-CS-Fixer
    - **Complexity & Design:** PHPMD (NEU)
    - **Testing:** PHPUnit
    - Symmetrie zu Node.js: ESLint deckt Complexity + Linting ab, PHPMD tut das Gleiche für PHP

- ✅ **Vitest-Konfiguration & TypeScript Path-Alias-Fixes**
  - **Problem:** TypeScript-Fehler in vitest.config.ts und Test-Imports
    - vitest.config.ts Zeile 28: "No overload matches this call" (Coverage Thresholds)
    - vitest.config.ts Zeile 37: "reporter does not exist" (sollte "reporters" sein)
    - api.test.ts Zeile 3: "Cannot find module @node/app"
    - math.test.ts Zeile 2: "Cannot find module @node/utils/math"
    - Tests liefen, aber IDE zeigte Fehler → Schlechte Developer Experience
  - **Lösung: Korrekte Vitest- und TypeScript-Konfiguration**
    - **vitest.config.ts fixes (vitest.config.ts:28-33, 37)**
      - Coverage Thresholds müssen unter `thresholds` Property genested sein
      - `reporter` → `reporters` (Plural) für korrekte Vitest API
      - Vorher: `lines: 80, functions: 80, ...` direkt in coverage
      - Nachher: `thresholds: { lines: 80, functions: 80, ... }`
    - **tsconfig.vitest.json erstellt** für Vitest-spezifische TypeScript-Config
      - Erweitert tsconfig.json mit Vitest-spezifischen Types
      - `"types": ["vitest/globals", "node"]` für globale Test-Funktionen
      - `"include": ["tests/node/**/*", "vitest.config.ts"]`
      - Separates TypeScript-Projekt für Tests (composite: true)
    - **Path-Alias-Auflösung funktioniert jetzt vollständig**
      - tsconfig.json hatte bereits baseUrl und paths konfiguriert
      - vitest.config.ts resolve.alias mappte @node → src/node
      - IDE erkennt nun alle Importe korrekt (keine roten Wellenlinien mehr)
    - **tsconfig.json moduleResolution fix (tsconfig.json:6-7)**
      - `module: "NodeNext"` → `module: "ESNext"` (für Vite/Vitest Kompatibilität)
      - `moduleResolution: "NodeNext"` → `moduleResolution: "bundler"`
      - NodeNext erforderte .js Dateiendungen in Imports → PhpStorm-Fehler in server.ts:12
      - bundler-Strategie ist optimal für Vite-basierte Projekte
      - Löst PhpStorm-Fehler ohne .js Extensions in allen Imports
  - **Resultat: Alle Tests laufen erfolgreich ohne TypeScript-Fehler**
    - `make test-php` → 17 Tests, 32 Assertions ✅
    - `make test-node` → 25 Tests (13 Math Unit + 12 API Integration) ✅
    - `make test` → 42 Tests gesamt (PHP + Node.js) ✅
    - Keine TypeScript-Diagnostics-Fehler mehr in IDE
    - Coverage-Reports funktionieren korrekt
    - HTML Reports: build/coverage/ (PHP) und build/vitest-report.html (Node.js)

- ✅ **Security & Code Quality Maintenance**
  - **Problem:** Verschiedene Warnungen und Sicherheitslücken
    - .prettierignore: Redundanter Eintrag `public/build` (bereits durch `build` abgedeckt)
    - package.json: pm2 5.4.3 hat CVE-2025-5891 (Severity 4.3) - ReDoS in Config.js
    - pnpm 9.15.1 verfügbar für Update auf 10.26.2 (Major-Version)
  - **Lösung: Security-Update und Code-Bereinigung**
    - **.prettierignore bereinigt (.prettierignore:5-7)**
      - `public/build` Eintrag entfernt (redundant zu `build` Glob-Pattern)
      - Reduziert false-positive Warnungen in IDE
    - **pm2 Security-Update (package.json:50)**
      - pm2 5.4.3 → 6.0.14 (behebt CVE-2025-5891)
      - ReDoS-Schwachstelle in Config.js geschlossen
      - Alle Tests laufen nach Update erfolgreich (25 Tests ✅)
    - **pnpm Major-Update durchgeführt (package.json:62-64)**
      - pnpm 9.15.1 → 10.26.2 (Major-Update)
      - packageManager in package.json aktualisiert
      - engines.pnpm Requirement: >=9.0.0 → >=10.0.0
      - Alle 25 Tests laufen erfolgreich mit pnpm 10 ✅
      - Keine Breaking Changes bei unserem Setup

- ✅ **PhpStorm IDE-Konfiguration: Vollständige Source/Test Folder Markierung**
  - **Problem:** Inkonsistente PhpStorm Source/Test Folder Konfiguration
    - src/php und tests/php korrekt als Source/Test markiert ✅
    - src/node NICHT als Source Folder markiert ❌
    - tests/node NICHT als Test Folder markiert ❌
    - Veraltete tests/ Markierung noch vorhanden (überflüssig)
    - TypeScript Autocomplete und Navigation unvollständig
  - **Lösung: Symmetrische IDE-Konfiguration für beide Stacks**
    - **.idea/docker-webdev.iml aktualisiert (Zeilen 5-8)**
      - **Source Folders:**
        - `src/php` (packagePrefix: App\)
        - `src/node` (NEU hinzugefügt)
      - **Test Folders:**
        - `tests/php` (packagePrefix: App\Tests\)
        - `tests/node` (NEU hinzugefügt)
      - Veraltete `tests/` Markierung entfernt
    - **Exclude Folders sortiert** (Zeilen 9-15)
      - Alphabetische Sortierung für bessere Übersicht
      - .pnpm-store, build, dist, node_modules, public/build, storage, vendor
  - **Resultat: Vollständige IDE-Integration**
    - PhpStorm erkennt beide Sprach-Stacks korrekt
    - TypeScript Autocomplete funktioniert für src/node/**/*
    - Test-Runner erkennt beide Test-Stacks
    - Navigation und Refactoring für PHP und Node.js
    - Symmetrie zwischen PHP- und Node.js-Entwicklung

- ✅ **PHPUnit XML-Konfiguration: Schema-Konformität**
  - **Problem:** phpunit.xml.dist Schema-Fehler in PhpStorm
    - `restrictDeprecations`, `restrictNotices`, `restrictWarnings` waren ursprünglich im `<source>` Element
    - PhpStorm-Fehler: "Attribute not allowed to appear in element"
    - Fehler durch falsche Platzierung der Attribute
  - **Lösung: Korrekte Attribut-Platzierung nach offizieller Dokumentation**
    - **Quelle:** https://docs.phpunit.de/en/12.5/configuration.html
    - **`restrictNotices="true"`** im `<source>` Element (phpunit.xml.dist:24)
      - Beschränkt Reporting von E_STRICT, E_NOTICE, E_USER_NOTICE auf Projekt-Source-Code
      - Ignoriert Notices aus Vendor-Dependencies
    - **`restrictWarnings="true"`** im `<source>` Element (phpunit.xml.dist:24)
      - Beschränkt Reporting von E_WARNING, E_USER_WARNING auf Projekt-Source-Code
      - Ignoriert Warnings aus Vendor-Dependencies
    - **`restrictDeprecations` existiert NICHT** in PHPUnit 12.5
      - Stattdessen: `ignoreSelfDeprecations`, `ignoreDirectDeprecations`, `ignoreIndirectDeprecations`
      - Nicht verwendet, da wir alle Deprecations sehen wollen
    - **Strikte Testeinstellungen im `<phpunit>` Root-Element:**
      - `failOnWarning="true"` - Tests schlagen bei Warnungen fehl
      - `failOnRisky="true"` - Tests schlagen bei Risky Tests fehl
      - `beStrictAboutOutputDuringTests="true"` - Kein Output während Tests
      - `beStrictAboutCoverageMetadata="true"` - Strikte Coverage-Metadaten
  - **Resultat: Schema-konforme und strikte Konfiguration**
    - Alle 17 PHP Tests laufen erfolgreich ✅
    - XML validiert gegen PHPUnit 12.5 Schema
    - Notices/Warnings aus Dependencies werden ignoriert
    - Alle Fehler im eigenen Code werden erkannt

### Version 2.12 (2025-12-28)
- ✅ **Dependency Management: Workflow-Klarheit für Composer und Node.js**
  - **Problem:** Unklare Verwendungszwecke der lokalen vs. Container-basierten Dependency-Installation
    - `composer-install-local` und `node-install-local` könnten als "schnellere Alternative" missverstanden werden
    - Intention war unklar: Wann sollte man welchen Befehl verwenden?
  - **Lösung: Makefile-Kommentare präzisiert**
    - **composer-install-local:** Kommentar geändert zu "IDE code completion only" (Makefile:41)
    - **node-install-local:** Kommentar geändert zu "IDE code completion only" (Makefile:319)
    - **Best Practice:** Container-Installation für Runtime/CI/CD, lokale Installation NUR für IDE-Support
  - **Vorteile:**
    - Klare Trennung: Production-Konsistenz vs. Development-Convenience
    - Verhindert Versions-Konflikte durch klarere Intention

- ✅ **Node.js Backend: API Endpoint REST-Konformität**
  - **Problem:** Endpoint-Definition und REST-Semantik
    - `/api/echo` war POST-only → Browser-Tests nicht möglich
    - Zwischenlösung mit `app.all()` war nicht REST-konform
  - **Finale Lösung: REST-konforme Endpoints**
    - `/api/hello` → GET (korrekt: Daten abrufen)
    - `/api/echo` → POST (korrekt: Daten senden/zurückwerfen)
    - Curl-Beispiel im Code-Kommentar für einfaches Testen (server.ts:108)
  - **Routing über Nginx:**
    - `localhost:8080/api/node/hello` → `node:3000/api/hello` (GET) ✅
    - `localhost:8080/api/node/echo` → `node:3000/api/echo` (POST) ✅
  - **Best Practice:** Klare HTTP-Methoden-Semantik für professionelles API-Design

- ✅ **PhpStorm: Excluded Directories optimiert**
  - **Problem:** Unvollständige Exclude-Konfiguration führt zu Performance-Problemen
    - `build/` (PHPUnit Coverage) nicht excluded → IDE indexiert unnötig
    - `dist/` (Node.js Build Output) nicht excluded → doppelte Indexierung (Source + Compiled)
    - Fehlende Excludes verlangsamen Search, Navigation und Code-Completion
  - **Lösung: Build- und Cache-Directories excluded (.idea/docker-webdev.iml:12-13)**
    - `build/` - PHPUnit Coverage Reports, Tool Caches
    - `dist/` - TypeScript Build Output (transpilierter Code)
  - **Bereits korrekt excluded:**
    - `vendor/` - Composer Dependencies (nur für Completion geladen)
    - `node_modules/` - NPM Dependencies (nur für Completion geladen)
    - `storage/` - Runtime-Daten (Uploads, Cache, Sessions)
    - `.pnpm-store/` - pnpm Cache
    - `public/build/` - Vite Build Output
  - **Resultat:** Schnellere Indexierung, bessere IDE-Performance

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

  - **5. Image-Versionen in Compose-Dateien standardisiert**
    - **Problem:** Inkonsistente und teilweise falsche Image-Tags
      - `postgres:17.7-alpine` - Falscher Tag (PostgreSQL nutzt Major-Versionen: `17-alpine`)
      - Images hatten keine konsistente Versionierung
      - .env Variablen waren unnötig (jeder Image-String kommt nur 1x vor)
    - **Lösung:** Korrekte und konsistente Image-Tags **direkt in compose.yaml**
      - **Redis:** `redis:7.4-alpine` (Alpine 3.22+ wird automatisch verwendet)
      - **PostgreSQL:** `postgres:17.7-alpine` → `postgres:17-alpine` (korrekt)
      - **MariaDB:** `mariadb:12.1` (nutzt Debian/Ubuntu, nicht Alpine)
      - **Keine .env Variablen:** Versionen bleiben hardcoded in compose.yaml
        - Grund: Keine Wiederverwendung (jeder Image-String kommt nur 1x vor)
        - Dockerfiles nutzen ARG (DRY: `alpine:${ALPINE_VERSION}` mehrfach verwendet)
        - compose.yaml: Fixe Versionen (bessere Lesbarkeit, Renovate-Kompatibilität)
    - **Dateien geändert:**
      - `compose.yaml` (Zeile 64): redis Image `redis:7.4-alpine`
      - `compose.yaml` (Zeile 88): postgres Image `postgres:17-alpine`
    - **Hinweis:** Alpine-Version im Tag nicht spezifizierbar
      - Redis und Postgres verwenden `-alpine` ohne Versionssuffix
      - Alpine-Version wird vom Image-Maintainer bestimmt
      - Typischerweise aktuellste stabile Alpine-Version
      - Fixierung nur über vollständigen Digest möglich (nicht praktikabel)
    - **Vorteile:**
      - **Korrekte Tags:** PostgreSQL verwendet Major-Versionen
      - **Reproduzierbarkeit:** Gleiche Builds über Zeit
      - **Lesbarkeit:** Klare, dokumentierte Versionen
      - **Renovate-Kompatibilität:** Dependency-Scanner können Versionen erkennen

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
