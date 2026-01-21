<?php

declare(strict_types=1);

namespace App\Infrastructure;

use InvalidArgumentException;
use PDO;
use RuntimeException;

/**
 * Database Configuration Helper (12-Factor App compliant)
 *
 * Provides database connection configuration following 12-Factor App principles.
 * DATABASE_URL takes precedence over individual variables for PaaS compatibility.
 *
 * Supports Docker Secrets via _FILE environment variables:
 * - DB_PASSWORD_FILE: Path to file containing database password (preferred)
 * - DB_PASSWORD: Fallback for external databases or CI environments
 *
 * Security: When not using DATABASE_URL, a password must be explicitly configured.
 * No hardcoded defaults are used to prevent accidental security misconfigurations.
 *
 * Uses PHP 8.4 property hooks with asymmetric visibility for clean API.
 *
 * @throws RuntimeException If password is not configured when using individual variables
 *
 * @SuppressWarnings("PHPMD.ExcessiveClassComplexity")
 * @noinspection PhpPublicPropertyModifierCanBeOmittedInspection - PHP 8.4 asymmetric visibility
 */
final class DatabaseConfig implements DatabaseConfigInterface
{
    private const int DEFAULT_POSTGRES_PORT = 5432;

    private const int DEFAULT_MYSQL_PORT = 3306;

    private const string DEFAULT_INTERNAL_CERT_PATH = '/etc/ssl/db-certs/cert.crt';

    private const string SYSTEM_CA_BUNDLE_PATH = '/etc/ssl/certs/ca-certificates.crt';

    public private(set) string $type;

    public private(set) string $host;

    public private(set) int $port;

    public private(set) string $name;

    public private(set) string $user;

    public private(set) string $password;

    public private(set) string $sslCa;

    public private(set) bool $sslVerify;

    public function __construct()
    {
        $databaseUrl = $this->getEnv('DATABASE_URL');

        if ($databaseUrl !== '') {
            $this->parseUrl($databaseUrl);
        } else {
            $this->loadFromIndividualVars();
        }

        $this->loadSslConfig();
    }

