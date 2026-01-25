<?php
/**
 * @var array<string, mixed> $healthStatus
 * @var array<string, mixed> $systemInfo
 * @var array<string, mixed> $gitStatus
 * @var array<string, mixed> $dbStats
 * @var array<string, mixed> $apiDocsStatus
 */
?>
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <!-- Overall Health Status -->
    <div class="bg-white rounded-lg shadow p-6 col-span-full">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">System Health</h2>
        <div class="flex items-center space-x-4">
            <?php
            $status       = $healthStatus['status'] ?? 'unknown';
            $statusColors = [
                'healthy'   => 'text-green-600 bg-green-100',
                'degraded'  => 'text-yellow-600 bg-yellow-100',
                'unhealthy' => 'text-red-600 bg-red-100',
            ];
            $statusIcons = [
                'healthy'   => '✓',
                'degraded'  => '⚠',
                'unhealthy' => '✗',
            ];
            $color = $statusColors[$status] ?? 'text-gray-600 bg-gray-100';
            $icon  = $statusIcons[$status] ?? '?';
            ?>
            <div class="flex-shrink-0">
                <span class="inline-flex items-center justify-center h-12 w-12 rounded-full <?= $color ?> text-2xl">
                    <?= $icon ?>
                </span>
            </div>
            <div>
                <p class="text-sm text-gray-500">Status</p>
                <p class="text-2xl font-bold capitalize <?= explode(' ', $color)[0] ?>"><?= $status ?></p>
            </div>
            <div class="border-l border-gray-200 pl-4">
                <p class="text-sm text-gray-500">Healthy Services</p>
                <p class="text-2xl font-bold text-gray-900"><?= $healthStatus['healthy_count'] ?? 0 ?></p>
            </div>
            <?php if (($healthStatus['unhealthy_count'] ?? 0) > 0): ?>
            <div class="border-l border-gray-200 pl-4">
                <p class="text-sm text-gray-500">Issues</p>
                <p class="text-2xl font-bold text-red-600"><?= $healthStatus['unhealthy_count'] ?></p>
            </div>
            <?php endif; ?>
            <div class="ml-auto text-sm text-gray-500">
                Last check: <?= $healthStatus['timestamp'] ?? 'Unknown' ?>
            </div>
        </div>
    </div>

    <!-- System Information Card -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">💻 System</h3>
        <dl class="space-y-2">
            <div class="flex justify-between">
                <dt class="text-sm text-gray-500">PHP Version</dt>
                <dd class="text-sm font-medium text-gray-900"><?= $systemInfo['php_version'] ?? 'Unknown' ?></dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-sm text-gray-500">Server</dt>
                <dd class="text-sm font-medium text-gray-900"><?= htmlspecialchars($systemInfo['server_software'] ?? 'Unknown') ?></dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-sm text-gray-500">Hostname</dt>
                <dd class="text-sm font-medium text-gray-900"><?= htmlspecialchars($systemInfo['hostname'] ?? 'Unknown') ?></dd>
            </div>
        </dl>
        <div class="mt-4">
            <a href="/_dev/system" class="text-sm text-blue-600 hover:text-blue-800 font-medium">View Details →</a>
        </div>
    </div>

    <!-- Git Status Card -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">🔀 Git Status</h3>
        <?php if ($gitStatus['initialized'] ?? false): ?>
        <dl class="space-y-2">
            <div class="flex justify-between">
                <dt class="text-sm text-gray-500">Branch</dt>
                <dd class="text-sm font-medium text-gray-900"><?= htmlspecialchars($gitStatus['branch'] ?? 'Unknown') ?></dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-sm text-gray-500">Commit</dt>
                <dd class="text-sm font-mono text-gray-900"><?= htmlspecialchars($gitStatus['commit'] ?? 'Unknown') ?></dd>
            </div>
        </dl>
        <?php else: ?>
        <p class="text-sm text-gray-500"><?= $gitStatus['message'] ?? 'Not available' ?></p>
        <?php endif; ?>
    </div>

    <!-- Database Status Card -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">🗄️ Database</h3>
        <?php if ($dbStats['available'] ?? false): ?>
        <dl class="space-y-2">
            <div class="flex justify-between">
                <dt class="text-sm text-gray-500">Type</dt>
                <dd class="text-sm font-medium text-gray-900"><?= htmlspecialchars($dbStats['type'] ?? 'Unknown') ?></dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-sm text-gray-500">Version</dt>
                <dd class="text-sm font-medium text-gray-900"><?= htmlspecialchars($dbStats['version'] ?? 'Unknown') ?></dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-sm text-gray-500">Tables</dt>
                <dd class="text-sm font-medium text-gray-900"><?= $dbStats['tables'] ?? 0 ?></dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-sm text-gray-500">Size</dt>
                <dd class="text-sm font-medium text-gray-900"><?= htmlspecialchars($dbStats['size'] ?? 'Unknown') ?></dd>
            </div>
        </dl>
        <div class="mt-4">
            <a href="/_dev/database" class="text-sm text-blue-600 hover:text-blue-800 font-medium">View Details →</a>
        </div>
        <?php else: ?>
        <p class="text-sm text-gray-500"><?= $dbStats['message'] ?? 'Database not available' ?></p>
        <?php endif; ?>
    </div>

    <!-- Quick Actions Card -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">⚡ Quick Actions</h3>
        <div class="space-y-2">
            <a href="/_dev/health" class="block w-full px-4 py-2 text-center text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded transition">
                Run Health Check
            </a>
            <a href="/_dev/system?phpinfo=1" class="block w-full px-4 py-2 text-center text-sm font-medium text-blue-600 bg-blue-50 hover:bg-blue-100 rounded transition">
                View phpinfo()
            </a>
            <a href="/_dev/logs" class="block w-full px-4 py-2 text-center text-sm font-medium text-blue-600 bg-blue-50 hover:bg-blue-100 rounded transition">
                View Logs
            </a>
        </div>
    </div>

    <!-- Documentation Card -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">📚 Documentation</h3>
        <div class="space-y-3">
            <!-- Guides -->
            <div class="flex items-center justify-between">
                <a href="/docs/" class="text-sm text-blue-600 hover:text-blue-800 font-medium">Guides & Docs</a>
                <span class="badge badge-green">Available</span>
            </div>

            <!-- PHP API -->
            <?php
            $phpStatus    = $apiDocsStatus['php'] ?? [];
            $phpHasSource = $phpStatus['hasSource'] ?? false;
            $phpExists    = $phpStatus['exists'] ?? false;
            $phpOutdated  = $phpStatus['outdated'] ?? false;
            ?>
            <div class="flex items-center justify-between">
                <?php if ($phpExists): ?>
                <a href="/docs/api/php/" class="text-sm text-blue-600 hover:text-blue-800 font-medium">PHP API</a>
                <?php else: ?>
                <span class="text-sm text-gray-400">PHP API</span>
                <?php endif; ?>
                <?php if (!$phpHasSource): ?>
                <span class="badge badge-gray">No source</span>
                <?php elseif (!$phpExists): ?>
                <span class="badge badge-red">Missing</span>
                <?php elseif ($phpOutdated): ?>
                <span class="badge badge-yellow"><?= $phpStatus['docsAge'] ?? 'Outdated' ?></span>
                <?php else: ?>
                <span class="badge badge-green"><?= $phpStatus['docsAge'] ?? 'Fresh' ?></span>
                <?php endif; ?>
            </div>

            <!-- Node Backend API -->
            <?php
            $backendStatus    = $apiDocsStatus['node_backend'] ?? [];
            $backendHasSource = $backendStatus['hasSource'] ?? false;
            $backendExists    = $backendStatus['exists'] ?? false;
            $backendOutdated  = $backendStatus['outdated'] ?? false;
            ?>
            <div class="flex items-center justify-between">
                <?php if ($backendExists): ?>
                <a href="/docs/api/node-backend/" class="text-sm text-blue-600 hover:text-blue-800 font-medium">Node Backend API</a>
                <?php else: ?>
                <span class="text-sm text-gray-400">Node Backend API</span>
                <?php endif; ?>
                <?php if (!$backendHasSource): ?>
                <span class="badge badge-gray">No source</span>
                <?php elseif (!$backendExists): ?>
                <span class="badge badge-red">Missing</span>
                <?php elseif ($backendOutdated): ?>
                <span class="badge badge-yellow"><?= $backendStatus['docsAge'] ?? 'Outdated' ?></span>
                <?php else: ?>
                <span class="badge badge-green"><?= $backendStatus['docsAge'] ?? 'Fresh' ?></span>
                <?php endif; ?>
            </div>

            <!-- Node Frontend -->
            <?php
            $frontendStatus    = $apiDocsStatus['node_frontend'] ?? [];
            $frontendHasSource = $frontendStatus['hasSource'] ?? false;
            $frontendExists    = $frontendStatus['exists'] ?? false;
            $frontendOutdated  = $frontendStatus['outdated'] ?? false;
            ?>
            <div class="flex items-center justify-between">
                <?php if ($frontendExists): ?>
                <a href="/docs/api/node-frontend/" class="text-sm text-blue-600 hover:text-blue-800 font-medium">Node Frontend</a>
                <?php else: ?>
                <span class="text-sm text-gray-400">Node Frontend</span>
                <?php endif; ?>
                <?php if (!$frontendHasSource): ?>
                <span class="badge badge-gray">No source</span>
                <?php elseif (!$frontendExists): ?>
                <span class="badge badge-red">Missing</span>
                <?php elseif ($frontendOutdated): ?>
                <span class="badge badge-yellow"><?= $frontendStatus['docsAge'] ?? 'Outdated' ?></span>
                <?php else: ?>
                <span class="badge badge-green"><?= $frontendStatus['docsAge'] ?? 'Fresh' ?></span>
                <?php endif; ?>
            </div>
        </div>

        <?php
        $phpNeedsRegen  = $phpHasSource && (!$phpExists || $phpOutdated);
        $nodeNeedsRegen = ($backendHasSource && (!$backendExists || $backendOutdated))
                       || ($frontendHasSource && (!$frontendExists || $frontendOutdated));
        ?>
        <?php if ($phpNeedsRegen || $nodeNeedsRegen): ?>
        <div class="mt-4 pt-3 border-t border-gray-200 space-y-2">
            <?php if ($phpNeedsRegen): ?>
            <button
                id="btn-regen-php-docs"
                onclick="regeneratePhpDocs()"
                class="w-full px-3 py-2 text-xs font-medium text-white bg-purple-600 hover:bg-purple-700 rounded transition disabled:opacity-50 disabled:cursor-not-allowed"
            >
                Regenerate PHP Docs
            </button>
            <?php endif; ?>
            <?php if ($nodeNeedsRegen): ?>
            <p class="text-xs text-gray-500">
                Node docs: <code class="bg-gray-100 px-1 rounded">make docs-node</code>
            </p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
