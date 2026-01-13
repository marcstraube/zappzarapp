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
                            <div class="bg-gray-50 border border-gray-200 rounded p-3">
                                <p class="text-xs text-gray-600 mb-2">View from host terminal:</p>
                                <code class="text-xs bg-gray-100 px-2 py-1 rounded text-gray-900 font-mono block">
                                    <?= htmlspecialchars((string) $source['command']) ?>
                                </code>
                            </div>

                            <?php if (isset($source['services'])): ?>
                                <div class="mt-3">
                                    <p class="text-xs text-gray-600 mb-2">Available services:</p>
                                    <div class="flex flex-wrap gap-2">
                                        <?php foreach ($source['services'] as $service): ?>
                                            <span class="badge badge-blue text-xs"><?= htmlspecialchars((string) $service) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php elseif ($source['type'] === 'file' && isset($source['files'])): ?>
                            <?php if (count($source['files']) > 0): ?>
                                <div class="bg-gray-50 border border-gray-200 rounded p-3">
                                    <p class="text-xs text-gray-600 mb-2">Log files:</p>
                                    <div class="space-y-1">
                                        <?php foreach ($source['files'] as $file): ?>
                                            <div class="flex justify-between items-center text-xs">
                                                <span class="font-mono text-gray-700"><?= htmlspecialchars((string) $file['name']) ?></span>
                                                <span class="text-gray-500">
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
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="font-medium text-gray-900"><?= htmlspecialchars($cmd['label']) ?></h3>
                    </div>
                    <p class="text-sm text-gray-600 mb-3"><?= htmlspecialchars($cmd['description']) ?></p>
                    <code class="text-xs bg-gray-100 px-3 py-2 rounded text-gray-900 font-mono block">
                        <?= htmlspecialchars($cmd['command']) ?>
                    </code>
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
        <code class="text-sm bg-green-100 px-3 py-2 rounded text-green-900 font-mono block">
            make logs
        </code>
        <p class="text-xs text-green-700 mt-2">
            This command shows logs from all services in real-time (equivalent to <code class="bg-green-100 px-1 py-0.5 rounded">docker compose logs -f</code>)
        </p>
    </div>
</div>
