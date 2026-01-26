<?php

declare(strict_types=1);

namespace DevDashboard\Services;

/**
 * Database Backup Service
 *
 * Handles database backup creation, restoration, and management
 */
class DatabaseBackupService
{
    private readonly string $backupDir;
    private readonly BackupFileUtils $fileUtils;
    private readonly CommandRunner $commandRunner;

    public function __construct(
        ?BackupFileUtils $fileUtils = null,
        ?CommandRunner $commandRunner = null,
    ) {
        $this->backupDir     = $this->getBackupDirectory();
        $this->fileUtils     = $fileUtils ?? new BackupFileUtils();
        $this->commandRunner = $commandRunner ?? new CommandRunner();
    }

    /**
     * List all available database backups
     *
     * @return array<string, mixed>
     */
    public function listBackups(): array
    {
        if (!is_dir($this->backupDir)) {
            return [
                'success' => false,
                'message' => 'Backup directory does not exist',
                'backups' => [],
            ];
        }

        $files = scandir($this->backupDir);

        if ($files === false) {
            return [
                'success' => false,
                'message' => 'Could not read backup directory',
                'backups' => [],
            ];
        }

        $backups = $this->collectBackupFiles($files);

        // Sort by timestamp descending (newest first)
        usort($backups, fn($a, $b) => $b['timestamp'] <=> $a['timestamp']);

        return [
            'success' => true,
            'backups' => $backups,
        ];
    }

    /**
     * Create a new database backup
     *
     * @return array<string, mixed>
     */
    public function createBackup(?int $retention = null): array
    {
        if (!$this->ensureBackupDirectory()) {
            return [
                'success' => false,
                'message' => 'Failed to create backup directory',
            ];
        }

        $backupFile = $this->generateBackupFilename();
        $command    = $this->buildBackupCommand($backupFile);
        $result     = $this->commandRunner->run($command);

        if ($result['exitCode'] !== 0 || !file_exists($backupFile)) {
            return [
                'success' => false,
                'message' => 'Backup creation failed: ' . $result['output'],
            ];
        }

        if ($retention !== null && $retention > 0) {
            $this->applyRetentionPolicy($retention);
        }

        $filesize = filesize($backupFile);

        return [
            'success'  => true,
            'message'  => 'Backup created successfully',
            'filename' => basename($backupFile),
            'size'     => $filesize !== false ? $this->fileUtils->formatBytes((int) $filesize) : 'unknown',
        ];
    }

    /**
     * Restore database from a backup file
     *
     * @return array<string, mixed>
     */
    public function restoreBackup(string $filename): array
    {
        if (!$this->fileUtils->validateFilename($filename)) {
            return [
                'success' => false,
                'message' => 'Invalid backup filename',
            ];
        }

        $backupFile = $this->backupDir . '/' . $filename;

        if (!file_exists($backupFile)) {
            return [
                'success' => false,
                'message' => 'Backup file not found',
            ];
        }

        $command = $this->buildRestoreCommand($backupFile);
        $result  = $this->commandRunner->run($command);

        if ($result['exitCode'] !== 0) {
            return [
                'success' => false,
                'message' => 'Restore failed: ' . $result['output'],
            ];
        }

        return [
            'success' => true,
            'message' => 'Database restored successfully from ' . $filename,
        ];
    }

    /**
     * Delete a backup file
     *
     * @return array<string, mixed>
     */
    public function deleteBackup(string $filename): array
    {
        if (!$this->fileUtils->validateFilename($filename)) {
            return [
                'success' => false,
                'message' => 'Invalid backup filename',
            ];
        }

        $backupFile = $this->backupDir . '/' . $filename;

        if (!file_exists($backupFile)) {
            return [
                'success' => false,
                'message' => 'Backup file not found',
            ];
        }

        if (!unlink($backupFile)) {
            return [
                'success' => false,
                'message' => 'Failed to delete backup file',
            ];
        }

        return [
            'success' => true,
            'message' => 'Backup deleted successfully',
        ];
    }

