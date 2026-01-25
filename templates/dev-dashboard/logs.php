<?php
/**
 * @var array<string, mixed> $log_stats
 * @var array<string, array<string, mixed>> $log_sources
 * @var array<int, array<string, string>> $log_commands
 */

$tabs = [
    'overview' => 'Overview',
    'sources'  => 'Sources',
    'commands' => 'Commands',
    'tips'     => 'Tips',
];
?>

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
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Log Statistics</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="text-center p-4 bg-blue-50 rounded-lg">
                <p class="text-3xl font-bold text-blue-600"><?= $log_stats['application_logs_count'] ?></p>
                <p class="text-sm text-gray-600 mt-1">Application Log Files</p>
            </div>
            <div class="text-center p-4 bg-green-50 rounded-lg">
                <p class="text-3xl font-bold text-green-600"><?= $log_stats['total_size_formatted'] ?></p>
                <p class="text-sm text-gray-600 mt-1">Total Log Size</p>
            </div>
            <div class="text-center p-4 bg-purple-50 rounded-lg">
                <p class="text-3xl font-bold text-purple-600"><?= count($log_sources) ?></p>
                <p class="text-sm text-gray-600 mt-1">Log Sources</p>
            </div>
        </div>
    </div>

    <div class="card" style="margin-top: 1.5rem;">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Quick Access</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition">
                <h3 class="font-medium text-gray-900 mb-2">All Container Logs</h3>
                <p class="text-sm text-gray-600 mb-3">View logs from all running containers</p>
                <div class="flex items-center gap-2">
                    <code class="text-xs bg-gray-100 px-2 py-1 rounded text-gray-900 font-mono flex-1">make logs</code>
                    <button type="button" onclick="copyCmd('make logs', this)" class="btn btn-secondary text-xs">Copy</button>
                </div>
            </div>
            <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition">
                <h3 class="font-medium text-gray-900 mb-2">Follow Specific Service</h3>
                <p class="text-sm text-gray-600 mb-3">Watch logs from a single service in real-time</p>
                <div class="flex items-center gap-2">
                    <code class="text-xs bg-gray-100 px-2 py-1 rounded text-gray-900 font-mono flex-1">docker compose logs -f php</code>
                    <button type="button" onclick="copyCmd('docker compose logs -f php', this)" class="btn btn-secondary text-xs">Copy</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tab: Sources -->
<div id="tab-sources" class="tab-panel hidden">
    <div class="card">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Available Log Sources</h2>
        <div class="space-y-4">
            <?php foreach ($log_sources as $source): ?>
                <div class="border border-gray-200 rounded-lg p-4 <?= $source['available'] ? '' : 'bg-gray-50 opacity-75' ?>">
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="font-medium text-gray-900"><?= htmlspecialchars((string) $source['name']) ?></h3>
                        <span class="badge <?= $source['available'] ? 'badge-green' : 'badge-gray' ?>">
                            <?= $source['available'] ? '✓ Available' : 'Disabled' ?>
                        </span>
                    </div>
                    <p class="text-sm text-gray-600 mb-3"><?= htmlspecialchars((string) $source['description']) ?></p>

                    <?php if ($source['available']): ?>
                        <?php if ($source['type'] === 'docker'): ?>
                            <div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 0.375rem; padding: 0.75rem;">
                                <p style="font-size: 0.75rem; color: #6b7280; margin-bottom: 0.5rem;">View from host terminal:</p>
                                <div class="flex items-center gap-2">
                                    <code class="text-xs bg-gray-100 px-3 py-2 rounded text-gray-900 font-mono block overflow-x-auto flex-1"><?= htmlspecialchars((string) $source['command']) ?></code>
                                    <button type="button" onclick="copyCmd('<?= htmlspecialchars((string) $source['command']) ?>', this)" class="btn btn-secondary text-xs">Copy</button>
                                </div>
                            </div>

                            <?php if (isset($source['services'])): ?>
                                <div style="margin-top: 0.75rem;">
                                    <p style="font-size: 0.75rem; color: #6b7280; margin-bottom: 0.5rem;">Available services:</p>
                                    <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                                        <?php foreach ($source['services'] as $service): ?>
                                            <span class="badge badge-blue" style="font-size: 0.75rem;"><?= htmlspecialchars((string) $service) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php elseif ($source['type'] === 'file' && isset($source['files'])): ?>
                            <?php if (count($source['files']) > 0): ?>
                                <div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 0.375rem; padding: 0.75rem;">
                                    <p style="font-size: 0.75rem; color: #6b7280; margin-bottom: 0.5rem;">Log files (click to view):</p>
                                    <div style="display: flex; flex-direction: column; gap: 0.25rem;">
                                        <?php foreach ($source['files'] as $file): ?>
                                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0.75rem; background: white; border: 1px solid #e5e7eb; border-radius: 0.25rem; transition: all 0.15s;">
                                                <button
                                                    onclick="openLogModal('<?= htmlspecialchars((string) $file['name'], ENT_QUOTES) ?>')"
                                                    style="font-family: ui-monospace, monospace; font-size: 0.8125rem; color: #2563eb; background: none; border: none; cursor: pointer; text-align: left; padding: 0;"
                                                    onmouseover="this.style.color='#1d4ed8'; this.style.textDecoration='underline';"
                                                    onmouseout="this.style.color='#2563eb'; this.style.textDecoration='none';"
                                                >
                                                    <?= htmlspecialchars((string) $file['name']) ?>
                                                </button>
                                                <span style="font-size: 0.75rem; color: #6b7280; white-space: nowrap; margin-left: 1rem;">
                                                    <?= number_format($file['size'] / 1024, 1) ?> KB
                                                </span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="bg-yellow-50 border border-yellow-200 rounded p-3">
                                    <p class="text-sm text-yellow-800">
                                        No application log files found in <?= htmlspecialchars((string) $source['path']) ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Tab: Commands -->