    /**
     * Get DSN for PDO connection.
     */
    public function getDsn(): string
    {
        if ($this->type === 'postgres' || $this->type === 'postgresql') {
            $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s', $this->host, $this->port, $this->name);

            // Add sslmode for PostgreSQL when SSL is configured
            $sslmode = $this->getPostgresSslMode();
            if ($sslmode !== '') {
                $dsn .= ';sslmode=' . $sslmode;
            }

            return $dsn;
        }

        return sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $this->host, $this->port, $this->name);
    }

    /**
     * Get PostgreSQL sslmode based on SSL configuration.
     *
     * @return string Empty string for default (prefer), or explicit mode
     */
    public function getPostgresSslMode(): string
    {
        // No SSL CA configured - use default behavior (prefer)
        if ($this->sslCa === '' || !$this->hasSsl()) {
            return '';
        }

        // SSL CA configured - enforce SSL with appropriate verification
        return $this->sslVerify ? 'verify-full' : 'require';
    }

    /**
     * Get DATABASE_URL format (for frameworks/ORMs that expect it).
     */
    public function getUrl(): string
    {
        $driver          = $this->type === 'postgres' ? 'postgresql' : 'mysql';
        $encodedPassword = rawurlencode($this->password);

        return sprintf(
            '%s://%s:%s@%s:%d/%s',
            $driver,
            $this->user,
            $encodedPassword,
            $this->host,
            $this->port,
            $this->name
        );
    }

    public function isPostgres(): bool
    {
        return $this->type === 'postgres' || $this->type === 'postgresql';
    }

    public function isMariaDb(): bool
    {
        return $this->type === 'mariadb' || $this->type === 'mysql';
    }

    /**
     * Check if SSL is configured and available.
     */
    public function hasSsl(): bool
    {
        return $this->sslCa !== '' && file_exists($this->sslCa);
    }

    /**
     * Get PDO options for SSL connection (MariaDB/MySQL only).
     * PostgreSQL handles SSL automatically via libpq.
     *
     * @return array<int, mixed>
     */
    public function getPdoSslOptions(): array
    {
        if (!$this->isMariaDb() || !$this->hasSsl()) {
            return [];
        }

        return [
            PDO::MYSQL_ATTR_SSL_CA                 => $this->sslCa,
            PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => $this->sslVerify,
        ];
    }

    /**
     * Parse DATABASE_URL into components.
     */
    private function parseUrl(string $url): void
    {
        $parts = parse_url($url);

        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            throw new InvalidArgumentException('Invalid DATABASE_URL format');
        }

        $this->type     = $parts['scheme'] === 'postgresql' ? 'postgres' : $parts['scheme'];
        $this->host     = $parts['host'];
        $this->port     = $parts['port'] ?? $this->getDefaultPort($this->type);
        $this->name     = ltrim($parts['path'] ?? '/app', '/');
        $this->user     = empty($parts['user']) ? 'app' : $parts['user'];
        $this->password = isset($parts['pass']) ? rawurldecode($parts['pass']) : '';
    }

    /**
     * Load configuration from individual environment variables.
     * Uses _FILE variant for password to support Docker Secrets.
     *
     * @throws RuntimeException If no database password is configured
     */
    private function loadFromIndividualVars(): void
    {
        $this->type     = $this->getEnv('DB_TYPE', 'postgres');
        $this->host     = $this->getEnv('DB_HOST', $this->type === 'postgres' ? 'postgres' : 'mariadb');
        $this->port     = (int) $this->getEnv('DB_PORT', (string) $this->getDefaultPort($this->type));
        $this->name     = $this->getEnv('DB_NAME', 'app');
        $this->user     = $this->getEnv('DB_USER', 'app');
        $this->password = $this->getEnvOrFile('DB_PASSWORD');

        if ($this->password === '') {
            throw new RuntimeException(
                'Database password not configured. Set DB_PASSWORD_FILE (recommended) or DB_PASSWORD environment variable. '
                . 'Run "make setup" to generate secrets, or set DB_PASSWORD in .env for external databases.'
            );
        }
    }

    private function getDefaultPort(string $type): int
    {
        return $type === 'postgres' || $type === 'postgresql'
            ? self::DEFAULT_POSTGRES_PORT
            : self::DEFAULT_MYSQL_PORT;
    }

    private function getEnv(string $name, string $default = ''): string
    {
        $value = getenv($name);

        return $value !== false && $value !== '' ? $value : $default;
    }

    /**
     * Get environment variable with _FILE support for Docker Secrets.
     * Checks for {NAME}_FILE first, reads file content if exists,
     * otherwise falls back to regular environment variable.
     */
    /** @noinspection PhpSameParameterValueInspection */
    private function getEnvOrFile(string $name, string $default = ''): string
    {
        // Check for _FILE variant first (Docker Secrets pattern)
        $fileVar  = $name . '_FILE';
        $filePath = $this->getEnv($fileVar);

        if ($filePath !== '' && file_exists($filePath) && is_readable($filePath)) {
            $content = file_get_contents($filePath);

            return $content !== false ? trim($content) : $default;
        }

        return $this->getEnv($name, $default);
    }

    /**
     * Load SSL configuration from environment variables.
     * Falls back to internal certificate for MariaDB if no explicit config is set.
     */
    private function loadSslConfig(): void
    {
        $sslCa = $this->getEnv('DB_SSL_CA');

        // Handle special "system" value - use system CA bundle for cloud databases
        if (strtolower($sslCa) === 'system') {
            $sslCa = file_exists(self::SYSTEM_CA_BUNDLE_PATH) ? self::SYSTEM_CA_BUNDLE_PATH : '';
        }
        // For MariaDB: fall back to internal cert if no explicit CA is configured
        elseif ($sslCa === '' && $this->isMariaDb() && file_exists(self::DEFAULT_INTERNAL_CERT_PATH)) {
            $sslCa = self::DEFAULT_INTERNAL_CERT_PATH;
        }

        $this->sslCa     = $sslCa;
        $this->sslVerify = $this->getEnv('DB_SSL_VERIFY', 'false') === 'true';
    }
}
