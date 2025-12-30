<?php

declare(strict_types=1);

namespace DevDashboard\Services;

/**
 * Quality Service
 *
 * Aggregates code quality metrics from various tools
 */
class QualityService
{
    private string $projectRoot;

    public function __construct()
    {
        $this->projectRoot = realpath(__DIR__ . '/../../../../') . '/';
    }

    /**
     * Get overall quality metrics
     */
    public function getQualityMetrics(): array
    {
        return [
            'php'           => $this->getPhpQualityMetrics(),
            'node'          => $this->getNodeQualityMetrics(),
            'code_stats'    => $this->getCodeStatistics(),
            'test_coverage' => $this->getTestCoverage(),
        ];
    }

    /**
     * Get PHP quality metrics (PHPStan, PHPMD, PHP CS Fixer)
     */
    private function getPhpQualityMetrics(): array
    {
        return [
            'phpstan'  => $this->getPhpStanStatus(),
            'phpmd'    => $this->getPhpMdStatus(),
            'cs_fixer' => $this->getCsFixerStatus(),
        ];
    }

    /**
     * Get Node.js quality metrics (ESLint, Prettier, TypeScript)
     */
    private function getNodeQualityMetrics(): array
    {
        return [
            'eslint'     => $this->getEslintStatus(),
            'prettier'   => $this->getPrettierStatus(),
            'typescript' => $this->getTypeScriptStatus(),
        ];
    }

    /**
     * Get PHPStan status and metrics
     */
    private function getPhpStanStatus(): array
    {
        $configFile = $this->projectRoot . 'phpstan.neon';

        if (!file_exists($configFile)) {
            return [
                'enabled' => false,
                'message' => 'PHPStan not configured',
            ];
        }

        // Extract level from config
        $config = file_get_contents($configFile);
        preg_match('/level:\s*(\d+)/', $config, $matches);
        $level = $matches[1] ?? 'unknown';

        return [
            'enabled'     => true,
            'level'       => (int) $level,
            'config_file' => 'phpstan.neon',
            'status'      => 'configured',
            'message'     => "PHPStan Level {$level} is configured",
        ];
    }

    /**
     * Get PHPMD status
     */
    private function getPhpMdStatus(): array
    {
        $configFile = $this->projectRoot . 'phpmd.xml.dist';

        if (!file_exists($configFile)) {
            return [
                'enabled' => false,
                'message' => 'PHPMD not configured',
            ];
        }

        return [
            'enabled'     => true,
            'config_file' => 'phpmd.xml.dist',
            'status'      => 'configured',
            'message'     => 'PHPMD is configured',
        ];
    }

    /**
     * Get PHP CS Fixer status
     */
    private function getCsFixerStatus(): array
    {
        $configFile = $this->projectRoot . '.php-cs-fixer.dist.php';

        if (!file_exists($configFile)) {
            return [
                'enabled' => false,
                'message' => 'PHP CS Fixer not configured',
            ];
        }

        return [
            'enabled'     => true,
            'config_file' => '.php-cs-fixer.dist.php',
            'status'      => 'configured',
            'message'     => 'PHP CS Fixer is configured (PER-CS standard)',
        ];
    }

    /**
     * Get ESLint status
     */
    private function getEslintStatus(): array
    {
        $configFile = $this->projectRoot . 'eslint.config.js';

        if (!file_exists($configFile)) {
            return [
                'enabled' => false,
                'message' => 'ESLint not configured',
            ];
        }

        return [
            'enabled'     => true,
            'config_file' => 'eslint.config.js',
            'status'      => 'configured',
            'message'     => 'ESLint is configured',
        ];
    }

    /**
     * Get Prettier status
     */
    private function getPrettierStatus(): array
    {
        $configFile = $this->projectRoot . '.prettierrc.json';

        if (!file_exists($configFile)) {
            return [
                'enabled' => false,
                'message' => 'Prettier not configured',
            ];
        }

        return [
            'enabled'     => true,
            'config_file' => '.prettierrc.json',
            'status'      => 'configured',
            'message'     => 'Prettier is configured',
        ];
    }

