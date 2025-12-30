<?php

declare(strict_types=1);

namespace App\Infrastructure;

/**
 * HealthCheck - Centralized service health status checker
 *
 * Checks the health of all infrastructure services:
 * - PHP-FPM
 * - Node.js Backend (if ENABLE_NODE=true)
 * - Redis (if ENABLE_REDIS=true)
 * - Database (if DB_TYPE is set)
 */
class HealthCheck
{
    private array $env;
    private array $status = [];

    public function __construct()
    {
        // Load environment variables
        $this->env = [
            'ENV'          => $_ENV['ENV'] ?? getenv('ENV') ?: 'production',
            'ENABLE_PHP'   => $this->parseBool($_ENV['ENABLE_PHP'] ?? getenv('ENABLE_PHP') ?: 'true'),
            'ENABLE_NODE'  => $this->parseBool($_ENV['ENABLE_NODE'] ?? getenv('ENABLE_NODE') ?: 'false'),
            'ENABLE_REDIS' => $this->parseBool($_ENV['ENABLE_REDIS'] ?? getenv('ENABLE_REDIS') ?: 'false'),
            'DB_TYPE'      => $_ENV['DB_TYPE'] ?? getenv('DB_TYPE') ?: null,
            'NODE_MODE'    => $_ENV['NODE_MODE'] ?? getenv('NODE_MODE') ?: 'none',
        ];
    }

    /**
     * Parse string boolean to actual boolean
     */
    private function parseBool(string $value): bool
    {
        return strtolower($value) === 'true';
    }

    /**
     * Check all services and return status array
     */
    public function checkAll(): array
    {
        $this->status = [
            'timestamp'      => date('c'),
            'environment'    => $this->env['ENV'],
            'overall_status' => 'ok',
            'services'       => [],
            'features'       => $this->env,
        ];

        // PHP-FPM is always running (otherwise this code wouldn't execute)
        $this->status['services']['php-fpm'] = [
            'status'  => 'ok',
            'version' => PHP_VERSION,
            'enabled' => $this->env['ENABLE_PHP'],
        ];

        // Check Node.js Backend
        if ($this->env['ENABLE_NODE']) {
            $this->checkNodeBackend();
        } else {
            $this->status['services']['node-backend'] = [
                'status'  => 'disabled',
                'enabled' => false,
            ];
        }

        // Check Redis
        if ($this->env['ENABLE_REDIS']) {
            $this->checkRedis();
        } else {
            $this->status['services']['redis'] = [
                'status'  => 'disabled',
                'enabled' => false,
            ];
        }

        // Check Database
        if ($this->env['DB_TYPE']) {
            $this->checkDatabase();
        } else {
            $this->status['services']['database'] = [
                'status'  => 'disabled',
                'enabled' => false,
            ];
        }

        // Set overall status to 'degraded' if any service is down
        foreach ($this->status['services'] as $service) {
            if (isset($service['status']) && $service['status'] === 'error') {
                $this->status['overall_status'] = 'degraded';
                break;
            }
        }

        return $this->status;
    }

    /**
     * Check Node.js Backend health
     */
    private function checkNodeBackend(): void
    {
        try {
            // Use internal Docker network hostname
            $url     = 'http://node:3000/health';
            $context = stream_context_create([
                'http' => [
                    'timeout'       => 2,
                    'ignore_errors' => true,
                ],
            ]);

            $response = @file_get_contents($url, false, $context);

            if ($response === false) {
                $this->status['services']['node-backend'] = [
                    'status'  => 'error',
                    'message' => 'Node backend not reachable',
                    'enabled' => true,
                    'mode'    => $this->env['NODE_MODE'],
                ];
                return;
            }

            $data = json_decode($response, true);

            $this->status['services']['node-backend'] = [
                'status'      => $data['status'] ?? 'unknown',
                'version'     => $data['node_version'] ?? 'unknown',
                'uptime'      => $data['uptime'] ?? null,
                'environment' => $data['environment'] ?? 'unknown',
                'enabled'     => true,
                'mode'        => $this->env['NODE_MODE'],
            ];
        } catch (\Exception $e) {
            $this->status['services']['node-backend'] = [
                'status'  => 'error',
                'message' => $e->getMessage(),
                'enabled' => true,
                'mode'    => $this->env['NODE_MODE'],
            ];
        }
    }

