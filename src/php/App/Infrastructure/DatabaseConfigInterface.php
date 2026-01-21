<?php

declare(strict_types=1);

namespace App\Infrastructure;

/**
 * Database Configuration Interface
 *
 * Defines the contract for database configuration providers.
 * Enables dependency injection and testability of database-dependent code.
 *
 * Uses PHP 8.4 property hooks for simple value access.
 *
 * @package Infrastructure
 */
interface DatabaseConfigInterface
{
    /** Database type (postgres, mysql, mariadb) */
    public string $type { get; }

    /** Database host */
    public string $host { get; }

    /** Database port */
    public int $port { get; }

    /** Database name */
    public string $name { get; }

    /** Database user */
    public string $user { get; }

    /** Database password */
    public string $password { get; }

    /** SSL CA certificate path */
    public string $sslCa { get; }

    /** SSL verification setting */
    public bool $sslVerify { get; }

    /**
     * Get DSN for PDO connection
     */
    public function getDsn(): string;

    /**
     * Get PostgreSQL sslmode based on SSL configuration
     *
     * @return string Empty string for default, or explicit mode (require, verify-full)
     */
    public function getPostgresSslMode(): string;

    /**
     * Get DATABASE_URL format (for frameworks/ORMs that expect it)
     */
    public function getUrl(): string;

    /**
     * Check if connected to PostgreSQL database
     */
    public function isPostgres(): bool;

    /**
     * Check if connected to MariaDB/MySQL database
     */
    public function isMariaDb(): bool;

    /**
     * Check if SSL is configured and available
     */
    public function hasSsl(): bool;

    /**
     * Get PDO options for SSL connection (MariaDB/MySQL only)
     *
     * @return array<int, mixed>
     */
    public function getPdoSslOptions(): array;
}
