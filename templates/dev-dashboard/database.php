<?php
/**
 * @var array<string, mixed> $overview
 * @var array<string, mixed> $connection_stats
 * @var array<int, array<string, mixed>> $tables
 * @var array<string, mixed> $commands
 * @var array<string, array<string, mixed>> $db_tools
 */

$tabs = [
    'overview' => 'Overview',
    'tables'   => 'Tables (' . count($tables) . ')',
    'tools'    => 'Tools',
    'commands' => 'Commands',
    'tips'     => 'Tips',
];
?>

<?php if (!$overview['connected']): ?>
    <div class="card bg-red-50 border border-red-200">
        <h2 class="text-lg font-semibold text-red-900 mb-3">Database Connection Error</h2>
        <p class="text-red-800 mb-4"><?= htmlspecialchars($overview['error'] ?? 'Could not connect to database') ?></p>
        <p class="text-sm text-red-700">
            Check <code class="bg-red-100 px-1 py-0.5 rounded">.env</code> and ensure the database container is running.
        </p>
    </div>
<?php else: ?>

<!-- Sub-Navigation -->
<nav class="sub-nav" style="background: white; border: 1px solid #e5e7eb; border-radius: 0.5rem; margin-bottom: 1.5rem; position: sticky; top: 120px; z-index: 50;">
    <div style="display: flex; gap: 0;">
        <?php foreach ($tabs as $id => $label): ?>
            <a href="#"
               data-tab="<?= $id ?>"
               class="sub-nav-link <?= $id === 'overview' ? 'active' : '' ?>"
               style="display: inline-block; padding: 0.75rem 1rem; border-bottom: 2px solid transparent; color: #6b7280; font-size: 0.875rem; font-weight: 500; text-decoration: none; transition: all 0.2s;"
            ><?= $label ?></a>
        <?php endforeach; ?>
    </div>
</nav>

<!-- Tab: Overview -->
<div id="tab-overview" class="tab-panel">
    <div class="card">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Database Overview</h2>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
            <div class="text-center p-4 bg-blue-50 rounded-lg">
                <p class="text-sm text-gray-600 mb-1">Type</p>
                <p class="text-2xl font-bold text-blue-600"><?= strtoupper((string) $overview['type']) ?></p>
            </div>
            <div class="text-center p-4 bg-green-50 rounded-lg">
                <p class="text-sm text-gray-600 mb-1">Version</p>
                <p class="text-lg font-bold text-green-600"><?= htmlspecialchars((string) $overview['version']) ?></p>
            </div>
            <div class="text-center p-4 bg-purple-50 rounded-lg">
                <p class="text-sm text-gray-600 mb-1">Tables</p>
                <p class="text-2xl font-bold text-purple-600"><?= $overview['table_count'] ?></p>
            </div>
            <div class="text-center p-4 bg-yellow-50 rounded-lg">
                <p class="text-sm text-gray-600 mb-1">Size</p>
                <p class="text-lg font-bold text-yellow-600"><?= htmlspecialchars((string) $overview['total_size']) ?></p>
            </div>
        </div>

        <div class="p-4 bg-gray-50 rounded-lg">
            <dl class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <dt class="text-gray-600">Host:</dt>
                    <dd class="font-mono text-gray-900"><?= htmlspecialchars((string) $overview['host']) ?>:<?= htmlspecialchars((string) $overview['port']) ?></dd>
                </div>
                <div>
                    <dt class="text-gray-600">Database:</dt>
                    <dd class="font-mono text-gray-900"><?= htmlspecialchars((string) $overview['database']) ?></dd>
                </div>
            </dl>
        </div>

        <?php if ($connection_stats['available']): ?>
            <h3 class="text-md font-semibold text-gray-900 mb-3 mt-4">Connection Pool</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="border border-gray-200 rounded-lg p-4 text-center">
                    <p class="text-3xl font-bold text-blue-600"><?= $connection_stats['total'] ?></p>
                    <p class="text-sm text-gray-600 mt-1">Total</p>
                </div>
                <?php if ($connection_stats['active'] !== 'N/A'): ?>
                    <div class="border border-gray-200 rounded-lg p-4 text-center">
                        <p class="text-3xl font-bold text-green-600"><?= $connection_stats['active'] ?></p>
                        <p class="text-sm text-gray-600 mt-1">Active</p>
                    </div>
                    <div class="border border-gray-200 rounded-lg p-4 text-center">
                        <p class="text-3xl font-bold text-gray-600"><?= $connection_stats['idle'] ?></p>
                        <p class="text-sm text-gray-600 mt-1">Idle</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Tab: Tables -->
