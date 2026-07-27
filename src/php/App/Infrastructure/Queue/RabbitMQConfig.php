<?php

declare(strict_types=1);

namespace App\Infrastructure\Queue;

use App\Infrastructure\Config\CredentialLoader;

/**
 * RabbitMQ Configuration
 *
 * Handles parsing of connection configuration from:
 * - Direct URL parameter
 * - RABBITMQ_URL environment variable
 * - Individual environment variables (RABBITMQ_HOST, RABBITMQ_PORT, etc.)
 * - Docker secrets for credentials (via CredentialLoader)
 *
 * Priority order for credentials:
 * 1. Credentials in URL (if provided)
 * 2. Docker secrets (/run/secrets/rabbitmq_user, etc.)
 * 3. Environment variables (RABBITMQ_USER, etc.)
 * 4. Insecure development default: guest/guest
 *
 * @package Infrastructure\Queue
 *
 * @SuppressWarnings("PHPMD.Superglobals") Required for environment variable access
 */
final readonly class RabbitMQConfig
{
    private CredentialLoader $credentials;

    public string $host;

    public int $port;

    public string $user;

    public string $password;

    public string $vhost;

    public bool $useTls;

    public function __construct(?string $url = null, ?CredentialLoader $credentials = null)
    {
        $this->credentials = $credentials ?? CredentialLoader::docker();

        $config = $this->parseConfig($url);

        $this->host     = $config['host'];
        $this->port     = $config['port'];
        $this->user     = $config['user'];
        $this->password = $config['password'];
        $this->vhost    = $config['vhost'];
        $this->useTls   = $config['useTls'];
    }

    /**
     * Parse connection configuration from URL or environment
     *
     * @return array{host: string, port: int, user: string, password: string, vhost: string, useTls: bool}
     */
    private function parseConfig(?string $url): array
    {
        $url ??= $this->getEnvUrl();

        if ($url !== null) {
            return $this->parseFromUrl($url);
        }

        return $this->parseFromEnvironment();
    }

    /**
     * Get URL from environment if set
     */
    private function getEnvUrl(): ?string
    {
        $envUrl = $_ENV['RABBITMQ_URL'] ?? getenv('RABBITMQ_URL');

        if (is_string($envUrl) && $envUrl !== '') {
            return $envUrl;
        }

        return null;
    }

    /**
     * Parse configuration from AMQP URL
     *
     * @return array{host: string, port: int, user: string, password: string, vhost: string, useTls: bool}
     */
    private function parseFromUrl(string $url): array
    {
        $parsed            = $this->parseUrl($url);
        $urlHasCredentials = $parsed['user'] !== null;

        $user     = $parsed['user'] ?? 'guest';
        $password = $parsed['password'] ?? 'guest';

        // Only load from secrets/environment if URL didn't provide credentials
        if (!$urlHasCredentials) {
            $user     = $this->credentials->loadWithInsecureDefault('rabbitmq_user', ['RABBITMQ_USER'], $user);
            $password = $this->credentials->loadWithInsecureDefault('rabbitmq_password', ['RABBITMQ_PASSWORD'], $password);
        }

        return [
            'host'     => $parsed['host'],
            'port'     => $parsed['port'],
            'user'     => $user,
            'password' => $password,
            'vhost'    => $parsed['vhost'],
            'useTls'   => $parsed['useTls'],
        ];
    }

    /**
     * Parse configuration from individual environment variables
     *
     * @return array{host: string, port: int, user: string, password: string, vhost: string, useTls: bool}
     */
    private function parseFromEnvironment(): array
    {
        return [
            'host'     => $this->getEnvString('RABBITMQ_HOST', 'rabbitmq'),
            'port'     => $this->getEnvInt('RABBITMQ_PORT', 5672),
            'user'     => $this->credentials->loadWithInsecureDefault('rabbitmq_user', ['RABBITMQ_USER'], 'guest'),
            'password' => $this->credentials->loadWithInsecureDefault('rabbitmq_password', ['RABBITMQ_PASSWORD'], 'guest'),
            'vhost'    => $this->getEnvString('RABBITMQ_VHOST', '/'),
            'useTls'   => false,
        ];
    }

    /**
     * Parse AMQP URL into components
     *
     * @return array{host: string, port: int, user: string|null, password: string|null, vhost: string, useTls: bool}
     */
    private function parseUrl(string $url): array
    {
        $useTls        = str_starts_with($url, 'amqps://');
        $normalizedUrl = preg_replace('/^amqps?:/', 'http:', $url);
        $parts         = parse_url($normalizedUrl ?? $url);

        return [
            'host'     => $parts['host'] ?? 'localhost',
            'port'     => $parts['port'] ?? ($useTls ? 5671 : 5672),
            'user'     => isset($parts['user']) ? urldecode($parts['user']) : null,
            'password' => isset($parts['pass']) ? urldecode($parts['pass']) : null,
            'vhost'    => $this->extractVhost($parts),
            'useTls'   => $useTls,
        ];
    }

    /**
     * Extract vhost from parsed URL parts
     *
     * @param array<string, mixed>|false $parts
     */
    private function extractVhost(array|false $parts): string
    {
        if (!is_array($parts) || !isset($parts['path'])) {
            return '/';
        }

        $path = $parts['path'];

        if (!is_string($path) || $path === '/') {
            return '/';
        }

        $vhost = urldecode(ltrim($path, '/'));

        return $vhost !== '' ? $vhost : '/';
    }

    /**
     * Get string value from environment
     */
    private function getEnvString(string $name, string $default): string
    {
        $value = $_ENV[$name] ?? getenv($name);

        if (is_string($value) && $value !== '') {
            return $value;
        }

        return $default;
    }

    /**
     * Get integer value from environment
     */
    private function getEnvInt(string $name, int $default): int
    {
        $value = $_ENV[$name] ?? getenv($name);

        if (is_string($value) && $value !== '') {
            return (int) $value;
        }

        return $default;
    }

}
