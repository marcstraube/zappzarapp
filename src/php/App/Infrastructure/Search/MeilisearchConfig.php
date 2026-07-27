<?php

declare(strict_types=1);

namespace App\Infrastructure\Search;

use App\Infrastructure\Config\CredentialLoader;

/**
 * Meilisearch Configuration
 *
 * Handles parsing of connection configuration from:
 * - Direct URL parameter
 * - MEILISEARCH_URL environment variable
 * - Individual environment variables (MEILISEARCH_HOST, MEILISEARCH_PORT)
 * - Docker secrets for master key (via CredentialLoader)
 *
 * Priority order for URL:
 * 1. URL parameter (constructor)
 * 2. MEILISEARCH_URL environment variable
 * 3. MEILISEARCH_HOST + MEILISEARCH_PORT environment variables
 * 4. Default: https://meilisearch:7700
 *
 * Priority order for master key:
 * 1. Docker secret (/run/secrets/meilisearch_master_key.txt)
 * 2. MEILISEARCH_MASTER_KEY environment variable
 * 3. Empty string (development without authentication)
 *
 * @package Infrastructure\Search
 *
 * @SuppressWarnings("PHPMD.Superglobals") Required for environment variable access
 */
final readonly class MeilisearchConfig
{
    private CredentialLoader $credentials;

    public string $url;

    public string $masterKey;

    public bool $useTls;

    public function __construct(?string $url = null, ?CredentialLoader $credentials = null)
    {
        $this->credentials = $credentials ?? CredentialLoader::docker();

        $config = $this->parseConfig($url);

        $this->url       = $config['url'];
        $this->masterKey = $config['masterKey'];
        $this->useTls    = $config['useTls'];
    }

    /**
     * Parse connection configuration from URL or environment
     *
     * @return array{url: string, masterKey: string, useTls: bool}
     */
    private function parseConfig(?string $url): array
    {
        $url ??= $this->getEnvUrl();

        if ($url === null) {
            $url = $this->buildUrlFromEnvironment();
        }

        $useTls    = str_starts_with($url, 'https://');
        $masterKey = $this->credentials->tryLoad('meilisearch_master_key', 'MEILISEARCH_MASTER_KEY') ?? '';

        return [
            'url'       => $url,
            'masterKey' => $masterKey,
            'useTls'    => $useTls,
        ];
    }

    /**
     * Get URL from MEILISEARCH_URL environment variable if set
     */
    private function getEnvUrl(): ?string
    {
        $envUrl = $_ENV['MEILISEARCH_URL'] ?? getenv('MEILISEARCH_URL');

        if (is_string($envUrl) && $envUrl !== '') {
            return $envUrl;
        }

        return null;
    }

    /**
     * Build URL from MEILISEARCH_HOST and MEILISEARCH_PORT environment variables
     */
    private function buildUrlFromEnvironment(): string
    {
        $host = $this->getEnvString('MEILISEARCH_HOST', 'meilisearch');
        $port = $this->getEnvInt('MEILISEARCH_PORT', 7700);

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

}
