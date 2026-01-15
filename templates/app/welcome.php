<?php
/**
 * Welcome Page Template
 *
 * This template is rendered by WelcomeController and expects the following variables:
 * @var ViteHelper $vite - Vite asset helper for HMR and production builds
 * @var array $env - Environment configuration from HealthCheck
 * @var array $status - Service health status from HealthCheck
 */

declare(strict_types=1);
use App\Infrastructure\ViteHelper;
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>zappzarapp</title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">

    <!-- Vite Assets - Dynamically loaded based on ENV -->
    <?= $vite->renderCssTags('js/app.js') ?>

</head>
<body>
<div class="container">
    <?php if ($vite->isDevelopment() && !$vite->isViteDevServerRunning()): ?>
    <div class="card" style="background: #fff3cd; border: 2px solid #ffc107; padding: 1rem; margin-bottom: 1rem;">
        <h3 style="color: #856404; margin: 0 0 0.5rem 0;">⚠️ Vite Dev Server Not Running</h3>
        <p style="color: #856404; margin: 0;">
            CSS styles are loaded via Vite HMR in development mode. The page may appear unstyled.
        </p>
        <p style="color: #856404; margin: 0.5rem 0 0 0; font-size: 0.9em;">
            <strong>Fix:</strong> Run <code style="background: #f8f9fa; padding: 0.2rem 0.4rem; border-radius: 3px;">make node-up</code>
            or set <code style="background: #f8f9fa; padding: 0.2rem 0.4rem; border-radius: 3px;">ENABLE_NODE=true</code> in your <code>.env</code> file.
        </p>
    </div>
    <?php endif; ?>

    <div class="card text-center">
        <h1>⚡ zappzarapp</h1>
        <p style="font-family: monospace; color: #666; margin: 0.25rem 0;">/ˈt͡sapt͡saˈʁap/</p>
        <p style="font-style: italic; color: #555; margin: 0.5rem 0 1rem 0;">
            German for "in a flash" — a professional web dev stack that gets you coding in minutes.
        </p>

        <div class="card" style="margin-top: 2rem; background: #f0f0f0; padding: 1rem; border-radius: 8px;">
            <h3>Current Environment</h3>
            <p><strong>ENV:</strong> <?= htmlspecialchars($env['ENV']) ?></p>
            <p><strong>Mode:</strong> <?= $vite->isDevelopment() ? '🔧 Development (Vite HMR)' : '🚀 Production (Built Assets)' ?></p>
            <p><strong>Overall Status:</strong>
                <span style="color: <?= $status['overall_status'] === 'ok' ? 'green' : 'orange' ?>; font-weight: bold;">
                    <?= strtoupper($status['overall_status']) ?>
                </span>
            </p>
        </div>
    </div>

    <div class="card">
        <h2>📊 Service Status</h2>
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #f5f5f5;">
                    <th style="padding: 0.5rem; text-align: left; border: 1px solid #ddd;">Service</th>
                    <th style="padding: 0.5rem; text-align: left; border: 1px solid #ddd;">Status</th>
                    <th style="padding: 0.5rem; text-align: left; border: 1px solid #ddd;">Details</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($status['services'] as $serviceName => $service): ?>
                <tr>
                    <td style="padding: 0.5rem; border: 1px solid #ddd;">
                        <strong><?= ucfirst(str_replace('-', ' ', $serviceName)) ?></strong>
                    </td>
                    <td style="padding: 0.5rem; border: 1px solid #ddd;">
                        <span style="color: <?= $service['status'] === 'ok' ? 'green' : ($service['status'] === 'disabled' ? 'gray' : 'red') ?>; font-weight: bold;">
                            <?= $service['status'] === 'ok' ? '✅ OK' : ($service['status'] === 'disabled' ? '⚪ Disabled' : '❌ Error') ?>
                        </span>
                    </td>
                    <td style="padding: 0.5rem; border: 1px solid #ddd; font-size: 0.9em;">
                        <?php if (isset($service['version'])): ?>
                            Version: <?= htmlspecialchars($service['version']) ?><br>
                        <?php endif; ?>
                        <?php if (isset($service['mode'])): ?>
                            Mode: <?= htmlspecialchars($service['mode']) ?><br>
                        <?php endif; ?>
                        <?php if (isset($service['message'])): ?>
                            <span style="color: red;"><?= htmlspecialchars($service['message']) ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2>⚙️ Active Features</h2>
        <ul style="list-style: none; padding: 0;">
            <li>🔧 <strong>Environment:</strong> <?= htmlspecialchars($env['ENV']) ?></li>
            <li>📦 <strong>PHP Service:</strong> <?= $env['ENABLE_PHP'] ? '✅ Enabled' : '❌ Disabled' ?></li>
            <li>🟢 <strong>Node.js Service:</strong> <?= $env['ENABLE_NODE'] ? '✅ Enabled (' . htmlspecialchars($env['NODE_MODE']) . ')' : '❌ Disabled' ?></li>
            <li>🔴 <strong>Redis Service:</strong> <?= $env['ENABLE_REDIS'] ? '✅ Enabled' : '❌ Disabled' ?></li>
            <li>💾 <strong>Database:</strong> <?= $env['ENABLE_DATABASE'] ? '✅ ' . strtoupper($env['DB_TYPE']) : '❌ Disabled' ?></li>
        </ul>
    </div>

    <div class="card">
        <h2>🔗 Available Endpoints</h2>
        <ul style="list-style: disc; padding-left: 1.5rem;">
            <!-- Pages -->
            <li><a href="/" style="color: #007bff; text-decoration: none;"><strong>GET /</strong></a> - Main landing page</li>
            <li><a href="/welcome" style="color: #007bff; text-decoration: none;"><strong>GET /welcome</strong></a> - Alias for /</li>
            <!-- Health Checks -->
            <li><a href="/api/health" style="color: #007bff; text-decoration: none;"><strong>GET /api/health</strong></a> - Simple JSON health check</li>
            <li><a href="/status" style="color: #007bff; text-decoration: none;"><strong>GET /status</strong></a> - Detailed JSON health check (all services)</li>
            <li><a href="/health.php" style="color: #007bff; text-decoration: none;"><strong>GET /health.php</strong></a> - Docker HEALTHCHECK (minimal overhead)</li>
            <!-- Node.js API -->
            <?php if ($env['ENABLE_NODE'] && in_array($env['NODE_MODE'], ['full-stack', 'backend-only'], true)): ?>
            <li><a href="http://localhost:3000/health" target="_blank" style="color: #007bff; text-decoration: none;"><strong>GET /api/node/health</strong></a> - Node.js backend health</li>
            <li><a href="http://localhost:3000/api/hello" target="_blank" style="color: #007bff; text-decoration: none;"><strong>GET /api/node/hello</strong></a> - Hello endpoint (?name=)</li>
            <li>
                <strong>POST /api/node/echo</strong> - Echo request body (JSON)
                <span
                    id="copy-curl-btn"
                    title="Click to copy curl command"
                    style="cursor: pointer; margin-left: 0.4rem; padding: 0.1rem 0.4rem; font-size: 0.75rem; font-weight: bold; color: #007bff; background: #e7f1ff; border: 1px solid #007bff; border-radius: 4px; user-select: none;"
                >i</span>
                <script>
                    document.getElementById('copy-curl-btn').addEventListener('click', function() {
                        var cmd = "curl -X POST https://localhost:8443/api/node/echo -H 'Content-Type: application/json' -d '{\"hello\": \"world\"}'";
                        var btn = this;
                        navigator.clipboard.writeText(cmd).then(function() {
                            btn.textContent = '✓';
                            btn.style.background = '#d4edda';
                            btn.style.borderColor = '#28a745';
                            btn.style.color = '#28a745';
                            setTimeout(function() {
                                btn.textContent = 'i';
                                btn.style.background = '#e7f1ff';
                                btn.style.borderColor = '#007bff';
                                btn.style.color = '#007bff';
                            }, 1500);
                        });
                    });
                </script>
            </li>
            <?php endif; ?>
            <!-- Development -->
            <li><a href="/_dev" style="color: #007bff; text-decoration: none;"><strong>GET /_dev</strong></a> - Development Dashboard</li>
        </ul>
        <p style="margin-top: 1rem; font-size: 0.9em; color: #666;">
            <strong>Tip:</strong> Use <code>/health.php</code> for Docker HEALTHCHECK (minimal overhead)
            and <code>/status</code> for monitoring dashboards (detailed service info).
        </p>
    </div>

    <?php if ($vite->isDevelopment()): ?>
    <div class="card">
        <h2>📖 Documentation</h2>
        <p style="margin-bottom: 1rem;">Guides and auto-generated API documentation:</p>
        <ul style="list-style: disc; padding-left: 1.5rem;">
            <li><a href="/docs/" style="color: #007bff; text-decoration: none;"><strong>/docs/</strong></a> - Guides & Documentation (Docsify)</li>
            <li><a href="/docs/api/php/" style="color: #007bff; text-decoration: none;"><strong>/docs/api/php/</strong></a> - PHP API Documentation (phpDocumentor)</li>
            <li><a href="/docs/api/node-backend/" style="color: #007bff; text-decoration: none;"><strong>/docs/api/node-backend/</strong></a> - Node.js Backend API Documentation (TypeDoc)</li>
            <li><a href="/docs/api/node-frontend/" style="color: #007bff; text-decoration: none;"><strong>/docs/api/node-frontend/</strong></a> - Node.js Frontend Documentation (TypeDoc)</li>
        </ul>
        <p style="margin-top: 1rem; font-size: 0.9em; color: #666;">
            <strong>Generate API Docs:</strong> Run <code>make docs</code> to generate/update API documentation.
            Documentation is only available in development mode.
        </p>
    </div>
    <?php endif; ?>

    <div class="card">
        <h2>📚 Quick Start</h2>

        <h3 style="margin-top: 1rem;">🚀 Initial Setup (First Time)</h3>
        <pre style="background: #f5f5f5; padding: 0.5rem; border-radius: 4px; overflow-x: auto;"># 1. Initialize project (copy .env.example to .env)