<div id="tab-tables" class="tab-panel hidden">
    <div class="card">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Database Tables</h2>
        <?php if (count($tables) > 0): ?>
            <div class="overflow-x-auto border border-gray-200 rounded-lg">
                <table>
                    <thead>
                        <tr>
                            <th>Table Name</th>
                            <?php if (isset($tables[0]['schema'])): ?><th>Schema</th><?php endif; ?>
                            <th>Rows</th>
                            <th>Size</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tables as $table): ?>
                            <tr>
                                <td class="font-mono font-medium text-gray-900"><?= htmlspecialchars((string) $table['name']) ?></td>
                                <?php if (isset($table['schema'])): ?><td class="text-gray-600"><?= htmlspecialchars((string) $table['schema']) ?></td><?php endif; ?>
                                <td class="text-gray-700"><?= number_format($table['row_count']) ?></td>
                                <td class="text-gray-700"><?= htmlspecialchars((string) $table['size']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <p class="text-yellow-800">No tables found. Run <code class="bg-yellow-100 px-1 rounded">make db-migrations</code> to create tables.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Tab: Database Tools -->
<div id="tab-tools" class="tab-panel hidden">
    <div class="card bg-purple-50 border border-purple-200">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-purple-900 mb-0">Database Tools</h2>
            <span class="badge badge-blue">Development Only</span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <?php foreach ($db_tools as $key => $tool): ?>
                <?php if (isset($tool['db_type']) && $tool['db_type'] !== $overview['type']) continue; ?>
                <div class="bg-white rounded-lg border border-purple-200 p-4">
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="font-semibold text-purple-900"><?= htmlspecialchars($tool['name']) ?></h3>
                        <span class="badge <?= $tool['enabled'] ? 'badge-green' : 'badge-gray' ?>">
                            <?= $tool['enabled'] ? 'Enabled' : 'Disabled' ?>
                        </span>
                    </div>
                    <p class="text-sm text-purple-700 mb-3"><?= htmlspecialchars($tool['description']) ?></p>

                    <div class="flex gap-2">
                        <?php if ($tool['enabled']): ?>
                            <a href="<?= htmlspecialchars($tool['url']) ?>" target="_blank" class="btn btn-primary text-sm" style="flex: 1; text-align: center;">Open</a>
                            <button type="button" onclick="copyCmd('<?= htmlspecialchars($tool['stop_cmd']) ?>', this)" class="btn btn-secondary text-sm">Stop</button>
                        <?php else: ?>
                            <button type="button" onclick="copyCmd('<?= htmlspecialchars($tool['start_cmd']) ?>', this)" class="btn btn-primary text-sm" style="flex: 1;">Start</button>
                        <?php endif; ?>
                    </div>

                    <?php if (!$tool['enabled']): ?>
                        <p class="text-xs text-purple-600 mt-2">
                            Or set <code class="bg-purple-200 px-1 rounded">ENABLE_<?= strtoupper($key) ?>=true</code> in <code class="bg-purple-200 px-1 rounded">.env.local</code>
                        </p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="flex gap-2" style="margin-top: 1rem; padding-top: 0.75rem; border-top: 1px solid #e9d5ff;">
            <button type="button" onclick="copyCmd('make db-tools-up', this)" class="btn btn-secondary text-xs">Start Both</button>
            <button type="button" onclick="copyCmd('make db-tools-down', this)" class="btn btn-secondary text-xs">Stop Both</button>
        </div>
    </div>
</div>

<!-- Tab: Commands -->
<div id="tab-commands" class="tab-panel hidden">
    <div class="card">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">CLI Commands</h2>
        <div class="space-y-3">
            <?php foreach ($commands as $cmd): ?>
                <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition">
                    <div class="flex items-start justify-between gap-4">
                        <div style="flex: 1;">
                            <h3 class="font-medium text-gray-900"><?= htmlspecialchars((string) $cmd['label']) ?></h3>
                            <p class="text-sm text-gray-600 mt-1"><?= htmlspecialchars((string) $cmd['description']) ?></p>
                            <code class="text-sm bg-gray-100 px-3 py-2 rounded text-gray-900 font-mono block mt-2"><?= htmlspecialchars((string) $cmd['command']) ?></code>
                        </div>
                        <button type="button" onclick="copyCmd('<?= htmlspecialchars((string) $cmd['command']) ?>', this)" class="btn btn-secondary text-xs" style="flex-shrink: 0;">Copy</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Tab: Tips -->
<div id="tab-tips" class="tab-panel hidden">
    <div class="space-y-4">
        <div class="card bg-blue-50 border border-blue-200">
            <h3 class="font-semibold text-blue-900 mb-2">JetBrains IDE Integration</h3>
            <p class="text-sm text-blue-800 mb-3">Configure PHPStorm/DataGrip for direct database access:</p>
            <div class="flex items-center gap-2 mb-3">
                <code class="text-sm font-mono text-blue-900 bg-white px-3 py-2 rounded border border-blue-200">make ide-config</code>
                <button type="button" onclick="copyCmd('make ide-config', this)" class="btn btn-secondary text-xs">Copy</button>
            </div>
            <ul class="text-sm text-blue-800 space-y-1" style="list-style: none; padding: 0;">
                <li>Host: <code class="bg-blue-100 px-1 rounded">localhost:<?= htmlspecialchars((string) $overview['port']) ?></code></li>
                <li>User: <code class="bg-blue-100 px-1 rounded">app</code></li>
                <li>Password: <code class="bg-blue-100 px-1 rounded">secrets/db_password.txt</code></li>
            </ul>
        </div>

        <div class="card">
            <h3 class="font-semibold text-gray-900 mb-2">External Database Clients</h3>
            <p class="text-sm text-gray-600 mb-3">Desktop applications for database management:</p>
            <ul class="text-sm text-gray-700 space-y-2" style="list-style: none; padding: 0;">
                <li><strong>DBeaver:</strong> Universal database tool, free and open-source</li>
                <li><strong>TablePlus:</strong> Modern native UI (macOS, Windows, Linux)</li>
                <li><strong>DataGrip:</strong> JetBrains professional database IDE</li>
            </ul>
        </div>
    </div>
</div>

<?php endif; ?>

<style>
.sub-nav-link.active { color: #2563eb !important; border-bottom-color: #2563eb !important; }
.sub-nav-link:hover { color: #374151; border-bottom-color: #d1d5db; }
</style>

<script>
// Tab switching with URL hash persistence
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

// Restore tab from URL hash on page load
const hash = window.location.hash.slice(1);
if (hash && document.getElementById('tab-' + hash)) {
    switchTab(hash);
}

// Copy command
function copyCmd(cmd, btn) {
    navigator.clipboard.writeText(cmd).then(() => {
        const orig = btn.textContent;
        btn.textContent = 'Copied!';
        btn.classList.add('bg-green-100');
        setTimeout(() => { btn.textContent = orig; btn.classList.remove('bg-green-100'); }, 1500);
    });
}
</script>
