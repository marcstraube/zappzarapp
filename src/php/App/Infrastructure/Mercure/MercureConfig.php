<?php

declare(strict_types=1);

namespace App\Infrastructure\Mercure;

use App\Infrastructure\Config\CredentialLoader;

/**
 * Mercure Configuration
 *
 * Resolves the hub publish endpoint and the publisher JWT key from:
 * - Direct constructor parameter (URL)
 * - MERCURE_URL / MERCURE_PUBLISH_URL environment variables
 * - Docker secret for the JWT key (via CredentialLoader)
 *
 * Priority order for the URL:
 * 1. URL parameter (constructor)
 * 2. MERCURE_URL environment variable
 * 3. MERCURE_PUBLISH_URL environment variable
 * 4. Default: https://mercure/.well-known/mercure
 *
 * Priority order for the JWT key:
 * 1. Docker secret (/run/secrets/mercure_jwt_secret.txt)
 * 2. MERCURE_JWT_SECRET environment variable
 * 3. MERCURE_PUBLISHER_JWT_KEY environment variable
 * 4. Empty string (publishing then fails fast with a MercureException)
 *
 * @package Infrastructure\Mercure
 *
 * @SuppressWarnings("PHPMD.Superglobals") Required for environment variable access
 */
final readonly class MercureConfig
{
    /** Default internal hub publish endpoint (Caddy on :443, zero-trust TLS) */
    private const string DEFAULT_URL = 'https://mercure/.well-known/mercure';

    private CredentialLoader $credentials;

    public string $url;

    public string $jwtKey;

    public bool $useTls;

    public function __construct(?string $url = null, ?CredentialLoader $credentials = null)
    {
        $this->credentials = $credentials ?? CredentialLoader::docker();

        $url ??= $this->getEnvUrl() ?? self::DEFAULT_URL;

        $this->url    = $url;
        $this->useTls = str_starts_with($url, 'https://');
        $this->jwtKey = $this->credentials->tryLoad(
            'mercure_jwt_secret',
            'MERCURE_JWT_SECRET',
            'MERCURE_PUBLISHER_JWT_KEY',
        ) ?? '';
    }

    /**
     * Get the publish URL from MERCURE_URL or MERCURE_PUBLISH_URL if set
     */
    private function getEnvUrl(): ?string
    {
        foreach (['MERCURE_URL', 'MERCURE_PUBLISH_URL'] as $name) {
            $value = $_ENV[$name] ?? getenv($name);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }
}
