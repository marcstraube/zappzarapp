<?php
/**
 * @var array<string, mixed> $overview
 * @var array<string, mixed> $connection_stats
 * @var array<int, array<string, mixed>> $tables
 * @var array<string, mixed> $commands
 */
?>
<?php if (!$overview['connected']): ?>
    <!-- Database Not Connected -->
    <div class="card bg-red-50 border border-red-200">
        <h2 class="text-lg font-semibold text-red-900 mb-3">❌ Database Connection Error</h2>
        <p class="text-red-800 mb-4"><?= htmlspecialchars($overview['error'] ?? 'Could not connect to database') ?></p>
        <p class="text-sm text-red-700">
            Please check your database configuration in <code class="bg-red-100 px-1 py-0.5 rounded">.env</code> and ensure the database container is running.
        </p>
    </div>
<?php else: ?>
    <div class="space-y-6">
        <!-- Database Overview -->
        <div class="card">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">💾 Database Overview</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="text-center p-4 bg-blue-50 rounded-lg">
                    <p class="text-sm text-gray-600 mb-1">Database Type</p>
                    <p class="text-2xl font-bold text-blue-600"><?= strtoupper($overview['type']) ?></p>
                </div>
                <div class="text-center p-4 bg-green-50 rounded-lg">
                    <p class="text-sm text-gray-600 mb-1">Version</p>
                    <p class="text-lg font-bold text-green-600"><?= htmlspecialchars($overview['version']) ?></p>
                </div>
                <div class="text-center p-4 bg-purple-50 rounded-lg">
                    <p class="text-sm text-gray-600 mb-1">Tables</p>
                    <p class="text-2xl font-bold text-purple-600"><?= $overview['table_count'] ?></p>
                </div>
                <div class="text-center p-4 bg-yellow-50 rounded-lg">
                    <p class="text-sm text-gray-600 mb-1">Database Size</p>
                    <p class="text-lg font-bold text-yellow-600"><?= htmlspecialchars($overview['total_size']) ?></p>
                </div>
            </div>
            <div class="mt-4 p-4 bg-gray-50 rounded-lg">
                <dl class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-gray-600">Host:</dt>
                        <dd class="font-mono text-gray-900"><?= htmlspecialchars($overview['host']) ?>:<?= htmlspecialchars($overview['port']) ?></dd>
                    </div>
                    <div>
                        <dt class="text-gray-600">Database Name:</dt>
                        <dd class="font-mono text-gray-900"><?= htmlspecialchars($overview['database']) ?></dd>
                    </div>
                </dl>
            </div>
        </div>

        <!-- Connection Statistics -->
        <?php if ($connection_stats['available']): ?>
            <div class="card">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">📊 Connection Pool Statistics</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="border border-gray-200 rounded-lg p-4 text-center">
                        <p class="text-3xl font-bold text-blue-600"><?= $connection_stats['total'] ?></p>
                        <p class="text-sm text-gray-600 mt-1">Total Connections</p>
                    </div>
                    <?php if ($connection_stats['active'] !== 'N/A'): ?>
                        <div class="border border-gray-200 rounded-lg p-4 text-center">
                            <p class="text-3xl font-bold text-green-600"><?= $connection_stats['active'] ?></p>
                            <p class="text-sm text-gray-600 mt-1">Active Connections</p>
                        </div>
                        <div class="border border-gray-200 rounded-lg p-4 text-center">
                            <p class="text-3xl font-bold text-gray-600"><?= $connection_stats['idle'] ?></p>
                            <p class="text-sm text-gray-600 mt-1">Idle Connections</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Tables List -->
        <div class="card">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">📋 Database Tables</h2>
            <?php if (count($tables) > 0): ?>
                <div class="overflow-hidden border border-gray-200 rounded-lg">
                    <table>
                        <thead>
                            <tr>
                                <th>Table Name</th>
                                <?php if (isset($tables[0]['schema'])): ?>
                                    <th>Schema</th>
                                <?php endif; ?>
                                <th>Row Count</th>
                                <th>Size</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tables as $table): ?>
                                <tr>
                                    <td class="font-mono font-medium text-gray-900"><?= htmlspecialchars($table['name']) ?></td>
                                    <?php if (isset($table['schema'])): ?>
                                        <td class="text-gray-600"><?= htmlspecialchars($table['schema']) ?></td>
                                    <?php endif; ?>
                                    <td class="text-gray-700"><?= number_format($table['row_count']) ?></td>
                                    <td class="text-gray-700"><?= htmlspecialchars($table['size']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <p class="text-yellow-800">
                        No tables found in database <strong><?= htmlspecialchars($overview['database']) ?></strong>.
                    </p>
                    <p class="text-sm text-yellow-700 mt-2">
                        Run database migrations or seed data to create tables.
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Database Commands -->
        <div class="card">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">💻 Database Commands</h2>
            <p class="text-sm text-gray-600 mb-4">
                Use these commands from your host terminal to interact with the database:
            </p>
            <div class="grid grid-cols-1 gap-3">
                <?php foreach ($commands as $cmd): ?>
                    <div class="border border-gray-200 rounded-lg p-4">
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="font-medium text-gray-900"><?= htmlspecialchars($cmd['label']) ?></h3>
                        </div>
                        <p class="text-sm text-gray-600 mb-3"><?= htmlspecialchars($cmd['description']) ?></p>
                        <code class="text-xs bg-gray-100 px-3 py-2 rounded text-gray-900 font-mono block overflow-x-auto">
                            <?= htmlspecialchars($cmd['command']) ?>
                        </code>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Usage Tips -->
        <div class="card bg-blue-50 border border-blue-200">
            <h2 class="text-lg font-semibold text-blue-900 mb-3">💡 Database Tips</h2>
            <ul class="space-y-2 text-sm text-blue-800" style="list-style: none; padding-left: 1rem;">
                <li>• <strong>Migrations:</strong> Use your framework's migration tools to manage database schema changes</li>
                <li>• <strong>Backups:</strong> Regularly backup your database before major changes</li>
                <li>• <strong>Restore:</strong> Test restores periodically to ensure backups are valid</li>
                <li>• <strong>Performance:</strong> Monitor query performance and add indexes where needed</li>
                <li>• <strong>Security:</strong> Never commit database credentials to version control</li>
            </ul>
        </div>

        <!-- Database Clients -->
        <div class="card bg-green-50 border border-green-200">
            <h2 class="text-lg font-semibold text-green-900 mb-3">🔧 Database Clients</h2>
            <p class="text-sm text-green-800 mb-3">
                You can also use these GUI clients to manage your database:
            </p>
            <ul class="space-y-2 text-sm text-green-800" style="list-style: none; padding-left: 1rem;">
                <?php if ($overview['type'] === 'postgres'): ?>
                    <li>• <strong>pgAdmin:</strong> Web-based PostgreSQL management tool</li>
                    <li>• <strong>DBeaver:</strong> Universal database tool (supports all databases)</li>
                    <li>• <strong>TablePlus:</strong> Modern database management tool (macOS/Windows)</li>
                <?php else: ?>
                    <li>• <strong>phpMyAdmin:</strong> Web-based MySQL/MariaDB management</li>
                    <li>• <strong>DBeaver:</strong> Universal database tool (supports all databases)</li>
                    <li>• <strong>TablePlus:</strong> Modern database management tool (macOS/Windows)</li>
                <?php endif; ?>
                <li>• <strong>DataGrip:</strong> JetBrains database IDE (powerful, paid)</li>
            </ul>
            <p class="text-xs text-green-700 mt-3">
                Connection: <code class="bg-green-100 px-1 py-0.5 rounded">localhost:<?= htmlspecialchars($overview['port']) ?></code>
            </p>
        </div>
    </div>
<?php endif; ?>
