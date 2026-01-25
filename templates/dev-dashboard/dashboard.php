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
    <!-- Health Status Overview -->
    <div class="card col-span-full">
        <div class="flex items-center justify-between">
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
                    <p class="text-sm text-gray-500">System Status</p>
                    <p class="text-2xl font-bold capitalize <?= explode(' ', $color)[0] ?>"><?= $status ?></p>
                </div>
                <div class="border-l border-gray-200 pl-4">
                    <p class="text-sm text-gray-500">Services</p>
                    <p class="text-lg font-bold text-gray-900">
                        <?= $healthStatus['healthy_count'] ?? 0 ?>/<?= ($healthStatus['healthy_count'] ?? 0) + ($healthStatus['unhealthy_count'] ?? 0) ?>
                    </p>
                </div>
                <?php if (($healthStatus['unhealthy_count'] ?? 0) > 0): ?>
                <div class="border-l border-gray-200 pl-4">
                    <p class="text-sm text-gray-500">Issues</p>
                    <p class="text-lg font-bold text-red-600"><?= $healthStatus['unhealthy_count'] ?></p>
                </div>
                <?php endif; ?>
            </div>
            <a href="/_dev/health" class="btn btn-secondary">Details</a>
        </div>
    </div>

    <!-- Quick Info Cards Row -->
    <div class="card">
        <div class="flex items-center justify-between mb-3">
            <h3 class="font-semibold text-gray-900">System</h3>
            <a href="/_dev/system" class="text-xs text-blue-600 hover:text-blue-800">Details</a>
        </div>
        <dl class="space-y-1 text-sm">
            <div class="flex justify-between">
                <dt class="text-gray-500">PHP</dt>
                <dd class="font-mono text-gray-900"><?= $systemInfo['php_version'] ?? 'Unknown' ?></dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-gray-500">Server</dt>
                <dd class="text-gray-900 text-xs"><?= htmlspecialchars($systemInfo['server_software'] ?? 'Unknown') ?></dd>
            </div>
        </dl>
    </div>

    <div class="card">
        <div class="flex items-center justify-between mb-3">
            <h3 class="font-semibold text-gray-900">Database</h3>
            <a href="/_dev/database" class="text-xs text-blue-600 hover:text-blue-800">Details</a>
        </div>
        <?php if ($dbStats['available'] ?? false): ?>
        <dl class="space-y-1 text-sm">
            <div class="flex justify-between">
                <dt class="text-gray-500">Type</dt>
                <dd class="font-medium text-gray-900"><?= strtoupper((string) $dbStats['type']) ?></dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-gray-500">Tables</dt>
                <dd class="font-medium text-gray-900"><?= $dbStats['tables'] ?? 0 ?></dd>
            </div>
        </dl>
        <?php else: ?>
        <p class="text-sm text-gray-500"><?= $dbStats['message'] ?? 'Not connected' ?></p>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="flex items-center justify-between mb-3">
            <h3 class="font-semibold text-gray-900">Git</h3>
            <a href="/_dev/system#git" class="text-xs text-blue-600 hover:text-blue-800">Details</a>
        </div>
        <?php if ($gitStatus['initialized'] ?? false): ?>
        <dl class="space-y-1 text-sm">
            <div class="flex justify-between">
                <dt class="text-gray-500">Branch</dt>
                <dd class="font-mono text-gray-900"><?= htmlspecialchars($gitStatus['branch'] ?? 'Unknown') ?></dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-gray-500">Commit</dt>
                <dd class="font-mono text-xs text-gray-700"><?= htmlspecialchars($gitStatus['commit'] ?? '-') ?></dd>
            </div>
        </dl>
        <?php else: ?>
        <p class="text-sm text-gray-500"><?= $gitStatus['message'] ?? 'Not available' ?></p>
        <?php endif; ?>
    </div>

    <!-- Quick Actions -->
    <div class="card">
        <h3 class="font-semibold text-gray-900 mb-3">Quick Actions</h3>
        <div class="space-y-2">
            <a href="/_dev/health" class="btn btn-primary btn-block text-sm">Run Health Check</a>
            <a href="/_dev/logs" class="btn btn-secondary btn-block text-sm">View Logs</a>
            <a href="/_dev/quality" class="btn btn-secondary btn-block text-sm">Quality Tools</a>
        </div>
    </div>

    <!-- Documentation Status -->
    <div class="card col-span-1 md:col-span-2">
        <div class="flex items-center justify-between mb-3">
            <h3 class="font-semibold text-gray-900">Documentation</h3>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <!-- Guides -->
            <a href="/docs/" class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                <span class="text-sm font-medium text-gray-900">Guides</span>
                <span class="badge badge-green">Available</span>
            </a>

            <!-- PHP API -->
            <?php
            $phpStatus    = $apiDocsStatus['php'] ?? [];
            $phpHasSource = $phpStatus['hasSource'] ?? false;
            $phpExists    = $phpStatus['exists'] ?? false;
            $phpOutdated  = $phpStatus['outdated'] ?? false;
            ?>
            <?php if ($phpExists): ?>
            <a href="/docs/api/php/" class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
            <?php else: ?>
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg opacity-60">
            <?php endif; ?>
                <span class="text-sm font-medium <?= $phpExists ? 'text-gray-900' : 'text-gray-400' ?>">PHP API</span>
                <?php if (!$phpHasSource): ?>
                <span class="badge badge-gray">No source</span>
                <?php elseif (!$phpExists): ?>
                <span class="badge badge-red">Missing</span>
                <?php elseif ($phpOutdated): ?>
                <span class="badge badge-yellow">Outdated</span>
                <?php else: ?>
                <span class="badge badge-green">Fresh</span>
                <?php endif; ?>
            <?php if ($phpExists): ?>
            </a>
            <?php else: ?>
            </div>
            <?php endif; ?>

            <!-- Node Backend API -->
            <?php
            $backendStatus    = $apiDocsStatus['node_backend'] ?? [];
            $backendHasSource = $backendStatus['hasSource'] ?? false;
            $backendExists    = $backendStatus['exists'] ?? false;
            $backendOutdated  = $backendStatus['outdated'] ?? false;
            ?>
            <?php if ($backendExists): ?>
            <a href="/docs/api/node-backend/" class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
            <?php else: ?>
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg opacity-60">
            <?php endif; ?>
                <span class="text-sm font-medium <?= $backendExists ? 'text-gray-900' : 'text-gray-400' ?>">Backend API</span>
                <?php if (!$backendHasSource): ?>
                <span class="badge badge-gray">No source</span>
                <?php elseif (!$backendExists): ?>
                <span class="badge badge-red">Missing</span>
                <?php elseif ($backendOutdated): ?>
                <span class="badge badge-yellow">Outdated</span>
                <?php else: ?>
                <span class="badge badge-green">Fresh</span>
                <?php endif; ?>
            <?php if ($backendExists): ?>
            </a>
            <?php else: ?>
            </div>
            <?php endif; ?>

            <!-- Node Frontend -->
            <?php
            $frontendStatus    = $apiDocsStatus['node_frontend'] ?? [];
            $frontendHasSource = $frontendStatus['hasSource'] ?? false;
            $frontendExists    = $frontendStatus['exists'] ?? false;
            $frontendOutdated  = $frontendStatus['outdated'] ?? false;
            ?>
            <?php if ($frontendExists): ?>
            <a href="/docs/api/node-frontend/" class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
            <?php else: ?>
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg opacity-60">
            <?php endif; ?>
                <span class="text-sm font-medium <?= $frontendExists ? 'text-gray-900' : 'text-gray-400' ?>">Frontend</span>
                <?php if (!$frontendHasSource): ?>
                <span class="badge badge-gray">No source</span>
                <?php elseif (!$frontendExists): ?>
                <span class="badge badge-red">Missing</span>
                <?php elseif ($frontendOutdated): ?>
                <span class="badge badge-yellow">Outdated</span>
                <?php else: ?>
                <span class="badge badge-green">Fresh</span>
                <?php endif; ?>
            <?php if ($frontendExists): ?>
            </a>
            <?php else: ?>
            </div>
            <?php endif; ?>
        </div>

        <?php
        $phpNeedsRegen      = $phpHasSource && (!$phpExists || $phpOutdated);
        $backendNeedsRegen  = $backendHasSource && (!$backendExists || $backendOutdated);
        $frontendNeedsRegen = $frontendHasSource && (!$frontendExists || $frontendOutdated);
        $nodeNeedsRegen     = $backendNeedsRegen || $frontendNeedsRegen;
        $multipleNeedRegen  = ($phpNeedsRegen ? 1 : 0) + ($nodeNeedsRegen ? 1 : 0) > 1;
        ?>
        <?php if ($phpNeedsRegen || $nodeNeedsRegen): ?>
        <div style="margin-top: 1rem; padding-top: 0.75rem; border-top: 1px solid #e5e7eb; display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem;">
            <?php if ($phpNeedsRegen): ?>
            <button
                id="btn-regen-php-docs"
                onclick="regenerateDocs('php')"
                class="btn btn-secondary text-xs"
            >
                Regenerate PHP
            </button>
            <?php endif; ?>
            <?php if ($nodeNeedsRegen): ?>
            <button
                type="button"
                onclick="copyCmd('make docs-node', this)"
                class="btn btn-secondary text-xs"
                title="Node docs require shell access - copy command to run in terminal"
            >
                Copy: make docs-node
            </button>
            <?php endif; ?>
            <?php if ($multipleNeedRegen): ?>
            <button
                id="btn-regen-all-docs"
                onclick="regenerateAllDocs()"
                class="btn btn-primary text-xs"
            >
                Regenerate All
            </button>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function copyCmd(cmd, btn) {
    navigator.clipboard.writeText(cmd).then(() => {
        const orig = btn.textContent;
        btn.textContent = 'Copied!';
        setTimeout(() => { btn.textContent = orig; }, 1500);
    });
}

