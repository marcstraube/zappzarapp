<?php

declare(strict_types=1);

namespace App\Infrastructure;

/**
 * TLS verification configuration based on environment
 * Development: disabled (self-signed certs)
 * Production: enabled with internal CA (trusted certs)
 *
 * CA certificate path comes from Task 06 Certificate Architecture:
 * - Mounted via compose.yaml from docker/certs/internal/ca.crt
 * - Default: /etc/ssl/certs/internal-ca.crt
 */
final class TlsConfig
{
    /** Default CA path (from Task 06 Certificate Architecture) */
    private const string DEFAULT_CA_PATH = '/etc/ssl/certs/internal-ca.crt';

    public static function shouldVerify(): bool
    {
        // Explicit override takes precedence
        $override = getenv('TLS_VERIFY_INTERNAL');
        if ($override !== false) {
            return $override === 'true';
        }

        // Default: verify in production only
        // Uses ENV variable (project standard), not APP_ENV
        $env = $_ENV['ENV'] ?? getenv('ENV') ?: 'production';
        return $env === 'production';
    }

    /**
     * Get CA certificate path
     */
    public static function getCaPath(): string
    {
        return $_ENV['TLS_CA_PATH'] ?? getenv('TLS_CA_PATH') ?: self::DEFAULT_CA_PATH;
    }

    /**
     * Get CA certificate path if verification is enabled and file exists
     */
    public static function getCaFile(): ?string
    {
        if (!self::shouldVerify()) {
            return null;
        }

        $caPath = self::getCaPath();
        return file_exists($caPath) ? $caPath : null;
    }

    /**
     * Get SSL context options for stream_context_create()
     *
     * @return array{verify_peer: bool, verify_peer_name: bool, allow_self_signed: bool, cafile?: string}
     */
    public static function getSslContextOptions(): array
    {
        $verify  = self::shouldVerify();
        $options = [
            'verify_peer'       => $verify,
            'verify_peer_name'  => $verify,
            'allow_self_signed' => !$verify,
        ];

        $caFile = self::getCaFile();
        if ($caFile !== null) {
            $options['cafile'] = $caFile;
        }

        return $options;
    }

    /**
     * Get Redis stream context options
     *
     * @return array{stream: array{verify_peer: bool, verify_peer_name: bool, allow_self_signed: bool, cafile?: string}}
     */
    public static function getRedisStreamOptions(): array
    {
        return ['stream' => self::getSslContextOptions()];
    }
}
