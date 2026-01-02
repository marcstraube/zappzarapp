<?php
/**
 * @var array<string, array<string, mixed>> $containers
 * @var array<string, array<string, mixed>> $databases
 * @var array<string, array<string, mixed>> $services
 * @var array<string, mixed> $ssl
 */
?>
<div class="space-y-6">
    <!-- Docker Containers -->
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">🐳 Docker Containers</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($containers as $name => $container): ?>
                <div class="border border-gray-200 rounded-lg p-4">
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="font-medium text-gray-900"><?= ucfirst($name) ?></h3>
                        <?php
                        $statusBadge = match ($container['status']) {
                            'running'     => 'badge-green',
                            'exited'      => 'badge-red',
                            'not_running' => 'badge-gray',
                            default       => 'badge-yellow'
                        };
                ?>
                        <span class="badge <?= $statusBadge ?>">
                            <?= $container['status'] ?? 'unknown' ?>
                        </span>
                    </div>
                    <div class="space-y-1 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-500">Health:</span>
                            <?php
                    $healthBadge = match ($container['health']) {
                        'healthy'        => 'badge-green',
                        'unhealthy'      => 'badge-red',
                        'no_healthcheck' => 'badge-gray',
                        default          => 'badge-yellow'
                    };
                ?>
                            <span class="badge <?= $healthBadge ?>">
                                <?= str_replace('_', ' ', $container['health'] ?? 'unknown') ?>
                            </span>
                        </div>
                        <?php if (isset($container['container_id'])): ?>
                        <div class="flex justify-between">
                            <span class="text-gray-500">ID:</span>
                            <span class="font-mono text-xs text-gray-700"><?= $container['container_id'] ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Database Connections -->
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">💾 Database Connections</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <?php foreach ($databases as $dbName => $db): ?>
                <div class="border border-gray-200 rounded-lg p-4">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="font-medium text-gray-900"><?= ucfirst($dbName) ?></h3>
                        <span class="badge <?= $db['connected'] ? 'badge-green' : 'badge-red' ?>">
                            <?= $db['connected'] ? '✓ Connected' : '✗ Disconnected' ?>
                        </span>
                    </div>
                    <?php if ($db['connected']): ?>
                        <dl class="space-y-1 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-gray-500">Host:</dt>
                                <dd class="font-mono text-gray-700"><?= $db['host'] ?>:<?= $db['port'] ?></dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-500">Database:</dt>
                                <dd class="font-mono text-gray-700"><?= $db['database'] ?></dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-500">Version:</dt>
                                <dd class="text-gray-700 text-xs"><?= htmlspecialchars(substr($db['version'], 0, 50)) ?></dd>
                            </div>
                        </dl>
                    <?php else: ?>
                        <div class="text-sm text-red-600 bg-red-50 rounded p-2">
                            <p class="font-medium">Connection Failed</p>
                            <p class="text-xs mt-1"><?= htmlspecialchars($db['error'] ?? 'Unknown error') ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Services Status -->
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">⚙️ Services</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <?php foreach ($services as $serviceName => $service): ?>
                <div class="border border-gray-200 rounded-lg p-4">
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="font-medium text-gray-900"><?= ucwords(str_replace('_', ' ', $serviceName)) ?></h3>
                        <span class="badge <?= $service['running'] ? 'badge-green' : 'badge-red' ?>">
                            <?= $service['running'] ? 'Running' : 'Stopped' ?>
                        </span>
                    </div>
                    <div class="text-sm text-gray-600">
                        <?php if (isset($service['version'])): ?>
                            <p class="font-mono text-xs"><?= htmlspecialchars($service['version']) ?></p>
                        <?php endif; ?>
                        <?php if (isset($service['sapi'])): ?>
                            <p class="text-gray-500 text-xs">SAPI: <?= $service['sapi'] ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- SSL Certificate Info -->
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">🔒 SSL Certificate</h2>
        <?php if ($ssl['exists']): ?>
            <div class="space-y-3">
                <div class="flex items-center space-x-2">
                    <span class="badge <?= $ssl['valid'] ? 'badge-green' : 'badge-red' ?>">
                        <?= $ssl['valid'] ? '✓ Valid' : '✗ Invalid' ?>
                    </span>
                    <?php if ($ssl['expires_soon'] ?? false): ?>
                        <span class="badge badge-yellow">⚠ Expires Soon (<?= $ssl['days_until_expiry'] ?> days)</span>
                    <?php endif; ?>
                </div>
                <dl class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-gray-500">Subject</dt>
                        <dd class="font-medium text-gray-900"><?= htmlspecialchars($ssl['subject']) ?></dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Issuer</dt>
                        <dd class="font-medium text-gray-900"><?= htmlspecialchars($ssl['issuer']) ?></dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Valid From</dt>
                        <dd class="font-medium text-gray-900"><?= $ssl['valid_from'] ?></dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Valid To</dt>
                        <dd class="font-medium text-gray-900"><?= $ssl['valid_to'] ?></dd>
                    </div>
                </dl>
            </div>
        <?php else: ?>
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <p class="text-sm text-yellow-800">
                    <?= htmlspecialchars($ssl['message'] ?? 'SSL certificate information not available') ?>
                </p>
                <p class="text-xs text-yellow-700 mt-2">
                    Run <code class="bg-yellow-100 px-1 py-0.5 rounded">make ssl-selfsigned</code> to generate a self-signed certificate.
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>
