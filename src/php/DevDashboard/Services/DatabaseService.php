<?php

declare(strict_types=1);

namespace DevDashboard\Services;

use PDO;
use PDOException;

/**
 * Database Service
 *
 * Provides database introspection and statistics
 *
 * @SuppressWarnings("PHPMD.ExcessiveClassComplexity")
 */
class DatabaseService
{
    private readonly string $dbType;

    private readonly string $dbHost;

    private readonly string $dbPort;

    private readonly string $dbName;

    private readonly string $dbUser;

    private readonly string $dbPassword;

    public function __construct()
    {
        $this->dbType     = getenv('DB_TYPE') ?: 'postgres';
        $this->dbHost     = $this->getDefaultHost();
        $this->dbPort     = $this->getDefaultPort();
        $this->dbName     = getenv('DB_NAME') ?: 'app';
        $this->dbUser     = getenv('DB_USER') ?: 'app';
        $this->dbPassword = getenv('DB_PASSWORD') ?: 'secret';
    }

    /**
     * Get defaultHost
     */
    public function getDefaultHost(): string
    {
        $host = getenv('DB_HOST');
        if ($host !== false && $host !== '') {
            return $host;
        }

        return $this->dbType === 'postgres' ? 'postgres' : 'mariadb';
    }

    /**
     * Get defaultPort
     */
    public function getDefaultPort(): string
    {
        $port = getenv('DB_PORT');
        if ($port !== false && $port !== '') {
            return $port;
        }

        return $this->dbType === 'postgres' ? '5432' : '3306';
    }

    /**
     * Get connection
     */
    public function getConnection(): ?PDO
    {
        try {
            if ($this->dbType === 'postgres') {
                $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $this->dbHost, $this->dbPort, $this->dbName);
            } else {
                $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s', $this->dbHost, $this->dbPort, $this->dbName);
            }

            return new PDO($dsn, $this->dbUser, $this->dbPassword, [
                PDO::ATTR_TIMEOUT => 3,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
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
            'type'        => $this->dbType,
            'host'        => $this->dbHost,
            'port'        => $this->dbPort,
            'database'    => $this->dbName,
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
            if ($this->dbType === 'postgres') {
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
            if ($this->dbType === 'postgres') {
                $stmt = $pdo->query(
                    "SELECT COUNT(*) FROM information_schema.tables
                    WHERE table_schema = 'public' AND table_type = 'BASE TABLE'"
                );
            } else {
                $stmt = $pdo->query(
                    "SELECT COUNT(*) FROM information_schema.tables
                    WHERE table_schema = '{$this->dbName}'"
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
            if ($this->dbType === 'postgres') {
                $stmt = $pdo->query(
                    sprintf("SELECT pg_size_pretty(pg_database_size('%s'))", $this->dbName)
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
                    WHERE table_schema = '{$this->dbName}'"
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
            if ($this->dbType === 'postgres') {
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
                    WHERE table_schema = '{$this->dbName}'
                    ORDER BY table_name"
                );
            }

            if ($stmt === false) {
                return [];
            }

            $tables = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if ($this->dbType === 'postgres') {
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
            if ($this->dbType === 'postgres') {
                $stmt = $pdo->query(
                    "SELECT
                        count(*) as total_connections,
                        count(*) FILTER (WHERE state = 'active') as active_connections,
                        count(*) FILTER (WHERE state = 'idle') as idle_connections
                    FROM pg_stat_activity
                    WHERE datname = '{$this->dbName}'"
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
     * Get useful database commands
          *
     * @return array<int, array<string, string>>
     */
    public function getDatabaseCommands(): array
    {
        return [
            [
                'label'   => 'Connect to Database (CLI)',
                'command' => $this->dbType === 'postgres'
                    ? sprintf('docker compose exec postgres psql -U %s -d %s', $this->dbUser, $this->dbName)
                    : sprintf('docker compose exec mariadb mysql -u %s -p%s %s', $this->dbUser, $this->dbPassword, $this->dbName),
                'description' => 'Open interactive database shell',
            ],
            [
                'label'   => 'List Tables',
                'command' => $this->dbType === 'postgres'
                    ? 'docker compose exec postgres psql -U app -d app -c "\\dt"'
                    : 'docker compose exec mariadb mysql -u app -psecret app -e "SHOW TABLES;"',
                'description' => 'Show all tables in database',
            ],
            [
                'label'   => 'Backup Database',
                'command' => $this->dbType === 'postgres'
                    ? sprintf('docker compose exec postgres pg_dump -U %s %s > backup.sql', $this->dbUser, $this->dbName)
                    : sprintf('docker compose exec mariadb mysqldump -u %s -p%s %s > backup.sql', $this->dbUser, $this->dbPassword, $this->dbName),
                'description' => 'Create database backup file',
            ],
            [
                'label'   => 'Restore Database',
                'command' => $this->dbType === 'postgres'
                    ? sprintf('docker compose exec -T postgres psql -U %s %s < backup.sql', $this->dbUser, $this->dbName)
                    : sprintf('docker compose exec -T mariadb mysql -u %s -p%s %s < backup.sql', $this->dbUser, $this->dbPassword, $this->dbName),
                'description' => 'Restore database from backup file',
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
}
