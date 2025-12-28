<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Docker WebDev Boilerplate</title>

    <!-- Vite Assets - Dynamically loaded based on ENV -->
    <?= $vite->renderCssTags('js/app.js') ?>

</head>
<body>
<div class="container">
    <div class="card text-center">
        <h1>🚀 Docker WebDev Boilerplate</h1>
        <p>Flexibles Setup für PHP und Node.js Entwicklung</p>

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
            <li>💾 <strong>Database:</strong> <?= $env['DB_TYPE'] ? '✅ ' . strtoupper($env['DB_TYPE']) : '❌ None' ?></li>
        </ul>
    </div>

    <div class="card">
        <h2>🔗 Available Endpoints</h2>
        <ul style="list-style: disc; padding-left: 1.5rem;">
            <li><a href="/" style="color: #007bff; text-decoration: none;"><strong>GET /</strong></a> - Service dashboard with live status (via Router)</li>
            <li><a href="/welcome" style="color: #007bff; text-decoration: none;"><strong>GET /welcome</strong></a> - Alias for / (via Router)</li>
            <li><a href="/status" style="color: #007bff; text-decoration: none;"><strong>GET /status</strong></a> - Detailed JSON health check (all services)</li>
            <li><a href="/api/health" style="color: #007bff; text-decoration: none;"><strong>GET /api/health</strong></a> - Simple JSON health (PHP-FPM only)</li>
            <li><a href="/health.php" style="color: #007bff; text-decoration: none;"><strong>GET /health.php</strong></a> - Minimal health check for Docker HEALTHCHECK</li>
            <?php if ($env['ENABLE_NODE']): ?>
            <li><a href="http://localhost:3000/health" target="_blank" style="color: #007bff; text-decoration: none;"><strong>Node: /health</strong></a> - Node.js backend health (port 3000)</li>
            <?php endif; ?>
        </ul>
        <p style="margin-top: 1rem; font-size: 0.9em; color: #666;">
            <strong>Tip:</strong> Use <code>/health.php</code> for Docker HEALTHCHECK (minimal overhead)
            and <code>/status</code> for monitoring dashboards (detailed service info).
        </p>
    </div>

    <div class="card">
        <h2>📚 Quick Start</h2>

        <h3 style="margin-top: 1rem;">🚀 Initial Setup (First Time)</h3>
        <pre style="background: #f5f5f5; padding: 0.5rem; border-radius: 4px; overflow-x: auto;"># 1. Initialize project (copy .env.example to .env)
make init

# 2. Edit .env and set your environment
# Set: ENV=development

# 3. Create project structure (directories, dependencies)
make setup

# 4. Build and start all services
make fresh</pre>

        <h3 style="margin-top: 1rem;">💻 Daily Development</h3>
        <pre style="background: #f5f5f5; padding: 0.5rem; border-radius: 4px; overflow-x: auto;"># Start services (uses existing images)
make up

# Stop services
make down

# Rebuild images (only when Dockerfile changes)
make build</pre>

        <h3 style="margin-top: 1rem;">⚙️ Service Configuration</h3>
        <pre style="background: #f5f5f5; padding: 0.5rem; border-radius: 4px; overflow-x: auto;"># Enable/disable services in .env:
ENABLE_PHP=true       # PHP-FPM
ENABLE_NODE=true      # Node.js (Vite + Backend)
ENABLE_REDIS=true     # Redis Cache
DB_TYPE=postgres      # Database (postgres/mariadb)
NODE_MODE=full-stack  # Vite + Express (vite-only|backend-only|none)</pre>
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
