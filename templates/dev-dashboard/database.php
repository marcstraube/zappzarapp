<?php
/**
 * @var array<string, mixed> $overview
 * @var array<string, mixed> $connection_stats
 * @var array<int, array<string, mixed>> $tables
 * @var array<string, mixed> $commands
 * @var array<string, array<string, mixed>> $db_tools
 * @var array{count: int, totalSize: string, oldestDate: string, newestDate: string} $backup_stats
 */

$tabs = [
    'overview' => 'Overview',
    'tables'   => 'Tables (' . count($tables) . ')',
    'backups'  => 'Backups (' . $backup_stats['count'] . ')',
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

<!-- Tab: Backups -->
<div id="tab-backups" class="tab-panel hidden">
    <!-- Statistics Card -->
    <div class="card mb-4">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Backup Statistics</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="text-center p-4 bg-blue-50 rounded-lg">
                <p class="text-sm text-gray-600 mb-1">Total Backups</p>
                <p class="text-2xl font-bold text-blue-600" id="stat-count"><?= $backup_stats['count'] ?></p>
            </div>
            <div class="text-center p-4 bg-green-50 rounded-lg">
                <p class="text-sm text-gray-600 mb-1">Total Size</p>
                <p class="text-lg font-bold text-green-600" id="stat-size"><?= htmlspecialchars($backup_stats['totalSize']) ?></p>
            </div>
            <div class="text-center p-4 bg-purple-50 rounded-lg">
                <p class="text-sm text-gray-600 mb-1">Newest</p>
                <p class="text-sm font-bold text-purple-600" id="stat-newest"><?= $backup_stats['newestDate'] ? htmlspecialchars($backup_stats['newestDate']) : '-' ?></p>
            </div>
            <div class="text-center p-4 bg-yellow-50 rounded-lg">
                <p class="text-sm text-gray-600 mb-1">Oldest</p>
                <p class="text-sm font-bold text-yellow-600" id="stat-oldest"><?= $backup_stats['oldestDate'] ? htmlspecialchars($backup_stats['oldestDate']) : '-' ?></p>
            </div>
        </div>
    </div>

    <!-- Create Backup Card -->
    <div class="card mb-4">
        <h2 class="text-lg font-semibold text-gray-900 mb-3">Create Backup</h2>
        <p class="text-sm text-gray-600 mb-4">Creates database backup with optional encryption and configurable retention policy.</p>

        <div class="flex items-end gap-3">
            <div style="flex: 1;">
                <label for="retention-input" class="text-sm font-medium text-gray-700 mb-1 block">Retention (days)</label>
                <input type="number" id="retention-input" value="30" min="0" max="365"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                       placeholder="Leave empty for default (30 days)">
                <p class="text-xs text-gray-500 mt-1">0 = keep all backups (no auto-deletion)</p>
            </div>
            <div style="flex: 0 0 auto;">
                <label class="flex items-center gap-2 text-sm font-medium text-gray-700 mb-1 block" style="white-space: nowrap;">
                    <input type="checkbox" id="encrypt-input" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    Encrypt Backup
                </label>
                <p class="text-xs text-gray-500">Requires BACKUP_ENCRYPTION_KEY</p>
            </div>
            <button id="btn-create-backup" data-action="create-backup" class="btn btn-primary" style="flex-shrink: 0;">
                Create Backup Now
            </button>
        </div>
    </div>

    <!-- Backup List Card -->
    <div class="card">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-gray-900 mb-0">Available Backups</h2>
            <button data-action="load-backups" class="btn btn-secondary text-xs">Refresh</button>
        </div>
        <div id="backup-list">
            <div class="flex items-center justify-center py-8">
                <span class="text-gray-500">Loading backups...</span>
            </div>
        </div>
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
                            <button type="button" data-action="copy-cmd" data-cmd="<?= htmlspecialchars($tool['stop_cmd']) ?>" class="btn btn-secondary text-sm">Stop</button>
                        <?php else: ?>
                            <button type="button" data-action="copy-cmd" data-cmd="<?= htmlspecialchars($tool['start_cmd']) ?>" class="btn btn-primary text-sm" style="flex: 1;">Start</button>
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
            <button type="button" data-action="copy-cmd" data-cmd="make db-tools-up" class="btn btn-secondary text-xs">Start Both</button>
            <button type="button" data-action="copy-cmd" data-cmd="make db-tools-down" class="btn btn-secondary text-xs">Stop Both</button>
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
                        <button type="button" data-action="copy-cmd" data-cmd="<?= htmlspecialchars((string) $cmd['command']) ?>" class="btn btn-secondary text-xs" style="flex-shrink: 0;">Copy</button>
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
                <button type="button" data-action="copy-cmd" data-cmd="make ide-config" class="btn btn-secondary text-xs">Copy</button>
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

<script nonce="<?= \DevDashboard\nonce() ?>">
// Tab switching with URL hash persistence
function switchTab(tabId) {
    document.querySelectorAll('.sub-nav-link').forEach(l => l.classList.remove('active'));
    document.querySelector(`.sub-nav-link[data-tab="${tabId}"]`)?.classList.add('active');

    document.querySelectorAll('.tab-panel').forEach(p => p.classList.add('hidden'));
    document.getElementById('tab-' + tabId)?.classList.remove('hidden');

    history.replaceState(null, '', '#' + tabId);

    // Load backups when backups tab is shown
    if (tabId === 'backups') {
        loadBackups();
    }
}

document.querySelectorAll('.sub-nav-link').forEach(link => {
    link.addEventListener('click', (e) => {
        e.preventDefault();
        switchTab(link.dataset.tab);
    });
});

// Event delegation for data-action clicks (CSP-compliant)
document.addEventListener('click', (e) => {
    const target = e.target.closest('[data-action]');
    if (!target) return;

    const action = target.dataset.action;
    switch (action) {
        case 'create-backup':
            createBackup();
            break;
        case 'load-backups':
            loadBackups();
            break;
        case 'copy-cmd':
            copyCmd(target.dataset.cmd, target);
            break;
        case 'restore-backup':
            restoreBackup(target.dataset.filename);
            break;
        case 'delete-backup':
            deleteBackup(target.dataset.filename);
            break;
    }
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

// === BACKUP MANAGEMENT FUNCTIONS ===

// Load backups from API
async function loadBackups() {
    try {
        const response = await fetch('/_dev/api/backup/list');
        const result = await response.json();

        if (result.success) {
            updateBackupList(result.backups || []);
        } else {
            showNotification('error', 'Failed to load backups');
        }
    } catch (error) {
        showNotification('error', 'Failed to load backups: ' + error.message);
    }
}

// Create new backup
async function createBackup() {
    const retentionInput = document.getElementById('retention-input');
    const encryptInput = document.getElementById('encrypt-input');
    const retention = retentionInput?.value || '';
    const encrypt = encryptInput?.checked || false;
    const btn = document.getElementById('btn-create-backup');

    const confirmMsg = encrypt
        ? 'Create an encrypted database backup? This may take a few moments.\n\nNote: You will need BACKUP_ENCRYPTION_KEY to restore this backup.'
        : 'Create an unencrypted database backup? This may take a few moments.';

    if (!confirm(confirmMsg)) {
        return;
    }

    const originalText = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Creating...';

    try {
        const params = new URLSearchParams();
        if (retention) params.append('retention', retention);
        if (encrypt) params.append('encrypt', 'true');

        const url = params.toString() ? `/_dev/api/backup/create?${params.toString()}` : '/_dev/api/backup/create';
        const response = await fetch(url, { method: 'POST' });
        const result = await response.json();

        if (result.success) {
            showNotification('success', result.message);
            loadBackups(); // Refresh list
        } else {
            showNotification('error', result.message);
        }
    } catch (error) {
        showNotification('error', 'Failed to create backup: ' + error.message);
    } finally {
        btn.disabled = false;
        btn.textContent = originalText;
    }
}

// Restore backup
async function restoreBackup(filename) {
    const confirmed = confirm(
        '⚠️ WARNING: This will OVERWRITE the current database!\n\n' +
        'Restoring: ' + filename + '\n\n' +
        'This action cannot be undone. Continue?'
    );

    if (!confirmed) return;

    const btnId = 'btn-restore-' + btoa(filename);
    const btn = document.getElementById(btnId);
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Restoring...';

    try {
        const response = await fetch('/_dev/api/backup/restore', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ filename })
        });

        const result = await response.json();

        if (result.success) {
            showNotification('success', result.message);
        } else {
            showNotification('error', result.message);
        }
    } catch (error) {
        showNotification('error', 'Failed to restore backup: ' + error.message);
    } finally {
        btn.disabled = false;
        btn.textContent = originalText;
    }
}

