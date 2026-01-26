<?php

declare(strict_types=1);

namespace DevDashboard\Services;

use RuntimeException;

/**
 * Database Backup Service
 *
 * Handles database backup creation, restoration, and management
 *
 * @SuppressWarnings("PHPMD.ExcessiveClassComplexity")
 */
class DatabaseBackupService
{
    private readonly string $backupDir;

    private readonly DatabaseConfig $dbConfig;

    private readonly DatabaseCommandBuilder $commandBuilder;

    public function __construct(
        private readonly BackupFileUtils $fileUtils = new BackupFileUtils(),
        private readonly CommandRunner $commandRunner = new CommandRunner(),
    ) {
        $this->backupDir      = $this->getBackupDirectory();
        $this->dbConfig       = new DatabaseConfig();
        $this->commandBuilder = new DatabaseCommandBuilder($this->dbConfig->getType());
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
                'success' => true,
                'message' => 'No backups created yet',
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
        usort($backups, fn(array $a, array $b): int => $b['timestamp'] <=> $a['timestamp']);

        return [
            'success' => true,
            'backups' => $backups,
        ];
    }

    /**
     * Create a new database backup
     *
     * @param bool $encrypt Whether to encrypt the backup
     * @return array<string, mixed>
     *
     * @SuppressWarnings("PHPMD.BooleanArgumentFlag")
     */
    public function createBackup(?int $retention = null, bool $encrypt = false): array
    {
        if (!$this->ensureBackupDirectory()) {
            return [
                'success' => false,
                'message' => 'Failed to create backup directory',
            ];
        }

        // Build encryption configuration
        try {
            $encryption = $encrypt
                ? BackupEncryption::enabled($this->getEncryptionKey() ?? '')
                : BackupEncryption::disabled();
        } catch (RuntimeException) {
            return [
                'success' => false,
                'message' => 'BACKUP_ENCRYPTION_KEY is not set. Set it in .env or disable encryption.',
            ];
        }

        return $this->executeBackup($encryption, $retention);
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

        // Build encryption configuration for restore
        $isEncrypted = $this->fileUtils->isEncrypted($filename);

        try {
            $encryption = $isEncrypted
                ? BackupEncryption::enabled($this->getEncryptionKey() ?? '')
                : BackupEncryption::disabled();
        } catch (RuntimeException) {
            return [
                'success' => false,
                'message' => 'Cannot restore encrypted backup: BACKUP_ENCRYPTION_KEY is not set',
            ];
        }

        $command = $this->buildRestoreCommand($backupFile, $encryption);
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
                'count'          => 0,
                'totalSize'      => '0 B',
                'newestDate'     => null,
                'oldestDate'     => null,
                'backup_dir'     => $this->backupDir,
                'backup_enabled' => is_dir($this->backupDir),
            ];
        }

        $backups = $result['backups'];
        $count   = count($backups);

        if ($count === 0) {
            return [
                'count'          => 0,
                'totalSize'      => '0 B',
                'newestDate'     => null,
                'oldestDate'     => null,
                'backup_dir'     => $this->backupDir,
                'backup_enabled' => true,
            ];
        }

        $totalSizeBytes = $this->calculateTotalSize($backups);
        $newest         = $backups[0] ?? null;
        $oldest         = $backups[$count - 1] ?? null;

        return [
            'count'          => $count,
            'totalSize'      => $this->fileUtils->formatBytes($totalSizeBytes),
            'newestDate'     => $newest['date'] ?? null,
            'oldestDate'     => $oldest['date'] ?? null,
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
                'size'      => $filesize !== false ? $this->fileUtils->formatBytes($filesize) : 'unknown',
                'age'       => $this->fileUtils->formatAge($metadata['timestamp']),
                'encrypted' => $metadata['encrypted'],
                'dbType'    => $metadata['dbType'] ?? 'unknown',
                'dbName'    => $metadata['dbName'] ?? 'unknown',
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
     *
     * Format: {db_type}_{db_name}_{timestamp}.sql[.gz.enc]
     * Example: postgres_app_20260126_121634.sql (unencrypted)
     *          postgres_app_20260126_121634.sql.gz.enc (encrypted)
     */
    private function generateBackupFilename(BackupEncryption $encryption): string
    {
        $timestamp = date('Ymd_His');

        return sprintf(
            '%s/%s_%s_%s%s',
            $this->backupDir,
            $this->dbConfig->getType(),
            $this->dbConfig->getName(),
            $timestamp,
            $encryption->getFileExtension()
        );
    }

    /**
     * Build backup command based on database type
     */
    private function buildBackupCommand(string $backupFile, BackupEncryption $encryption): string
    {
        return $this->commandBuilder->buildBackupCommand($backupFile, $encryption);
    }

    /**
     * Build restore command based on database type
     */
    private function buildRestoreCommand(string $backupFile, BackupEncryption $encryption): string
    {
        return $this->commandBuilder->buildRestoreCommand($backupFile, $encryption);
    }

    /**
     * Get encryption key from environment
     */
    private function getEncryptionKey(): ?string
    {
        $key = getenv('BACKUP_ENCRYPTION_KEY');

        return $key !== false && $key !== '' ? $key : null;
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
                    $totalSizeBytes += $size;
                }
            }
        }

        return $totalSizeBytes;
    }

    /**
     * Execute backup creation
     *
     * @return array<string, mixed>
     */
    private function executeBackup(BackupEncryption $encryption, ?int $retention): array
    {
        $backupFile = $this->generateBackupFilename($encryption);
        $command    = $this->buildBackupCommand($backupFile, $encryption);
        $result     = $this->commandRunner->run($command);

        if ($result['exitCode'] !== 0 || !file_exists($backupFile)) {
            return $this->buildBackupErrorResponse($result, $backupFile);
        }

        if ($retention !== null && $retention > 0) {
            $this->applyRetentionPolicy($retention);
        }

        return $this->buildBackupSuccessResponse($backupFile, $encryption);
    }

    /**
     * Build error response for failed backup
     *
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    private function buildBackupErrorResponse(array $result, string $backupFile): array
    {
        $errorDetails = sprintf(
            'Exit code: %d, File exists: %s, Output: %s',
            $result['exitCode'],
            file_exists($backupFile) ? 'yes' : 'no',
            $result['output']
        );

        return [
            'success' => false,
            'message' => 'Backup creation failed: ' . $errorDetails,
        ];
    }

    /**
     * Build success response for completed backup
     *
     * @return array<string, mixed>
     */
    private function buildBackupSuccessResponse(string $backupFile, BackupEncryption $encryption): array
    {
        $filesize = filesize($backupFile);

        return [
            'success'   => true,
            'message'   => 'Backup created successfully',
            'filename'  => basename($backupFile),
            'size'      => $filesize !== false ? $this->fileUtils->formatBytes($filesize) : 'unknown',
            'encrypted' => $encryption->isEnabled(),
        ];
    }

    /**
     * Get backup directory path
     */
    private function getBackupDirectory(): string
    {
        return realpath(__DIR__ . '/../../../../') . '/backups/db';
    }
}
