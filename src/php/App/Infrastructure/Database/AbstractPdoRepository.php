<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use App\Infrastructure\DatabaseConfig;
use App\Infrastructure\DatabaseConfigInterface;
use PDO;
use PDOException;
use Zappzarapp\AuditLogger\AuditLogEntry;
use Zappzarapp\AuditLogger\AuditLoggerInterface;

/**
 * Abstract PDO Repository Implementation
 *
 * Base class for PDO-based repositories supporting PostgreSQL and MariaDB.
 * Uses lazy connection initialization and prepared statements for security.
 *
 * Features:
 * - Lazy connection (connects on first use)
 * - Automatic reconnection on failure
 * - Prepared statements (SQL injection protection)
 * - Transaction support
 * - Column-level encryption support
 * - Works with both PostgreSQL and MariaDB
 *
 * Configuration:
 * - Uses DatabaseConfig for connection settings
 * - Supports SSL/TLS connections
 * - Encryption key from Docker secret or environment
 *
 * Usage:
 * <code>
 * class UserRepository extends AbstractPdoRepository implements UserRepositoryInterface
 * {
 *     protected function getTable(): string { return 'users'; }
 *
 *     // Optional: Define encrypted fields
 *     protected function getEncryptedFields(): array { return ['totp_secret']; }
 * }
 * </code>
 *
 * @SuppressWarnings("PHPMD.ExcessiveClassComplexity") Complexity from RepositoryInterface methods + cross-DB support + encryption
 * @SuppressWarnings("PHPMD.ExcessiveClassLength") Comprehensive implementation of 12-method interface with encryption support
 * @SuppressWarnings("PHPMD.TooManyPublicMethods") Implements RepositoryInterface (12 methods)
 *
 * @package Infrastructure\Database
 */
abstract class AbstractPdoRepository implements RepositoryInterface
{
    private ?PDO $pdo = null;

    private readonly DatabaseConfigInterface $config;

    public function __construct(
        private readonly AuditLoggerInterface $auditLogger,
        ?DatabaseConfigInterface $config = null
    ) {
        $this->config      = $config ?? new DatabaseConfig();
    }

    /**
     * Get the table name for this repository
     *
     * Must be implemented by concrete repository classes.
     */
    abstract protected function getTable(): string;

    /**
     * Get the primary key column name
     *
     * Override in subclass if not 'id'.
     */
    protected function getPrimaryKey(): string
    {
        return 'id';
    }

    /**
     * Get list of encrypted field names
     *
     * Override in subclass to define which columns use encryption.
     * These fields will be encrypted on insert/update and decrypted on read.
     *
     * @return string[]
     */
    protected function getEncryptedFields(): array
    {
        return [];
    }

    // =========================================================================
    // RepositoryInterface Implementation
    // =========================================================================

