<?php

declare(strict_types=1);

namespace DevDashboard\Services;

use PDO;
use PDOException;

/**
 * Health Check Service
 *
 * Checks health of containers, databases, services, SSL certificates
 *
 * @SuppressWarnings("PHPMD.ExcessiveClassComplexity")
 */
class HealthCheckService
{
    /**
     * Get overall system health status
     *
     * @return array<string, mixed>
     */
    public function getOverallStatus(): array
    {
        $containers = $this->getContainerStatus();
        $databases  = $this->getDatabaseStatus();

        $healthy   = 0;
        $unhealthy = 0;

        foreach ($containers as $container) {
            if ($container['status'] === 'healthy' || $container['status'] === 'running') {
                $healthy++;
            } else {
                $unhealthy++;
            }
        }

        foreach ($databases as $db) {
            if ($db['connected']) {
                $healthy++;
            } else {
                $unhealthy++;
            }
        }

        return [
            'status'          => $unhealthy === 0 ? 'healthy' : 'degraded',
            'healthy_count'   => $healthy,
            'unhealthy_count' => $unhealthy,
            'timestamp'       => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Get Docker container status
          *
     * @return array<string, mixed>
     */
    public function getContainerStatus(): array
    {
        // Build list of containers to check based on configuration
        $containers = ['nginx']; // Nginx always runs

        // Check optional services based on environment
        if (getenv('ENABLE_PHP') !== 'false') {
            $containers[] = 'php';
        }

        if (getenv('ENABLE_NODE') !== 'false') {
            $containers[] = 'node';
        }

        if (getenv('ENABLE_REDIS') !== 'false') {
            $containers[] = 'redis';
        }

        // Check database based on DB_TYPE
        $dbType = getenv('DB_TYPE') ?: 'postgres';
        if ($dbType === 'postgres') {
            $containers[] = 'postgres';
        } elseif ($dbType === 'mariadb' || $dbType === 'mysql') {
            $containers[] = 'mariadb';
        }

        $status = [];
        foreach ($containers as $container) {
            $status[$container] = $this->checkContainer($container);
        }

        return $status;
    }

    /**
     * Check individual container status
     * Uses service connectivity checks instead of Docker commands (works inside containers)
          *
     * @return array<string, mixed>
     */
    private function checkContainer(string $containerName): array
    {
        // Map container names to their service checks
        $checks = [
            'php'      => $this->checkPhpFpm(...),
            'node'     => $this->checkNode(...),
            'nginx'    => $this->checkNginx(...),
            'postgres' => $this->checkPostgresConnection(...),
            'mariadb'  => $this->checkMariadbConnection(...),
            'redis'    => $this->checkRedisConnection(...),
        ];

        if (!isset($checks[$containerName])) {
            return [
                'name'    => $containerName,
                'status'  => 'unknown',
                'health'  => 'unknown',
                'message' => 'Unknown container',
            ];
        }

        $checkResult = $checks[$containerName]();
        $isRunning   = $checkResult['running'] ?? $checkResult['connected'] ?? false;

        return [
            'name'    => $containerName,
            'status'  => $isRunning ? 'running' : 'not_running',
            'health'  => $isRunning ? 'healthy' : 'unhealthy',
            'details' => $checkResult,
        ];
    }

    /**
     * Check PostgreSQL connection
          *
     * @return array<string, mixed>
     * @SuppressWarnings("PHPMD.UnusedLocalVariable")
     */
    private function checkPostgresConnection(): array
    {
        $host = 'postgres';
        $port = 5432;

        $socket = fsockopen($host, $port, $_errno, $errstr, 1);
        if ($socket) {
            fclose($socket);
            return ['connected' => true, 'host' => $host, 'port' => $port];
        }

        return ['connected' => false, 'error' => $errstr];
    }

    /**
     * Check MariaDB connection
          *
     * @return array<string, mixed>
     * @SuppressWarnings("PHPMD.UnusedLocalVariable")
     */
    private function checkMariadbConnection(): array
    {
        $host = 'mariadb';
        $port = 3306;

        $socket = fsockopen($host, $port, $_errno, $errstr, 1);
        if ($socket) {
            fclose($socket);
            return ['connected' => true, 'host' => $host, 'port' => $port];
        }

        return ['connected' => false, 'error' => $errstr];
    }

    /**
     * Check Redis connection
          *
     * @return array<string, mixed>
     * @SuppressWarnings("PHPMD.UnusedLocalVariable")
     */
    private function checkRedisConnection(): array
    {
        $host = 'redis';
        $port = 6379;

        $socket = fsockopen($host, $port, $_errno, $errstr, 1);
        if ($socket) {
            fclose($socket);
            return ['connected' => true, 'host' => $host, 'port' => $port];
        }

        return ['connected' => false, 'error' => $errstr];
    }

    /**
     * Get database connection status
          *
     * @return array<string, mixed>
     */
    public function getDatabaseStatus(): array
    {
        $dbType = getenv('DB_TYPE') ?: 'postgres';
        $status = [];

        // Only check the configured database type
        if ($dbType === 'postgres') {
            $status['postgresql'] = $this->checkPostgresql();
        } elseif ($dbType === 'mariadb' || $dbType === 'mysql') {
            $status['mariadb'] = $this->checkMariadb();
        }

        return $status;
    }

    /**
     * Check PostgreSQL connection
          *
     * @return array<string, mixed>
     */
    private function checkPostgresql(): array
    {
        $host     = getenv('DB_HOST') ?: 'postgres';
        $port     = getenv('DB_PORT') ?: '5432';
        $dbname   = getenv('DB_NAME') ?: 'app';
        $user     = getenv('DB_USER') ?: 'app';
        $password = getenv('DB_PASSWORD') ?: 'secret';

        try {
            $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $host, $port, $dbname);
            $pdo = new PDO($dsn, $user, $password, [
                PDO::ATTR_TIMEOUT => 3,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);

            $stmt = $pdo->query('SELECT version()');
            if ($stmt === false) {
                return [
                    'connected' => true,
                    'host'      => $host,
                    'port'      => $port,
                    'database'  => $dbname,
                    'version'   => 'Unknown',
                ];
            }

            $version = $stmt->fetchColumn();
            if ($version === false) {
                $version = 'Unknown';
            }

            return [
                'connected' => true,
                'host'      => $host,
                'port'      => $port,
                'database'  => $dbname,
                'version'   => $version,
            ];
        } catch (PDOException $pdoException) {
            return [
                'connected' => false,
                'host'      => $host,
                'port'      => $port,
                'error'     => $pdoException->getMessage(),
            ];
        }
    }

    /**
     * Check MariaDB connection
          *
     * @return array<string, mixed>
     */
    private function checkMariadb(): array
    {
        $host     = getenv('DB_HOST') ?: 'mariadb';
        $port     = getenv('DB_PORT') ?: '3306';
        $dbname   = getenv('DB_NAME') ?: 'app';
        $user     = getenv('DB_USER') ?: 'app';
        $password = getenv('DB_PASSWORD') ?: 'secret';

        try {
            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s', $host, $port, $dbname);
            $pdo = new PDO($dsn, $user, $password, [
                PDO::ATTR_TIMEOUT => 3,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);

            $stmt = $pdo->query('SELECT VERSION()');
            if ($stmt === false) {
                return [
                    'connected' => true,
                    'host'      => $host,
                    'port'      => $port,
                    'database'  => $dbname,
                    'version'   => 'Unknown',
                ];
            }

            $version = $stmt->fetchColumn();
            if ($version === false) {
                $version = 'Unknown';
            }

            return [
                'connected' => true,
                'host'      => $host,
                'port'      => $port,
                'database'  => $dbname,
                'version'   => $version,
            ];
        } catch (PDOException $pdoException) {
            return [
                'connected' => false,
                'host'      => $host,
                'port'      => $port,
                'error'     => $pdoException->getMessage(),
            ];
        }
    }

    /**
     * Get service health (PHP-FPM, Node.js, Nginx)
          *
     * @return array<string, mixed>
     */
    public function getServiceStatus(): array
    {
        return [
            'php_fpm' => $this->checkPhpFpm(),
            'node'    => $this->checkNode(),
            'nginx'   => $this->checkNginx(),
        ];
    }

    /**
     * Check PHP-FPM status
          *
     * @return array<string, mixed>
     */
    private function checkPhpFpm(): array
    {
        // Check if we're running in PHP-FPM
        $isFpm = PHP_SAPI === 'fpm-fcgi';

        return [
            'running' => $isFpm,
            'sapi'    => PHP_SAPI,
            'version' => PHP_VERSION,
        ];
    }

    /**
     * Check Node.js service
          *
     * @return array<string, mixed>
     * @SuppressWarnings("PHPMD.UnusedLocalVariable")
     */
    private function checkNode(): array
    {
        $host = 'node';
        $port = 3000;

        $socket = fsockopen($host, $port, $_errno, $errstr, 1);
        if ($socket) {
            fclose($socket);
            return [
                'running' => true,
                'host'    => $host,
                'port'    => $port,
            ];
        }

        return [
            'running' => false,
            'error'   => $errstr ?: 'Service not reachable',
        ];
    }

    /**
     * Check Nginx status
          *
     * @return array<string, mixed>
     * @SuppressWarnings("PHPMD.UnusedLocalVariable")
     */
    private function checkNginx(): array
    {
        // Check if we can connect to nginx
        $socket = fsockopen('nginx', 8080, $_errno, $errstr, 1);
        if ($socket) {
            fclose($socket);
            return [
                'running' => true,
                'host'    => 'nginx',
                'port'    => 8080,
            ];
        }

        return [
            'running' => false,
            'error'   => $errstr ?: 'Service not reachable',
        ];
    }

    /**
     * Get SSL certificate information
          *
     * @return array<string, mixed>
     */
    public function getSslInfo(): array
    {
        $certPath = __DIR__ . '/../../../../docker/certs/cert.crt';

        if (!file_exists($certPath)) {
            return [
                'exists'  => false,
                'message' => 'No SSL certificate found',
            ];
        }

        $certContent = file_get_contents($certPath);
        if ($certContent === false) {
            return [
                'exists'  => true,
                'valid'   => false,
                'message' => 'Could not read certificate file',
            ];
        }

        $certData = openssl_x509_parse($certContent);

        if (!$certData) {
            return [
                'exists'  => true,
                'valid'   => false,
                'message' => 'Invalid certificate',
            ];
        }

        $now             = time();
        $validFrom       = $certData['validFrom_time_t'];
        $validTo         = $certData['validTo_time_t'];
        $daysUntilExpiry = floor(($validTo - $now) / 86400);

        return [
            'exists'            => true,
            'valid'             => $now >= $validFrom && $now <= $validTo,
            'subject'           => $certData['subject']['CN'] ?? 'Unknown',
            'issuer'            => $certData['issuer']['CN'] ?? 'Unknown',
            'valid_from'        => date('Y-m-d H:i:s', $validFrom),
            'valid_to'          => date('Y-m-d H:i:s', $validTo),
            'days_until_expiry' => $daysUntilExpiry,
            'expires_soon'      => $daysUntilExpiry < 30,
        ];
    }

}
