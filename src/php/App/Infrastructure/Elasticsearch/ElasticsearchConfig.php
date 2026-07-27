<?php

declare(strict_types=1);

namespace App\Infrastructure\Elasticsearch;

use App\Infrastructure\Config\CredentialLoader;

/**
 * Elasticsearch Configuration
 *
 * Handles parsing of connection configuration from:
 * - Direct URL parameter
 * - ELASTICSEARCH_URL environment variable
 * - Individual environment variables (ELASTICSEARCH_HOST, ELASTICSEARCH_PORT)
 * - Docker secrets for credentials (via CredentialLoader)
 *
 * Priority order for URL:
 * 1. URL parameter (constructor)
 * 2. ELASTICSEARCH_URL environment variable
 * 3. ELASTICSEARCH_HOST + ELASTICSEARCH_PORT environment variables
 * 4. Default: https://elasticsearch:9200
 *
 * Priority order for API key:
 * 1. Docker secret (/run/secrets/elasticsearch_api_key.txt)
 * 2. ELASTICSEARCH_API_KEY environment variable
 * 3. Empty string (development without authentication)
 *
 * @package Infrastructure\Elasticsearch
 *
 * @SuppressWarnings("PHPMD.Superglobals") Required for environment variable access
 */
final readonly class ElasticsearchConfig
{
    private CredentialLoader $credentials;

    public string $url;

    public string $apiKey;

    public bool $useTls;

    public bool $verifySsl;

    public function __construct(?string $url = null, ?CredentialLoader $credentials = null)
    {
        $this->credentials = $credentials ?? CredentialLoader::docker();

        $config = $this->parseConfig($url);

        $this->url       = $config['url'];
        $this->apiKey    = $config['apiKey'];
        $this->useTls    = $config['useTls'];
        $this->verifySsl = $config['verifySsl'];
    }

    /**
     * Parse connection configuration from URL or environment
     *
     * @return array{url: string, apiKey: string, useTls: bool, verifySsl: bool}
     */
    private function parseConfig(?string $url): array
    {
        $url ??= $this->getEnvUrl();

        if ($url === null) {
            $url = $this->buildUrlFromEnvironment();
        }

        $useTls    = str_starts_with($url, 'https://');
        $apiKey    = $this->credentials->tryLoad('elasticsearch_api_key', 'ELASTICSEARCH_API_KEY') ?? '';
        $verifySsl = $this->getEnvBool('ELASTICSEARCH_VERIFY_SSL', false);

        return [
            'url'       => $url,
            'apiKey'    => $apiKey,
            'useTls'    => $useTls,
            'verifySsl' => $verifySsl,
        ];
    }

    /**
     * Get URL from ELASTICSEARCH_URL environment variable if set
     */
    private function getEnvUrl(): ?string
    {
        $envUrl = $_ENV['ELASTICSEARCH_URL'] ?? getenv('ELASTICSEARCH_URL');

        if (is_string($envUrl) && $envUrl !== '') {
            return $envUrl;
        }

        return null;
    }

    /**
     * Build URL from ELASTICSEARCH_HOST and ELASTICSEARCH_PORT environment variables
     */
    private function buildUrlFromEnvironment(): string
    {
        $host = $this->getEnvString('ELASTICSEARCH_HOST', 'elasticsearch');
        $port = $this->getEnvInt('ELASTICSEARCH_PORT', 9200);

        // Default to HTTPS in production environment
        $protocol = 'https';

        return sprintf('%s://%s:%d', $protocol, $host, $port);
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
     *
     * @noinspection PhpSameParameterValueInspection Config class - called with fixed defaults
     */
    private function getEnvInt(string $name, int $default): int
    {
        $value = $_ENV[$name] ?? getenv($name);

        if (is_string($value) && $value !== '') {
            return (int) $value;
        }

        return $default;
    }

    /**
     * Get boolean value from environment
     *
     * @noinspection PhpSameParameterValueInspection Config class - called with fixed defaults
     */
    private function getEnvBool(string $name, bool $default): bool
    {
        $value = $_ENV[$name] ?? getenv($name);

        if (is_string($value) && $value !== '') {
            return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
        }

        return $default;
    }

}
