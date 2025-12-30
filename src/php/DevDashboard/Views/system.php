<div class="space-y-6">
    <!-- PHP Version -->
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">🐘 PHP Information</h2>
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
            <a href="/_dev/system?phpinfo=1" class="inline-flex items-center px-4 py-2 border border-blue-300 text-sm font-medium rounded text-blue-700 bg-blue-50 hover:bg-blue-100 transition">
                View Full phpinfo()
            </a>
        </div>
    </div>

    <?php if ($showPhpInfo): ?>
    <!-- Full phpinfo() Output -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-semibold text-gray-900">📋 Full PHP Info</h2>
            <a href="/_dev/system" class="text-sm text-blue-600 hover:text-blue-800">Hide phpinfo()</a>
        </div>
        <div class="border border-gray-200 rounded overflow-hidden">
            <div style="max-height: 600px; overflow-y: auto;">
                <?php ob_start();
        phpinfo();
        $phpinfo = ob_get_clean(); ?>
                <?php
        // Remove styling from phpinfo and inject Tailwind classes
        $phpinfo = preg_replace('%^.*<body>(.*)</body>.*$%ms', '$1', $phpinfo);
        $phpinfo = str_replace('<table', '<table class="w-full text-sm"', $phpinfo);
        echo $phpinfo;
        ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- PHP Extensions -->
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">🔌 Loaded Extensions (<?= count($extensions) ?>)</h2>
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2">
            <?php foreach ($extensions as $ext): ?>
                <div class="flex justify-between items-center p-2 bg-gray-50 rounded text-sm">
                    <span class="font-medium text-gray-700"><?= htmlspecialchars($ext['name']) ?></span>
                    <span class="text-xs text-gray-500"><?= htmlspecialchars($ext['version']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Environment Variables -->
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">🔐 Environment Variables</h2>
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
                                <?= htmlspecialchars($value) ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
