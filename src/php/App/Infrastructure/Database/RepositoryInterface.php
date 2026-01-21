<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

/**
 * Generic Repository Interface for CRUD operations
 *
 * Provides a database-agnostic interface for data access.
 * Implementations should support both PostgreSQL and MariaDB.
 *
 * Return Value Conventions (consistent with other infrastructure services):
 * - null: Record not found (expected case, not an error)
 * - false: Operation failed due to error (connection, query, etc.)
 * - data: Operation succeeded
 *
 * Usage:
 * <code>
 * // Find a single record
 * $user = $repository->find(1);
 * if ($user === null) {
 *     // Not found
 * }
 *
 * // Find with criteria
 * $activeUsers = $repository->findBy(['status' => 'active'], limit: 10);
 *
 * // Insert new record
 * $id = $repository->insert(['email' => 'user@example.com', 'name' => 'John']);
 * if ($id === false) {
 *     // Insert failed
 * }
 *
 * // Transaction example
 * $repository->beginTransaction();
 * try {
 *     $repository->insert($data1);
 *     $repository->update($id, $data2);
 *     $repository->commit();
 * } catch (Exception $e) {
 *     $repository->rollback();
 * }
 * </code>
 *
 * @package Infrastructure\Database
 */
interface RepositoryInterface
{
    /**
     * Find a single record by ID
     *
     * @param int|string $id Primary key value
     *
     * @return array<string, mixed>|null Record data or null if not found
     */
    public function find(int|string $id): ?array;

    /**
     * Find all records with optional pagination
     *
     * @param int|null $limit  Maximum records to return (null for all)
     * @param int      $offset Number of records to skip
     *
     * @return array<int, array<string, mixed>> Array of records (empty if none found)
     */
    public function findAll(?int $limit = null, int $offset = 0): array;

    /**
     * Find records matching criteria
     *
     * Criteria are combined with AND. For more complex queries,
     * use a custom method in your repository implementation.
     *
     * @param array<string, mixed> $criteria Key-value pairs for WHERE conditions
     * @param int|null             $limit    Maximum records to return
     * @param int                  $offset   Number of records to skip
     *
     * @return array<int, array<string, mixed>> Array of matching records
     */
    public function findBy(array $criteria, ?int $limit = null, int $offset = 0): array;

    /**
     * Insert a new record
     *
     * @param array<string, mixed> $data Column-value pairs to insert
     *
     * @return int|string|false Inserted ID on success, false on failure
     */
    public function insert(array $data): int|string|false;

    /**
     * Update an existing record
     *
     * @param int|string           $id   Primary key value
     * @param array<string, mixed> $data Column-value pairs to update
     *
     * @return bool True on success, false on failure
     */
    public function update(int|string $id, array $data): bool;

    /**
     * Delete a record
     *
     * @param int|string $id Primary key value
     *
     * @return bool True on success, false on failure
     */
    public function delete(int|string $id): bool;

    /**
     * Check if a record exists
     *
     * @param int|string $id Primary key value
     *
     * @return bool True if record exists
     */
    public function exists(int|string $id): bool;

    /**
     * Count records matching optional criteria
     *
     * @param array<string, mixed> $criteria Optional WHERE conditions (empty for all)
     *
     * @return int Number of matching records (0 on error)
     */
    public function count(array $criteria = []): int;

    /**
     * Begin a database transaction
     *
     * @return bool True if transaction started successfully
     */
    public function beginTransaction(): bool;

    /**
     * Commit the current transaction
     *
     * @return bool True if committed successfully
     */
    public function commit(): bool;

    /**
     * Rollback the current transaction
     *
     * @return bool True if rolled back successfully
     */
    public function rollback(): bool;

    /**
     * Check if database connection is available and responsive
     *
     * @return bool True if connected and can execute queries
     */
    public function isAvailable(): bool;
}
