<?php
/**
 * @var array<string, array<string, mixed>> $php_quality
 * @var array<string, array<string, mixed>> $node_quality
 * @var array<string, mixed> $code_stats
 * @var array<string, mixed> $test_coverage
 * @var array<int, array<string, mixed>> $quick_actions
 */
?>
<div class="space-y-6">
    <!-- PHP Quality Tools -->
    <div class="card">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">🐘 PHP Quality Tools</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- PHPStan -->
            <div class="border border-gray-200 rounded-lg p-4">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-medium text-gray-900">PHPStan</h3>
                    <span class="badge <?= $php_quality['phpstan']['enabled'] ? 'badge-green' : 'badge-gray' ?>">
                        <?= $php_quality['phpstan']['enabled'] ? '✓ Enabled' : 'Disabled' ?>
                    </span>
                </div>
                <?php if ($php_quality['phpstan']['enabled']): ?>
                    <dl class="space-y-1 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Level:</dt>
                            <dd class="font-medium text-gray-900"><?= $php_quality['phpstan']['level'] ?></dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Config:</dt>
                            <dd class="font-mono text-xs text-gray-700"><?= $php_quality['phpstan']['config_file'] ?></dd>
                        </div>
                    </dl>
                    <p class="text-xs text-gray-600 mt-2"><?= $php_quality['phpstan']['message'] ?></p>
                <?php else: ?>
                    <p class="text-sm text-gray-500"><?= $php_quality['phpstan']['message'] ?></p>
                <?php endif; ?>
            </div>

            <!-- PHPMD -->
            <div class="border border-gray-200 rounded-lg p-4">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-medium text-gray-900">PHPMD</h3>
                    <span class="badge <?= $php_quality['phpmd']['enabled'] ? 'badge-green' : 'badge-gray' ?>">
                        <?= $php_quality['phpmd']['enabled'] ? '✓ Enabled' : 'Disabled' ?>
                    </span>
                </div>
                <?php if ($php_quality['phpmd']['enabled']): ?>
                    <dl class="space-y-1 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Config:</dt>
                            <dd class="font-mono text-xs text-gray-700"><?= $php_quality['phpmd']['config_file'] ?></dd>
                        </div>
                    </dl>
                    <p class="text-xs text-gray-600 mt-2"><?= $php_quality['phpmd']['message'] ?></p>
                <?php else: ?>
                    <p class="text-sm text-gray-500"><?= $php_quality['phpmd']['message'] ?></p>
                <?php endif; ?>
            </div>

            <!-- PHP CS Fixer -->
            <div class="border border-gray-200 rounded-lg p-4">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-medium text-gray-900">PHP CS Fixer</h3>
                    <span class="badge <?= $php_quality['cs_fixer']['enabled'] ? 'badge-green' : 'badge-gray' ?>">
                        <?= $php_quality['cs_fixer']['enabled'] ? '✓ Enabled' : 'Disabled' ?>
                    </span>
                </div>
                <?php if ($php_quality['cs_fixer']['enabled']): ?>
                    <dl class="space-y-1 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Config:</dt>
                            <dd class="font-mono text-xs text-gray-700"><?= $php_quality['cs_fixer']['config_file'] ?></dd>
                        </div>
                    </dl>
                    <p class="text-xs text-gray-600 mt-2"><?= $php_quality['cs_fixer']['message'] ?></p>
                <?php else: ?>
                    <p class="text-sm text-gray-500"><?= $php_quality['cs_fixer']['message'] ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Node.js Quality Tools -->
    <div class="card">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">🟢 Node.js Quality Tools</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- ESLint -->
            <div class="border border-gray-200 rounded-lg p-4">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-medium text-gray-900">ESLint</h3>
                    <span class="badge <?= $node_quality['eslint']['enabled'] ? 'badge-green' : 'badge-gray' ?>">
                        <?= $node_quality['eslint']['enabled'] ? '✓ Enabled' : 'Disabled' ?>
                    </span>
                </div>
                <?php if ($node_quality['eslint']['enabled']): ?>
                    <dl class="space-y-1 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Config:</dt>
                            <dd class="font-mono text-xs text-gray-700"><?= $node_quality['eslint']['config_file'] ?></dd>
                        </div>
                    </dl>
                    <p class="text-xs text-gray-600 mt-2"><?= $node_quality['eslint']['message'] ?></p>
                <?php else: ?>
                    <p class="text-sm text-gray-500"><?= $node_quality['eslint']['message'] ?></p>
                <?php endif; ?>
            </div>

            <!-- Prettier -->
            <div class="border border-gray-200 rounded-lg p-4">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-medium text-gray-900">Prettier</h3>
                    <span class="badge <?= $node_quality['prettier']['enabled'] ? 'badge-green' : 'badge-gray' ?>">
                        <?= $node_quality['prettier']['enabled'] ? '✓ Enabled' : 'Disabled' ?>
                    </span>
                </div>
                <?php if ($node_quality['prettier']['enabled']): ?>
                    <dl class="space-y-1 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Config:</dt>
                            <dd class="font-mono text-xs text-gray-700"><?= $node_quality['prettier']['config_file'] ?></dd>
                        </div>
                    </dl>
                    <p class="text-xs text-gray-600 mt-2"><?= $node_quality['prettier']['message'] ?></p>
                <?php else: ?>
                    <p class="text-sm text-gray-500"><?= $node_quality['prettier']['message'] ?></p>
                <?php endif; ?>
            </div>

            <!-- TypeScript -->
            <div class="border border-gray-200 rounded-lg p-4">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-medium text-gray-900">TypeScript</h3>
                    <span class="badge <?= $node_quality['typescript']['enabled'] ? 'badge-green' : 'badge-gray' ?>">
                        <?= $node_quality['typescript']['enabled'] ? '✓ Enabled' : 'Disabled' ?>
                    </span>
                </div>
                <?php if ($node_quality['typescript']['enabled']): ?>
                    <dl class="space-y-1 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Config:</dt>
                            <dd class="font-mono text-xs text-gray-700"><?= $node_quality['typescript']['config_file'] ?></dd>
                        </div>
                    </dl>
                    <p class="text-xs text-gray-600 mt-2"><?= $node_quality['typescript']['message'] ?></p>
                <?php else: ?>
                    <p class="text-sm text-gray-500"><?= $node_quality['typescript']['message'] ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Code Statistics -->
    <div class="card">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">📊 Code Statistics</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="text-center p-4 bg-blue-50 rounded-lg">
                <p class="text-3xl font-bold text-blue-600"><?= $code_stats['files']['php']['count'] ?></p>
                <p class="text-sm text-gray-600 mt-1">PHP Source Files</p>
            </div>
            <div class="text-center p-4 bg-green-50 rounded-lg">
                <p class="text-3xl font-bold text-green-600"><?= $code_stats['files']['typescript']['count'] ?></p>
                <p class="text-sm text-gray-600 mt-1">TS/JS Source Files</p>
            </div>
            <div class="text-center p-4 bg-purple-50 rounded-lg">
                <p class="text-3xl font-bold text-purple-600"><?= $code_stats['files']['tests_php']['count'] ?></p>
                <p class="text-sm text-gray-600 mt-1">PHP Test Files</p>
            </div>
            <div class="text-center p-4 bg-yellow-50 rounded-lg">
                <p class="text-3xl font-bold text-yellow-600"><?= $code_stats['files']['tests_node']['count'] ?></p>
                <p class="text-sm text-gray-600 mt-1">Node Test Files</p>
            </div>
        </div>
        <div class="mt-4 p-4 bg-gray-50 rounded-lg">
            <div class="flex justify-between items-center">
                <div>
                    <p class="text-sm text-gray-600">Total Source Files</p>
                    <p class="text-2xl font-bold text-gray-900"><?= $code_stats['total_source_files'] ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Total Test Files</p>
                    <p class="text-2xl font-bold text-gray-900"><?= $code_stats['total_test_files'] ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Test Ratio</p>
                    <p class="text-2xl font-bold text-gray-900">
                        <?php
                        $ratio = $code_stats['total_source_files'] > 0
                            ? round(($code_stats['total_test_files'] / $code_stats['total_source_files']) * 100)
                            : 0;
                        echo $ratio . '%';
                        ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Test Coverage -->
    <div class="card">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">📈 Test Coverage</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- PHP Coverage -->
            <div class="border border-gray-200 rounded-lg p-4">
                <h3 class="font-medium text-gray-900 mb-3">PHP (PHPUnit)</h3>
                <?php if ($test_coverage['php']['available']): ?>
                    <div class="flex items-center space-x-2 mb-3">
                        <span class="badge badge-green">✓ Report Available</span>
                    </div>
                    <a href="<?= $test_coverage['php']['report_path'] ?>"
                       target="_blank"
                       class="btn btn-primary btn-block">
                        View Coverage Report →
                    </a>
                <?php else: ?>
                    <div class="bg-yellow-50 border border-yellow-200 rounded p-3">
                        <p class="text-sm text-yellow-800 mb-2"><?= $test_coverage['php']['message'] ?></p>
                        <code class="text-xs bg-yellow-100 px-2 py-1 rounded text-yellow-900">make test-coverage-php</code>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Node Coverage -->
            <div class="border border-gray-200 rounded-lg p-4">
                <h3 class="font-medium text-gray-900 mb-3">Node.js (Vitest)</h3>
                <?php if ($test_coverage['node']['available']): ?>
                    <div class="flex items-center space-x-2 mb-3">
                        <span class="badge badge-green">✓ Report Available</span>
                    </div>
                    <a href="<?= $test_coverage['node']['report_path'] ?>"
                       target="_blank"
                       class="btn btn-primary btn-block">
                        View Coverage Report →
                    </a>
                <?php else: ?>
                    <div class="bg-yellow-50 border border-yellow-200 rounded p-3">
                        <p class="text-sm text-yellow-800 mb-2"><?= $test_coverage['node']['message'] ?></p>
                        <code class="text-xs bg-yellow-100 px-2 py-1 rounded text-yellow-900">make test-coverage-node</code>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="card">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">⚡ Quick Actions</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <?php foreach ($quick_actions as $action): ?>
                <div class="border border-gray-200 rounded-lg p-4">
                    <h3 class="font-medium text-gray-900 mb-1"><?= htmlspecialchars((string) $action['label']) ?></h3>
                    <p class="text-sm text-gray-600 mb-3"><?= htmlspecialchars((string) $action['description']) ?></p>
                    <code class="text-xs bg-gray-100 px-2 py-1 rounded text-gray-900 font-mono">
                        <?= htmlspecialchars((string) $action['command']) ?>
                    </code>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
