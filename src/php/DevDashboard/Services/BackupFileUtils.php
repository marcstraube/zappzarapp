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
     * @return array{timestamp: int}|null
     */
    public function parseFilename(string $filename): ?array
    {
        if (!preg_match('/^backup_(\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2})\.sql$/', $filename, $matches)) {
            return null;
        }

        try {
            // Convert "2026-01-26_12-30-45" to "2026-01-26 12:30:45"
            $dateTime = preg_replace('/^(\d{4}-\d{2}-\d{2})_(\d{2})-(\d{2})-(\d{2})$/', '$1 $2:$3:$4', $matches[1]);

            if ($dateTime === null) {
                return null;
            }

            $timestamp = strtotime($dateTime);

            if ($timestamp === false) {
                return null;
            }

            return ['timestamp' => $timestamp];
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Validate backup filename against allowed pattern
     */
    public function validateFilename(string $filename): bool
    {
        return preg_match('/^backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.sql$/', $filename) === 1;
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
