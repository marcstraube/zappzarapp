<?php

declare(strict_types=1);

namespace App\Infrastructure;

use PDO;
use PDOException;
use Redis;
use Exception;

/**
 * HealthCheck - Centralized service health status checker
 *
 * Checks the health of all infrastructure services:
 * - PHP-FPM
 * - Node.js Backend (if ENABLE_NODE=true)
 * - Redis (if ENABLE_REDIS=true)
 * - Database (if DB_TYPE is set)
 * - Optional services (Mercure, Meilisearch, Elasticsearch, Mailpit, MinIO, RabbitMQ)
 *
 * @SuppressWarnings("PHPMD.ExcessiveClassComplexity")
 * @SuppressWarnings("PHPMD.ExcessiveClassLength")
 * @SuppressWarnings("PHPMD.TooManyMethods")
 * @SuppressWarnings("PHPMD.UnusedPrivateMethod") check* methods called dynamically via $this->$method()
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
            'ENABLE_MINIO'         => $this->parseBool($_ENV['ENABLE_MINIO'] ?? getenv('ENABLE_MINIO') ?: 'false'),
            'ENABLE_RABBITMQ'      => $this->parseBool($_ENV['ENABLE_RABBITMQ'] ?? getenv('ENABLE_RABBITMQ') ?: 'false'),
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

        // Check optional services
        $optionalServices = [
            'ENABLE_MERCURE'       => 'checkMercure',
            'ENABLE_MEILISEARCH'   => 'checkMeilisearch',
            'ENABLE_ELASTICSEARCH' => 'checkElasticsearch',
            'ENABLE_MAILPIT'       => 'checkMailpit',
            'ENABLE_MINIO'         => 'checkMinio',
            'ENABLE_RABBITMQ'      => 'checkRabbitmq',
        ];

        foreach ($optionalServices as $envKey => $method) {
            if ($this->env[$envKey]) {
                $this->$method();
            }
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
            $redis = new Redis();

            // Parse REDIS_URL to determine TLS mode
            $redisUrl = $_ENV['REDIS_URL'] ?? getenv('REDIS_URL') ?: 'rediss://redis:6379';
            $useTls   = str_starts_with((string) $redisUrl, 'rediss://');

            // Parse host and port from URL
            $parsedUrl = parse_url((string) $redisUrl);
            $host      = $parsedUrl['host'] ?? 'redis';
            $port      = $parsedUrl['port'] ?? 6379;

            if ($useTls) {
                // TLS connection with self-signed certificate support
                $connected = $redis->connect($host, $port, 2, '', 0, 0, [
                    'stream' => [
                        'verify_peer'       => false,
                        'verify_peer_name'  => false,
                        'allow_self_signed' => true,
                    ],
                ]);
            } else {
                $connected = $redis->connect($host, $port, 2);
            }

            if (!$connected) {
                $this->status['services']['redis'] = [
                    'status'  => 'error',
                    'message' => 'Could not connect to Redis',
                    'enabled' => true,
                    'tls'     => $useTls,
                ];
                return;
            }

            $pong = $redis->ping();
            $info = $redis->info('SERVER');

            $this->status['services']['redis'] = [
                'status'  => ($pong === '+PONG' || $pong === true) ? 'ok' : 'error',
                'version' => $info['redis_version'] ?? 'unknown',
                'enabled' => true,
                'tls'     => $useTls,
            ];

            $redis->close();
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
        $dbType = $config->getType();

        // Check if required extension is loaded
        $requiredExt = $config->isPostgres() ? 'pdo_pgsql' : 'pdo_mysql';
        if (!extension_loaded($requiredExt)) {
            $this->status['services']['database'] = [
                'status'  => 'error',
                'type'    => $dbType,
                'message' => "PDO {$dbType} extension not installed",
                'enabled' => true,
            ];

            return;
        }

        try {
            $options = [
                PDO::ATTR_TIMEOUT => 2,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ] + $config->getPdoSslOptions();

            $pdo = new PDO($config->getDsn(), $config->getUser(), $config->getPassword(), $options);

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
     * Get overall status (ok, degraded, error)
     */
    public function getOverallStatus(): string
    {
        if ($this->status === []) {
            $this->checkAll();
        }

        return $this->status['overall_status'];
    }

    /**
     * Get all services status
     *
     * @return array<string, mixed>
     */
    public function getServices(): array
    {
        if ($this->status === []) {
            $this->checkAll();
        }

        return $this->status['services'];
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
     * Check MinIO connection
     */
    private function checkMinio(): void
    {
        try {
            $url     = 'http://minio:9000/minio/health/live';
            $context = stream_context_create([
                'http' => [
                    'timeout'       => 2,
                    'ignore_errors' => true,
                ],
            ]);

            $response = $this->fetchUrl($url, $context);

            // MinIO returns empty body with 200 OK on success
            if ($response !== false) {
                $this->status['services']['minio'] = [
                    'status'  => 'ok',
                    'enabled' => true,
                ];
                return;
            }
        } catch (Exception) {
            // Fall through to error
        }

        $this->status['services']['minio'] = [
            'status'  => 'error',
            'message' => 'MinIO not reachable',
            'enabled' => true,
        ];
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
     * Check TCP connection to a service
     *
     * @return array<string, mixed>
     * @SuppressWarnings("PHPMD.ErrorControlOperator")
     * @SuppressWarnings("PHPMD.UnusedLocalVariable") $errno required by fsockopen signature
     */
    private function checkTcpConnection(string $host, int $port): array
    {
        $socket = @fsockopen($host, $port, $_errno, $errstr, 2);

        if ($socket) {
            fclose($socket);
            return ['connected' => true];
        }

        return ['connected' => false, 'error' => $errstr ?: 'Connection failed'];
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
