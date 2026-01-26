<?php

declare(strict_types=1);

namespace DevDashboard\Services;

use App\Infrastructure\DatabaseConfig;
use DateTime;
use PDO;
use PDOException;

/**
 * Database Service
 *
 * Provides database introspection and statistics
 *
 * @SuppressWarnings("PHPMD.ExcessiveClassComplexity")
 */
readonly class DatabaseService
{
    private DatabaseConfig $config;

    public function __construct()
    {
        $this->config = new DatabaseConfig();
    }

    /**
     * Get connection
     */
    public function getConnection(): ?PDO
    {
        try {
            $options = [
                PDO::ATTR_TIMEOUT => 3,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ] + $this->config->getPdoSslOptions();

            return new PDO($this->config->getDsn(), $this->config->user, $this->config->password, $options);
        } catch (PDOException) {
            return null;
        }
    }

    /**
     * Get database overview
          *
     * @return array<string, mixed>
     */
    public function getDatabaseOverview(): array
    {
        $pdo = $this->getConnection();

        if (!$pdo instanceof PDO) {
            return [
                'connected' => false,
                'error'     => 'Could not connect to database',
            ];
        }

        return [
            'connected'   => true,
            'type'        => $this->config->type,
            'host'        => $this->config->host,
            'port'        => $this->config->port,
            'database'    => $this->config->name,
            'version'     => $this->getDatabaseVersion($pdo),
            'table_count' => $this->getTableCount($pdo),
            'total_size'  => $this->getDatabaseSize($pdo),
        ];
    }

    /**
     * Get databaseVersion
     */
    public function getDatabaseVersion(PDO $pdo): string
    {
        try {
            if ($this->config->isPostgres()) {
                $stmt = $pdo->query('SELECT version()');
                if ($stmt === false) {
                    return 'Unknown';
                }

                $version = $stmt->fetchColumn();
                if (!is_string($version)) {
                    return 'Unknown';
                }

                // Extract just the version number
                preg_match('/PostgreSQL ([\d.]+)/', $version, $matches);
                return $matches[1] ?? $version;
            } else {
                $stmt = $pdo->query('SELECT VERSION()');
                if ($stmt === false) {
                    return 'Unknown';
                }

                $result = $stmt->fetchColumn();
                return is_string($result) ? $result : 'Unknown';
            }
        } catch (PDOException) {
            return 'Unknown';
        }
    }

    /**
     * Get tableCount
     */
    public function getTableCount(PDO $pdo): int
    {
        try {
            if ($this->config->isPostgres()) {
                $stmt = $pdo->query(
                    "SELECT COUNT(*) FROM information_schema.tables
                    WHERE table_schema = 'public' AND table_type = 'BASE TABLE'"
                );
            } else {
                $stmt = $pdo->query(
                    "SELECT COUNT(*) FROM information_schema.tables
                    WHERE table_schema = '{$this->config->name}'"
                );
            }

            if ($stmt === false) {
                return 0;
            }

            return (int) $stmt->fetchColumn();
        } catch (PDOException) {
            return 0;
        }
    }

    /**
     * Get databaseSize
     */
    public function getDatabaseSize(PDO $pdo): string
    {
        try {
            if ($this->config->isPostgres()) {
                $stmt = $pdo->query(
                    sprintf("SELECT pg_size_pretty(pg_database_size('%s'))", $this->config->name)
                );
                if ($stmt === false) {
                    return 'Unknown';
                }

                $result = $stmt->fetchColumn();
                return $result !== false ? (string)$result : '0 bytes';
            } else {
                $stmt = $pdo->query(
                    "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
                    FROM information_schema.tables
                    WHERE table_schema = '{$this->config->name}'"
                );
                if ($stmt === false) {
                    return 'Unknown';
                }

                $sizeMb = $stmt->fetchColumn();
                return $sizeMb !== false ? $sizeMb . ' MB' : '0 MB';
            }
        } catch (PDOException) {
            return 'Unknown';
        }
    }

    /**
     * Get list of tables with details
          *
     * @return array<int, array<string, mixed>>
     */
    public function getTables(): array
    {
        $pdo = $this->getConnection();

        if (!$pdo instanceof PDO) {
            return [];
        }

        try {
            if ($this->config->isPostgres()) {
                $stmt = $pdo->query(
                    "SELECT
                        schemaname as schema_name,
                        tablename as table_name,
                        pg_size_pretty(pg_total_relation_size(schemaname||'.'||tablename)) as total_size
                    FROM pg_tables
                    WHERE schemaname = 'public'
                    ORDER BY tablename"
                );
            } else {
                $stmt = $pdo->query(
                    "SELECT
                        table_name,
                        ROUND((data_length + index_length) / 1024 / 1024, 2) as total_size_mb
                    FROM information_schema.tables
                    WHERE table_schema = '{$this->config->name}'
                    ORDER BY table_name"
                );
            }

            if ($stmt === false) {
                return [];
            }

            $tables = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if ($this->config->isPostgres()) {
                    $tables[] = [
                        'name'      => $row['table_name'],
                        'schema'    => $row['schema_name'],
                        'size'      => $row['total_size'],
                        'row_count' => $this->getTableRowCount($pdo, $row['table_name']),
                    ];
                } else {
                    $tables[] = [
                        'name'      => $row['table_name'],
                        'size'      => $row['total_size_mb'] . ' MB',
                        'row_count' => $this->getTableRowCount($pdo, $row['table_name']),
                    ];
                }
            }

            return $tables;
        } catch (PDOException) {
            return [];
        }
    }

    /**
     * Get tableRowCount
     */
    public function getTableRowCount(PDO $pdo, string $tableName): int
    {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM " . $pdo->quote($tableName));
            if ($stmt === false) {
                return 0;
            }

            return (int) $stmt->fetchColumn();
        } catch (PDOException) {
            return 0;
        }
    }

    /**
     * Get connection pool statistics
          *
     * @return array<string, mixed>
     */
    public function getConnectionStats(): array
    {
        $pdo = $this->getConnection();

        if (!$pdo instanceof PDO) {
            return [
                'available' => false,
                'message'   => 'Could not connect to database',
            ];
        }

        try {
            if ($this->config->isPostgres()) {
                $stmt = $pdo->query(
                    "SELECT
                        count(*) as total_connections,
                        count(*) FILTER (WHERE state = 'active') as active_connections,
                        count(*) FILTER (WHERE state = 'idle') as idle_connections
                    FROM pg_stat_activity
                    WHERE datname = '{$this->config->name}'"
                );
                if ($stmt === false) {
                    return ['available' => false, 'message' => 'Query failed'];
                }

                $stats = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($stats === false) {
                    return ['available' => false, 'message' => 'No data'];
                }

                return [
                    'available' => true,
                    'total'     => $stats['total_connections'],
                    'active'    => $stats['active_connections'],
                    'idle'      => $stats['idle_connections'],
                ];
            } else {
                $stmt = $pdo->query('SHOW STATUS LIKE "Threads_connected"');
                if ($stmt === false) {
                    return ['available' => false, 'message' => 'Query failed'];
                }

                $connected = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($connected === false) {
                    return ['available' => false, 'message' => 'No data'];
                }

                return [
                    'available' => true,
                    'total'     => $connected['Value'] ?? 0,
                    'active'    => 'N/A',
                    'idle'      => 'N/A',
                ];
            }
        } catch (PDOException $pdoException) {
            return [
                'available' => false,
                'error'     => $pdoException->getMessage(),
            ];
        }
    }

    /**
     * Get useful database commands (using Make targets)
     *
     * @return array<int, array<string, string>>
     */
    public function getDatabaseCommands(): array
    {
        return [
            [
                'label'       => 'Connect to Database (CLI)',
                'command'     => $this->config->isPostgres() ? 'make postgres-cli' : 'make mariadb-cli',
                'description' => 'Open interactive database shell',
            ],
            [
                'label'       => 'Connect with Auto-Complete',
                'command'     => $this->config->isPostgres() ? 'make postgres-cli-enhanced' : 'make mariadb-cli-enhanced',
                'description' => 'Enhanced CLI with syntax highlighting and auto-complete (pgcli/mycli)',
            ],
            [
                'label'       => 'Backup Database',
                'command'     => 'make backup-db',
                'description' => 'Create encrypted database backup to backups/db/ directory',
            ],
            [
                'label'       => 'List Backups',
                'command'     => 'make backup-db-list',
                'description' => 'List all available database backups',
            ],
            [
                'label'       => 'Restore Database',
                'command'     => 'make backup-db-restore',
                'description' => 'Restore database from backup file (interactive)',
            ],
            [
                'label'       => 'Run Migrations',
                'command'     => 'make db-migrations',
                'description' => 'Execute pending database migrations',
            ],
        ];
    }

    /**
     * Get database tools status
     *
     * @return array<string, array<string, mixed>>
     */
    public function getDbToolsStatus(): array
    {
        return [
            'adminer' => [
                'enabled'     => $this->isContainerRunning('adminer'),
                'name'        => 'Adminer',
                'description' => 'Universal DB admin (PostgreSQL, MariaDB, SQLite)',
                'url'         => '/_dev/adminer/',
                'start_cmd'   => 'make adminer-up',
                'stop_cmd'    => 'make adminer-down',
            ],
            'pgadmin' => [
                'enabled'     => $this->isContainerRunning('pgadmin'),
                'name'        => 'pgAdmin',
                'description' => 'Full-featured PostgreSQL management',
                'url'         => '/_dev/pgadmin/',
                'start_cmd'   => 'make pgadmin-up',
                'stop_cmd'    => 'make pgadmin-down',
                'db_type'     => 'postgres',
            ],
        ];
    }

    /**
     * Check if a container is running by attempting to connect to its service
     */
    private function isContainerRunning(string $service): bool
    {
        $hosts = [
            'adminer' => 'adminer:8080',
            'pgadmin' => 'pgadmin:80',
        ];

        if (!isset($hosts[$service])) {
            return false;
        }

        [$host, $port] = explode(':', $hosts[$service]);

        $connection = fsockopen($host, (int) $port, timeout: 1);
        if ($connection !== false) {
            fclose($connection);

            return true;
        }

        return false;
    }

    /**
     * Get quick stats for dashboard
          *
     * @return array<string, mixed>
     */
    public function getQuickStats(): array
    {
        $overview = $this->getDatabaseOverview();

        if (!$overview['connected']) {
            return [
                'available' => false,
                'message'   => $overview['error'] ?? 'Database not available',
            ];
        }

        return [
            'available' => true,
            'type'      => $overview['type'],
            'version'   => $overview['version'],
            'tables'    => $overview['table_count'],
            'size'      => $overview['total_size'],
        ];
    }

    /**
     * List all database backups
     *
     * @return array{success: bool, backups: array<int, array{
     *   filename: string,
     *   dbType: string,
     *   dbName: string,
     *   timestamp: string,
     *   encrypted: bool,
     *   size: string,
     *   sizeBytes: int,
     *   age: string
     * }>}
     */
    public function listBackups(): array
    {
        $backupDir = $this->getBackupDirectory();

        if (!is_dir($backupDir)) {
            return ['success' => true, 'backups' => []];
        }

        $backups = [];
        $files   = glob($backupDir . '/*.sql.gz*');

        if ($files === false) {
            return ['success' => true, 'backups' => []];
        }

        foreach ($files as $file) {
            $filename = basename($file);
            $metadata = $this->parseBackupFilename($filename);

            if ($metadata === null) {
                continue;
            }

            $sizeBytes = filesize($file);
            $mtime     = filemtime($file);

            $backups[] = [
                'filename'  => $filename,
                'dbType'    => $metadata['dbType'],
                'dbName'    => $metadata['dbName'],
                'timestamp' => $metadata['timestamp'],
                'encrypted' => $metadata['encrypted'],
                'size'      => $this->formatBytes($sizeBytes ?: 0),
                'sizeBytes' => $sizeBytes ?: 0,
                'age'       => $this->formatAge($mtime ?: 0),
            ];
        }

        // Sort by timestamp descending (newest first)
        usort($backups, function ($a, $b) {
            return strcmp($b['timestamp'], $a['timestamp']);
        });

        return ['success' => true, 'backups' => $backups];
    }

    /**
     * Create database backup
     *
     * @param int|null $retention Retention days (null = use default)
     * @return array{success: bool, message: string, output?: string, backup?: array{filename: string}|null}
     */
    public function createBackup(?int $retention = null): array
    {
        $scriptPath = '/var/www/html/docker/scripts/backup-databases.sh';

        if (!file_exists($scriptPath)) {
            return [
                'success' => false,
                'message' => 'Backup script not found',
            ];
        }

        $command = escapeshellcmd($scriptPath);

        if ($retention !== null && $retention >= 0) {
            $command .= ' --retention ' . escapeshellarg((string) $retention);
        }

        $result = $this->runCommand($command);

        if ($result['exitCode'] === 0) {
            // Parse output to extract backup filename
            $backupFile = null;
            if (preg_match('/File:\s+.*?\/([^\s]+\.sql\.gz(?:\.enc)?)/', $result['output'], $matches)) {
                $backupFile = $matches[1];
            }

            $backup = $backupFile ? ['filename' => $backupFile] : null;

            return [
                'success' => true,
                'message' => 'Backup created successfully',
                'output'  => $result['output'],
                'backup'  => $backup,
            ];
        }

        return [
            'success' => false,
            'message' => 'Backup failed',
            'output'  => $result['output'],
        ];
    }

    /**
     * Restore database from backup
     *
     * @param string $filename Backup filename (e.g., "postgres_app_20260126_033715.sql.gz.enc")
     * @return array{success: bool, message: string, output?: string}
     */
    public function restoreBackup(string $filename): array
    {
        // Validate filename for security (prevent directory traversal)
        if (!$this->validateBackupFilename($filename)) {
            return [
                'success' => false,
                'message' => 'Invalid backup filename',
            ];
        }

        $backupDir  = $this->getBackupDirectory();
        $backupPath = $backupDir . '/' . $filename;

        if (!file_exists($backupPath)) {
            return [
                'success' => false,
                'message' => 'Backup file not found: ' . $filename,
            ];
        }

        $scriptPath = '/var/www/html/docker/scripts/restore-database.sh';

        if (!file_exists($scriptPath)) {
            return [
                'success' => false,
                'message' => 'Restore script not found',
            ];
        }

        $command = escapeshellcmd($scriptPath) . ' --yes ' . escapeshellarg($backupPath);

        $result = $this->runCommand($command);

        if ($result['exitCode'] === 0) {
            return [
                'success' => true,
                'message' => 'Database restored successfully from: ' . $filename,
                'output'  => $result['output'],
            ];
        }

        return [
            'success' => false,
            'message' => 'Restore failed',
            'output'  => $result['output'],
        ];
    }

    /**
     * Delete backup file
     *
     * @param string $filename Backup filename (validates path to prevent traversal)
     * @return array{success: bool, message: string}
     */
    public function deleteBackup(string $filename): array
    {
        // Validate filename for security
        if (!$this->validateBackupFilename($filename)) {
            return [
                'success' => false,
                'message' => 'Invalid backup filename',
            ];
        }

        $backupDir  = $this->getBackupDirectory();
        $backupPath = $backupDir . '/' . $filename;

        if (!file_exists($backupPath)) {
            return [
                'success' => false,
                'message' => 'Backup file not found: ' . $filename,
            ];
        }

        if (unlink($backupPath)) {
            return [
                'success' => true,
                'message' => 'Backup deleted successfully: ' . $filename,
            ];
        }

        return [
            'success' => false,
            'message' => 'Failed to delete backup: ' . $filename,
        ];
    }

    /**
     * Get backup statistics
     *
     * @return array{count: int, totalSize: string, oldestDate: string, newestDate: string}
     */
    public function getBackupStats(): array
    {
        $result = $this->listBackups();

        if (!$result['success'] || empty($result['backups'])) {
            return [
                'count'      => 0,
                'totalSize'  => '0 B',
                'oldestDate' => 'N/A',
                'newestDate' => 'N/A',
            ];
        }

        $backups    = $result['backups'];
        $totalBytes = array_sum(array_column($backups, 'sizeBytes'));
        $lastIndex  = count($backups) - 1;

        return [
            'count'      => count($backups),
            'totalSize'  => $this->formatBytes($totalBytes),
            'oldestDate' => $backups[$lastIndex]['timestamp'],
            'newestDate' => $backups[0]['timestamp'],
        ];
    }

    /**
     * Run a shell command using proc_open
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

        // @phpstan-ignore ekinoBannedCode.function (DevDashboard is development-only, needs command execution for backup operations)
        $process = proc_open($command, $descriptors, $pipes);

        if (!is_resource($process)) {
            return ['exitCode' => -1, 'output' => 'Failed to start process'];
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        return [
            'exitCode' => $exitCode,
            'output'   => trim(($stdout ?: '') . "\n" . ($stderr ?: '')),
        ];
    }

    /**
     * Parse backup filename to extract metadata
     *
     * @return array{dbType: string, dbName: string, timestamp: string, encrypted: bool}|null
     */
    private function parseBackupFilename(string $filename): ?array
    {
        // Pattern: {dbType}_{dbName}_{timestamp}.sql.gz[.enc]
        // Example: postgres_app_20260126_120530.sql.gz.enc
        if (!preg_match('/^(postgres|mariadb)_([^_]+)_(\d{8}_\d{6})\.sql\.gz(\.enc)?$/', $filename, $matches)) {
            return null;
        }

        $timestamp = DateTime::createFromFormat('Ymd_His', $matches[3]);

        return [
            'dbType'    => $matches[1],
            'dbName'    => $matches[2],
            'timestamp' => $timestamp ? $timestamp->format('Y-m-d H:i:s') : $matches[3],
            'encrypted' => isset($matches[4]),
        ];
    }

    /**
     * Validate backup filename (prevent directory traversal attacks)
     */
    private function validateBackupFilename(string $filename): bool
    {
        // Must not contain directory separators
        if (str_contains($filename, '/') || str_contains($filename, '\\')) {
            return false;
        }

        // Must match expected pattern
        return preg_match('/^(postgres|mariadb)_[^_]+_\d{8}_\d{6}\.sql\.gz(\.enc)?$/', $filename) === 1;
    }

    /**
     * Get backup directory path
     */
    private function getBackupDirectory(): string
    {
        return '/var/www/html/backups/db';
    }

    /**
     * Format bytes to human-readable size
     */
    private function formatBytes(int $bytes): string
    {
        $units  = ['B', 'KB', 'MB', 'GB', 'TB'];
        $factor = (int) floor((strlen((string) $bytes) - 1) / 3);

        return sprintf('%.2f %s', $bytes / (1024 ** $factor), $units[$factor]);
    }

    /**
     * Format file age to human-readable string
     */
    private function formatAge(int $timestamp): string
    {
        $diff = time() - $timestamp;

        if ($diff < 60) {
            return 'Just now';
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
