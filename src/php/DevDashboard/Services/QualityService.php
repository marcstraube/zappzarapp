<?php

declare(strict_types=1);

namespace DevDashboard\Services;

use Exception;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Quality Service
 *
 * Aggregates code quality metrics from various tools
      *
     * @return array<string, mixed>
     */
readonly class QualityService
{
    private string $projectRoot;

    public function __construct()
    {
        $this->projectRoot = realpath(__DIR__ . '/../../../../') . '/';
    }

    /**
     * Get overall quality metrics
          *
     * @return array<string, mixed>
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
          *
     * @return array<string, mixed>
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
          *
     * @return array<string, mixed>
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
          *
     * @return array<string, mixed>
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
        if ($config === false) {
            return ['enabled' => false, 'message' => 'Could not read config'];
        }

        preg_match('/level:\s*(\d+)/', $config, $matches);
        $level = $matches[1] ?? 'unknown';

        return [
            'enabled'     => true,
            'level'       => (int) $level,
            'config_file' => 'phpstan.neon',
            'status'      => 'configured',
            'message'     => sprintf('PHPStan Level %s is configured', $level),
        ];
    }

    /**
     * Get PHPMD status
          *
     * @return array<string, mixed>
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
          *
     * @return array<string, mixed>
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
          *
     * @return array<string, mixed>
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
          *
     * @return array<string, mixed>
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
          *
     * @return array<string, mixed>
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
          *
     * @return array<string, mixed>
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
          *
     * @param array<int, string> $extensions
     * @return array<string, mixed>
     */
    private function countFiles(string $directory, array $extensions): array
    {
        $path = $this->projectRoot . $directory;

        if (!is_dir($path)) {
            return ['count' => 0, 'exists' => false];
        }

        $count = 0;

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $extension = $file->getExtension();
                    if (in_array($extension, $extensions, true)) {
                        $count++;
                    }
                }
            }
        } catch (Exception $exception) {
            return ['count' => 0, 'exists' => false, 'error' => $exception->getMessage()];
        }

        return ['count' => $count, 'exists' => true];
    }

    /**
     * Get test coverage information
          *
     * @return array<string, mixed>
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
          *
     * @return array<string, mixed>
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
          *
     * @return array<string, mixed>
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
          *
     * @return array<int, array<string, mixed>>
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