    /**
     * Get TypeScript compiler status
     */
    private function getTypeScriptStatus(): array
    {
        $configFile = $this->projectRoot . 'tsconfig.json';

        if (!file_exists($configFile)) {
            return [
                'enabled' => false,
                'message' => 'TypeScript not configured',
            ];
        }

        return [
            'enabled'     => true,
            'config_file' => 'tsconfig.json',
            'status'      => 'configured',
            'message'     => 'TypeScript is configured',
        ];
    }

    /**
     * Get code statistics
     */
    private function getCodeStatistics(): array
    {
        $stats = [
            'php'        => $this->countFiles('src/php', ['php']),
            'typescript' => $this->countFiles('src/node', ['ts', 'js']),
            'tests_php'  => $this->countFiles('tests/php', ['php']),
            'tests_node' => $this->countFiles('tests/node', ['ts', 'js']),
        ];

        return [
            'files'              => $stats,
            'total_source_files' => $stats['php']['count'] + $stats['typescript']['count'],
            'total_test_files'   => $stats['tests_php']['count'] + $stats['tests_node']['count'],
        ];
    }

    /**
     * Count files in a directory
     */
    private function countFiles(string $directory, array $extensions): array
    {
        $path = $this->projectRoot . $directory;

        if (!is_dir($path)) {
            return ['count' => 0, 'exists' => false];
        }

        $count = 0;

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $extension = $file->getExtension();
                    if (in_array($extension, $extensions, true)) {
                        $count++;
                    }
                }
            }
        } catch (\Exception $e) {
            return ['count' => 0, 'exists' => false, 'error' => $e->getMessage()];
        }

        return ['count' => $count, 'exists' => true];
    }

    /**
     * Get test coverage information
     */
    private function getTestCoverage(): array
    {
        $phpCoverage  = $this->getPhpTestCoverage();
        $nodeCoverage = $this->getNodeTestCoverage();

        return [
            'php'  => $phpCoverage,
            'node' => $nodeCoverage,
        ];
    }

    /**
     * Get PHP test coverage
     */
    private function getPhpTestCoverage(): array
    {
        $coverageFile = $this->projectRoot . 'build/coverage-php/index.html';

        if (!file_exists($coverageFile)) {
            return [
                'available' => false,
                'message'   => 'Run "make test-coverage-php" to generate coverage report',
            ];
        }

        return [
            'available'   => true,
            'report_path' => '/build/coverage-php/index.html',
            'message'     => 'Coverage report available',
        ];
    }

    /**
     * Get Node.js test coverage
     */
    private function getNodeTestCoverage(): array
    {
        $coverageFile = $this->projectRoot . 'build/coverage-node/index.html';

        if (!file_exists($coverageFile)) {
            return [
                'available' => false,
                'message'   => 'Run "make test-coverage-node" to generate coverage report',
            ];
        }

        return [
            'available'   => true,
            'report_path' => '/build/coverage-node/index.html',
            'message'     => 'Coverage report available',
        ];
    }

    /**
     * Get quick actions for quality checks
     */
    public function getQuickActions(): array
    {
        return [
            [
                'label'       => 'Run All Quality Checks',
                'command'     => 'make quality',
                'description' => 'Run PHPStan, PHPMD, ESLint, Prettier, TypeScript checks',
            ],
            [
                'label'       => 'Fix All Code Style',
                'command'     => 'make fix',
                'description' => 'Auto-fix PHP CS Fixer and Prettier issues',
            ],
            [
                'label'       => 'Generate PHP Coverage',
                'command'     => 'make test-coverage-php',
                'description' => 'Run PHPUnit tests with coverage report',
            ],
            [
                'label'       => 'Generate Node Coverage',
                'command'     => 'make test-coverage-node',
                'description' => 'Run Vitest tests with coverage report',
            ],
        ];
    }
}