async function regenerateDocs(type, skipReload = false) {
    const btn = document.getElementById('btn-regen-' + type + '-docs');
    if (!btn) return false;

    const originalText = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Generating...';

    try {
        const response = await fetch('/_dev/api/docs/generate?type=' + type, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        });

        const result = await response.json();

        if (result.success) {
            btn.textContent = 'Done!';
            btn.classList.remove('btn-secondary');
            btn.classList.add('btn-success');
            if (!skipReload) {
                setTimeout(() => location.reload(), 1000);
            }
            return true;
        } else {
            btn.textContent = 'Failed';
            btn.classList.remove('btn-secondary');
            btn.classList.add('btn-danger');
            alert('Error: ' + result.message);
            setTimeout(() => {
                btn.textContent = originalText;
                btn.classList.remove('btn-danger');
                btn.classList.add('btn-secondary');
                btn.disabled = false;
            }, 2000);
            return false;
        }
    } catch (error) {
        btn.textContent = 'Error';
        btn.classList.remove('btn-secondary');
        btn.classList.add('btn-danger');
        alert('Request failed: ' + error.message);
        setTimeout(() => {
            btn.textContent = originalText;
            btn.classList.remove('btn-danger');
            btn.classList.add('btn-secondary');
            btn.disabled = false;
        }, 2000);
        return false;
    }
}

async function regenerateAllDocs() {
    const btn = document.getElementById('btn-regen-all-docs');
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Generating...';

    // Regenerate PHP docs (skip auto-reload)
    const phpSuccess = await regenerateDocs('php', true);

    // Copy Node command to clipboard
    await navigator.clipboard.writeText('make docs-node');

    if (phpSuccess) {
        btn.textContent = 'Done! Node cmd copied';
        btn.classList.remove('btn-primary');
        btn.classList.add('btn-success');
        setTimeout(() => location.reload(), 1500);
    } else {
        btn.textContent = originalText;
        btn.disabled = false;
    }
}
</script>