    public function find(int|string $id): ?array
    {
        $pdo = $this->getConnection();
        if (!$pdo instanceof PDO) {
            return null;
        }

        try {
            $sql = sprintf(
                'SELECT * FROM %s WHERE %s = ?',
                $this->quoteIdentifier($this->getTable()),
                $this->quoteIdentifier($this->getPrimaryKey())
            );

            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);

            $result = $stmt->fetch();

            if ($result === false) {
                return null;
            }

            return $this->decryptFields($result);
        } catch (PDOException) {
            $this->disconnect();

            return null;
        }
    }

    public function findAll(?int $limit = null, int $offset = 0): array
    {
        $pdo = $this->getConnection();
        if (!$pdo instanceof PDO) {
            return [];
        }

        try {
            $sql = sprintf(
                'SELECT * FROM %s ORDER BY %s%s',
                $this->quoteIdentifier($this->getTable()),
                $this->quoteIdentifier($this->getPrimaryKey()),
                $this->buildLimitClause($limit, $offset)
            );

            $stmt = $pdo->prepare($sql);
            $stmt->execute();

            $results = $stmt->fetchAll();

            return array_map($this->decryptFields(...), $results);
        } catch (PDOException) {
            $this->disconnect();

            return [];
        }
    }

    public function findBy(array $criteria, ?int $limit = null, int $offset = 0): array
    {
        if ($criteria === []) {
            return $this->findAll($limit, $offset);
        }

        $pdo = $this->getConnection();
        if (!$pdo instanceof PDO) {
            return [];
        }

        try {
            [$whereClause, $parameters] = $this->buildWhereClause($criteria);

            $sql = sprintf(
                'SELECT * FROM %s WHERE %s ORDER BY %s%s',
                $this->quoteIdentifier($this->getTable()),
                $whereClause,
                $this->quoteIdentifier($this->getPrimaryKey()),
                $this->buildLimitClause($limit, $offset)
            );

            $stmt = $pdo->prepare($sql);
            $stmt->execute($parameters);

            $results = $stmt->fetchAll();

            return array_map($this->decryptFields(...), $results);
        } catch (PDOException) {
            $this->disconnect();

            return [];
        }
    }

    public function insert(array $data): int|string|false
    {
        if ($data === []) {
            return false;
        }

        $pdo = $this->getConnection();
        if (!$pdo instanceof PDO) {
            return false;
        }

        try {
            $data = $this->encryptFields($data);

            $columns      = array_keys($data);
            $placeholders = array_fill(0, count($columns), '?');

            $sql = sprintf(
                'INSERT INTO %s (%s) VALUES (%s)',
                $this->quoteIdentifier($this->getTable()),
                implode(', ', array_map($this->quoteIdentifier(...), $columns)),
                implode(', ', $placeholders)
            );

            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_values($data));

            $lastId = $pdo->lastInsertId();

            // PostgreSQL with SERIAL returns string "0" if lastInsertId is called without sequence name
            // In that case, try to get the ID from the sequence
            if ($lastId === '0' && $this->isPostgres()) {
                $sequenceName = $this->getTable() . '_' . $this->getPrimaryKey() . '_seq';
                $lastId       = $pdo->lastInsertId($sequenceName);
            }

            /** @var int|string $insertedId */
            $insertedId = is_numeric($lastId) ? (int) $lastId : $lastId;

            // Audit log: Record creation
            $this->auditLogger->log(new AuditLogEntry(
                action: $this->getTable() . '.create',
                entityType: $this->getTable(),
                entityId: $insertedId,
                userId: $this->getCurrentUserId(),
                data: ['fields' => array_keys($data)],
            ));

            return $insertedId;
        } catch (PDOException) {
            $this->disconnect();

            return false;
        }
    }

    public function update(int|string $id, array $data): bool
    {
        if ($data === []) {
            return false;
        }

        $pdo = $this->getConnection();
        if (!$pdo instanceof PDO) {
            return false;
        }

        try {
            $data = $this->encryptFields($data);

            $setClauses = [];
            $parameters = [];

            foreach ($data as $column => $value) {
                $setClauses[] = sprintf('%s = ?', $this->quoteIdentifier($column));
                $parameters[] = $value;
            }

            $parameters[] = $id;

            $sql = sprintf(
                'UPDATE %s SET %s WHERE %s = ?',
                $this->quoteIdentifier($this->getTable()),
                implode(', ', $setClauses),
                $this->quoteIdentifier($this->getPrimaryKey())
            );

            $stmt = $pdo->prepare($sql);
            $stmt->execute($parameters);

            $success = $stmt->rowCount() > 0;

            if ($success) {
                // Audit log: Record update
                $this->auditLogger->log(new AuditLogEntry(
                    action: $this->getTable() . '.update',
                    entityType: $this->getTable(),
                    entityId: $id,
                    userId: $this->getCurrentUserId(),
                    data: ['fields' => array_keys($data)],
                ));
            }

            return $success;
        } catch (PDOException) {
            $this->disconnect();

            return false;
        }
    }

    public function delete(int|string $id): bool
    {
        $pdo = $this->getConnection();
        if (!$pdo instanceof PDO) {
            return false;
        }

        try {
            $sql = sprintf(
                'DELETE FROM %s WHERE %s = ?',
                $this->quoteIdentifier($this->getTable()),
                $this->quoteIdentifier($this->getPrimaryKey())
            );

            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);

            $success = $stmt->rowCount() > 0;

            if ($success) {
                // Audit log: Record deletion (GDPR Art. 17 - Right to erasure)
                $this->auditLogger->log(new AuditLogEntry(
                    action: $this->getTable() . '.delete',
                    entityType: $this->getTable(),
                    entityId: $id,
                    userId: $this->getCurrentUserId(),
                ));
            }

            return $success;
        } catch (PDOException) {
            $this->disconnect();

            return false;
        }
    }

    public function exists(int|string $id): bool
    {
        $pdo = $this->getConnection();
        if (!$pdo instanceof PDO) {
            return false;
        }

        try {
            $sql = sprintf(
                'SELECT 1 FROM %s WHERE %s = ?',
                $this->quoteIdentifier($this->getTable()),
                $this->quoteIdentifier($this->getPrimaryKey())
            );

            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);

            return $stmt->fetch() !== false;
        } catch (PDOException) {
            $this->disconnect();

            return false;
        }
    }

    public function count(array $criteria = []): int
    {
        $pdo = $this->getConnection();
        if (!$pdo instanceof PDO) {
            return 0;
        }

        try {
            if ($criteria === []) {
                $sql  = sprintf('SELECT COUNT(*) FROM %s', $this->quoteIdentifier($this->getTable()));
                $stmt = $pdo->prepare($sql);
                $stmt->execute();
            } else {
                [$whereClause, $parameters] = $this->buildWhereClause($criteria);
                $sql                        = sprintf(
                    'SELECT COUNT(*) FROM %s WHERE %s',
                    $this->quoteIdentifier($this->getTable()),
                    $whereClause
                );
                $stmt = $pdo->prepare($sql);
                $stmt->execute($parameters);
            }

            $count = $stmt->fetchColumn();

            return is_numeric($count) ? (int) $count : 0;
        } catch (PDOException) {
            $this->disconnect();

            return 0;
        }
    }

    public function beginTransaction(): bool
    {
        $pdo = $this->getConnection();
        if (!$pdo instanceof PDO) {
            return false;
        }

        try {
            return $pdo->beginTransaction();
        } catch (PDOException) {
            return false;
        }
    }

    public function commit(): bool
    {
        if (!$this->pdo instanceof PDO) {
            return false;
        }

        try {
            return $this->pdo->commit();
        } catch (PDOException) {
            return false;
        }
    }

    public function rollback(): bool
    {
        if (!$this->pdo instanceof PDO) {
            return false;
        }

        try {
            return $this->pdo->rollBack();
        } catch (PDOException) {
            return false;
        }
    }

    public function isAvailable(): bool
    {
        $pdo = $this->getConnection();
        if (!$pdo instanceof PDO) {
            return false;
        }

        try {
            $pdo->query('SELECT 1');

            return true;
        } catch (PDOException) {
            $this->disconnect();

            return false;
        }
    }

    // =========================================================================
    // Encryption Support
    // =========================================================================

    /**
     * Encrypt a single value using database encryption function
     *
     * @param string $value The plaintext value to encrypt
     *
     * @return string|null Encrypted value or null if encryption fails
     */
    protected function encryptValue(string $value): ?string
    {
        $key = $this->getEncryptionKey();
        if ($key === null) {
            return null;
        }

        $pdo = $this->getConnection();
        if (!$pdo instanceof PDO) {
            return null;
        }

        try {
            $stmt = $pdo->prepare('SELECT encrypt_text(?, ?)');

            $stmt->execute([$value, $key]);
            $result = $stmt->fetchColumn();

            return $result !== false ? (string) $result : null;
        } catch (PDOException) {
            return null;
        }
    }

    /**
     * Decrypt a single value using database decryption function
     *
     * @param string $encrypted The encrypted value to decrypt
     *
     * @return string|null Decrypted value or null if decryption fails
     */
    protected function decryptValue(string $encrypted): ?string
    {
        $key = $this->getEncryptionKey();
        if ($key === null) {
            return null;
        }

        $pdo = $this->getConnection();
        if (!$pdo instanceof PDO) {
            return null;
        }

        try {
            $stmt = $pdo->prepare('SELECT decrypt_text(?, ?)');
            $stmt->execute([$encrypted, $key]);
            $result = $stmt->fetchColumn();

            return $result !== false ? (string) $result : null;
        } catch (PDOException) {
            return null;
        }
    }

    /**
     * Encrypt all encrypted fields in the data array
     *
     * @param array<string, mixed> $data Data to process
     *
     * @return array<string, mixed> Data with encrypted fields
     */
    protected function encryptFields(array $data): array
    {
        $encryptedFields = $this->getEncryptedFields();

        foreach ($encryptedFields as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $encrypted = $this->encryptValue($data[$field]);
                if ($encrypted !== null) {
                    $data[$field] = $encrypted;
                }
            }
        }

        return $data;
    }

    /**
     * Decrypt all encrypted fields in the data array
     *
     * @param array<string, mixed> $data Data to process
     *
     * @return array<string, mixed> Data with decrypted fields
     */
    protected function decryptFields(array $data): array
    {
        $encryptedFields = $this->getEncryptedFields();

        foreach ($encryptedFields as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $decrypted = $this->decryptValue($data[$field]);
                if ($decrypted !== null) {
                    $data[$field] = $decrypted;
                }
            }
        }

        return $data;
    }

    /**
     * Get the encryption key from Docker secret or environment
     *
     * @SuppressWarnings("PHPMD.Superglobals") Required for environment variable access
     */
    protected function getEncryptionKey(): ?string
    {
        // Check for Docker secret file first
        $keyFile = $_ENV['ENCRYPTION_KEY_FILE'] ?? getenv('ENCRYPTION_KEY_FILE');
        if ($keyFile === false || $keyFile === '') {
            $keyFile = '/run/secrets/encryption_key.txt';
        }

        if (is_string($keyFile) && file_exists($keyFile) && is_readable($keyFile)) {
            $content = file_get_contents($keyFile);
            if ($content !== false) {
                return trim($content);
            }
        }

        // Fall back to environment variable
        $key = $_ENV['ENCRYPTION_KEY'] ?? getenv('ENCRYPTION_KEY');

        return is_string($key) && $key !== '' ? $key : null;
    }

    // =========================================================================
    // Connection Management
    // =========================================================================

    /**
     * Get or create PDO connection (lazy initialization)
     */
    protected function getConnection(): ?PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        try {
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            // Merge SSL options for MariaDB
            $options = array_merge($options, $this->config->getPdoSslOptions());

            $this->pdo = new PDO(
                $this->config->getDsn(),
                $this->config->user,
                $this->config->password,
                $options
            );

            return $this->pdo;
        } catch (PDOException) {
            return null;
        }
    }

    /**
     * Close the database connection
     */
    protected function disconnect(): void
    {
        $this->pdo = null;
    }

    /**
     * Get the DatabaseConfig instance (for testing)
     */
    protected function getConfig(): DatabaseConfigInterface
    {
        return $this->config;
    }

    /**
     * Check if connected to PostgreSQL database
     *
     * Can be overridden in subclasses for testing without config.
     */
    protected function isPostgres(): bool
    {
        return $this->config->isPostgres();
    }

    // =========================================================================
    // SQL Building Helpers
    // =========================================================================

    /**
     * Quote an identifier (table/column name) for the current database
     *
     * PostgreSQL uses double quotes, MariaDB uses backticks.
     */
    protected function quoteIdentifier(string $identifier): string
    {
        if ($this->isPostgres()) {
            return '"' . str_replace('"', '""', $identifier) . '"';
        }

        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    /**
     * Build WHERE clause from criteria array
     *
     * @param array<string, mixed> $criteria Key-value pairs
     *
     * @return array{0: string, 1: array<int, mixed>} [whereClause, parameters]
     */
    protected function buildWhereClause(array $criteria): array
    {
        $conditions = [];
        $parameters = [];

        foreach ($criteria as $column => $value) {
            if ($value === null) {
                $conditions[] = sprintf('%s IS NULL', $this->quoteIdentifier($column));
            } else {
                $conditions[] = sprintf('%s = ?', $this->quoteIdentifier($column));
                $parameters[] = $value;
            }
        }

        return [implode(' AND ', $conditions), $parameters];
    }

    /**
     * Build LIMIT/OFFSET clause
     *
     * Both PostgreSQL and MariaDB use the same syntax.
     */
    protected function buildLimitClause(?int $limit, int $offset): string
    {
        if ($limit === null && $offset === 0) {
            return '';
        }

        $clause = '';
        if ($limit !== null) {
            $clause = ' LIMIT ' . $limit;
        }

        if ($offset > 0) {
            $clause .= ' OFFSET ' . $offset;
        }

        return $clause;
    }

    // =========================================================================
    // Audit Logging Helpers
    // =========================================================================

    /**
     * Get current user ID from session (for audit logging)
     *
     * Override in subclass if user context is stored differently.
     *
     * @SuppressWarnings("PHPMD.Superglobals") Required for session access
     */
    protected function getCurrentUserId(): ?int
    {
        return isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id'])
            ? (int) $_SESSION['user_id']
            : null;
    }
}
