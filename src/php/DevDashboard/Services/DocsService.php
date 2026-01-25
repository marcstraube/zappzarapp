<?php

declare(strict_types=1);

namespace DevDashboard\Services;

use Exception;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Service for checking documentation availability and freshness
 */
readonly class DocsService
{
    private string $docsPath;
    private string $srcPath;

    public function __construct()
    {
        // Paths from container perspective
        $this->docsPath = '/var/www/html/docs';
        $this->srcPath  = '/var/www/html/src';
    }

    /**
     * Get status of all API documentation
     *
     * @return array{available: bool, allFresh: bool, php: array<string, mixed>, node_backend: array<string, mixed>, node_frontend: array<string, mixed>, commands: array<string, string>}
     */
    public function getApiDocsStatus(): array
    {
        $phpDocs = $this->checkDocsDirectory(
            docsSubPath: 'api/php',
            name: 'PHP API',
            sourceDir: 'php',
            extensions: ['php'],
        );
        $nodeBackendDocs = $this->checkDocsDirectory(
            docsSubPath: 'api/node-backend',
            name: 'Node Backend API',
            sourceDir: 'node/backend',
            extensions: ['ts', 'js'],
        );
        $nodeFrontendDocs = $this->checkDocsDirectory(
            docsSubPath: 'api/node-frontend',
            name: 'Node Frontend',
            sourceDir: 'node/frontend',
            extensions: ['ts', 'tsx', 'js', 'jsx'],
        );

        // Only consider docs that have source files
        $allAvailable = $this->isDocsSatisfied($phpDocs)
            && $this->isDocsSatisfied($nodeBackendDocs)
            && $this->isDocsSatisfied($nodeFrontendDocs);

        $allFresh = $allAvailable
            && !$phpDocs['outdated']
            && !$nodeBackendDocs['outdated']
            && !$nodeFrontendDocs['outdated'];

        return [
            'available'     => $allAvailable,
            'allFresh'      => $allFresh,
            'php'           => $phpDocs,
            'node_backend'  => $nodeBackendDocs,
            'node_frontend' => $nodeFrontendDocs,
            'commands'      => $this->getMissingDocsCommands($phpDocs, $nodeBackendDocs, $nodeFrontendDocs),
        ];
    }

    /**
     * Check if a docs directory exists, has content, and is up-to-date
     *
     * @param string[] $extensions File extensions to check for source changes
     *
     * @return array{exists: bool, outdated: bool, hasSource: bool, path: string, name: string, url: string|null, docsAge: string|null}
     */
    private function checkDocsDirectory(
        string $docsSubPath,
        string $name,
        string $sourceDir,
        array $extensions,
    ): array {
        $fullPath  = $this->docsPath . '/' . $docsSubPath;
        $indexFile = $fullPath . '/index.html';
        $exists    = file_exists($indexFile);

        $outdated  = false;
        $docsAge   = null;
        $srcMtime  = $this->getNewestMtime($this->srcPath . '/' . $sourceDir, $extensions);
        $hasSource = $srcMtime !== null;

        if ($exists && $hasSource) {
            $docsMtime = filemtime($indexFile);

            if ($docsMtime !== false && $srcMtime > $docsMtime) {
                $outdated = true;
            }

            // Calculate docs age for display
            if ($docsMtime !== false) {
                $docsAge = $this->formatAge(time() - $docsMtime);
            }
        }

        return [
            'exists'    => $exists,
            'outdated'  => $outdated,
            'hasSource' => $hasSource,
            'path'      => $docsSubPath,
            'name'      => $name,
            'url'       => $exists ? '/docs/' . $docsSubPath . '/' : null,
            'docsAge'   => $docsAge,
        ];
    }

    /**
     * Get the newest file modification time in a directory
     *
     * @param string[] $extensions Only check files with these extensions
     */
    private function getNewestMtime(string $directory, array $extensions): ?int
    {
        if (!is_dir($directory)) {
            return null;
        }

        $newestMtime = null;
        $extPattern  = '/\.(' . implode('|', $extensions) . ')$/i';

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY,
            );

            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }

                if (!preg_match($extPattern, $file->getFilename())) {
                    continue;
                }

                $mtime = $file->getMTime();
                if ($newestMtime === null || $mtime > $newestMtime) {
                    $newestMtime = $mtime;
                }
            }
        } catch (Exception) {
            return null;
        }

        return $newestMtime;
    }

    /**
     * Format age in human-readable form
     */
    private function formatAge(int $seconds): string
    {
        if ($seconds < 60) {
            return 'just now';
        }
        if ($seconds < 3600) {
            $mins = (int) floor($seconds / 60);

            return $mins . ' min' . ($mins > 1 ? 's' : '') . ' ago';
        }
        if ($seconds < 86400) {
            $hours = (int) floor($seconds / 3600);

            return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        }

        $days = (int) floor($seconds / 86400);

        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    }

    /**
     * Generate PHP API documentation
     *
     * @return array{success: bool, message: string, output: string}
     */
    public function generatePhpDocs(): array
    {
        $phpdocPath = '/var/www/html/tools/phpdoc.phar';

        if (!file_exists($phpdocPath)) {
            return [
                'success' => false,
                'message' => 'phpDocumentor not found. Run "make docs-php" from host first.',
                'output'  => '',
            ];
        }

        // Run phpDocumentor using proc_open (exec is disabled)
        $command = sprintf(
            'cd /var/www/html && php %s run --config=phpdoc.xml 2>&1',
            escapeshellarg($phpdocPath),
        );

        $result = $this->runCommand($command);

        if ($result['exitCode'] !== 0) {
            return [
                'success' => false,
                'message' => 'phpDocumentor failed with exit code ' . $result['exitCode'],
                'output'  => $result['output'],
            ];
        }

        return [
            'success' => true,
            'message' => 'PHP documentation generated successfully',
            'output'  => $result['output'],
        ];
    }

    /**
     * Run a shell command using proc_open (since exec is disabled)
     *
     * @return array{exitCode: int, output: string}
     */
    private function runCommand(string $command): array
    {
        $descriptors = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w'],  // stderr
        ];

        $process = proc_open($command, $descriptors, $pipes, '/var/www/html');

        if (!is_resource($process)) {
            return ['exitCode' => -1, 'output' => 'Failed to start process'];
        }

        // Close stdin
        fclose($pipes[0]);

        // Read stdout and stderr
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        return [
            'exitCode' => $exitCode,
            'output'   => $stdout . ($stderr ? "\n" . $stderr : ''),
        ];
    }

    /**
     * Check if docs are satisfied (exists or no source to document)
     *
     * @param array<string, mixed> $docs
     */
    private function isDocsSatisfied(array $docs): bool
    {
        // No source files = nothing to document = satisfied
        if (!$docs['hasSource']) {
            return true;
        }

        // Has source files = docs must exist
        return $docs['exists'];
    }

    /**
     * Check if docs need regeneration (has source AND (missing OR outdated))
     *
     * @param array<string, mixed> $docs
     */
    private function needsRegeneration(array $docs): bool
    {
        // No source files = nothing to regenerate
        if (!$docs['hasSource']) {
            return false;
        }

        return !$docs['exists'] || $docs['outdated'];
    }

    /**
     * Get make commands needed to generate missing or outdated docs
     *
     * @param array<string, mixed> $phpDocs
     * @param array<string, mixed> $nodeBackendDocs
     * @param array<string, mixed> $nodeFrontendDocs
     *
     * @return array<string, string>
     */
    private function getMissingDocsCommands(
        array $phpDocs,
        array $nodeBackendDocs,
        array $nodeFrontendDocs,
    ): array {
        $commands = [];

        $phpNeedsRegen      = $this->needsRegeneration($phpDocs);
        $backendNeedsRegen  = $this->needsRegeneration($nodeBackendDocs);
        $frontendNeedsRegen = $this->needsRegeneration($nodeFrontendDocs);

        // Suggest most efficient command
        if ($phpNeedsRegen && ($backendNeedsRegen || $frontendNeedsRegen)) {
            $commands['all'] = 'make docs';
        } else {
            if ($phpNeedsRegen) {
                $commands['php'] = 'make docs-php';
            }
            if ($backendNeedsRegen && $frontendNeedsRegen) {
                $commands['node'] = 'make docs-node';
            } elseif ($backendNeedsRegen) {
                $commands['node_backend'] = 'make docs-node-backend';
            } elseif ($frontendNeedsRegen) {
                $commands['node_frontend'] = 'make docs-node-frontend';
            }
        }

        return $commands;
    }
}
