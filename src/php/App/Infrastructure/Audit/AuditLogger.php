<?php

declare(strict_types=1);

namespace App\Infrastructure\Audit;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Audit Logger Service for GDPR-compliant audit logging
 *
 * GDPR Art. 30: Records of processing activities
 * GDPR Art. 32: Security measures
 *
 * Features:
 * - Stores audit logs in database (audit_logs table)
 * - Encrypts sensitive data (using ENCRYPTION_KEY from environment)
 * - Tamper-proof (SHA-256 checksum)
 * - Logs to file (storage/logs/audit.log) for redundancy
 * - IP address and user agent tracking
 *
 * Usage:
 * <code>
 * $auditLogger = new AuditLogger($pdo, $_ENV['ENCRYPTION_KEY']);
 *
 * // Log user data access
 * $auditLogger->log(
 *     action: 'user.view',
 *     entityType: 'user',
 *     entityId: 123,
 *     userId: $_SESSION['user_id'] ?? null
 * );
 *
 * // Log data modification with additional data
 * $auditLogger->log(
 *     action: 'user.update',
 *     entityType: 'user',
 *     entityId: 123,
 *     userId: $_SESSION['user_id'],
 *     data: ['changed_fields' => ['email', 'phone']]
 * );
 * </code>
 *
 * @package Infrastructure\Audit
 */
final readonly class AuditLogger implements AuditLoggerInterface
{
    private string $logFilePath;

    /**
     * @param PDO $pdo Database connection
     * @param string $encryptionKey Encryption key for sensitive data (from $_ENV['ENCRYPTION_KEY'])
     * @param string|null $logFilePath Optional log file path (default: storage/logs/audit.log)
     */
    public function __construct(
        private PDO $pdo,
        private string $encryptionKey,
        ?string $logFilePath = null
    ) {
        $this->logFilePath = $logFilePath ?? __DIR__ . '/../../../../storage/logs/audit.log';
    }

    /**
     * @inheritDoc
     */
    public function log(
        string $action,
        string $entityType,
        string|int $entityId,
        ?int $userId = null,
        array $data = []
    ): void {
        $this->writeLog($action, $entityType, (string)$entityId, $userId, $data);
    }

    /**
     * @inheritDoc
     */
    public function logAuth(
        string $action,
        ?int $userId = null,
        array $data = []
    ): void {
        $this->writeLog($action, 'auth', (string)($userId ?? 0), $userId, $data);
    }

    /**
     * @inheritDoc
     */
    public function logAdmin(
        string $action,
        int $adminUserId,
        string $entityType,
        string|int $entityId,
        array $data = []
    ): void {
        $data['admin_user_id'] = $adminUserId;
        $this->writeLog($action, $entityType, (string)$entityId, $adminUserId, $data);
    }

    /**
     * @inheritDoc
     */
    public function getLogsForEntity(
        string $entityType,
        string|int $entityId,
        int $limit = 100
    ): array {
        $stmt = $this->pdo->prepare('
            SELECT
                id,
                timestamp,
                user_id,
                ip_address,
                action,
                entity_type,
                entity_id,
                decrypt_text(data, :encryption_key) as data_decrypted
            FROM audit_logs
            WHERE entity_type = :entity_type
              AND entity_id = :entity_id
            ORDER BY timestamp DESC
            LIMIT :limit
        ');

        $stmt->bindValue(':entity_type', $entityType);
        $stmt->bindValue(':entity_id', (string)$entityId);
        $stmt->bindValue(':encryption_key', $this->encryptionKey);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @inheritDoc
     */
    public function getLogsForUser(
        int $userId,
        int $limit = 100
    ): array {
        $stmt = $this->pdo->prepare('
            SELECT
                id,
                timestamp,
                user_id,
                ip_address,
                action,
                entity_type,
                entity_id,
                decrypt_text(data, :encryption_key) as data_decrypted
            FROM audit_logs
            WHERE user_id = :user_id
            ORDER BY timestamp DESC
            LIMIT :limit
        ');

        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':encryption_key', $this->encryptionKey);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Write audit log to database and file
     *
     * @param array<string, mixed> $data
     */
    private function writeLog(
        string $action,
        string $entityType,
        string $entityId,
        ?int $userId,
        array $data
    ): void {
        $timestamp = date('Y-m-d H:i:s');
        $ipAddress = $this->getClientIp();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

        // Add metadata to data
        $data['user_agent'] = $userAgent;
        $data['timestamp']  = $timestamp;

        // Serialize and encrypt data
        $dataJson = json_encode($data);
        if ($dataJson === false) {
            throw new RuntimeException('Failed to encode audit data as JSON');
        }

        // Calculate checksum (tamper-proof)
        $checksum = hash('sha256', $timestamp . $action . $entityType . $entityId . $dataJson);

        // Write to database
        try {
            $stmt = $this->pdo->prepare('
                INSERT INTO audit_logs (timestamp, user_id, ip_address, action, entity_type, entity_id, data, checksum)
                VALUES (
                    :timestamp,
                    :user_id,
                    :ip_address,
                    :action,
                    :entity_type,
                    :entity_id,
                    encrypt_text(:data, :encryption_key),
                    :checksum
                )
            ');

            $stmt->execute([
                'timestamp'      => $timestamp,
                'user_id'        => $userId,
                'ip_address'     => $ipAddress,
                'action'         => $action,
                'entity_type'    => $entityType,
                'entity_id'      => $entityId,
                'data'           => $dataJson,
                'encryption_key' => $this->encryptionKey,
                'checksum'       => $checksum,
            ]);
        } catch (PDOException $pdoException) {
            // Log to file as fallback
            $this->writeLogToFile($timestamp, $action, $entityType, $entityId, $userId, $ipAddress, $dataJson);
            throw new RuntimeException('Failed to write audit log to database: ' . $pdoException->getMessage(), 0, $pdoException);
        }

        // Also write to file for redundancy
        $this->writeLogToFile($timestamp, $action, $entityType, $entityId, $userId, $ipAddress, $dataJson);
    }

    /**
     * Write audit log to file (JSON format, one line per log entry)
     */
    private function writeLogToFile(
        string $timestamp,
        string $action,
        string $entityType,
        string $entityId,
        ?int $userId,
        string $ipAddress,
        string $dataJson
    ): void {
        // Ensure log directory exists
        $logDir = dirname($this->logFilePath);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        // Format as JSON (one line per entry)
        $logEntry = json_encode([
            'timestamp'   => $timestamp,
            'user_id'     => $userId,
            'ip_address'  => $ipAddress,
            'action'      => $action,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'data'        => json_decode($dataJson, true),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // Append to log file
        file_put_contents($this->logFilePath, $logEntry . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    /**
     * Get client IP address (handles proxies)
     */
    private function getClientIp(): string
    {
        // Check for proxies (X-Forwarded-For, X-Real-IP)
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // X-Forwarded-For can contain multiple IPs, take the first one
            $ips = explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }

        if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            return $_SERVER['HTTP_X_REAL_IP'];
        }

        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}