// Delete backup
async function deleteBackup(filename) {
    if (!confirm('Delete backup: ' + filename + '?\n\nThis action cannot be undone.')) {
        return;
    }

    try {
        const response = await fetch('/_dev/api/backup/delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ filename })
        });

        const result = await response.json();

        if (result.success) {
            showNotification('success', result.message);
            loadBackups(); // Refresh list
        } else {
            showNotification('error', result.message);
        }
    } catch (error) {
        showNotification('error', 'Failed to delete backup: ' + error.message);
    }
}

// Update backup list UI
function updateBackupList(backups) {
    const container = document.getElementById('backup-list');

    if (!backups || backups.length === 0) {
        container.innerHTML = '<p class="text-gray-600 text-center py-8">No backups found. Create your first backup above.</p>';
        updateBackupStats({ count: 0, totalSize: '0 B', oldestDate: 'N/A', newestDate: 'N/A' });
        return;
    }

    container.innerHTML = backups.map(backup => {
        const encryptedBadge = backup.encrypted
            ? '<span class="badge badge-green ml-2">Encrypted</span>'
            : '<span class="badge badge-gray ml-2">Unencrypted</span>';

        return `
            <div class="border border-gray-200 rounded-lg p-4 mb-3 hover:bg-gray-50">
                <div class="flex items-start justify-between gap-4">
                    <div style="flex: 1;">
                        <h3 class="font-medium text-gray-900 mb-1">${escapeHtml(backup.filename)}</h3>
                        <p class="text-sm text-gray-600">
                            ${escapeHtml(backup.timestamp)} • ${escapeHtml(backup.size)} • ${escapeHtml(backup.age)}
                            ${encryptedBadge}
                        </p>
                        <p class="text-xs text-gray-500 mt-1">
                            DB: ${escapeHtml(backup.dbType)} / ${escapeHtml(backup.dbName)}
                        </p>
                    </div>
                    <div class="flex gap-2" style="flex-shrink: 0;">
                        <button data-action="restore-backup" data-filename="${escapeJs(backup.filename)}"
                                id="btn-restore-${btoa(backup.filename)}"
                                class="btn btn-primary text-sm">
                            Restore
                        </button>
                        <button data-action="delete-backup" data-filename="${escapeJs(backup.filename)}"
                                class="btn btn-danger text-sm">
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        `;
    }).join('');

    // Update stats
    const totalBytes = backups.reduce((sum, b) => sum + (b.sizeBytes || 0), 0);
    updateBackupStats({
        count: backups.length,
        totalSize: formatBytes(totalBytes),
        oldestDate: backups[backups.length - 1]?.timestamp || 'N/A',
        newestDate: backups[0]?.timestamp || 'N/A'
    });
}

