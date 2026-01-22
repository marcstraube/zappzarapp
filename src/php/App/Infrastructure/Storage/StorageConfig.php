<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

/**
 * S3-Compatible Storage Configuration
 *
 * Handles parsing of connection configuration from:
 * - Direct parameters (constructor)
 * - Environment variables
 * - Docker secrets for credentials
 *
 * Priority order for endpoint:
 * 1. Endpoint parameter (constructor)
 * 2. S3_ENDPOINT_URL environment variable
 * 3. SEAWEEDFS_ENDPOINT environment variable
 * 4. Default: http://seaweedfs:8333
 *
 * Priority order for credentials:
 * 1. Docker secrets (/run/secrets/seaweedfs_access_key.txt, etc.)
 * 2. S3_ACCESS_KEY / S3_SECRET_KEY environment variables
 * 3. SEAWEEDFS_S3_ACCESS_KEY / SEAWEEDFS_S3_SECRET_KEY environment variables
 * 4. Default: admin/admin (development)
 *
 * @package Infrastructure\Storage
 *
 * @SuppressWarnings("PHPMD.Superglobals") Required for environment variable access
 */
final readonly class StorageConfig
{
    public string $endpoint;

    public string $accessKey;

    public string $secretKey;

    public string $region;

    public string $bucket;

    public bool $useTls;

    public bool $verifySsl;

    public bool $usePathStyle;

    public function __construct(
        ?string $endpoint = null,
        ?string $accessKey = null,
        ?string $secretKey = null,
        ?string $region = null,
        ?string $bucket = null
    ) {
        $config = $this->parseConfig($endpoint, $accessKey, $secretKey, $region, $bucket);

        $this->endpoint     = $config['endpoint'];
        $this->accessKey    = $config['accessKey'];
        $this->secretKey    = $config['secretKey'];
        $this->region       = $config['region'];
        $this->bucket       = $config['bucket'];
        $this->useTls       = $config['useTls'];
        $this->verifySsl    = $config['verifySsl'];
        $this->usePathStyle = $config['usePathStyle'];
    }

    /**
     * Parse connection configuration
     *
     * @return array{endpoint: string, accessKey: string, secretKey: string, region: string, bucket: string, useTls: bool, verifySsl: bool, usePathStyle: bool}
     */
    private function parseConfig(
        ?string $endpoint,
        ?string $accessKey,
        ?string $secretKey,
        ?string $region,
        ?string $bucket
    ): array {
        $endpoint ??= $this->getEnvString('S3_ENDPOINT_URL', null)
            ?? $this->getEnvString('SEAWEEDFS_ENDPOINT', 'http://seaweedfs:8333');

        $accessKey ??= $this->loadCredential(
            'seaweedfs_access_key',
            ['S3_ACCESS_KEY', 'SEAWEEDFS_S3_ACCESS_KEY'],
            'admin'
        );

        $secretKey ??= $this->loadCredential(
            'seaweedfs_secret_key',
            ['S3_SECRET_KEY', 'SEAWEEDFS_S3_SECRET_KEY'],
            'admin'
        );

        $region ??= $this->getEnvString('S3_REGION', null)
            ?? $this->getEnvString('SEAWEEDFS_REGION', 'us-east-1');

        $bucket ??= $this->getEnvString('S3_BUCKET', null)
            ?? $this->getEnvString('SEAWEEDFS_BUCKET', 'default');

        // Ensure non-null values with defaults
        $endpoint ??= 'http://seaweedfs:8333';
        $region ??= 'us-east-1';
        $bucket ??= 'default';

        $useTls    = str_starts_with($endpoint, 'https://');
        $verifySsl = $this->getEnvBool('S3_VERIFY_SSL', false);

        // SeaweedFS requires path-style URLs
        $usePathStyle = $this->getEnvBool('S3_USE_PATH_STYLE', true);

        return [
            'endpoint'     => rtrim($endpoint, '/'),
            'accessKey'    => $accessKey,
            'secretKey'    => $secretKey,
            'region'       => $region,
            'bucket'       => $bucket,
            'useTls'       => $useTls,
            'verifySsl'    => $verifySsl,
            'usePathStyle' => $usePathStyle,
        ];
    }

    /**
     * Get string value from environment
     */
    private function getEnvString(string $name, ?string $default): ?string
    {
        $value = $_ENV[$name] ?? getenv($name);

        if (is_string($value) && $value !== '') {
            return $value;
        }

        return $default;
    }

    /**
     * Get boolean value from environment
     */
    private function getEnvBool(string $name, bool $default): bool
    {
        $value = $_ENV[$name] ?? getenv($name);

        if (is_string($value) && $value !== '') {
            return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
        }

        return $default;
    }

    /**
     * Load credential from Docker secret or environment variables
     *
     * @param string $secretName Name of the Docker secret file
     * @param array<string> $envNames Environment variable names to try (in order)
     * @param string $default Default value
     *
     * @noinspection PhpSameParameterValueInspection - Default kept for API flexibility
     */
    private function loadCredential(string $secretName, array $envNames, string $default): string
    {
        // Try Docker secret with .txt extension
        $value = $this->readSecret(sprintf('/run/secrets/%s.txt', $secretName));
        if ($value !== null) {
            return $value;
        }

        // Try Docker secret without extension
        $value = $this->readSecret('/run/secrets/' . $secretName);
        if ($value !== null) {
            return $value;
        }

        // Try environment variables in order
        foreach ($envNames as $envName) {
            $envValue = $this->getEnvString($envName, null);
            if ($envValue !== null) {
                return $envValue;
            }
        }

        return $default;
    }

    /**
     * Read a secret file if it exists
     */
    private function readSecret(string $path): ?string
    {
        if (!file_exists($path)) {
            return null;
        }

        $value = file_get_contents($path);

        if ($value === false) {
            return null;
        }

        return trim($value);
    }
}
