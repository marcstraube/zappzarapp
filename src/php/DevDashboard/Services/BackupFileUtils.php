<?php

declare(strict_types=1);

namespace DevDashboard\Services;

use Exception;

/**
 * Backup File Utilities
 *
 * Handles file operations and formatting for database backups
 */
class BackupFileUtils
{
    /**
     * Parse backup filename to extract metadata
     *
     * Supports formats:
     * - New encrypted: {dbtype}_{dbname}_{YYYYMMDD_HHMMSS}.sql.gz.enc
     * - New unencrypted: {dbtype}_{dbname}_{YYYYMMDD_HHMMSS}.sql
     * - Legacy: backup_{YYYY-MM-DD_HH-MM-SS}.sql
     *
     * @return array{timestamp: int, dbType?: string, dbName?: string, encrypted: bool}|null
     */
    public function parseFilename(string $filename): ?array
    {
        // Try new format first: {dbtype}_{dbname}_{YYYYMMDD_HHMMSS}.sql[.gz.enc]
        if (preg_match('/^(postgres|mariadb)_(\w+)_(\d{8}_\d{6})\.sql(?:\.gz\.enc)?$/', $filename, $matches)) {
            try {
                // Convert "20260126_121905" to "2026-01-26 12:19:05"
                $dateTime = preg_replace('/^(\d{4})(\d{2})(\d{2})_(\d{2})(\d{2})(\d{2})$/', '$1-$2-$3 $4:$5:$6', $matches[3]);

                if ($dateTime === null) {
                    return null;
                }

                $timestamp = strtotime($dateTime);

                if ($timestamp === false) {
                    return null;
                }

                return [
                    'timestamp' => $timestamp,
                    'dbType'    => $matches[1],
                    'dbName'    => $matches[2],
                    'encrypted' => $this->isEncrypted($filename),
                ];
            } catch (Exception) {
                return null;
            }
        }

        // Try legacy format: backup_{YYYY-MM-DD_HH-MM-SS}.sql
        if (preg_match('/^backup_(\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2})\.sql$/', $filename, $matches)) {
            try {
                // Convert "2026-01-26_12-30-45" to "2026-01-26 12:30:45"
                $dateTime = preg_replace('/^(\d{4}-\d{2}-\d{2})_(\d{2})-(\d{2})-(\d{2})$/', '$1 $2:$3:$4', $matches[1]);

                if ($dateTime === null) {
                    return null;
                }

                $timestamp = strtotime($dateTime);

                return $timestamp !== false ? [
                    'timestamp' => $timestamp,
                    'encrypted' => false,
                ] : null;
            } catch (Exception) {
                return null;
            }
        }

        return null;
    }

    /**
     * Validate backup filename against allowed patterns
     *
     * Supports formats:
     * - New encrypted: {dbtype}_{dbname}_{YYYYMMDD_HHMMSS}.sql.gz.enc
     * - New unencrypted: {dbtype}_{dbname}_{YYYYMMDD_HHMMSS}.sql
     * - Legacy: backup_{YYYY-MM-DD_HH-MM-SS}.sql
     */
    public function validateFilename(string $filename): bool
    {
        // New format (encrypted)
        if (preg_match('/^(?:postgres|mariadb)_\w+_\d{8}_\d{6}\.sql\.gz\.enc$/', $filename) === 1) {
            return true;
        }

        // New format (unencrypted)
        if (preg_match('/^(?:postgres|mariadb)_\w+_\d{8}_\d{6}\.sql$/', $filename) === 1) {
            return true;
        }

        // Legacy format
        return preg_match('/^backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.sql$/', $filename) === 1;
    }

    /**
     * Check if backup file is encrypted
     */
    public function isEncrypted(string $filename): bool
    {
        return str_ends_with($filename, '.gz.enc') || str_ends_with($filename, '.enc');
    }

    /**
     * Format bytes to human readable size
     */
    public function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        }

        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }

        return $bytes . ' bytes';
    }

    /**
     * Format timestamp age as human-readable string
     */
    public function formatAge(int $timestamp): string
    {
        $diff = time() - $timestamp;

        if ($diff < 60) {
            return $diff . ' second' . ($diff !== 1 ? 's' : '') . ' ago';
        }

        if ($diff < 3600) {
            $minutes = (int) ($diff / 60);

            return $minutes . ' minute' . ($minutes !== 1 ? 's' : '') . ' ago';
        }

        if ($diff < 86400) {
            $hours = (int) ($diff / 3600);

            return $hours . ' hour' . ($hours !== 1 ? 's' : '') . ' ago';
        }

        $days = (int) ($diff / 86400);

        return $days . ' day' . ($days !== 1 ? 's' : '') . ' ago';
    }
}
