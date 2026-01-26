<?php

declare(strict_types=1);

namespace DevDashboard\Services;

use App\Infrastructure\DatabaseConfig;
use PDO;
use PDOException;

/**
 * Database Metrics Service
 *
 * Collects database metrics and statistics
 */
class DatabaseMetricsService
{
    public function __construct(
        private readonly DatabaseConfig $config,
    ) {}

    /**
     * Get database version
     */
    public function getDatabaseVersion(PDO $pdo): string
    {
        try {
            if ($this->config->isPostgres()) {
                return $this->getPostgresVersion($pdo);
            }

            return $this->getMySqlVersion($pdo);
        } catch (PDOException) {
            return 'Unknown';
        }
    }

    /**
     * Get table count
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
     * Get database size
     */
    public function getDatabaseSize(PDO $pdo): string
    {
        try {
            if ($this->config->isPostgres()) {
                return $this->getPostgresSize($pdo);
            }

            return $this->getMySqlSize($pdo);
        } catch (PDOException) {
            return 'Unknown';
        }
    }

    /**
     * Get table row count
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
    public function getConnectionStats(PDO $pdo): array
    {
        try {
            if ($this->config->isPostgres()) {
                return $this->getPostgresConnectionStats($pdo);
            }

            return $this->getMySqlConnectionStats($pdo);
        } catch (PDOException $pdoException) {
            return [
                'available' => false,
                'error'     => $pdoException->getMessage(),
            ];
        }
    }

    /**
     * Get PostgreSQL version
     */
    private function getPostgresVersion(PDO $pdo): string
    {
        $stmt = $pdo->query('SELECT version()');
        if ($stmt === false) {
            return 'Unknown';
        }

        $version = $stmt->fetchColumn();
        if (!is_string($version)) {
            return 'Unknown';
        }

        preg_match('/PostgreSQL ([\d.]+)/', $version, $matches);

        return $matches[1] ?? $version;
    }

    /**
     * Get MySQL version
     */
    private function getMySqlVersion(PDO $pdo): string
    {
        $stmt = $pdo->query('SELECT VERSION()');
        if ($stmt === false) {
            return 'Unknown';
        }

        $result = $stmt->fetchColumn();

        return is_string($result) ? $result : 'Unknown';
    }

    /**
     * Get PostgreSQL database size
     */
    private function getPostgresSize(PDO $pdo): string
    {
        $stmt = $pdo->query(
            sprintf("SELECT pg_size_pretty(pg_database_size('%s'))", $this->config->name)
        );
        if ($stmt === false) {
            return 'Unknown';
        }

        $result = $stmt->fetchColumn();

        return $result !== false ? (string) $result : '0 bytes';
    }

    /**
     * Get MySQL database size
     */
    private function getMySqlSize(PDO $pdo): string
    {
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

    /**
     * Get PostgreSQL connection statistics
     *
     * @return array<string, mixed>
     */
    private function getPostgresConnectionStats(PDO $pdo): array
    {
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
    }

    /**
     * Get MySQL connection statistics
     *
     * @return array<string, mixed>
     */
    private function getMySqlConnectionStats(PDO $pdo): array
    {
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
}