<div id="tab-commands" class="tab-panel hidden">
    <div class="card">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Log Viewing Commands</h2>
        <p class="text-sm text-gray-600 mb-4">
            Use these commands from your host terminal to view logs in real-time:
        </p>
        <div class="grid grid-cols-1 gap-3">
            <?php foreach ($log_commands as $cmd): ?>
                <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition">
                    <div class="flex items-start justify-between gap-4">
                        <div style="flex: 1;">
                            <h3 class="font-medium text-gray-900 mb-1"><?= htmlspecialchars($cmd['label']) ?></h3>
                            <p class="text-sm text-gray-600 mb-3"><?= htmlspecialchars($cmd['description']) ?></p>
                            <code class="text-xs bg-gray-100 px-3 py-2 rounded text-gray-900 font-mono block overflow-x-auto"><?= htmlspecialchars($cmd['command']) ?></code>
                        </div>
                        <button type="button" onclick="copyCmd('<?= htmlspecialchars($cmd['command']) ?>', this)" class="btn btn-secondary text-xs" style="flex-shrink: 0;">Copy</button>
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
            <h2 class="text-lg font-semibold text-blue-900 mb-3">Usage Tips</h2>
            <ul class="space-y-2 text-sm text-blue-800" style="list-style: none; padding-left: 1rem;">
                <li>• <strong>Real-time monitoring:</strong> Use <code class="bg-blue-100 px-1 py-0.5 rounded">-f</code> flag to follow logs in real-time</li>
                <li>• <strong>Filter by service:</strong> Add service name to view specific container logs</li>
                <li>• <strong>Tail logs:</strong> Use <code class="bg-blue-100 px-1 py-0.5 rounded">--tail=N</code> to show last N lines</li>
                <li>• <strong>Search patterns:</strong> Pipe to grep to search for specific errors or patterns</li>
                <li>• <strong>Save to file:</strong> Redirect output with <code class="bg-blue-100 px-1 py-0.5 rounded">&gt; logs.txt</code> to save logs</li>
            </ul>
        </div>

        <div class="card bg-green-50 border border-green-200">
            <h2 class="text-lg font-semibold text-green-900 mb-3">Makefile Commands</h2>
            <p class="text-sm text-green-800 mb-3">
                The project includes a Makefile command for easy log access:
            </p>
            <div class="flex items-center gap-2">
                <code class="text-sm bg-green-100 px-3 py-2 rounded text-green-900 font-mono flex-1">make logs</code>
                <button type="button" onclick="copyCmd('make logs', this)" class="btn btn-secondary text-xs">Copy</button>
            </div>
            <p class="text-xs text-green-700 mt-2">
                This command shows logs from all services in real-time (equivalent to <code class="bg-green-100 px-1 py-0.5 rounded">docker compose logs -f</code>)
            </p>
        </div>
    </div>
</div>

