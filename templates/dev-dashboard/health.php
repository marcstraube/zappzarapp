<?php
/**
 * @var array<string, array<string, array<string, mixed>>> $services
 * @var array<string, array<string, mixed>> $connections
 * @var array<string, mixed> $ssl
 */

$categoryLabels = [
    'core'     => ['icon' => '🔧', 'title' => 'Core Services', 'desc' => 'Essential services for the application'],
    'data'     => ['icon' => '💾', 'title' => 'Data Services', 'desc' => 'Databases and caching'],
    'optional' => ['icon' => '🔌', 'title' => 'Optional Services', 'desc' => 'Additional tools and integrations'],
];
?>
<div class="space-y-6">
    <!-- Services by Category -->
    <?php foreach ($services as $category => $categoryServices): ?>
        <?php if (empty($categoryServices)) continue; ?>
        <?php $label = $categoryLabels[$category] ?? ['icon' => '📦', 'title' => ucfirst($category), 'desc' => '']; ?>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="mb-4">
                <h2 class="text-lg font-semibold text-gray-900">
                    <?= $label['icon'] ?> <?= $label['title'] ?>
                </h2>
                <p class="text-sm text-gray-500"><?= $label['desc'] ?></p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($categoryServices as $key => $service): ?>
                    <div class="border border-gray-200 rounded-lg p-4 <?= $service['status'] === 'stopped' ? 'bg-red-50 border-red-200' : '' ?>">
                        <div class="flex items-center justify-between mb-2">
                            <div>
                                <h3 class="font-medium text-gray-900"><?= htmlspecialchars($service['name']) ?></h3>
                                <p class="text-xs text-gray-500"><?= htmlspecialchars($service['description']) ?></p>
                            </div>
                            <span class="badge <?= $service['status'] === 'running' ? 'badge-green' : 'badge-red' ?>">
                                <?= $service['status'] === 'running' ? '● Running' : '○ Stopped' ?>
                            </span>
                        </div>

                        <?php if ($service['port']): ?>
                            <div class="text-xs text-gray-600 mt-2">
                                <span class="font-mono bg-gray-100 px-1.5 py-0.5 rounded">
                                    <?= $key ?>:<?= $service['port'] ?>
                                </span>
                            </div>
                        <?php endif; ?>

                        <?php if ($service['status'] === 'stopped' && isset($service['details']['error'])): ?>
                            <div class="mt-2 text-xs text-red-600">
                                <?= htmlspecialchars($service['details']['error']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <!-- Connections (Detailed Health Checks) -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="mb-4">
            <h2 class="text-lg font-semibold text-gray-900">🔗 Connections</h2>
            <p class="text-sm text-gray-500">Detailed connectivity and version information</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <?php foreach ($connections as $name => $conn): ?>
                <div class="border border-gray-200 rounded-lg p-4 <?= !$conn['connected'] ? 'bg-red-50 border-red-200' : '' ?>">
                    <div class="flex items-center justify-between mb-3">
                        <div>
                            <h3 class="font-medium text-gray-900"><?= htmlspecialchars($conn['type'] ?? ucfirst($name)) ?></h3>
                        </div>
                        <span class="badge <?= $conn['connected'] ? 'badge-green' : 'badge-red' ?>">
                            <?= $conn['connected'] ? '✓ Connected' : '✗ Failed' ?>
                        </span>
                    </div>

                    <?php if ($conn['connected']): ?>
                        <dl class="space-y-1.5 text-sm">
                            <?php if (isset($conn['host'])): ?>
                                <div class="flex justify-between">
                                    <dt class="text-gray-500">Host:</dt>
                                    <dd class="font-mono text-gray-700"><?= htmlspecialchars($conn['host']) ?>:<?= $conn['port'] ?></dd>
                                </div>
                            <?php endif; ?>

                            <?php if (isset($conn['database'])): ?>
                                <div class="flex justify-between">
                                    <dt class="text-gray-500">Database:</dt>
                                    <dd class="font-mono text-gray-700"><?= htmlspecialchars($conn['database']) ?></dd>
                                </div>
                            <?php endif; ?>

                            <?php if (isset($conn['version'])): ?>
                                <div class="flex justify-between">
                                    <dt class="text-gray-500">Version:</dt>
                                    <dd class="text-gray-700 text-xs truncate max-w-[200px]" title="<?= htmlspecialchars((string) $conn['version']) ?>">
                                        <?= htmlspecialchars(substr((string) $conn['version'], 0, 40)) ?>
                                    </dd>
                                </div>
                            <?php endif; ?>

                            <?php if (isset($conn['tls'])): ?>
                                <div class="flex justify-between">
                                    <dt class="text-gray-500">TLS:</dt>
                                    <dd>
                                        <span class="badge <?= $conn['tls'] ? 'badge-green' : 'badge-gray' ?>">
                                            <?= $conn['tls'] ? '🔒 Enabled' : 'Disabled' ?>
                                        </span>
                                    </dd>
                                </div>
                            <?php endif; ?>
                        </dl>
                    <?php else: ?>
                        <div class="text-sm text-red-600 bg-red-100 rounded p-2">
                            <p class="font-medium">Connection Failed</p>
                            <p class="text-xs mt-1"><?= htmlspecialchars($conn['error'] ?? 'Unknown error') ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- SSL Certificate -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="mb-4">
            <h2 class="text-lg font-semibold text-gray-900">🔒 SSL Certificate</h2>
            <p class="text-sm text-gray-500">HTTPS certificate status</p>
        </div>

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
                        <dd class="font-medium text-gray-900"><?= htmlspecialchars((string) $ssl['subject']) ?></dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Issuer</dt>
                        <dd class="font-medium text-gray-900"><?= htmlspecialchars((string) $ssl['issuer']) ?></dd>
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
