<?php

/** @noinspection PhpMultipleClassDeclarationsInspection Override is native in PHP 8.3 */

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Database;

use App\Infrastructure\Database\AbstractPdoRepository;
use App\Infrastructure\DatabaseConfigInterface;
use Override;
use PDO;
use PDOException;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;

/**
 * Unit tests for AbstractPdoRepository
 *
 * Tests the base repository functionality including CRUD operations,
 * encryption support, and cross-database compatibility.
 *
 * @SuppressWarnings("PHPMD.TooManyPublicMethods") Unit tests for 12+ interface methods
 * @SuppressWarnings("PHPMD.TooManyMethods") Comprehensive coverage for all CRUD + encryption + transaction methods
 * @SuppressWarnings("PHPMD.ExcessivePublicCount") Each interface method needs 3-4 test cases
 * @SuppressWarnings("PHPMD.ExcessiveClassComplexity") Comprehensive tests for complex base class
 * @SuppressWarnings("PHPMD.ExcessiveClassLength") Test coverage for 12-method interface + encryption
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects") Required mocks for PDO testing
 */
#[CoversClass(AbstractPdoRepository::class)]
final class AbstractPdoRepositoryTest extends TestCase
{
    // =========================================================================
    // find() Tests
    // =========================================================================

    public function testFindReturnsRecordWhenFound(): void
    {
        $expectedRow = ['id' => 1, 'name' => 'Test User', 'email' => 'test@example.com'];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
            ->method('execute')
            ->with([1])
            ->willReturn(true);
        $stmt->expects($this->once())
            ->method('fetch')
            ->willReturn($expectedRow);

        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('SELECT * FROM'))
            ->willReturn($stmt);

        $repository = $this->createRepository($pdo);

