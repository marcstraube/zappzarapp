<?php
/**
 * @var array<string, mixed> $phpVersion
 * @var bool $showPhpInfo
 * @var array<int, array<string, mixed>> $extensions
 * @var array<string, mixed> $envVars
 * @var array<string, mixed> $gitStatus
 */

$tabs = [
    'php'         => 'PHP',
    'extensions'  => 'PHP Extensions (' . count($extensions) . ')',
    'environment' => 'Environment',
    'git'         => 'Git',
];
?>

<!-- Sub-Navigation -->
<nav class="sub-nav" style="background: white; border: 1px solid #e5e7eb; border-radius: 0.5rem; margin-bottom: 1.5rem; position: sticky; top: 120px; z-index: 50;">
    <div style="display: flex; gap: 0;">
        <?php foreach ($tabs as $id => $label): ?>
            <a href="#"
               data-tab="<?= $id ?>"
               class="sub-nav-link <?= $id === 'php' ? 'active' : '' ?>"
               style="display: inline-block; padding: 0.75rem 1rem; border-bottom: 2px solid transparent; color: #6b7280; font-size: 0.875rem; font-weight: 500; text-decoration: none; transition: all 0.2s;"
            ><?= $label ?></a>
        <?php endforeach; ?>
    </div>
</nav>

<!-- Tab: PHP -->
<div id="tab-php" class="tab-panel">
    <div class="card">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">PHP Information</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div>
                <p class="text-sm text-gray-500">Version</p>
                <p class="text-lg font-medium text-gray-900"><?= $phpVersion['version'] ?? 'Unknown' ?></p>
            </div>
            <div>
                <p class="text-sm text-gray-500">SAPI</p>
                <p class="text-lg font-medium text-gray-900"><?= $phpVersion['sapi'] ?? 'Unknown' ?></p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Zend Version</p>
                <p class="text-lg font-medium text-gray-900"><?= $phpVersion['zend_version'] ?? 'Unknown' ?></p>
            </div>
        </div>
        <div class="mt-4">
            <?php if ($showPhpInfo): ?>
                <a href="/_dev/system#php" class="inline-flex items-center px-4 py-2 border border-blue-300 text-sm font-medium rounded text-blue-700 bg-blue-50 hover:bg-blue-100 transition">
                    Hide phpinfo()
                </a>
            <?php else: ?>
                <a href="/_dev/system?phpinfo=1#php" class="inline-flex items-center px-4 py-2 border border-blue-300 text-sm font-medium rounded text-blue-700 bg-blue-50 hover:bg-blue-100 transition">
                    View Full phpinfo()
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($showPhpInfo): ?>
    <div class="card" style="margin-top: 1.5rem;">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Full PHP Info</h2>
        <div class="border border-gray-200 rounded overflow-hidden">
            <div style="max-height: 600px; overflow-y: auto;">
                <?php ob_start();
        phpinfo();
        $phpinfo = ob_get_clean();
        if ($phpinfo !== false) {
            $phpinfo = preg_replace('%^.*<body>(.*)</body>.*$%ms', '$1', $phpinfo);
            if ($phpinfo !== null) {
                $phpinfo = str_replace('<table', '<table class="w-full text-sm"', $phpinfo);
                echo $phpinfo;
            }
        }
        ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Tab: Extensions -->
<div id="tab-extensions" class="tab-panel hidden">
    <div class="card">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Loaded Extensions</h2>
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2">
            <?php foreach ($extensions as $ext): ?>
                <div class="flex justify-between items-center p-2 bg-gray-50 rounded text-sm">
                    <span class="font-medium text-gray-700"><?= htmlspecialchars((string) $ext['name']) ?></span>
                    <span class="text-xs text-gray-500"><?= htmlspecialchars((string) $ext['version']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Tab: Environment -->
<div id="tab-environment" class="tab-panel hidden">
    <div class="card">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Environment Variables</h2>
        <div class="bg-gray-50 rounded border border-gray-200 overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Variable</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Value</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($envVars as $key => $value): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2 text-sm font-mono text-gray-900"><?= htmlspecialchars($key) ?></td>
                        <td class="px-4 py-2 text-sm font-mono text-gray-700">
                            <?php if ($value === '********'): ?>
                                <span class="text-gray-400">********</span>
                            <?php else: ?>
                                <?= htmlspecialchars((string) $value) ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Tab: Git -->
<div id="tab-git" class="tab-panel hidden">
    <div class="card">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Git Status</h2>
        <?php if ($gitStatus['initialized'] ?? false): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="p-4 bg-gray-50 rounded-lg">
                    <p class="text-sm text-gray-500 mb-1">Branch</p>
                    <p class="text-lg font-mono font-medium text-gray-900"><?= htmlspecialchars($gitStatus['branch'] ?? 'Unknown') ?></p>
                </div>
                <div class="p-4 bg-gray-50 rounded-lg">
                    <p class="text-sm text-gray-500 mb-1">Commit</p>
                    <p class="text-lg font-mono font-medium text-gray-900"><?= htmlspecialchars($gitStatus['commit'] ?? 'Unknown') ?></p>
                </div>
            </div>
            <?php if (!empty($gitStatus['status'])): ?>
                <div class="mt-4 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <p class="text-sm font-medium text-yellow-900 mb-2">Working Directory Changes</p>
                    <pre class="text-xs font-mono text-yellow-800 whitespace-pre-wrap"><?= htmlspecialchars($gitStatus['status']) ?></pre>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="p-4 bg-gray-50 rounded-lg">
                <p class="text-sm text-gray-500"><?= $gitStatus['message'] ?? 'Git not available' ?></p>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.sub-nav-link.active { color: #2563eb !important; border-bottom-color: #2563eb !important; }
.sub-nav-link:hover { color: #374151; border-bottom-color: #d1d5db; }
</style>

<script nonce="<?= \DevDashboard\nonce() ?>">
function switchTab(tabId) {
    document.querySelectorAll('.sub-nav-link').forEach(l => l.classList.remove('active'));
    document.querySelector(`.sub-nav-link[data-tab="${tabId}"]`)?.classList.add('active');

    document.querySelectorAll('.tab-panel').forEach(p => p.classList.add('hidden'));
    document.getElementById('tab-' + tabId)?.classList.remove('hidden');

    history.replaceState(null, '', '#' + tabId);
}

document.querySelectorAll('.sub-nav-link').forEach(link => {
    link.addEventListener('click', (e) => {
        e.preventDefault();
        switchTab(link.dataset.tab);
    });
});

const hash = window.location.hash.slice(1);
if (hash && document.getElementById('tab-' + hash)) {
    switchTab(hash);
}
</script>
