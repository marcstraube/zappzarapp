<?php

declare(strict_types=1);

namespace DevDashboard\Services;

use RuntimeException;

/**
 * Database Command Builder
 *
 * Builds Docker commands for database backup and restore operations
 */
class DatabaseCommandBuilder
{
    public function __construct(
        private readonly string $dbType,
    ) {
        if (!in_array($this->dbType, ['postgres', 'mariadb'], true)) {
            throw new RuntimeException(sprintf("Invalid DB_TYPE: %s. Must be 'postgres' or 'mariadb'.", $this->dbType));
        }
    }

    /**
     * Build backup command based on database type
     */
    public function buildBackupCommand(string $backupFile, BackupEncryption $encryption): string
    {
        return match ($this->dbType) {
            'postgres' => $this->buildPostgresBackupCommand($backupFile, $encryption),
            'mariadb'  => $this->buildMariadbBackupCommand($backupFile, $encryption),
        };
    }

    /**
     * Build restore command based on database type
     */
    public function buildRestoreCommand(string $backupFile, BackupEncryption $encryption): string
    {
        return match ($this->dbType) {
            'postgres' => $this->buildPostgresRestoreCommand($backupFile, $encryption),
            'mariadb'  => $this->buildMariadbRestoreCommand($backupFile, $encryption),
        };
    }

    /**
     * Build pg_dump command for PostgreSQL backup
     */
    private function buildPostgresBackupCommand(string $backupFile, BackupEncryption $encryption): string
    {
        $projectName   = getenv('COMPOSE_PROJECT_NAME') ?: 'app';
        $containerName = $projectName . '-postgres';
        $dbUser        = getenv('DB_USER') ?: getenv('POSTGRES_USER') ?: 'app';
        $dbName        = getenv('DB_NAME') ?: getenv('POSTGRES_DB') ?: 'app';

        if ($encryption->isEnabled()) {
            // Encrypted backup: pg_dump | gzip | openssl enc
            return sprintf(
                'docker exec %s pg_dump -U %s %s | gzip -9 | openssl enc -aes-256-cbc -salt -pbkdf2 -pass pass:%s > %s 2>&1',
                escapeshellarg($containerName),
                escapeshellarg($dbUser),
                escapeshellarg($dbName),
                escapeshellarg((string) $encryption->getKey()),
                escapeshellarg($backupFile)
            );
        }

        // Unencrypted backup
        return sprintf(
            'docker exec %s pg_dump -U %s %s > %s 2>&1',
            escapeshellarg($containerName),
            escapeshellarg($dbUser),
            escapeshellarg($dbName),
            escapeshellarg($backupFile),
        );
    }

    /**
     * Build mariadb-dump command for MariaDB backup
     */
    private function buildMariadbBackupCommand(string $backupFile, BackupEncryption $encryption): string
    {
        $containerName = $this->getMariadbContainerName();
        $dbUser        = $this->getMariadbUser();
        $dbPassword    = $this->getMariadbPassword();
        $dbName        = $this->getMariadbDatabase();

        $baseCommand = sprintf(
            'docker exec %s mariadb-dump -u %s -p%s %s --single-transaction --routines --triggers',
            escapeshellarg($containerName),
            escapeshellarg($dbUser),
            escapeshellarg($dbPassword),
            escapeshellarg($dbName)
        );

        if ($encryption->isEnabled()) {
            return sprintf(
                '%s | gzip -9 | openssl enc -aes-256-cbc -salt -pbkdf2 -pass pass:%s > %s 2>&1',
                $baseCommand,
                escapeshellarg((string) $encryption->getKey()),
                escapeshellarg($backupFile)
            );
        }

        return sprintf('%s > %s 2>&1', $baseCommand, escapeshellarg($backupFile));
    }

    /**
     * Build psql command for PostgreSQL restore
     */
    private function buildPostgresRestoreCommand(string $backupFile, BackupEncryption $encryption): string
    {
        $projectName   = getenv('COMPOSE_PROJECT_NAME') ?: 'app';
        $containerName = $projectName . '-postgres';
        $dbUser        = getenv('DB_USER') ?: getenv('POSTGRES_USER') ?: 'app';
        $dbName        = getenv('DB_NAME') ?: getenv('POSTGRES_DB') ?: 'app';

        if ($encryption->isEnabled()) {
            // Decrypt and restore: openssl dec | gunzip | psql
            return sprintf(
                'openssl enc -aes-256-cbc -d -pbkdf2 -pass pass:%s -in %s | gunzip | docker exec -i %s psql -U %s %s 2>&1',
                escapeshellarg((string) $encryption->getKey()),
                escapeshellarg($backupFile),
                escapeshellarg($containerName),
                escapeshellarg($dbUser),
                escapeshellarg($dbName)
            );
        }

        // Unencrypted restore
        return sprintf(
            'docker exec -i %s psql -U %s %s < %s 2>&1',
            escapeshellarg($containerName),
            escapeshellarg($dbUser),
            escapeshellarg($dbName),
            escapeshellarg($backupFile),
        );
    }

    /**
     * Build mariadb command for MariaDB restore
     */
    private function buildMariadbRestoreCommand(string $backupFile, BackupEncryption $encryption): string
    {
        $containerName = $this->getMariadbContainerName();
        $dbUser        = $this->getMariadbUser();
        $dbPassword    = $this->getMariadbPassword();
        $dbName        = $this->getMariadbDatabase();

        $restoreTarget = sprintf(
            'docker exec -i %s mariadb -u %s -p%s %s',
            escapeshellarg($containerName),
            escapeshellarg($dbUser),
            escapeshellarg($dbPassword),
            escapeshellarg($dbName)
        );

        if ($encryption->isEnabled()) {
            return sprintf(
                'openssl enc -aes-256-cbc -d -pbkdf2 -pass pass:%s -in %s | gunzip | %s 2>&1',
                escapeshellarg((string) $encryption->getKey()),
                escapeshellarg($backupFile),
                $restoreTarget
            );
        }

        return sprintf('%s < %s 2>&1', $restoreTarget, escapeshellarg($backupFile));
    }

    /**
     * Get MariaDB container name
     */
    private function getMariadbContainerName(): string
    {
        $projectName = getenv('COMPOSE_PROJECT_NAME') ?: 'app';

        return $projectName . '-mariadb';
    }

    /**
     * Get MariaDB user
     */
    private function getMariadbUser(): string
    {
        return getenv('DB_USER') ?: getenv('MARIADB_USER') ?: 'app';
    }

    /**
     * Get MariaDB password
     */
    private function getMariadbPassword(): string
    {
        return getenv('DB_PASSWORD') ?: getenv('MARIADB_PASSWORD') ?: '';
    }

    /**
     * Get MariaDB database name
     */
    private function getMariadbDatabase(): string
    {
        return getenv('DB_NAME') ?: getenv('MARIADB_DATABASE') ?: 'app';
    }
}
