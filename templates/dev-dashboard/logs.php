<?php
/**
 * @var array<string, mixed> $log_stats
 * @var array<string, array<string, mixed>> $log_sources
 * @var array<int, array<string, string>> $log_commands
 */
?>
<div class="space-y-6">
    <!-- Log Statistics -->
    <div class="card">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">📊 Log Statistics</h2>
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

    <!-- Available Log Sources -->
    <div class="card">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">📝 Available Log Sources</h2>
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
                                <code class="text-xs bg-gray-100 px-3 py-2 rounded text-gray-900 font-mono block overflow-x-auto"><?= htmlspecialchars((string) $source['command']) ?></code>
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
                                                    📄 <?= htmlspecialchars((string) $file['name']) ?>
                                                </button>
                                                <span style="font-size: 0.75rem; color: #6b7280; white-space: nowrap; margin-left: 1rem;">
                                                    <?= number_format($file['size'] / 1024, 1) ?> KB •
                                                    <?= date('Y-m-d H:i', $file['modified']) ?>
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

    <!-- Log Viewing Commands -->
    <div class="card">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">💻 Log Viewing Commands</h2>
        <p class="text-sm text-gray-600 mb-4">
            Use these commands from your host terminal to view logs in real-time:
        </p>
        <div class="grid grid-cols-1 gap-3">
            <?php foreach ($log_commands as $cmd): ?>
                <div class="border border-gray-200 rounded-lg p-4">
                    <h3 class="font-medium text-gray-900 mb-1"><?= htmlspecialchars($cmd['label']) ?></h3>
                    <p class="text-sm text-gray-600 mb-3"><?= htmlspecialchars($cmd['description']) ?></p>
                    <code class="text-xs bg-gray-100 px-3 py-2 rounded text-gray-900 font-mono block overflow-x-auto"><?= htmlspecialchars($cmd['command']) ?></code>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Usage Tips -->
    <div class="card bg-blue-50 border border-blue-200">
        <h2 class="text-lg font-semibold text-blue-900 mb-3">💡 Usage Tips</h2>
        <ul class="space-y-2 text-sm text-blue-800" style="list-style: none; padding-left: 1rem;">
            <li>• <strong>Real-time monitoring:</strong> Use <code class="bg-blue-100 px-1 py-0.5 rounded">-f</code> flag to follow logs in real-time</li>
            <li>• <strong>Filter by service:</strong> Add service name to view specific container logs</li>
            <li>• <strong>Tail logs:</strong> Use <code class="bg-blue-100 px-1 py-0.5 rounded">--tail=N</code> to show last N lines</li>
            <li>• <strong>Search patterns:</strong> Pipe to grep to search for specific errors or patterns</li>
            <li>• <strong>Save to file:</strong> Redirect output with <code class="bg-blue-100 px-1 py-0.5 rounded">&gt; logs.txt</code> to save logs</li>
        </ul>
    </div>

    <!-- Makefile Integration -->
    <div class="card bg-green-50 border border-green-200">
        <h2 class="text-lg font-semibold text-green-900 mb-3">🔧 Makefile Commands</h2>
        <p class="text-sm text-green-800 mb-3">
            The project includes a Makefile command for easy log access:
        </p>
        <code class="text-sm bg-green-100 px-3 py-2 rounded text-green-900 font-mono block">make logs</code>
        <p class="text-xs text-green-700 mt-2">
            This command shows logs from all services in real-time (equivalent to <code class="bg-green-100 px-1 py-0.5 rounded">docker compose logs -f</code>)
        </p>
    </div>
</div>

<!-- Log Viewer Modal -->
<div id="logModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 9999;">
    <!-- Backdrop -->
    <div style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.5);" onclick="closeLogModal()"></div>

    <!-- Modal Content -->
    <div style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 90%; max-width: 1000px; max-height: 90vh; background: white; border-radius: 0.5rem; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); display: flex; flex-direction: column;">
        <!-- Header -->
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

        <!-- Log Content -->
        <div style="flex: 1; overflow: auto; padding: 1rem; background: #111827; max-height: 60vh;">
            <pre id="logModalContent" style="font-size: 0.75rem; color: #4ade80; font-family: ui-monospace, monospace; white-space: pre-wrap; word-break: break-word; margin: 0;">Loading...</pre>
        </div>

        <!-- Footer -->
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

<script>
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
        contentEl.style.color = '#4ade80'; // green

        try {
            const response = await fetch(`/_dev/api/logs?file=${encodeURIComponent(filename)}&lines=${lines}`);
            const data = await response.json();

            if (data.error) {
                contentEl.textContent = `Error: ${data.error}`;
                contentEl.style.color = '#f87171'; // red
                metaEl.textContent = '';
            } else {
                contentEl.textContent = data.content || '(empty file)';
                contentEl.style.color = '#4ade80'; // green
                const sizeKb = (data.size / 1024).toFixed(1);
                const modified = new Date(data.modified * 1000).toLocaleString();
                metaEl.textContent = `${sizeKb} KB • Last modified: ${modified} • Showing last ${data.lines} lines`;
            }
        } catch (error) {
            contentEl.textContent = `Failed to load log: ${error.message}`;
            contentEl.style.color = '#f87171'; // red
            metaEl.textContent = '';
        }
    }

    // Close modal on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && document.getElementById('logModal').style.display !== 'none') {
            closeLogModal();
        }
    });
</script>
