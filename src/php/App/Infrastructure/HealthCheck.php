<?php

declare(strict_types=1);

namespace App\Infrastructure;

use ErrorException;
use Exception;
use PDO;
use Redis;

/**
 * HealthCheck - Centralized service health status checker
 *
 * Checks the health of all infrastructure services:
 * - PHP-FPM
 * - Node.js Backend (if ENABLE_NODE=true)
 * - Redis (if ENABLE_REDIS=true)
 * - Database (if DB_TYPE is set)
 * - Optional services (Mercure, Meilisearch, Elasticsearch, Mailpit, SeaweedFS, RabbitMQ)
 *
 * @SuppressWarnings("PHPMD.ExcessiveClassComplexity")
 * @SuppressWarnings("PHPMD.ExcessiveClassLength")
 * @SuppressWarnings("PHPMD.TooManyMethods")
 */
class HealthCheck
{
    /** @var array<string, mixed> */
    private array $env;

    /** @var array<string, mixed> */
    private array $status = [];

    /**
     * @SuppressWarnings("PHPMD.NPathComplexity")
     */
    public function __construct()
    {
        // Load environment variables
        $this->env = [
            'ENV'                  => $_ENV['ENV'] ?? getenv('ENV') ?: 'production',
            'ENABLE_PHP'           => $this->parseBool($_ENV['ENABLE_PHP'] ?? getenv('ENABLE_PHP') ?: 'true'),
            'ENABLE_NODE'          => $this->parseBool($_ENV['ENABLE_NODE'] ?? getenv('ENABLE_NODE') ?: 'false'),
            'ENABLE_DATABASE'      => $this->parseBool($_ENV['ENABLE_DATABASE'] ?? getenv('ENABLE_DATABASE') ?: 'false'),
            'ENABLE_REDIS'         => $this->parseBool($_ENV['ENABLE_REDIS'] ?? getenv('ENABLE_REDIS') ?: 'false'),
            'ENABLE_MERCURE'       => $this->parseBool($_ENV['ENABLE_MERCURE'] ?? getenv('ENABLE_MERCURE') ?: 'false'),
            'ENABLE_MEILISEARCH'   => $this->parseBool($_ENV['ENABLE_MEILISEARCH'] ?? getenv('ENABLE_MEILISEARCH') ?: 'false'),
            'ENABLE_ELASTICSEARCH' => $this->parseBool($_ENV['ENABLE_ELASTICSEARCH'] ?? getenv('ENABLE_ELASTICSEARCH') ?: 'false'),
            'ENABLE_MAILPIT'       => $this->parseBool($_ENV['ENABLE_MAILPIT'] ?? getenv('ENABLE_MAILPIT') ?: 'false'),
            'ENABLE_RABBITMQ'      => $this->parseBool($_ENV['ENABLE_RABBITMQ'] ?? getenv('ENABLE_RABBITMQ') ?: 'false'),
            'ENABLE_SEAWEEDFS'     => $this->parseBool($_ENV['ENABLE_SEAWEEDFS'] ?? getenv('ENABLE_SEAWEEDFS') ?: 'false'),
            'DB_TYPE'              => $_ENV['DB_TYPE'] ?? getenv('DB_TYPE') ?: null,
            'NODE_MODE'            => $_ENV['NODE_MODE'] ?? getenv('NODE_MODE') ?: 'none',
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
     *
     * @return array<string, mixed>
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

        $this->checkCoreServices();
        $this->checkOptionalServices();
        $this->updateOverallStatus();

        return $this->status;
    }

    /**
     * Check core services (Node, Redis, Database)
     */
    private function checkCoreServices(): void
    {
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
        if ($this->env['ENABLE_DATABASE']) {
            $this->checkDatabase();
        } else {
            $this->status['services']['database'] = [
                'status'  => 'disabled',
                'enabled' => false,
            ];
        }
    }

    /**
     * Check optional services (alphabetically sorted)
     */
    private function checkOptionalServices(): void
    {
        if ($this->env['ENABLE_ELASTICSEARCH']) {
            $this->checkElasticsearch();
        }

        if ($this->env['ENABLE_MAILPIT']) {
            $this->checkMailpit();
        }

        if ($this->env['ENABLE_MEILISEARCH']) {
            $this->checkMeilisearch();
        }

        if ($this->env['ENABLE_MERCURE']) {
            $this->checkMercure();
        }

        if ($this->env['ENABLE_RABBITMQ']) {
            $this->checkRabbitmq();
        }

        if ($this->env['ENABLE_SEAWEEDFS']) {
            $this->checkSeaweedfs();
        }
    }

    /**
     * Update overall status based on individual service statuses
     */
    private function updateOverallStatus(): void
    {
        foreach ($this->status['services'] as $service) {
            if (isset($service['status']) && $service['status'] === 'error') {
                $this->status['overall_status'] = 'degraded';
                break;
            }
        }
    }

    /**
     * Check Node.js Backend health
     */
    private function checkNodeBackend(): void
    {
        try {
            // Use internal Docker network hostname with TLS
            $url     = 'https://node-backend:3000/health';
            $context = stream_context_create([
                'http' => [
                    'timeout'       => 2,
                    'ignore_errors' => true,
                ],
                'ssl' => TlsConfig::getSslContextOptions(),
            ]);

            $response = $this->fetchUrl($url, $context);

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
        } catch (Exception $exception) {
            $this->status['services']['node-backend'] = [
                'status'  => 'error',
                'message' => $exception->getMessage(),
                'enabled' => true,
                'mode'    => $this->env['NODE_MODE'],
            ];
        }
    }

    /**
     * Check Redis connection (with TLS support)
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
            $conn = $this->createRedisConnection();

            if ($conn['redis'] === null) {
                $this->status['services']['redis'] = [
                    'status'  => 'error',
                    'message' => $conn['error'],
                    'enabled' => true,
                    'tls'     => $conn['useTls'],
                ];
                return;
            }

            $redis = $conn['redis'];
            $pong  = $redis->ping();
            $info  = $redis->info('SERVER');
            $redis->close();

            $this->status['services']['redis'] = [
                'status'  => ($pong === '+PONG' || $pong === true) ? 'ok' : 'error',
                'version' => $info['redis_version'] ?? 'unknown',
                'enabled' => true,
                'tls'     => $conn['useTls'],
            ];
        } catch (Exception $exception) {
            $this->status['services']['redis'] = [
                'status'  => 'error',
                'message' => $exception->getMessage(),
                'enabled' => true,
            ];
        }
    }

    /**
     * Check Database connection using DatabaseConfig for consistent SSL handling.
     */
    private function checkDatabase(): void
    {
        $config = new DatabaseConfig();
        $dbType = $config->type;

        // Check if required extension is loaded
        $requiredExt = $config->isPostgres() ? 'pdo_pgsql' : 'pdo_mysql';
        if (!extension_loaded($requiredExt)) {
            $this->status['services']['database'] = [
                'status'  => 'error',
                'type'    => $dbType,
                'message' => sprintf('PDO %s extension not installed', $dbType),
                'enabled' => true,
            ];

            return;
        }

        try {
            $options = [
                PDO::ATTR_TIMEOUT => 2,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ] + $config->getPdoSslOptions();

            $pdo = new PDO($config->getDsn(), $config->user, $config->password, $options);

            $query   = $config->isPostgres() ? 'SELECT version()' : 'SELECT VERSION()';
            $stmt    = $pdo->query($query);
            $version = $stmt !== false ? $stmt->fetchColumn() : 'unknown';

            $this->status['services']['database'] = [
                'status'  => 'ok',
                'type'    => $dbType,
                'version' => $version,
                'enabled' => true,
            ];
        } catch (Exception $exception) {
            $this->status['services']['database'] = [
                'status'  => 'error',
                'type'    => $dbType,
                'message' => $exception->getMessage(),
                'enabled' => true,
            ];
        }
    }

    /**
     * Get environment info
     *
     * @return array<string, mixed>
     */
    public function getEnvironment(): array
    {
        return $this->env;
    }

    /**
     * Readiness check - checks enabled services with latency measurement
     *
     * @return array<string, mixed>
     */
    public function checkReadiness(): array
    {
        $checks        = [];
        $overallStatus = 'ok';

        // Check Database with latency
        if ($this->env['ENABLE_DATABASE']) {
            $checks['database'] = $this->checkDatabaseWithLatency();
            if ($checks['database']['status'] !== 'ok') {
                $overallStatus = 'degraded';
            }
        } else {
            $checks['database'] = ['status' => 'disabled'];
        }

        // Check Redis with latency
        if ($this->env['ENABLE_REDIS']) {
            $checks['redis'] = $this->checkRedisWithLatency();
            if ($checks['redis']['status'] !== 'ok') {
                $overallStatus = 'degraded';
            }
        } else {
            $checks['redis'] = ['status' => 'disabled'];
        }

        // Check Node Backend with latency
        if ($this->isNodeBackendEnabled()) {
            $checks['node-backend'] = $this->checkNodeBackendWithLatency();
            if ($checks['node-backend']['status'] !== 'ok') {
                $overallStatus = 'degraded';
            }
        } else {
            $checks['node-backend'] = ['status' => 'disabled'];
        }

        // Check Node Frontend with latency (if framework mode)
        if ($this->isNodeFrontendEnabled()) {
            $checks['node-frontend'] = $this->checkNodeFrontendWithLatency();
            if ($checks['node-frontend']['status'] !== 'ok') {
                $overallStatus = 'degraded';
            }
        } else {
            $checks['node-frontend'] = ['status' => 'disabled'];
        }

        return [
            'status'      => $overallStatus,
            'timestamp'   => date('c'),
            'service'     => 'php-backend',
            'environment' => $this->env['ENV'],
            'uptime'      => $this->getUptime(),
            'checks'      => $checks,
        ];
    }

    /**
     * Check if Node backend is enabled based on NODE_MODE
     */
    private function isNodeBackendEnabled(): bool
    {
        if (!$this->env['ENABLE_NODE']) {
            return false;
        }

        $mode = $this->env['NODE_MODE'];

        return in_array($mode, ['api', 'backend', 'assets-api', 'framework-api'], true);
    }

    /**
     * Check if Node frontend is enabled based on NODE_MODE
     */
    private function isNodeFrontendEnabled(): bool
    {
        if (!$this->env['ENABLE_NODE']) {
            return false;
        }

        $mode = $this->env['NODE_MODE'];

        return str_contains((string) $mode, 'framework');
    }

    /**
     * Get system uptime in seconds
     */
    private function getUptime(): int
    {
        $uptime = (int) (file_get_contents('/proc/uptime') ?: '0');

        return $uptime > 0 ? $uptime : (int) (time() - $_SERVER['REQUEST_TIME']);
    }

    /**
     * Check database connection with latency measurement
     *
     * @return array<string, mixed>
     */
    private function checkDatabaseWithLatency(): array
    {
        $config = new DatabaseConfig();
        $dbType = $config->type;

        $requiredExt = $config->isPostgres() ? 'pdo_pgsql' : 'pdo_mysql';
        if (!extension_loaded($requiredExt)) {
            return [
                'status'  => 'unhealthy',
                'type'    => $dbType,
                'message' => sprintf('PDO %s extension not installed', $dbType),
            ];
        }

        $start = hrtime(true);

        try {
            $options = [
                PDO::ATTR_TIMEOUT => 2,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ] + $config->getPdoSslOptions();

            $pdo = new PDO($config->getDsn(), $config->user, $config->password, $options);

            $query = 'SELECT 1';
            $pdo->query($query);

            $latencyMs = (int) ((hrtime(true) - $start) / 1_000_000);

            return [
                'status'     => 'ok',
                'type'       => $dbType,
                'latency_ms' => $latencyMs,
            ];
        } catch (Exception $exception) {
            return [
                'status'  => 'unhealthy',
                'type'    => $dbType,
                'message' => $exception->getMessage(),
            ];
        }
    }

    /**
     * Check Redis connection with latency measurement
     *
     * @return array<string, mixed>
     */
    private function checkRedisWithLatency(): array
    {
        if (!extension_loaded('redis')) {
            return [
                'status'  => 'unhealthy',
                'message' => 'Redis PHP extension not installed',
            ];
        }

        $start = hrtime(true);

        try {
            $conn = $this->createRedisConnection();

            if ($conn['redis'] === null) {
                return [
                    'status'  => 'unhealthy',
                    'message' => $conn['error'],
                ];
            }

            $conn['redis']->ping();
            $conn['redis']->close();

            $latencyMs = (int) ((hrtime(true) - $start) / 1_000_000);

            return [
                'status'     => 'ok',
                'latency_ms' => $latencyMs,
            ];
        } catch (Exception $exception) {
            return [
                'status'  => 'unhealthy',
                'message' => $exception->getMessage(),
            ];
        }
    }

    /**
     * Check Node.js Backend with latency measurement
     *
     * @return array<string, mixed>
     */
    private function checkNodeBackendWithLatency(): array
    {
        $start = hrtime(true);

        try {
            $url     = 'https://node-backend:3000/health';
            $context = stream_context_create([
                'http' => [
                    'timeout'       => 2,
                    'ignore_errors' => true,
                ],
                'ssl' => TlsConfig::getSslContextOptions(),
            ]);

            $response = $this->fetchUrl($url, $context);

            $latencyMs = (int) ((hrtime(true) - $start) / 1_000_000);

            if ($response === false) {
                return [
                    'status'  => 'unhealthy',
                    'message' => 'Node backend not reachable',
                ];
            }

            return [
                'status'     => 'ok',
                'latency_ms' => $latencyMs,
            ];
        } catch (Exception $exception) {
            return [
                'status'  => 'unhealthy',
                'message' => $exception->getMessage(),
            ];
        }
    }

    /**
     * Check Node.js Frontend with latency measurement
     *
     * @return array<string, mixed>
     */
    private function checkNodeFrontendWithLatency(): array
    {
        $start = hrtime(true);

        try {
            $url     = 'https://node:3001/';
            $context = stream_context_create([
                'http' => [
                    'timeout'       => 2,
                    'ignore_errors' => true,
                    'method'        => 'HEAD',
                ],
                'ssl' => TlsConfig::getSslContextOptions(),
            ]);

            $response = $this->fetchUrl($url, $context);

            $latencyMs = (int) ((hrtime(true) - $start) / 1_000_000);

            if ($response === false) {
                return [
                    'status'  => 'unhealthy',
                    'message' => 'Node frontend not reachable',
                ];
            }

            return [
                'status'     => 'ok',
                'latency_ms' => $latencyMs,
            ];
        } catch (Exception $exception) {
            return [
                'status'  => 'unhealthy',
                'message' => $exception->getMessage(),
            ];
        }
    }

    /**
     * Check Mercure connection
     */
    private function checkMercure(): void
    {
        $result = $this->checkTcpConnection('mercure', 80);

        $this->status['services']['mercure'] = [
            'status'  => $result['connected'] ? 'ok' : 'error',
            'enabled' => true,
        ] + ($result['connected'] ? [] : ['message' => $result['error'] ?? 'Connection failed']);
    }

    /**
     * Check Meilisearch connection
     */
    private function checkMeilisearch(): void
    {
        try {
            $url     = 'http://meilisearch:7700/health';
            $context = stream_context_create([
                'http' => [
                    'timeout'       => 2,
                    'ignore_errors' => true,
                ],
            ]);

            $response = $this->fetchUrl($url, $context);

            if ($response !== false) {
                $data                                    = json_decode($response, true);
                $this->status['services']['meilisearch'] = [
                    'status'  => ($data['status'] ?? '') === 'available' ? 'ok' : 'error',
                    'enabled' => true,
                ];
                return;
            }
        } catch (Exception) {
            // Fall through to error
        }

        $this->status['services']['meilisearch'] = [
            'status'  => 'error',
            'message' => 'Meilisearch not reachable',
            'enabled' => true,
        ];
    }

    /**
     * Check Elasticsearch connection
     */
    private function checkElasticsearch(): void
    {
        try {
            $url     = 'http://elasticsearch:9200/_cluster/health';
            $context = stream_context_create([
                'http' => [
                    'timeout'       => 2,
                    'ignore_errors' => true,
                ],
            ]);

            $response = $this->fetchUrl($url, $context);

            if ($response !== false) {
                $data                                      = json_decode($response, true);
                $status                                    = $data['status'] ?? 'unknown';
                $this->status['services']['elasticsearch'] = [
                    'status'         => in_array($status, ['green', 'yellow'], true) ? 'ok' : 'error',
                    'cluster_status' => $status,
                    'enabled'        => true,
                ];
                return;
            }
        } catch (Exception) {
            // Fall through to error
        }

        $this->status['services']['elasticsearch'] = [
            'status'  => 'error',
            'message' => 'Elasticsearch not reachable',
            'enabled' => true,
        ];
    }

    /**
     * Check Mailpit connection
     */
    private function checkMailpit(): void
    {
        $result = $this->checkTcpConnection('mailpit', 8025);

        $this->status['services']['mailpit'] = [
            'status'  => $result['connected'] ? 'ok' : 'error',
            'enabled' => true,
        ] + ($result['connected'] ? [] : ['message' => $result['error'] ?? 'Connection failed']);
    }

    /**
     * Check RabbitMQ connection
     */
    private function checkRabbitmq(): void
    {
        $result = $this->checkTcpConnection('rabbitmq', 5672);

        $this->status['services']['rabbitmq'] = [
            'status'  => $result['connected'] ? 'ok' : 'error',
            'enabled' => true,
        ] + ($result['connected'] ? [] : ['message' => $result['error'] ?? 'Connection failed']);
    }

    /**
     * Check SeaweedFS connection (master cluster status)
     */
    private function checkSeaweedfs(): void
    {
        try {
            // Use master cluster status endpoint (returns 200)
            $url     = 'http://seaweedfs:9333/cluster/status';
            $context = stream_context_create([
                'http' => [
                    'timeout'       => 2,
                    'ignore_errors' => true,
                ],
            ]);

            $response = $this->fetchUrl($url, $context);

            if ($response !== false) {
                $data                                  = json_decode($response, true);
                $this->status['services']['seaweedfs'] = [
                    'status'  => isset($data['IsLeader']) ? 'ok' : 'error',
                    'enabled' => true,
                ];
                return;
            }
        } catch (Exception) {
            // Fall through to error
        }

        $this->status['services']['seaweedfs'] = [
            'status'  => 'error',
            'message' => 'SeaweedFS not reachable',
            'enabled' => true,
        ];
    }

    /**
     * Create Redis connection with TLS support
     *
     * @return array{redis: Redis|null, host: string, port: int, useTls: bool, error: string|null}
     * @throws ErrorException If connection fails and custom error handler catches warnings
     */
    private function createRedisConnection(): array
    {
        $redisUrl  = $_ENV['REDIS_URL'] ?? getenv('REDIS_URL') ?: 'rediss://redis:6379';
        $useTls    = str_starts_with((string) $redisUrl, 'rediss://');
        $parsedUrl = parse_url((string) $redisUrl);
        $host      = $parsedUrl['host'] ?? 'redis';
        $port      = $parsedUrl['port'] ?? 6379;

        $redis     = new Redis();
        $connected = false;
        $errorMsg  = null;

        // Set custom error handler to catch warnings and convert to exceptions
        set_error_handler(static function (int $errno, string $errstr): bool {
            throw new ErrorException($errstr, 0, $errno);
        });

        try {
            if ($useTls) {
                $connected = $redis->connect($host, $port, 2, '', 0, 0, TlsConfig::getRedisStreamOptions());
            } else {
                $connected = $redis->connect($host, $port, 2);
            }
        }
        // ErrorException is thrown by custom error handler above when redis->connect() emits a warning
        /** @noinspection PhpRedundantCatchClauseInspection */
        catch (ErrorException $errorException) {
            $errorMsg = 'Could not connect to Redis: ' . $errorException->getMessage();
        } finally {
            // Always restore previous error handler
            restore_error_handler();
        }

        if (!$connected) {
            return [
                'redis'  => null,
                'host'   => $host,
                'port'   => $port,
                'useTls' => $useTls,
                'error'  => $errorMsg ?? 'Could not connect to Redis',
            ];
        }

        return [
            'redis'  => $redis,
            'host'   => $host,
            'port'   => $port,
            'useTls' => $useTls,
            'error'  => null,
        ];
    }

    /**
     * Check TCP connection to a service
     *
     * @return array<string, mixed>
     */
    private function checkTcpConnection(string $host, int $port): array
    {
        $errno  = 0;
        $errstr = '';
        $socket = $this->safeSocketOpen($host, $port, $errno, $errstr, 2);

        if ($socket) {
            fclose($socket);
            return ['connected' => true];
        }

        $errorMsg = $errstr ?: 'Connection failed';
        if ($errno > 0) {
            $errorMsg .= sprintf(' (errno: %d)', $errno);
        }

        return ['connected' => false, 'error' => $errorMsg];
    }

    /**
     * Safe wrapper for fsockopen that suppresses warnings without @ operator
     *
     * @param int<0, max> $timeout Connection timeout in seconds
     * @return resource|false Socket resource on success, false on failure
     * @noinspection PhpMixedReturnTypeCanBeReducedInspection - 'resource' is not a native PHP type
     */
    private function safeSocketOpen(string $host, int $port, ?int &$errno, ?string &$errstr, int $timeout = 1): mixed
    {
        set_error_handler(static fn (): bool => true);

        try {
            return fsockopen($host, $port, $errno, $errstr, $timeout);
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Fetch URL content with error suppression
     *
     * Uses set_error_handler instead of @ operator to satisfy PHPMD.
     * Errors are intentionally ignored as we check the return value.
     *
     * @param resource|null $context Stream context from stream_context_create()
     */
    private function fetchUrl(string $url, mixed $context = null): string|false
    {
        set_error_handler(static fn (): bool => true);

        try {
            return file_get_contents($url, false, $context);
        } finally {
            restore_error_handler();
        }
    }
}