    /**
     * Check Redis connection
     */
    private function checkRedis(): void
    {
        if (!extension_loaded('redis')) {
            $this->status['services']['redis'] = [
                'status'  => 'error',
                'message' => 'Redis PHP extension not installed',
                'enabled' => true,
            ];
            return;
        }

        try {
            $redis     = new \Redis();
            $connected = @$redis->connect('redis', 6379, 2);

            if (!$connected) {
                $this->status['services']['redis'] = [
                    'status'  => 'error',
                    'message' => 'Could not connect to Redis',
                    'enabled' => true,
                ];
                return;
            }

            $pong = $redis->ping();
            $info = $redis->info('SERVER');

            $this->status['services']['redis'] = [
                'status'  => ($pong === '+PONG' || $pong === true) ? 'ok' : 'error',
                'version' => $info['redis_version'] ?? 'unknown',
                'enabled' => true,
            ];

            $redis->close();
        } catch (\Exception $e) {
            $this->status['services']['redis'] = [
                'status'  => 'error',
                'message' => $e->getMessage(),
                'enabled' => true,
            ];
        }
    }

    /**
     * Check Database connection
     */
    private function checkDatabase(): void
    {
        $dbType = $this->env['DB_TYPE'];

        if ($dbType === 'postgres') {
            $this->checkPostgreSQL();
        } elseif ($dbType === 'mariadb') {
            $this->checkMariaDB();
        } else {
            $this->status['services']['database'] = [
                'status'  => 'error',
                'message' => 'Unknown database type: ' . $dbType,
                'enabled' => true,
            ];
        }
    }

    /**
     * Check PostgreSQL connection
     */
    private function checkPostgreSQL(): void
    {
        if (!extension_loaded('pdo_pgsql')) {
            $this->status['services']['database'] = [
                'status'  => 'error',
                'type'    => 'postgres',
                'message' => 'PDO PostgreSQL extension not installed',
                'enabled' => true,
            ];
            return;
        }

        try {
            $dbName = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: 'app';
            $dbUser = $_ENV['DB_USER'] ?? getenv('DB_USER') ?: 'app';
            $dbPass = $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: 'secret';

            $dsn = "pgsql:host=postgres;port=5432;dbname={$dbName}";
            $pdo = new \PDO($dsn, $dbUser, $dbPass, [
                \PDO::ATTR_TIMEOUT => 2,
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            ]);

            $version = $pdo->query('SELECT version()')->fetchColumn();

            $this->status['services']['database'] = [
                'status'  => 'ok',
                'type'    => 'postgres',
                'version' => $version,
                'enabled' => true,
            ];
        } catch (\Exception $e) {
            $this->status['services']['database'] = [
                'status'  => 'error',
                'type'    => 'postgres',
                'message' => $e->getMessage(),
                'enabled' => true,
            ];
        }
    }

    /**
     * Check MariaDB connection
     */
    private function checkMariaDB(): void
    {
        if (!extension_loaded('pdo_mysql')) {
            $this->status['services']['database'] = [
                'status'  => 'error',
                'type'    => 'mariadb',
                'message' => 'PDO MySQL extension not installed',
                'enabled' => true,
            ];
            return;
        }

        try {
            $dbName = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: 'app';
            $dbUser = $_ENV['DB_USER'] ?? getenv('DB_USER') ?: 'app';
            $dbPass = $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: 'secret';

            $dsn = "mysql:host=mariadb;port=3306;dbname={$dbName}";
            $pdo = new \PDO($dsn, $dbUser, $dbPass, [
                \PDO::ATTR_TIMEOUT => 2,
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            ]);

            $version = $pdo->query('SELECT VERSION()')->fetchColumn();

            $this->status['services']['database'] = [
                'status'  => 'ok',
                'type'    => 'mariadb',
                'version' => $version,
                'enabled' => true,
            ];
        } catch (\Exception $e) {
            $this->status['services']['database'] = [
                'status'  => 'error',
                'type'    => 'mariadb',
                'message' => $e->getMessage(),
                'enabled' => true,
            ];
        }
    }

    /**
     * Get overall status (ok, degraded, error)
     */
    public function getOverallStatus(): string
    {
        if (empty($this->status)) {
            $this->checkAll();
        }

        return $this->status['overall_status'];
    }

    /**
     * Get all services status
     */
    public function getServices(): array
    {
        if (empty($this->status)) {
            $this->checkAll();
        }

        return $this->status['services'];
    }

    /**
     * Get environment info
     */
    public function getEnvironment(): array
    {
        return $this->env;
    }
}
