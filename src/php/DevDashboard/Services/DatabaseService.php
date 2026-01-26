<?php

declare(strict_types=1);

namespace DevDashboard\Services;

use App\Infrastructure\DatabaseConfig;
use PDO;
use PDOException;

/**
 * Database Service
 *
 * Provides database introspection and statistics
 */
class DatabaseService
{
    private readonly DatabaseConfig $config;
    private readonly DatabaseMetricsService $metricsService;

    public function __construct(
        private readonly DatabaseBackupService $backupService = new DatabaseBackupService(),
        ?DatabaseMetricsService $metricsService = null,
    ) {
        $this->config         = new DatabaseConfig();
        $this->metricsService = $metricsService ?? new DatabaseMetricsService($this->config);
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
            'version'     => $this->metricsService->getDatabaseVersion($pdo),
            'table_count' => $this->metricsService->getTableCount($pdo),
            'total_size'  => $this->metricsService->getDatabaseSize($pdo),
        ];
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
                return $this->getPostgresTables($pdo);
            }

            return $this->getMySqlTables($pdo);
        } catch (PDOException) {
            return [];
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

        return $this->metricsService->getConnectionStats($pdo);
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
     * List all database backups (delegates to DatabaseBackupService)
     *
     * @return array<string, mixed>
     */
    public function listBackups(): array
    {
        return $this->backupService->listBackups();
    }

    /**
     * Create a new database backup (delegates to DatabaseBackupService)
     *
     * @return array<string, mixed>
     */
    public function createBackup(?int $retention = null): array
    {
        return $this->backupService->createBackup($retention);
    }

    /**
     * Restore database from backup (delegates to DatabaseBackupService)
     *
     * @return array<string, mixed>
     */
    public function restoreBackup(string $filename): array
    {
        return $this->backupService->restoreBackup($filename);
    }

    /**
     * Delete a backup file (delegates to DatabaseBackupService)
     *
     * @return array<string, mixed>
     */
    public function deleteBackup(string $filename): array
    {
        return $this->backupService->deleteBackup($filename);
    }

    /**
     * Get backup statistics (delegates to DatabaseBackupService)
     *
     * @return array<string, mixed>
     */
    public function getBackupStats(): array
    {
        return $this->backupService->getBackupStats();
    }

    /**
     * Get PostgreSQL tables
     *
     * @return array<int, array<string, mixed>>
     */
    private function getPostgresTables(PDO $pdo): array
    {
        $stmt = $pdo->query(
            "SELECT
                schemaname as schema_name,
                tablename as table_name,
                pg_size_pretty(pg_total_relation_size(schemaname||'.'||tablename)) as total_size
            FROM pg_tables
            WHERE schemaname = 'public'
            ORDER BY tablename"
        );

        if ($stmt === false) {
            return [];
        }

        $tables = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $tables[] = [
                'name'      => $row['table_name'],
                'schema'    => $row['schema_name'],
                'size'      => $row['total_size'],
                'row_count' => $this->metricsService->getTableRowCount($pdo, $row['table_name']),
            ];
        }

        return $tables;
    }

    /**
     * Get MySQL tables
     *
     * @return array<int, array<string, mixed>>
     */
    private function getMySqlTables(PDO $pdo): array
    {
        $stmt = $pdo->query(
            "SELECT
                table_name,
                ROUND((data_length + index_length) / 1024 / 1024, 2) as total_size_mb
            FROM information_schema.tables
            WHERE table_schema = '{$this->config->name}'
            ORDER BY table_name"
        );

        if ($stmt === false) {
            return [];
        }

        $tables = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $tables[] = [
                'name'      => $row['table_name'],
                'size'      => $row['total_size_mb'] . ' MB',
                'row_count' => $this->metricsService->getTableRowCount($pdo, $row['table_name']),
            ];
        }

        return $tables;
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

        // Temporarily suppress warnings for network errors (expected when services unavailable)
        set_error_handler(static fn(): true => true);
        $connection = fsockopen($host, (int) $port, timeout: 1);
        restore_error_handler();

        if ($connection !== false) {
            fclose($connection);

            return true;
        }

        return false;
    }
}
