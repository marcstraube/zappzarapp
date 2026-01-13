<?php

declare(strict_types=1);

namespace App\Infrastructure;

/**
 * Database Configuration Helper (12-Factor App compliant)
 *
 * Provides database connection configuration following 12-Factor App principles.
 * DATABASE_URL takes precedence over individual variables for PaaS compatibility.
 */
final class DatabaseConfig
{
    private const DEFAULT_POSTGRES_PORT      = 5432;
    private const DEFAULT_MYSQL_PORT         = 3306;
    private const DEFAULT_INTERNAL_CERT_PATH = '/etc/ssl/db-certs/cert.crt';
    private const SYSTEM_CA_BUNDLE_PATH      = '/etc/ssl/certs/ca-certificates.crt';

    private string $type;
    private string $host;
    private int $port;
    private string $name;
    private string $user;
    private string $password;
    private string $sslCa;
    private bool $sslVerify;

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

    public function getType(): string
    {
        return $this->type;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getUser(): string
    {
        return $this->user;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function isPostgres(): bool
    {
        return $this->type === 'postgres' || $this->type === 'postgresql';
    }

    public function isMariaDb(): bool
    {
        return $this->type === 'mariadb' || $this->type === 'mysql';
    }

    public function getSslCa(): string
    {
        return $this->sslCa;
    }

    public function getSslVerify(): bool
    {
        return $this->sslVerify;
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
            \PDO::MYSQL_ATTR_SSL_CA                 => $this->sslCa,
            \PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => $this->sslVerify,
        ];
    }

    /**
     * Parse DATABASE_URL into components.
     */
    private function parseUrl(string $url): void
    {
        $parts = parse_url($url);

        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            throw new \InvalidArgumentException('Invalid DATABASE_URL format');
        }

        $this->type     = $parts['scheme'] === 'postgresql' ? 'postgres' : $parts['scheme'];
        $this->host     = $parts['host'];
        $this->port     = $parts['port'] ?? $this->getDefaultPort($this->type);
        $this->name     = ltrim($parts['path'] ?? '/app', '/');
        $this->user     = !empty($parts['user']) ? $parts['user'] : 'app';
        $this->password = isset($parts['pass']) ? rawurldecode($parts['pass']) : '';
    }

    /**
     * Load configuration from individual environment variables.
     */
    private function loadFromIndividualVars(): void
    {
        $this->type     = $this->getEnv('DB_TYPE', 'postgres');
        $this->host     = $this->getEnv('DB_HOST', $this->type === 'postgres' ? 'postgres' : 'mariadb');
        $this->port     = (int) $this->getEnv('DB_PORT', (string) $this->getDefaultPort($this->type));
        $this->name     = $this->getEnv('DB_NAME', 'app');
        $this->user     = $this->getEnv('DB_USER', 'app');
        $this->password = $this->getEnv('DB_PASSWORD', 'secret');
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