        $this->assertEquals($expectedRow, $repository->find(1));
    }

    public function testFindReturnsNullWhenNotFound(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
            ->method('execute')
            ->willReturn(true);
        $stmt->expects($this->once())
            ->method('fetch')
            ->willReturn(false);

        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('prepare')
            ->willReturn($stmt);

        $repository = $this->createRepository($pdo);

        $this->assertNull($repository->find(999));
    }

    public function testFindReturnsNullOnPdoException(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('prepare')
            ->willThrowException(new PDOException('Connection lost'));

        $repository = $this->createRepository($pdo);

        $this->assertNull($repository->find(1));
    }

    public function testFindReturnsNullWhenNoConnection(): void
    {
        $repository = $this->createRepositoryWithoutConnection();

        $this->assertNull($repository->find(1));
    }

    // =========================================================================
    // findAll() Tests
    // =========================================================================

    public function testFindAllReturnsAllRecords(): void
    {
        $expectedRows = [
            ['id' => 1, 'name' => 'User 1'],
            ['id' => 2, 'name' => 'User 2'],
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
            ->method('execute')
            ->willReturn(true);
        $stmt->expects($this->once())
            ->method('fetchAll')
            ->willReturn($expectedRows);

        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->logicalNot($this->stringContains('LIMIT')))
            ->willReturn($stmt);

        $repository = $this->createRepository($pdo);

        $this->assertEquals($expectedRows, $repository->findAll());
    }

    public function testFindAllRespectsLimitAndOffset(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
            ->method('execute')
            ->willReturn(true);
        $stmt->expects($this->once())
            ->method('fetchAll')
            ->willReturn([['id' => 3]]);

        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('LIMIT 10 OFFSET 20'))
            ->willReturn($stmt);

        $repository = $this->createRepository($pdo);

        $this->assertEquals([['id' => 3]], $repository->findAll(10, 20));
    }

    public function testFindAllReturnsEmptyArrayOnException(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('prepare')
            ->willThrowException(new PDOException('Query failed'));

        $repository = $this->createRepository($pdo);

        $this->assertEquals([], $repository->findAll());
    }

    public function testFindAllReturnsEmptyArrayWhenNoConnection(): void
    {
        $repository = $this->createRepositoryWithoutConnection();

        $this->assertEquals([], $repository->findAll());
    }

    // =========================================================================
    // findBy() Tests
    // =========================================================================

    public function testFindByReturnMatchingRecords(): void
    {
        $expectedRows = [['id' => 1, 'status' => 'active']];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
            ->method('execute')
            ->with(['active'])
            ->willReturn(true);
        $stmt->expects($this->once())
            ->method('fetchAll')
            ->willReturn($expectedRows);

        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('WHERE'))
            ->willReturn($stmt);

        $repository = $this->createRepository($pdo);

        $this->assertEquals($expectedRows, $repository->findBy(['status' => 'active']));
    }

    public function testFindByDelegatesEmptyCriteriaToFindAll(): void
    {
        $expectedRows = [['id' => 1], ['id' => 2]];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
            ->method('execute')
            ->willReturn(true);
        $stmt->expects($this->once())
            ->method('fetchAll')
            ->willReturn($expectedRows);

        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->logicalNot($this->stringContains('WHERE')))
            ->willReturn($stmt);

        $repository = $this->createRepository($pdo);

        $this->assertEquals($expectedRows, $repository->findBy([]));
    }

    public function testFindByHandlesNullCriteriaValue(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
            ->method('execute')
            ->with([]) // NULL values are not bound as parameters
            ->willReturn(true);
        $stmt->expects($this->once())
            ->method('fetchAll')
            ->willReturn([]);

        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('IS NULL'))
            ->willReturn($stmt);

        $repository = $this->createRepository($pdo);

        $this->assertEquals([], $repository->findBy(['deleted_at' => null]));
    }

    // =========================================================================
    // insert() Tests
    // =========================================================================

    public function testInsertReturnsIdOnSuccess(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
            ->method('execute')
            ->with(['test@example.com', 'Test User'])
            ->willReturn(true);

        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('INSERT INTO'))
            ->willReturn($stmt);
        $pdo->expects($this->once())
            ->method('lastInsertId')
            ->willReturn('42');

        $repository = $this->createRepository($pdo);

        $this->assertEquals(42, $repository->insert(['email' => 'test@example.com', 'name' => 'Test User']));
    }

    public function testInsertReturnsFalseForEmptyData(): void
    {
        $repository = $this->createRepositoryWithoutConnection();

        $this->assertFalse($repository->insert([]));
    }

    public function testInsertReturnsFalseOnException(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('prepare')
            ->willThrowException(new PDOException('Duplicate key'));

        $repository = $this->createRepository($pdo);

        $this->assertFalse($repository->insert(['email' => 'test@example.com']));
    }

    public function testInsertReturnsFalseWhenNoConnection(): void
    {
        $repository = $this->createRepositoryWithoutConnection();

        $this->assertFalse($repository->insert(['email' => 'test@example.com']));
    }

    // =========================================================================
    // update() Tests
    // =========================================================================

    public function testUpdateReturnsTrueOnSuccess(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
            ->method('execute')
            ->with(['New Name', 1])
            ->willReturn(true);
        $stmt->expects($this->once())
            ->method('rowCount')
            ->willReturn(1);

        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('UPDATE'))
            ->willReturn($stmt);

        $repository = $this->createRepository($pdo);

        $this->assertTrue($repository->update(1, ['name' => 'New Name']));
    }

    public function testUpdateReturnsFalseWhenNoRowsAffected(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
            ->method('execute')
            ->willReturn(true);
        $stmt->expects($this->once())
            ->method('rowCount')
            ->willReturn(0);

        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('prepare')
            ->willReturn($stmt);

        $repository = $this->createRepository($pdo);

        $this->assertFalse($repository->update(999, ['name' => 'New Name']));
    }

    public function testUpdateReturnsFalseForEmptyData(): void
    {
        $repository = $this->createRepositoryWithoutConnection();

        $this->assertFalse($repository->update(1, []));
    }

    public function testUpdateReturnsFalseOnException(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('prepare')
            ->willThrowException(new PDOException('Update failed'));

        $repository = $this->createRepository($pdo);

        $this->assertFalse($repository->update(1, ['name' => 'New Name']));
    }

    // =========================================================================
    // delete() Tests
    // =========================================================================

    public function testDeleteReturnsTrueOnSuccess(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
            ->method('execute')
            ->with([1])
            ->willReturn(true);
        $stmt->expects($this->once())
            ->method('rowCount')
            ->willReturn(1);

        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('DELETE FROM'))
            ->willReturn($stmt);

        $repository = $this->createRepository($pdo);

        $this->assertTrue($repository->delete(1));
    }

    public function testDeleteReturnsFalseWhenNoRowsAffected(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
            ->method('execute')
            ->willReturn(true);
        $stmt->expects($this->once())
            ->method('rowCount')
            ->willReturn(0);

        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('prepare')
            ->willReturn($stmt);

        $repository = $this->createRepository($pdo);

        $this->assertFalse($repository->delete(999));
    }

    public function testDeleteReturnsFalseOnException(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('prepare')
            ->willThrowException(new PDOException('Delete failed'));

        $repository = $this->createRepository($pdo);

        $this->assertFalse($repository->delete(1));
    }

    // =========================================================================
    // exists() Tests
    // =========================================================================

    public function testExistsReturnsTrueWhenRecordExists(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
            ->method('execute')
            ->with([1])
            ->willReturn(true);
        $stmt->expects($this->once())
            ->method('fetch')
            ->willReturn([1]);

        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('SELECT 1'))
            ->willReturn($stmt);

        $repository = $this->createRepository($pdo);

        $this->assertTrue($repository->exists(1));
    }

    public function testExistsReturnsFalseWhenRecordDoesNotExist(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
            ->method('execute')
            ->willReturn(true);
        $stmt->expects($this->once())
            ->method('fetch')
            ->willReturn(false);

        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('prepare')
            ->willReturn($stmt);

        $repository = $this->createRepository($pdo);

        $this->assertFalse($repository->exists(999));
    }

    public function testExistsReturnsFalseOnException(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('prepare')
            ->willThrowException(new PDOException('Query failed'));

        $repository = $this->createRepository($pdo);

        $this->assertFalse($repository->exists(1));
    }

    // =========================================================================
    // count() Tests
    // =========================================================================

    public function testCountReturnsRecordCount(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
            ->method('execute')
            ->willReturn(true);
        $stmt->expects($this->once())
            ->method('fetchColumn')
            ->willReturn(42);

        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('SELECT COUNT(*)'))
            ->willReturn($stmt);

        $repository = $this->createRepository($pdo);

        $this->assertEquals(42, $repository->count());
    }

    public function testCountWithCriteriaReturnsFilteredCount(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
            ->method('execute')
            ->with(['active'])
            ->willReturn(true);
        $stmt->expects($this->once())
            ->method('fetchColumn')
            ->willReturn(5);

        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('WHERE'))
            ->willReturn($stmt);

        $repository = $this->createRepository($pdo);

        $this->assertEquals(5, $repository->count(['status' => 'active']));
    }

    public function testCountReturnsZeroOnException(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('prepare')
            ->willThrowException(new PDOException('Query failed'));

        $repository = $this->createRepository($pdo);

        $this->assertEquals(0, $repository->count());
    }

    public function testCountReturnsZeroWhenNoConnection(): void
    {
        $repository = $this->createRepositoryWithoutConnection();

        $this->assertEquals(0, $repository->count());
    }

    // =========================================================================
    // Transaction Tests
    // =========================================================================

    public function testBeginTransactionReturnsTrue(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('beginTransaction')
            ->willReturn(true);

        $repository = $this->createRepository($pdo);

        $this->assertTrue($repository->beginTransaction());
    }

    public function testBeginTransactionReturnsFalseOnException(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('beginTransaction')
            ->willThrowException(new PDOException('Already in transaction'));

        $repository = $this->createRepository($pdo);

        $this->assertFalse($repository->beginTransaction());
    }

    public function testBeginTransactionReturnsFalseWhenNoConnection(): void
    {
        $repository = $this->createRepositoryWithoutConnection();

        $this->assertFalse($repository->beginTransaction());
    }

    public function testCommitReturnsTrue(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('commit')
            ->willReturn(true);

        $repository = $this->createRepository($pdo);

        $this->assertTrue($repository->commit());
    }

    public function testCommitReturnsFalseOnException(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('commit')
            ->willThrowException(new PDOException('No active transaction'));

        $repository = $this->createRepository($pdo);

        $this->assertFalse($repository->commit());
    }

    public function testCommitReturnsFalseWhenNoConnection(): void
    {
        $repository = $this->createRepositoryWithoutConnection();

        $this->assertFalse($repository->commit());
    }

    public function testRollbackReturnsTrue(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('rollBack')
            ->willReturn(true);

        $repository = $this->createRepository($pdo);

        $this->assertTrue($repository->rollback());
    }

    public function testRollbackReturnsFalseOnException(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('rollBack')
            ->willThrowException(new PDOException('No active transaction'));

        $repository = $this->createRepository($pdo);

        $this->assertFalse($repository->rollback());
    }

    public function testRollbackReturnsFalseWhenNoConnection(): void
    {
        $repository = $this->createRepositoryWithoutConnection();

        $this->assertFalse($repository->rollback());
    }

    // =========================================================================
    // isAvailable() Tests
    // =========================================================================

    public function testIsAvailableReturnsTrueWhenConnected(): void
    {
        $stmt = $this->createMock(PDOStatement::class);

        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('query')
            ->with('SELECT 1')
            ->willReturn($stmt);

        $repository = $this->createRepository($pdo);

        $this->assertTrue($repository->isAvailable());
    }

    public function testIsAvailableReturnsFalseOnException(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('query')
            ->willThrowException(new PDOException('Connection lost'));

        $repository = $this->createRepository($pdo);

        $this->assertFalse($repository->isAvailable());
    }

    public function testIsAvailableReturnsFalseWhenNoConnection(): void
    {
        $repository = $this->createRepositoryWithoutConnection();

        $this->assertFalse($repository->isAvailable());
    }

    // =========================================================================
    // Identifier Quoting Tests
    // =========================================================================

    public function testPostgresUsesDoubleQuotesForIdentifiers(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(['id' => 1]);

        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('"test_table"'))
            ->willReturn($stmt);

        $repository = $this->createPostgresRepository($pdo);

        $repository->find(1);
    }

    public function testMariaDbUsesBackticksForIdentifiers(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(['id' => 1]);

        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('`test_table`'))
            ->willReturn($stmt);

        $repository = $this->createRepository($pdo); // MariaDB by default

        $repository->find(1);
    }

    // =========================================================================
    // Encryption Tests
    // =========================================================================

    public function testFindDecryptsEncryptedFields(): void
    {
        $encryptedRow = ['id' => 1, 'secret' => 'encrypted_value'];

        $fetchStmt = $this->createMock(PDOStatement::class);
        $fetchStmt->expects($this->once())
            ->method('execute')
            ->willReturn(true);
        $fetchStmt->expects($this->once())
            ->method('fetch')
            ->willReturn($encryptedRow);

        $decryptStmt = $this->createMock(PDOStatement::class);
        $decryptStmt->expects($this->once())
            ->method('execute')
            ->with(['encrypted_value', 'test_key'])
            ->willReturn(true);
        $decryptStmt->expects($this->once())
            ->method('fetchColumn')
            ->willReturn('decrypted_value');

        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->exactly(2))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls($fetchStmt, $decryptStmt);

        $repository = $this->createRepositoryWithEncryption($pdo, ['secret']);

        $result = $repository->find(1);

        $this->assertNotNull($result);
        $this->assertEquals('decrypted_value', $result['secret']);
    }

    public function testInsertEncryptsEncryptedFields(): void
    {
        $encryptStmt = $this->createMock(PDOStatement::class);
        $encryptStmt->expects($this->once())
            ->method('execute')
            ->with(['secret_value', 'test_key'])
            ->willReturn(true);
        $encryptStmt->expects($this->once())
            ->method('fetchColumn')
            ->willReturn('encrypted_value');

        $insertStmt = $this->createMock(PDOStatement::class);
        $insertStmt->expects($this->once())
            ->method('execute')
            ->with(['encrypted_value'])
            ->willReturn(true);

        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->exactly(2))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls($encryptStmt, $insertStmt);
        $pdo->method('lastInsertId')->willReturn('1');

        $repository = $this->createRepositoryWithEncryption($pdo, ['secret']);

        $result = $repository->insert(['secret' => 'secret_value']);

        $this->assertEquals(1, $result);
    }

    // =========================================================================
    // PostgreSQL Sequence Handling Tests
    // =========================================================================

    public function testInsertHandlesPostgresSequenceForZeroLastInsertId(): void
    {
        $insertStmt = $this->createMock(PDOStatement::class);
        $insertStmt->expects($this->once())
            ->method('execute')
            ->willReturn(true);

        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('prepare')
            ->willReturn($insertStmt);
        $pdo->expects($this->exactly(2))
            ->method('lastInsertId')
            ->willReturnOnConsecutiveCalls('0', '42'); // First returns 0, then sequence value

        $repository = $this->createPostgresRepository($pdo);

        $result = $repository->insert(['name' => 'Test']);

        $this->assertEquals(42, $result);
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    /**
     * Create a DatabaseConfigInterface stub for MariaDB
     */
    private function createMockMariaDbConfig(): DatabaseConfigInterface
    {
        return new DatabaseConfigStub(postgres: false);
    }

    /**
     * Create a DatabaseConfigInterface stub for PostgreSQL
     */
    private function createMockPostgresConfig(): DatabaseConfigInterface
    {
        return new DatabaseConfigStub(postgres: true);
    }

    /**
     * Create a testable MariaDB repository with mocked PDO connection
     *
     * @throws ReflectionException
     */
    private function createRepository(PDO $pdo): MariaDbTestableRepository
    {
        $config     = $this->createMockMariaDbConfig();
        $repository = new MariaDbTestableRepository($config);
        $repository->setPdo($pdo);

        return $repository;
    }

    /**
     * Create a testable PostgreSQL repository with mocked PDO connection
     *
     * @throws ReflectionException
     */
    private function createPostgresRepository(PDO $pdo): PostgresTestableRepository
    {
        $config     = $this->createMockPostgresConfig();
        $repository = new PostgresTestableRepository($config);
        $repository->setPdo($pdo);

        return $repository;
    }

    /**
     * Create a testable repository without connection (for null connection tests)
     */
    private function createRepositoryWithoutConnection(): MariaDbTestableRepository
    {
        $config = $this->createMockMariaDbConfig();

        return new MariaDbTestableRepository($config);
    }

    /**
     * Create a testable repository with encryption support
     *
     * @param string[] $encryptedFields
     *
     * @throws ReflectionException
     */
    private function createRepositoryWithEncryption(PDO $pdo, array $encryptedFields): EncryptedTestableRepository
    {
        $config     = $this->createMockMariaDbConfig();
        $repository = new EncryptedTestableRepository($config, $encryptedFields);
        $repository->setPdo($pdo);

        return $repository;
    }
}

