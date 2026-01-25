<?php

/** @noinspection PhpMultipleClassDeclarationsInspection Override is native in PHP 8.3 */

declare(strict_types=1);

namespace App\Infrastructure\Database;

use App\Infrastructure\Audit\AuditLoggerInterface;
use App\Infrastructure\DatabaseConfigInterface;
use Override;
use PDO;
use PDOException;

/**
 * User Repository Implementation
 *
 * Concrete repository for the users table with TOTP encryption support.
 * Extends AbstractPdoRepository for CRUD operations and encryption.
 *
 * Features:
 * - Email-based user lookup
 * - TOTP secret encryption/decryption using DB functions
 * - Duplicate email checking (with exclude for updates)
 *
 * Table Schema:
 * - id: Primary key (INT/SERIAL)
 * - email: Unique email address
 * - password_hash: Bcrypt hashed password
 * - name: Display name
 * - totp_secret: Encrypted TOTP secret (uses encrypt_text/decrypt_text)
 * - totp_enabled: Boolean flag for 2FA status
 * - created_at: Timestamp
 * - updated_at: Timestamp
 *
 * @package Infrastructure\Database
 */
class UserRepository extends AbstractPdoRepository implements UserRepositoryInterface
{
    public function __construct(
        AuditLoggerInterface $auditLogger,
        ?DatabaseConfigInterface $config = null
    ) {
        parent::__construct($auditLogger, $config);
    }

    /**
     * @inheritDoc
     */
    protected function getTable(): string
    {
        return 'users';
    }

    /**
     * Define encrypted fields (TOTP secret needs encryption)
     *
     * @return string[]
     */
    #[Override]
    protected function getEncryptedFields(): array
    {
        return ['totp_secret'];
    }

    /**
     * @inheritDoc
     */
    public function findByEmail(string $email): ?array
    {
        $results = $this->findBy(['email' => $email], 1);

        return $results[0] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function emailExists(string $email, ?int $excludeUserId = null): bool
    {
        $pdo = $this->getConnection();
        if (!$pdo instanceof PDO) {
            return false;
        }

        try {
            if ($excludeUserId === null) {
                $sql = sprintf(
                    'SELECT 1 FROM %s WHERE %s = ?',
                    $this->quoteIdentifier($this->getTable()),
                    $this->quoteIdentifier('email')
                );
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$email]);
            } else {
                $sql = sprintf(
                    'SELECT 1 FROM %s WHERE %s = ? AND %s != ?',
                    $this->quoteIdentifier($this->getTable()),
                    $this->quoteIdentifier('email'),
                    $this->quoteIdentifier($this->getPrimaryKey())
                );
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$email, $excludeUserId]);
            }

            return $stmt->fetch() !== false;
        } catch (PDOException) {
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function enableTotp(int $userId, string $secret): bool
    {
        // Encrypt the secret using parent's encryptValue method
        $encryptedSecret = $this->encryptValue($secret);
        if ($encryptedSecret === null) {
            return false;
        }

        // Bypass the automatic encryption since we already encrypted manually
        $pdo = $this->getConnection();
        if (!$pdo instanceof PDO) {
            return false;
        }

        try {
            $sql = sprintf(
                'UPDATE %s SET %s = ?, %s = ? WHERE %s = ?',
                $this->quoteIdentifier($this->getTable()),
                $this->quoteIdentifier('totp_secret'),
                $this->quoteIdentifier('totp_enabled'),
                $this->quoteIdentifier($this->getPrimaryKey())
            );

            $stmt = $pdo->prepare($sql);
            $stmt->execute([$encryptedSecret, $this->isPostgres() ? 't' : 1, $userId]);

            return $stmt->rowCount() > 0;
        } catch (PDOException) {
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function disableTotp(int $userId): bool
    {
        $pdo = $this->getConnection();
        if (!$pdo instanceof PDO) {
            return false;
        }

        try {
            $sql = sprintf(
                'UPDATE %s SET %s = NULL, %s = ? WHERE %s = ?',
                $this->quoteIdentifier($this->getTable()),
                $this->quoteIdentifier('totp_secret'),
                $this->quoteIdentifier('totp_enabled'),
                $this->quoteIdentifier($this->getPrimaryKey())
            );

            $stmt = $pdo->prepare($sql);
            $stmt->execute([$this->isPostgres() ? 'f' : 0, $userId]);

            return $stmt->rowCount() > 0;
        } catch (PDOException) {
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function getTotpSecret(int $userId): ?string
    {
        $pdo = $this->getConnection();
        if (!$pdo instanceof PDO) {
            return null;
        }

        try {
            // First get the encrypted secret
            $sql = sprintf(
                'SELECT %s FROM %s WHERE %s = ? AND %s = ?',
                $this->quoteIdentifier('totp_secret'),
                $this->quoteIdentifier($this->getTable()),
                $this->quoteIdentifier($this->getPrimaryKey()),
                $this->quoteIdentifier('totp_enabled')
            );

            $stmt = $pdo->prepare($sql);
            $stmt->execute([$userId, $this->isPostgres() ? 't' : 1]);

            $encryptedSecret = $stmt->fetchColumn();
            if ($encryptedSecret === false || $encryptedSecret === null) {
                return null;
            }

            // Decrypt and return
            return $this->decryptValue((string) $encryptedSecret);
        } catch (PDOException) {
            return null;
        }
    }
}