    /**
     * Get backup statistics
     *
     * @return array<string, mixed>
     */
    public function getBackupStats(): array
    {
        $result = $this->listBackups();

        if (!$result['success']) {
            return [
                'total'          => 0,
                'total_size'     => 0,
                'latest_backup'  => null,
                'oldest_backup'  => null,
                'backup_dir'     => $this->backupDir,
                'backup_enabled' => is_dir($this->backupDir),
            ];
        }

        $backups = $result['backups'];
        $count   = count($backups);

        if ($count === 0) {
            return [
                'total'          => 0,
                'total_size'     => 0,
                'latest_backup'  => null,
                'oldest_backup'  => null,
                'backup_dir'     => $this->backupDir,
                'backup_enabled' => true,
            ];
        }

        $totalSizeBytes = $this->calculateTotalSize($backups);

        return [
            'total'          => $count,
            'total_size'     => $this->fileUtils->formatBytes($totalSizeBytes),
            'latest_backup'  => $backups[0] ?? null,
            'oldest_backup'  => $backups[$count - 1] ?? null,
            'backup_dir'     => $this->backupDir,
            'backup_enabled' => true,
        ];
    }

    /**
     * Collect backup files from directory listing
     *
     * @param array<int, string> $files
     * @return array<int, array<string, mixed>>
     */
    private function collectBackupFiles(array $files): array
    {
        $backups = [];

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $metadata = $this->fileUtils->parseFilename($file);
            if ($metadata === null) {
                continue;
            }

            $filepath = $this->backupDir . '/' . $file;
            $filesize = file_exists($filepath) ? filesize($filepath) : 0;

            $backups[] = [
                'filename'  => $file,
                'timestamp' => $metadata['timestamp'],
                'date'      => date('Y-m-d H:i:s', $metadata['timestamp']),
                'size'      => $filesize !== false ? $this->fileUtils->formatBytes((int) $filesize) : 'unknown',
                'age'       => $this->fileUtils->formatAge($metadata['timestamp']),
            ];
        }

        return $backups;
    }

    /**
     * Ensure backup directory exists
     */
    private function ensureBackupDirectory(): bool
    {
        return is_dir($this->backupDir) || (mkdir($this->backupDir, 0755, true) && is_dir($this->backupDir));
    }

    /**
     * Generate backup filename with timestamp
     */
    private function generateBackupFilename(): string
    {
        return $this->backupDir . '/backup_' . date('Y-m-d_H-i-s') . '.sql';
    }

    /**
     * Build pg_dump command for backup
     */
    private function buildBackupCommand(string $backupFile): string
    {
        $containerName = 'postgres';
        $dbName        = getenv('POSTGRES_DB') ?: 'app_db';

        return sprintf(
            'docker exec %s pg_dump -U postgres %s > %s 2>&1',
            escapeshellarg($containerName),
            escapeshellarg($dbName),
            escapeshellarg($backupFile),
        );
    }

    /**
     * Build psql command for restore
     */
    private function buildRestoreCommand(string $backupFile): string
    {
        $containerName = 'postgres';
        $dbName        = getenv('POSTGRES_DB') ?: 'app_db';

        return sprintf(
            'docker exec -i %s psql -U postgres %s < %s 2>&1',
            escapeshellarg($containerName),
            escapeshellarg($dbName),
            escapeshellarg($backupFile),
        );
    }

    /**
     * Apply retention policy - keep only N most recent backups
     */
    private function applyRetentionPolicy(int $keepCount): void
    {
        $result = $this->listBackups();

        if (!$result['success'] || count($result['backups']) <= $keepCount) {
            return;
        }

        $toDelete = array_slice($result['backups'], $keepCount);

        foreach ($toDelete as $backup) {
            $this->deleteBackup($backup['filename']);
        }
    }

    /**
     * Calculate total size of all backups
     *
     * @param array<int, array<string, mixed>> $backups
     */
    private function calculateTotalSize(array $backups): int
    {
        $totalSizeBytes = 0;

        foreach ($backups as $backup) {
            $filepath = $this->backupDir . '/' . $backup['filename'];
            if (file_exists($filepath)) {
                $size = filesize($filepath);
                if ($size !== false) {
                    $totalSizeBytes += (int) $size;
                }
            }
        }

        return $totalSizeBytes;
    }

    /**
     * Get backup directory path
     */
    private function getBackupDirectory(): string
    {
        return realpath(__DIR__ . '/../../../../') . '/build/backups';
    }
}