<!-- Log Viewer Modal -->
<div id="logModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 9999;">
    <div style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.5);" onclick="closeLogModal()"></div>

    <div style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 90%; max-width: 1000px; max-height: 90vh; background: white; border-radius: 0.5rem; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); display: flex; flex-direction: column;">
        <div style="display: flex; align-items: center; justify-content: space-between; padding: 1rem 1.5rem; border-bottom: 1px solid #e5e7eb; background: #f9fafb; border-radius: 0.5rem 0.5rem 0 0;">
            <div>
                <h3 id="logModalTitle" style="font-size: 1.125rem; font-weight: 600; color: #111827; margin: 0;">Log Viewer</h3>
                <p id="logModalMeta" style="font-size: 0.75rem; color: #6b7280; margin-top: 0.25rem;"></p>
            </div>
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <select id="logLinesSelect" onchange="refreshLog()">
                    <option value="50">Last 50 lines</option>
                    <option value="100" selected>Last 100 lines</option>
                    <option value="200">Last 200 lines</option>
                    <option value="500">Last 500 lines</option>
                </select>
                <button onclick="refreshLog()" style="padding: 0.5rem; color: #6b7280; background: none; border: none; cursor: pointer;" title="Refresh">
                    ↻
                </button>
                <button onclick="closeLogModal()" style="padding: 0.5rem; color: #6b7280; background: none; border: none; cursor: pointer; font-size: 1.25rem;" title="Close">
                    ✕
                </button>
            </div>
        </div>

        <div style="flex: 1; overflow: auto; padding: 1rem; background: #111827; max-height: 60vh;">
            <pre id="logModalContent" style="font-size: 0.75rem; color: #4ade80; font-family: ui-monospace, monospace; white-space: pre-wrap; word-break: break-word; margin: 0;">Loading...</pre>
        </div>

        <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.75rem 1.5rem; border-top: 1px solid #e5e7eb; background: #f9fafb; border-radius: 0 0 0.5rem 0.5rem;">
            <p style="font-size: 0.75rem; color: #6b7280; margin: 0;">
                Press <kbd style="padding: 0.125rem 0.375rem; background: #e5e7eb; border-radius: 0.25rem; color: #374151;">Esc</kbd> to close
            </p>
            <button onclick="closeLogModal()" class="btn btn-secondary">
                Close
            </button>
        </div>
    </div>
</div>

<style>
.sub-nav-link.active { color: #2563eb !important; border-bottom-color: #2563eb !important; }
.sub-nav-link:hover { color: #374151; border-bottom-color: #d1d5db; }
</style>

<script>
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

function copyCmd(cmd, btn) {
    navigator.clipboard.writeText(cmd).then(() => {
        const orig = btn.textContent;
        btn.textContent = 'Copied!';
        btn.classList.add('bg-green-100');
        setTimeout(() => { btn.textContent = orig; btn.classList.remove('bg-green-100'); }, 1500);
    });
}

let currentLogFile = '';

function openLogModal(filename) {
    currentLogFile = filename;
    document.getElementById('logModal').style.display = 'block';
    document.getElementById('logModalTitle').textContent = filename;
    document.body.style.overflow = 'hidden';
    loadLogContent(filename);
}

function closeLogModal() {
    document.getElementById('logModal').style.display = 'none';
    document.body.style.overflow = '';
    currentLogFile = '';
}

function refreshLog() {
    if (currentLogFile) {
        loadLogContent(currentLogFile);
    }
}

async function loadLogContent(filename) {
    const contentEl = document.getElementById('logModalContent');
    const metaEl = document.getElementById('logModalMeta');
    const lines = document.getElementById('logLinesSelect').value;

    contentEl.textContent = 'Loading...';
    contentEl.style.color = '#4ade80';

    try {
        const response = await fetch(`/_dev/api/logs?file=${encodeURIComponent(filename)}&lines=${lines}`);
        const data = await response.json();

        if (data.error) {
            contentEl.textContent = `Error: ${data.error}`;
            contentEl.style.color = '#f87171';
            metaEl.textContent = '';
        } else {
            contentEl.textContent = data.content || '(empty file)';
            contentEl.style.color = '#4ade80';
            const sizeKb = (data.size / 1024).toFixed(1);
            const modified = new Date(data.modified * 1000).toLocaleString();
            metaEl.textContent = `${sizeKb} KB • Last modified: ${modified} • Showing last ${data.lines} lines`;
        }
    } catch (error) {
        contentEl.textContent = `Failed to load log: ${error.message}`;
        contentEl.style.color = '#f87171';
        metaEl.textContent = '';
    }
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && document.getElementById('logModal').style.display !== 'none') {
        closeLogModal();
    }
});
</script>
