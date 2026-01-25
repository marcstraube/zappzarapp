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

$tabs = [
    'services'    => 'Services',
    'connections' => 'Connections',
    'ssl'         => 'SSL',
];
?>

<!-- Sub-Navigation -->
<nav class="sub-nav" style="background: white; border: 1px solid #e5e7eb; border-radius: 0.5rem; margin-bottom: 1.5rem; position: sticky; top: 120px; z-index: 50;">
    <div style="display: flex; gap: 0;">
        <?php foreach ($tabs as $id => $label): ?>
            <a href="#"
               data-tab="<?= $id ?>"
               class="sub-nav-link <?= $id === 'services' ? 'active' : '' ?>"
               style="display: inline-block; padding: 0.75rem 1rem; border-bottom: 2px solid transparent; color: #6b7280; font-size: 0.875rem; font-weight: 500; text-decoration: none; transition: all 0.2s;"
            ><?= $label ?></a>
        <?php endforeach; ?>
    </div>
</nav>

<!-- Tab: Services -->
<div id="tab-services" class="tab-panel">
    <div class="space-y-6">
        <?php foreach ($services as $category => $categoryServices): ?>
            <?php if (empty($categoryServices)) continue; ?>
            <?php $label = $categoryLabels[$category] ?? ['icon' => '📦', 'title' => ucfirst($category), 'desc' => '']; ?>

            <div class="card">
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
    </div>
</div>

<!-- Tab: Connections -->
<div id="tab-connections" class="tab-panel hidden">
    <div class="card">
        <div class="mb-4">
            <h2 class="text-lg font-semibold text-gray-900">Connections</h2>
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
</div>

<!-- Tab: SSL -->
<div id="tab-ssl" class="tab-panel hidden">
    <div class="card">
        <div class="mb-4">
            <h2 class="text-lg font-semibold text-gray-900">SSL Certificates</h2>
            <p class="text-sm text-gray-500">HTTPS and internal service certificates</p>
        </div>

        <?php if ($ssl['exists'] && !empty($ssl['certificates'])): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php foreach ($ssl['certificates'] as $key => $cert): ?>
                    <div class="border border-gray-200 rounded-lg p-4 <?= !$cert['valid'] ? 'bg-red-50 border-red-200' : '' ?>">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="font-medium text-gray-900"><?= htmlspecialchars($cert['name']) ?></h3>
                            <div class="flex items-center gap-2">
                                <?php if (!$cert['valid']): ?>
                                    <span class="badge badge-red">Invalid</span>
                                <?php elseif ($cert['expires_soon'] ?? false): ?>
                                    <span class="badge badge-yellow"><?= $cert['days_until_expiry'] ?>d</span>
                                <?php else: ?>
                                    <span class="badge badge-green">Valid</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if (isset($cert['message'])): ?>
                            <p class="text-sm text-red-600"><?= htmlspecialchars($cert['message']) ?></p>
                        <?php else: ?>
                            <dl class="space-y-1 text-sm">
                                <div class="flex justify-between">
                                    <dt class="text-gray-500">Subject</dt>
                                    <dd class="font-mono text-xs text-gray-900"><?= htmlspecialchars($cert['subject']) ?></dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-gray-500">Issuer</dt>
                                    <dd class="font-mono text-xs text-gray-700"><?= htmlspecialchars($cert['issuer']) ?></dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-gray-500">Expires</dt>
                                    <dd class="text-gray-900">
                                        <?= $cert['valid_to'] ?>
                                        <span class="text-gray-500">(<?= $cert['days_until_expiry'] ?>d)</span>
                                    </dd>
                                </div>
                            </dl>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <p class="text-sm text-yellow-800">
                    <?= htmlspecialchars($ssl['message'] ?? 'No SSL certificates found') ?>
                </p>
                <p class="text-xs text-yellow-700 mt-2">
                    Run <code class="bg-yellow-100 px-1 py-0.5 rounded">make ssl-selfsigned</code> or
                    <code class="bg-yellow-100 px-1 py-0.5 rounded">make ssl-internal</code> to generate certificates.
                </p>
            </div>
        <?php endif; ?>
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
</script>