// Update statistics display
function updateBackupStats(stats) {
    document.getElementById('stat-count').textContent = stats.count;
    document.getElementById('stat-size').textContent = stats.totalSize;
    document.getElementById('stat-newest').textContent = stats.newestDate;
    document.getElementById('stat-oldest').textContent = stats.oldestDate;
}

// Show notification
function showNotification(type, message) {
    const bgColor = type === 'success' ? '#10b981' : '#ef4444';
    const notification = document.createElement('div');
    notification.style.cssText = `
        position: fixed; top: 80px; right: 20px; z-index: 9999;
        background: ${bgColor}; color: white;
        padding: 1rem 1.5rem; border-radius: 0.5rem; box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        max-width: 400px; animation: slideIn 0.3s ease-out;
    `;
    notification.textContent = message;
    document.body.appendChild(notification);

    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease-out';
        setTimeout(() => notification.remove(), 300);
    }, 4000);
}

// Helper: Escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Helper: Escape for JS string
function escapeJs(text) {
    return text.replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '\\"');
}

// Helper: Format bytes
function formatBytes(bytes) {
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let factor = 0;
    while (bytes >= 1024 && factor < units.length - 1) {
        bytes /= 1024;
        factor++;
    }
    return bytes.toFixed(2) + ' ' + units[factor];
}

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn { from { transform: translateX(400px); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
    @keyframes slideOut { from { transform: translateX(0); opacity: 1; } to { transform: translateX(400px); opacity: 0; } }
`;
document.head.appendChild(style);
</script>
