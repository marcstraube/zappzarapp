<?php

declare(strict_types=1);

namespace DevDashboard\Services;

use Exception;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Coverage Parser Service
 *
 * Handles parsing of test coverage reports from various formats
 */
class CoverageParser
{
    private readonly string $projectRoot;

    public function __construct()
    {
        $this->projectRoot = realpath(__DIR__ . '/../../../../') . '/';
    }

    /**
     * Parse coverage metrics from HTML file
     *
     * @return array<string, float>|null
     */
    public function parseCoverageMetrics(string $htmlFile): ?array
    {
        $html = file_get_contents($htmlFile);
        if ($html === false) {
            return null;
        }

        // Try Node.js/Vitest format first
        $metrics = $this->parseNodeCoverageFormat($html);

        // Try PHPUnit format if Node format not found
        if ($metrics === []) {
            $metrics = $this->parsePhpUnitCoverageFormat($html);
        }

        return $metrics === [] ? null : $metrics;
    }

    /**
     * Parse Node.js/Vitest coverage format
     *
     * @return array<string, float>
     */
    public function parseNodeCoverageFormat(string $html): array
    {
        $metrics = [];

        // Pattern: "Statements : 85.5% ( 123 / 144 )"
        if (preg_match('/Statements\s*:\s*([0-9.]+)%/', $html, $matches)) {
            $metrics['statements'] = (float) $matches[1];
        }
        if (preg_match('/Branches\s*:\s*([0-9.]+)%/', $html, $matches)) {
            $metrics['branches'] = (float) $matches[1];
        }
        if (preg_match('/Functions\s*:\s*([0-9.]+)%/', $html, $matches)) {
            $metrics['functions'] = (float) $matches[1];
        }
        if (preg_match('/Lines\s*:\s*([0-9.]+)%/', $html, $matches)) {
            $metrics['lines'] = (float) $matches[1];
        }

        return $metrics;
    }

    /**
     * Parse PHPUnit coverage format
     *
     * @return array<string, float>
     */
    public function parsePhpUnitCoverageFormat(string $html): array
    {
        $metrics = [];

        // Pattern: <div class="coverage-summary"><strong>85.5%</strong></div>
        if (preg_match('/<div[^>]*class="coverage[^"]*"[^>]*>.*?([0-9.]+)%/s', $html, $matches)) {
            $coverage              = (float) $matches[1];
            $metrics['lines']      = $coverage;
            $metrics['statements'] = $coverage;
        }

        return $metrics;
    }

    /**
     * Get PHP test coverage
     *
     * @return array<string, mixed>
     */
    public function getPhpTestCoverage(): array
    {
        $htmlFile = $this->projectRoot . 'build/coverage-php/index.html';

        if (!file_exists($htmlFile)) {
            return [
                'available' => false,
                'message'   => 'No coverage report found. Run "make test-coverage-php" first.',
            ];
        }

        $metrics = $this->parseCoverageMetrics($htmlFile);
        $mtime   = $this->getNewestFileMtime(
            $this->projectRoot . 'src/php',
            ['php'],
        );

        return [
            'available' => $metrics !== null,
            'metrics'   => $metrics,
            'age'       => $mtime ? $this->formatAge($mtime) : 'unknown',
            'report'    => '/build/coverage-php/index.html',
        ];
    }

    /**
     * Get Node.js test coverage
     *
     * @return array<string, mixed>
     */
    public function getNodeTestCoverage(): array
    {
        $htmlFile = $this->projectRoot . 'build/coverage-node/index.html';

        if (!file_exists($htmlFile)) {
            return [
                'available' => false,
                'message'   => 'No coverage report found. Run "make test-coverage-node" first.',
            ];
        }

        $metrics = $this->parseCoverageMetrics($htmlFile);
        $mtime   = $this->getNewestFileMtime(
            $this->projectRoot . 'src/node',
            ['ts', 'js'],
        );

        return [
            'available' => $metrics !== null,
            'metrics'   => $metrics,
            'age'       => $mtime ? $this->formatAge($mtime) : 'unknown',
            'report'    => '/build/coverage-node/index.html',
        ];
    }

    /**
     * Get newest file modification time in a directory
     *
     * @param string[] $extensions
     */
    private function getNewestFileMtime(string $directory, array $extensions): ?int
    {
        if (!is_dir($directory)) {
            return null;
        }

        $newestMtime = null;

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY,
            );

            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }

                $ext = $file->getExtension();
                if (!in_array($ext, $extensions, true)) {
                    continue;
                }

                $mtime = $file->getMTime();
                if ($mtime !== false && ($newestMtime === null || $mtime > $newestMtime)) {
                    $newestMtime = $mtime;
                }
            }
        } catch (Exception) {
            return null;
        }

        return $newestMtime;
    }

    /**
     * Format file age to human-readable string
     */
    private function formatAge(int $timestamp): string
    {
        $diff = time() - $timestamp;

        if ($diff < 60) {
            return 'just now';
        }

        if ($diff < 3600) {
            $minutes = floor($diff / 60);
            return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
        }

        if ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        }

        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    }
}
