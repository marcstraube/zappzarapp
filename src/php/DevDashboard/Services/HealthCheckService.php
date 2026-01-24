<?php

declare(strict_types=1);

namespace DevDashboard\Services;

use App\Infrastructure\DatabaseConfig;
use App\Infrastructure\TlsConfig;
use Exception;
use PDO;
use PDOException;
use Redis;

/**
 * Health Check Service
 *
 * Checks health of containers, databases, services, SSL certificates
 *
 * @SuppressWarnings("PHPMD.ExcessiveClassComplexity")
 * @SuppressWarnings("PHPMD.ExcessiveClassLength")
 */
class HealthCheckService
{
    /**
     * Service category constants
     */
    private const string CATEGORY_CORE     = 'core';

    private const string CATEGORY_DATA     = 'data';

    private const string CATEGORY_OPTIONAL = 'optional';

    /**
     * Get overall system health status
     *
     * @return array<string, mixed>
     */
    public function getOverallStatus(): array
    {
        $services = $this->getServices();

        $healthy   = 0;
        $unhealthy = 0;

        foreach ($services as $category) {
            foreach ($category as $service) {
                if ($service['status'] === 'running') {
                    $healthy++;
                } else {
                    $unhealthy++;
                }
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
     * Get all services grouped by category
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    public function getServices(): array
    {
        return [
            self::CATEGORY_CORE     => $this->getCoreServices(),
            self::CATEGORY_DATA     => $this->getDataServices(),
            self::CATEGORY_OPTIONAL => $this->getOptionalServices(),
        ];
    }

    /**
     * Get core services (always needed for the application)
     *
     * @return array<string, array<string, mixed>>
     */
    private function getCoreServices(): array
    {
        $services = [];

        // Nginx is always enabled
        $services['nginx'] = $this->buildServiceStatus('nginx', 'Nginx', 'Web Server / Reverse Proxy');

        if (getenv('ENABLE_NODE') !== 'false') {
            $services['node'] = $this->buildServiceStatus('node', 'Node.js', 'JavaScript Runtime');
        }

        if (getenv('ENABLE_PHP') !== 'false') {
            $services['php'] = $this->buildServiceStatus('php', 'PHP-FPM', 'PHP FastCGI Process Manager');
        }

        return $services;
    }

    /**
     * Get data services (databases, caches)
     *
     * @return array<string, array<string, mixed>>
     */
    private function getDataServices(): array
    {
        $services = [];

        // Database based on DB_TYPE
        $dbType = getenv('DB_TYPE') ?: 'postgres';
        if ($dbType === 'postgres') {
            $services['postgres'] = $this->buildServiceStatus('postgres', 'PostgreSQL', 'Relational Database');
        } elseif ($dbType === 'mariadb' || $dbType === 'mysql') {
            $services['mariadb'] = $this->buildServiceStatus('mariadb', 'MariaDB', 'Relational Database');
        }

        // Redis
        if (getenv('ENABLE_REDIS') !== 'false') {
            $services['redis'] = $this->buildServiceStatus('redis', 'Redis', 'In-Memory Cache & Sessions');
        }

        return $services;
    }

    /**
     * Get optional services
     *
     * @return array<string, array<string, mixed>>
     */
    private function getOptionalServices(): array
    {
        $services = [];

        $optionalConfig = [
            'elasticsearch' => ['env' => 'ENABLE_ELASTICSEARCH', 'name' => 'Elasticsearch', 'desc' => 'Search & Analytics'],
            'mailpit'       => ['env' => 'ENABLE_MAILPIT', 'name' => 'Mailpit', 'desc' => 'Email Testing'],
            'meilisearch'   => ['env' => 'ENABLE_MEILISEARCH', 'name' => 'Meilisearch', 'desc' => 'Search Engine'],
            'mercure'       => ['env' => 'ENABLE_MERCURE', 'name' => 'Mercure', 'desc' => 'Real-time Messaging (SSE)'],
            'rabbitmq'      => ['env' => 'ENABLE_RABBITMQ', 'name' => 'RabbitMQ', 'desc' => 'Message Broker'],
            'seaweedfs'     => ['env' => 'ENABLE_SEAWEEDFS', 'name' => 'SeaweedFS', 'desc' => 'S3-Compatible Storage'],
        ];

        foreach ($optionalConfig as $key => $config) {
            if (getenv($config['env']) === 'true') {
                $services[$key] = $this->buildServiceStatus($key, $config['name'], $config['desc']);
            }
        }

        return $services;
    }

    /**
     * Build service status array
     *
     * @return array<string, mixed>
     */
    private function buildServiceStatus(string $key, string $name, string $description): array
    {
        $check     = $this->checkService($key);
        $isRunning = $check['running'] ?? $check['connected'] ?? false;

        return [
            'name'        => $name,
            'description' => $description,
            'status'      => $isRunning ? 'running' : 'stopped',
            'port'        => $check['port'] ?? null,
            'details'     => $check,
        ];
    }

    /**
     * Check individual service status
     *
     * @return array<string, mixed>
     */
    private function checkService(string $serviceName): array
    {
        $checks = [
            'php'           => $this->checkPhpFpm(...),
            'node'          => $this->checkNode(...),
            'nginx'         => $this->checkNginx(...),
            'postgres'      => $this->checkPostgresConnection(...),
            'mariadb'       => $this->checkMariadbConnection(...),
            'redis'         => $this->checkRedisConnection(...),
            'mercure'       => $this->checkMercure(...),
            'meilisearch'   => $this->checkMeilisearch(...),
            'elasticsearch' => $this->checkElasticsearch(...),
            'mailpit'       => $this->checkMailpit(...),
            'rabbitmq'      => $this->checkRabbitmq(...),
            'seaweedfs'     => $this->checkSeaweedfs(...),
        ];

        if (!isset($checks[$serviceName])) {
            return ['running' => false, 'error' => 'Unknown service'];
        }

        return $checks[$serviceName]();
    }

    /**
     * Check PostgreSQL connection
     *
     * @return array<string, mixed>
     */
    private function checkPostgresConnection(): array
    {
        return $this->checkSocketConnection('postgres', 5432);
    }

    /**
     * Check MariaDB connection
     *
     * @return array<string, mixed>
     */
    private function checkMariadbConnection(): array
    {
        return $this->checkSocketConnection('mariadb', 3306);
    }

    /**
     * Check Redis connection
     *
     * @return array<string, mixed>
     */
    private function checkRedisConnection(): array
    {
        return $this->checkSocketConnection('redis', 6379);
    }

    /**
     * Check Mercure connection (HTTPS on port 443)
     *
     * @return array<string, mixed>
     */
    private function checkMercure(): array
    {
        return $this->checkSocketConnection('mercure', 443, useTls: true);
    }

    /**
     * Check Meilisearch connection (HTTPS on port 7700)
     *
     * @return array<string, mixed>
     */
    private function checkMeilisearch(): array
    {
        return $this->checkSocketConnection('meilisearch', 7700, useTls: true);
    }

    /**
     * Check Elasticsearch connection (HTTPS on port 9200)
     *
     * @return array<string, mixed>
     */
    private function checkElasticsearch(): array
    {
        return $this->checkSocketConnection('elasticsearch', 9200, useTls: true);
    }

    /**
     * Check Mailpit connection
     *
     * @return array<string, mixed>
     */
    private function checkMailpit(): array
    {
        return $this->checkSocketConnection('mailpit', 8025);
    }

    /**
     * Check RabbitMQ connection
     *
     * @return array<string, mixed>
     */
    private function checkRabbitmq(): array
    {
        return $this->checkSocketConnection('rabbitmq', 5672);
    }

    /**
     * Check SeaweedFS connection (S3 API port)
     *
     * @return array<string, mixed>
     */
    private function checkSeaweedfs(): array
    {
        return $this->checkSocketConnection('seaweedfs', 8333, useTls: true);
    }

    /**
     * Get detailed connection status for data services
     *
     * @return array<string, array<string, mixed>>
     */
    public function getConnections(): array
    {
        $connections = [];

        // Database connection
        $dbType = getenv('DB_TYPE') ?: 'postgres';
        if ($dbType === 'postgres') {
            $connections['database'] = $this->checkPostgresql() + ['type' => 'PostgreSQL'];
        } elseif ($dbType === 'mariadb' || $dbType === 'mysql') {
            $connections['database'] = $this->checkMariadb() + ['type' => 'MariaDB'];
        }

        // Redis connection
        if (getenv('ENABLE_REDIS') !== 'false') {
            $connections['redis'] = $this->checkRedisDetailed();
        }

        return $connections;
    }

    /**
     * Check Redis with detailed info (PING test, version)
     *
     * @return array<string, mixed>
     */
    private function checkRedisDetailed(): array
    {
        if (!extension_loaded('redis')) {
            return [
                'connected' => false,
                'type'      => 'Redis',
                'error'     => 'Redis PHP extension not installed',
            ];
        }

        try {
            $redis    = new Redis();
            $redisUrl = getenv('REDIS_URL') ?: 'redis://redis:6379';
            $useTls   = str_starts_with($redisUrl, 'rediss://');

            $parsedUrl = parse_url($redisUrl);
            $host      = $parsedUrl['host'] ?? 'redis';
            $port      = $parsedUrl['port'] ?? 6379;

            if ($useTls) {
                $connected = $this->safeRedisConnect($redis, $host, $port, TlsConfig::getRedisStreamOptions());
            } else {
                $connected = $this->safeRedisConnect($redis, $host, $port);
            }

            if (!$connected) {
                return [
                    'connected' => false,
                    'type'      => 'Redis',
                    'host'      => $host,
                    'port'      => $port,
                    'error'     => 'Connection failed',
                ];
            }

            $pong    = $redis->ping();
            $info    = $redis->info('SERVER');
            $version = $info['redis_version'] ?? 'Unknown';
            $redis->close();

            return [
                'connected' => $pong === true || $pong === '+PONG',
                'type'      => 'Redis',
                'host'      => $host,
                'port'      => $port,
                'version'   => $version,
                'tls'       => $useTls,
            ];
        } catch (Exception $exception) {
            return [
                'connected' => false,
                'type'      => 'Redis',
                'error'     => $exception->getMessage(),
            ];
        }
    }

    /**
     * Check PostgreSQL connection
     *
     * @return array<string, mixed>
     */
    private function checkPostgresql(): array
    {
        return $this->checkDatabaseVersion();
    }

    /**
     * Check MariaDB connection
     *
     * @return array<string, mixed>
     */
    private function checkMariadb(): array
    {
        return $this->checkDatabaseVersion(includeSslOptions: true);
    }

    /**
     * Check database connection and retrieve version
     *
     * @return array<string, mixed>
     * @SuppressWarnings("PHPMD.BooleanArgumentFlag")
     */
    private function checkDatabaseVersion(bool $includeSslOptions = false): array
    {
        $config = new DatabaseConfig();

        $options = [
            PDO::ATTR_TIMEOUT => 3,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ];

        if ($includeSslOptions) {
            $options += $config->getPdoSslOptions();
        }

        try {
            $pdo = new PDO($config->getDsn(), $config->user, $config->password, $options);

            $stmt = $pdo->query('SELECT version()');
            if ($stmt === false) {
                return [
                    'connected' => true,
                    'host'      => $config->host,
                    'port'      => $config->port,
                    'database'  => $config->name,
                    'version'   => 'Unknown',
                ];
            }

            $version = $stmt->fetchColumn();
            if ($version === false) {
                $version = 'Unknown';
            }

            return [
                'connected' => true,
                'host'      => $config->host,
                'port'      => $config->port,
                'database'  => $config->name,
                'version'   => $version,
            ];
        } catch (PDOException $pdoException) {
            return [
                'connected' => false,
                'host'      => $config->host,
                'port'      => $config->port,
                'error'     => $pdoException->getMessage(),
            ];
        }
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
     */
    private function checkNode(): array
    {
        return $this->checkSocketConnection('node-backend', 3000, useRunningKey: true);
    }

    /**
     * Check Nginx status
     *
     * @return array<string, mixed>
     */
    private function checkNginx(): array
    {
        return $this->checkSocketConnection('nginx', 8080, useRunningKey: true);
    }

    /**
     * Get SSL certificate information
          *
     * @return array<string, mixed>
     */
    public function getSslInfo(): array
    {
        $certPath = __DIR__ . '/../../../../docker/certs/cert.crt';

        if (!file_exists($certPath) || !is_file($certPath)) {
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

    /**
     * Check if a service is reachable via socket connection
     *
     * @return array<string, mixed>
     * @SuppressWarnings("PHPMD.BooleanArgumentFlag")
     */
    private function checkSocketConnection(
        string $host,
        int $port,
        bool $useTls = false,
        bool $useRunningKey = false
    ): array {
        $errno     = null;
        $errstr    = null;
        $socket    = $this->safeSocketOpen($host, $port, $errno, $errstr);
        $statusKey = $useRunningKey ? 'running' : 'connected';

        if ($socket) {
            fclose($socket);
            $result = [$statusKey => true, 'host' => $host, 'port' => $port];
            if ($useTls) {
                $result['tls'] = true;
            }

            return $result;
        }

        return [$statusKey => false, 'error' => $errstr ?: 'Service not reachable'];
    }

    /**
     * Safe wrapper for fsockopen that suppresses warnings without @ operator
     *
     * Uses set_error_handler to suppress DNS resolution and connection warnings
     * that occur when services are unavailable (expected in health checks).
     *
     * @return resource|false Socket resource on success, false on failure
     */
    /** @noinspection PhpMixedReturnTypeCanBeReducedInspection */
    private function safeSocketOpen(string $host, int $port, ?int &$errno, ?string &$errstr): mixed
    {
        set_error_handler(static fn (): bool => true);

        try {
            return fsockopen($host, $port, $errno, $errstr, 1);
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Safe wrapper for Redis::connect that suppresses warnings without @ operator
     *
     * @param array<string, mixed>|null $context TLS stream context options
     */
    private function safeRedisConnect(Redis $redis, string $host, int $port, ?array $context = null): bool
    {
        set_error_handler(static fn (): bool => true);

        try {
            if ($context !== null) {
                return $redis->connect($host, $port, 2, '', 0, 0, $context);
            }

            return $redis->connect($host, $port, 2);
        } finally {
            restore_error_handler();
        }
    }
}
