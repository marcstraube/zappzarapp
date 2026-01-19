<?php

declare(strict_types=1);

namespace DevDashboard\Services;

use SplFileObject;

/**
 * Log Service
 *
 * Provides access to application and service logs
      *
     * @return array<string, mixed>
     */
class LogService
{
    private readonly string $projectRoot;

    private readonly string $storageDir;

    public function __construct()
    {
        $this->projectRoot = __DIR__ . '/../../../../';
        $this->storageDir  = $this->projectRoot . 'storage/logs/';
    }

    /**
     * Get available log sources (only enabled services)
     *
     * @return array<string, array<string, mixed>>
     */
    public function getAvailableLogSources(): array
    {
        $sources = [
            'docker' => [
                'name'        => 'Docker Services',
                'description' => 'Container logs from all services',
                'type'        => 'docker',
                'available'   => true,
                'command'     => 'docker compose logs --tail=100 -f',
                'services'    => $this->getActiveServices(),
            ],
            'application' => [
                'name'        => 'Application Logs',
                'description' => 'Application-level logs from storage/logs/',
                'type'        => 'file',
                'available'   => is_dir($this->storageDir),
                'path'        => 'storage/logs/',
                'files'       => $this->getApplicationLogs(),
            ],
            'nginx' => [
                'name'        => 'Nginx Access/Error Logs',
                'description' => 'Web server access and error logs',
                'type'        => 'docker',
                'available'   => true,
                'command'     => 'docker compose logs nginx --tail=100',
            ],
            'php' => [
                'name'        => 'PHP-FPM Logs',
                'description' => 'PHP-FPM error and debug logs',
                'type'        => 'docker',
                'available'   => getenv('ENABLE_PHP') !== 'false',
                'command'     => 'docker compose logs php --tail=100',
            ],
            'node' => [
                'name'        => 'Node.js Logs',
                'description' => 'Node.js application and PM2 logs',
                'type'        => 'docker',
                'available'   => getenv('ENABLE_NODE') !== 'false',
                'command'     => 'docker compose logs node --tail=100',
            ],
            'mercure' => [
                'name'        => 'Mercure Logs',
                'description' => 'Real-time messaging hub logs',
                'type'        => 'docker',
                'available'   => getenv('ENABLE_MERCURE') === 'true',
                'command'     => 'docker compose logs mercure --tail=100',
            ],
            'meilisearch' => [
                'name'        => 'Meilisearch Logs',
                'description' => 'Search engine logs',
                'type'        => 'docker',
                'available'   => getenv('ENABLE_MEILISEARCH') === 'true',
                'command'     => 'docker compose logs meilisearch --tail=100',
            ],
            'elasticsearch' => [
                'name'        => 'Elasticsearch Logs',
                'description' => 'Distributed search and analytics engine logs',
                'type'        => 'docker',
                'available'   => getenv('ENABLE_ELASTICSEARCH') === 'true',
                'command'     => 'docker compose logs elasticsearch --tail=100',
            ],
            'mailpit' => [
                'name'        => 'Mailpit Logs',
                'description' => 'Email testing tool logs',
                'type'        => 'docker',
                'available'   => getenv('ENABLE_MAILPIT') === 'true',
                'command'     => 'docker compose logs mailpit --tail=100',
            ],
            'rabbitmq' => [
                'name'        => 'RabbitMQ Logs',
                'description' => 'Message broker logs',
                'type'        => 'docker',
                'available'   => getenv('ENABLE_RABBITMQ') === 'true',
                'command'     => 'docker compose logs rabbitmq --tail=100',
            ],
            'seaweedfs' => [
                'name'        => 'SeaweedFS Logs',
                'description' => 'S3-compatible object storage logs',
                'type'        => 'docker',
                'available'   => getenv('ENABLE_SEAWEEDFS') === 'true',
                'command'     => 'docker compose logs seaweedfs --tail=100',
            ],
        ];

        // Filter out unavailable sources
        return array_filter($sources, static fn (array $source): bool => $source['available']);
    }

    /**
     * Get application log files from storage
          *
     * @return array<int, array<string, mixed>>
     */
    private function getApplicationLogs(): array
    {
        if (!is_dir($this->storageDir)) {
            return [];
        }

        $logs  = [];
        $files = glob($this->storageDir . '*.log');

        if ($files === false) {
            return [];
        }

        foreach ($files as $file) {
            $logs[] = [
                'name'     => basename($file),
                'path'     => $file,
                'size'     => filesize($file),
                'modified' => filemtime($file),
            ];
        }

        // Sort by modification time (newest first)
        usort($logs, fn (array $a, array $b): int => $b['modified'] <=> $a['modified']);

        return $logs;
    }