make init

# 2. Edit .env: Adjust USER_ID, GROUP_ID to match your host user (id -u, id -g)

# 3. Full setup (build, install dependencies, start containers)
make setup</pre>

        <h3 style="margin-top: 1rem;">💻 Daily Development</h3>
        <pre style="background: #f5f5f5; padding: 0.5rem; border-radius: 4px; overflow-x: auto;"># Start services (uses existing images)
make up

# Stop services
make down

# Rebuild images (only when Dockerfile changes)
make build

# Service-specific commands (optional service names)
make restart php nginx    # Restart specific services
make logs php             # View logs of specific services
make build php            # Build specific images</pre>

        <h3 style="margin-top: 1rem;">⚙️ Service Configuration</h3>
        <pre style="background: #f5f5f5; padding: 0.5rem; border-radius: 4px; overflow-x: auto;"># Enable/disable services in .env:
ENABLE_PHP=true       # PHP-FPM
ENABLE_NODE=true      # Node.js (Vite + Backend)
ENABLE_DATABASE=true  # Database container
ENABLE_REDIS=true     # Redis Cache

# Database type (postgres or mariadb)
DB_TYPE=postgres

# Node.js mode (full-stack|vite-only|backend-only|none)
NODE_MODE=full-stack</pre>
    </div>

    <?php if ($vite->isDevelopment()): ?>
    <div class="card">
        <h2>🎨 HMR Demo</h2>
        <p>Try editing <code>resources/css/test.scss</code> or <code>resources/js/app.js</code> - changes appear instantly without page reload!</p>
        <button id="test-btn" class="btn">Test Button (Click me!)</button>
    </div>
    <?php endif; ?>
</div>

<!-- Vite JavaScript - Loaded last -->
<?= $vite->renderScriptTags('js/app.js') ?>

</body>
</html>