async function regeneratePhpDocs() {
    const btn = document.getElementById('btn-regen-php-docs');
    const originalText = btn.textContent;

    btn.disabled = true;
    btn.textContent = 'Generating...';

    try {
        const response = await fetch('/_dev/api/docs/generate?type=php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        });

        const result = await response.json();

        if (result.success) {
            btn.textContent = 'Done!';
            btn.classList.remove('bg-purple-600', 'hover:bg-purple-700');
            btn.classList.add('bg-green-600');
            setTimeout(() => location.reload(), 1000);
        } else {
            btn.textContent = 'Failed';
            btn.classList.remove('bg-purple-600', 'hover:bg-purple-700');
            btn.classList.add('bg-red-600');
            alert('Error: ' + result.message);
            setTimeout(() => {
                btn.textContent = originalText;
                btn.classList.remove('bg-red-600');
                btn.classList.add('bg-purple-600', 'hover:bg-purple-700');
                btn.disabled = false;
            }, 2000);
        }
    } catch (error) {
        btn.textContent = 'Error';
        btn.classList.remove('bg-purple-600', 'hover:bg-purple-700');
        btn.classList.add('bg-red-600');
        alert('Request failed: ' + error.message);
        setTimeout(() => {
            btn.textContent = originalText;
            btn.classList.remove('bg-red-600');
            btn.classList.add('bg-purple-600', 'hover:bg-purple-700');
            btn.disabled = false;
        }, 2000);
    }
}
</script>

<!-- Recent Activity / Info -->
<div class="mt-8 bg-blue-50 border border-blue-200 rounded-lg p-6">
    <h3 class="text-lg font-semibold text-blue-900 mb-2">ℹ️ Welcome to the zappzarapp Development Dashboard</h3>
    <p class="text-sm text-blue-700">
        This dashboard provides real-time insights into your development environment.
        Monitor system health, check container status, view logs, and more.
    </p>
    <div class="mt-4 flex items-center space-x-4 text-sm text-blue-600">
        <a href="/_dev/system" class="hover:text-blue-800 font-medium">System Info</a>
        <span class="text-blue-300">•</span>
        <a href="/_dev/health" class="hover:text-blue-800 font-medium">Health Checks</a>
        <span class="text-blue-300">•</span>
        <a href="https://github.com" class="hover:text-blue-800 font-medium">Documentation</a>
    </div>
</div>