    /**
     * Read log file content
          *
     * @return array<string, mixed>
     */
    public function readLogFile(string $filename, int $lines = 100): array
    {
        $filepath = $this->storageDir . basename($filename); // Security: prevent path traversal

        if (!file_exists($filepath)) {
            return [
                'error'    => 'Log file not found',
                'filename' => $filename,
            ];
        }

        $content = $this->tail($filepath, $lines);

        return [
            'filename' => basename($filename),
            'lines'    => $lines,
            'content'  => $content,
            'size'     => filesize($filepath),
            'modified' => filemtime($filepath),
        ];
    }

    /**
     * Read last N lines from file (like tail command)
     */
    private function tail(string $filepath, int $lines = 100): string
    {
        $file = new SplFileObject($filepath, 'r');
        $file->seek(PHP_INT_MAX);

        $lastLine = $file->key();

        $offset = max(0, $lastLine - $lines);
        $file->seek($offset);

        $content = '';
        while (!$file->eof()) {
            $content .= $file->fgets();
        }

        return $content;
    }

    /**
     * Get log viewing commands for CLI
          *
     * @return array<int, array<string, string>>
     */
    public function getLogCommands(): array
    {
        return [
            [
                'label'       => 'View All Logs (Real-time)',
                'command'     => 'docker compose logs -f',
                'description' => 'Follow all service logs in real-time',
            ],
            [
                'label'       => 'View Specific Service',
                'command'     => 'docker compose logs -f [service]',
                'description' => 'Replace [service] with: nginx, php, node, postgres, mariadb, redis',
            ],
            [
                'label'       => 'View Last 100 Lines',
                'command'     => 'docker compose logs --tail=100',
                'description' => 'Show last 100 log lines from all services',
            ],
            [
                'label'       => 'View Logs Since Timestamp',
                'command'     => 'docker compose logs --since="2025-01-01T00:00:00"',
                'description' => 'Show logs since a specific timestamp',
            ],
            [
                'label'       => 'Search Logs',
                'command'     => 'docker compose logs | grep "error"',
                'description' => 'Search for specific patterns in logs',
            ],
        ];
    }

    /**
     * Get log statistics
          *
     * @return array<string, mixed>
     */
    public function getLogStatistics(): array
    {
        $logs      = $this->getApplicationLogs();
        $totalSize = array_sum(array_column($logs, 'size'));

        return [
            'application_logs_count' => count($logs),
            'total_size'             => $totalSize,
            'total_size_formatted'   => $this->formatBytes($totalSize),
            'storage_dir_exists'     => is_dir($this->storageDir),
        ];
    }

    /**
     * Format bytes to human-readable format
     */
    private function formatBytes(int $bytes): string
    {
        $units  = ['B', 'KB', 'MB', 'GB'];
        $factor = (int) floor((strlen((string) $bytes) - 1) / 3);

        return sprintf('%.2f %s', $bytes / (1024 ** $factor), $units[$factor]);
    }

    /**
     * Get list of active services based on environment configuration
     *
     * @return array<int, string>
     * @SuppressWarnings("PHPMD.CyclomaticComplexity")
     * @SuppressWarnings("PHPMD.NPathComplexity")
     */
    private function getActiveServices(): array
    {
        $services = ['nginx'];

        // Core services (enabled by default)
        $coreServices = [
            'php'   => 'ENABLE_PHP',
            'node'  => 'ENABLE_NODE',
            'redis' => 'ENABLE_REDIS',
        ];

        foreach ($coreServices as $service => $envVar) {
            if (getenv($envVar) !== 'false') {
                $services[] = $service;
            }
        }

        // Database (based on DB_TYPE)
        $dbType = getenv('DB_TYPE') ?: 'postgres';
        if ($dbType === 'postgres') {
            $services[] = 'postgres';
        } elseif ($dbType === 'mariadb' || $dbType === 'mysql') {
            $services[] = 'mariadb';
        }

        // Optional services (disabled by default)
        $optionalServices = [
            'mercure'       => 'ENABLE_MERCURE',
            'meilisearch'   => 'ENABLE_MEILISEARCH',
            'elasticsearch' => 'ENABLE_ELASTICSEARCH',
            'mailpit'       => 'ENABLE_MAILPIT',
            'rabbitmq'      => 'ENABLE_RABBITMQ',
            'seaweedfs'     => 'ENABLE_SEAWEEDFS',
        ];

        foreach ($optionalServices as $service => $envVar) {
            if (getenv($envVar) === 'true') {
                $services[] = $service;
            }
        }

        return $services;
    }
}