/**
 * Concrete MariaDB implementation for testing AbstractPdoRepository
 */
class MariaDbTestableRepository extends AbstractPdoRepository
{
    public function __construct(DatabaseConfigInterface $config)
    {
        parent::__construct($config);
    }

    /**
     * Inject PDO connection for testing
     *
     * @throws ReflectionException
     */
    public function setPdo(PDO $pdo): void
    {
        $reflection = new ReflectionClass(AbstractPdoRepository::class);
        $property   = $reflection->getProperty('pdo');
        $property->setValue($this, $pdo);
    }

    #[Override]
    protected function getTable(): string
    {
        return 'test_table';
    }

    #[Override]
    protected function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    #[Override]
    protected function isPostgres(): bool
    {
        return false;
    }
}

/**
 * Concrete PostgreSQL implementation for testing AbstractPdoRepository
 */
class PostgresTestableRepository extends AbstractPdoRepository
{
    public function __construct(DatabaseConfigInterface $config)
    {
        parent::__construct($config);
    }

    /**
     * Inject PDO connection for testing
     *
     * @throws ReflectionException
     */
    public function setPdo(PDO $pdo): void
    {
        $reflection = new ReflectionClass(AbstractPdoRepository::class);
        $property   = $reflection->getProperty('pdo');
        $property->setValue($this, $pdo);
    }

    #[Override]
    protected function getTable(): string
    {
        return 'test_table';
    }

    #[Override]
    protected function quoteIdentifier(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }

    #[Override]
    protected function isPostgres(): bool
    {
        return true;
    }
}

/**
 * Concrete implementation with encryption support for testing
 */
class EncryptedTestableRepository extends AbstractPdoRepository
{
    /**
     * @param string[] $encryptedFields
     */
    public function __construct(
        DatabaseConfigInterface $config,
        private readonly array $encryptedFields = []
    ) {
        parent::__construct($config);
    }

    /**
     * Inject PDO connection for testing
     *
     * @throws ReflectionException
     */
    public function setPdo(PDO $pdo): void
    {
        $reflection = new ReflectionClass(AbstractPdoRepository::class);
        $property   = $reflection->getProperty('pdo');
        $property->setValue($this, $pdo);
    }

    #[Override]
    protected function getTable(): string
    {
        return 'test_table';
    }

    #[Override]
    protected function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    /**
     * @return string[]
     */
    #[Override]
    protected function getEncryptedFields(): array
    {
        return $this->encryptedFields;
    }

    #[Override]
    protected function getEncryptionKey(): ?string
    {
        return 'test_key';
    }
}
