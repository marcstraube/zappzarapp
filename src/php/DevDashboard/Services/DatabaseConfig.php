<?php

declare(strict_types=1);

namespace DevDashboard\Services;

use RuntimeException;

/**
 * Database Configuration
 *
 * Handles database configuration from environment variables
 */
class DatabaseConfig
{
    private readonly string $type;

    private readonly string $name;

    private readonly string $projectName;

    public function __construct()
    {
        $this->type        = $this->loadDatabaseType();
        $this->name        = $this->loadDatabaseName();
        $this->projectName = $this->loadProjectName();
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getProjectName(): string
    {
        return $this->projectName;
    }

    /**
     * Load database type from environment
     */
    private function loadDatabaseType(): string
    {
        $dbType = getenv('DB_TYPE');

        if ($dbType === false || $dbType === '') {
            return 'postgres'; // Default to postgres for backward compatibility
        }

        $dbType = strtolower($dbType);

        if (!in_array($dbType, ['postgres', 'mariadb'], true)) {
            throw new RuntimeException(sprintf("Invalid DB_TYPE: %s. Must be 'postgres' or 'mariadb'.", $dbType));
        }

        return $dbType;
    }

    /**
     * Load database name from environment
     */
    private function loadDatabaseName(): string
    {
        // Try DB_NAME first (generic)
        $dbName = getenv('DB_NAME');
        if ($dbName !== false && $dbName !== '') {
            return $dbName;
        }

        // Fall back to database-specific env vars
        if ($this->type === 'postgres') {
            $dbName = getenv('POSTGRES_DB');
        } elseif ($this->type === 'mariadb') {
            $dbName = getenv('MARIADB_DATABASE');
        }

        if ($dbName === false || $dbName === '') {
            return 'app'; // Default fallback
        }

        return $dbName;
    }

    /**
     * Load project name from environment
     */
    private function loadProjectName(): string
    {
        $projectName = getenv('COMPOSE_PROJECT_NAME');

        return $projectName !== false && $projectName !== '' ? $projectName : 'app';
    }
}
