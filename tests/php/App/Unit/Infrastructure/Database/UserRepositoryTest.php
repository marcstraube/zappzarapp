<?php

/* @noinspection PhpUnhandledExceptionInspection Test methods — PHPUnit catches all exceptions */
/** @noinspection PhpMultipleClassDeclarationsInspection Override is native in PHP 8.3 */

declare(strict_types=1);

namespace Tests\App\Unit\Infrastructure\Database;

use App\Infrastructure\Database\AbstractPdoRepository;
use App\Infrastructure\Database\UserRepository;
use App\Infrastructure\DatabaseConfigInterface;
use Override;
use PDO;
use PDOException;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Zappzarapp\AuditLogger\AuditLoggerInterface;
use Zappzarapp\AuditLogger\NullAuditLogger;

/**
 * Unit tests for UserRepository
 *
 * Tests user-specific repository operations including email lookup,
 * TOTP secret encryption/decryption, and duplicate email checking.
 *
 * @SuppressWarnings("PHPMD.TooManyPublicMethods") Comprehensive tests for user operations
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects") Required mocks for PDO testing
 */
#[CoversClass(UserRepository::class)]
final class UserRepositoryTest extends TestCase
{
    // =========================================================================
    // findByEmail() Tests
    // =========================================================================

    public function testFindByEmailReturnsUserWhenFound(): void
    {
        $expectedUser = [
            'id'           => 1,
            'email'        => 'user@example.com',
            'name'         => 'Test User',
            'totp_enabled' => false,
        ];

        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn([$expectedUser]);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repository = $this->createRepository($pdo);

        $this->assertEquals($expectedUser, $repository->findByEmail('user@example.com'));
    }

    public function testFindByEmailReturnsNullWhenNotFound(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn([]);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repository = $this->createRepository($pdo);

        $this->assertNull($repository->findByEmail('nonexistent@example.com'));
    }

    // =========================================================================
    // emailExists() Tests
    // =========================================================================

    public function testEmailExistsReturnsTrueWhenEmailFound(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
            ->method('execute')
            ->with(['user@example.com'])
            ->willReturn(true);
        $stmt->expects($this->once())
            ->method('fetch')
            ->willReturn([1]);

        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('WHERE'))
            ->willReturn($stmt);

        $repository = $this->createRepository($pdo);

        $this->assertTrue($repository->emailExists('user@example.com'));
    }

    public function testEmailExistsReturnsFalseWhenEmailNotFound(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(false);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repository = $this->createRepository($pdo);

        $this->assertFalse($repository->emailExists('nonexistent@example.com'));
    }

    public function testEmailExistsExcludesUserIdWhenProvided(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
            ->method('execute')
            ->with(['user@example.com', 5])
            ->willReturn(true);
        $stmt->expects($this->once())
            ->method('fetch')
            ->willReturn(false);

        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('!= ?'))
            ->willReturn($stmt);

        $repository = $this->createRepository($pdo);

        $this->assertFalse($repository->emailExists('user@example.com', 5));
    }

    public function testEmailExistsReturnsFalseOnException(): void
    {
        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')
            ->willThrowException(new PDOException('Query failed'));

        $repository = $this->createRepository($pdo);

        $this->assertFalse($repository->emailExists('user@example.com'));
    }

    // =========================================================================
    // enableTotp() Tests
    // =========================================================================

    public function testEnableTotpReturnsTrueOnSuccess(): void
    {
        $encryptStmt = $this->createStub(PDOStatement::class);
        $encryptStmt->method('execute')->willReturn(true);
        $encryptStmt->method('fetchColumn')->willReturn('encrypted_secret');

        $updateStmt = $this->createStub(PDOStatement::class);
        $updateStmt->method('execute')->willReturn(true);
        $updateStmt->method('rowCount')->willReturn(1);

        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->exactly(2))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls($encryptStmt, $updateStmt);

        $repository = $this->createRepositoryWithEncryption($pdo);

        $this->assertTrue($repository->enableTotp(1, 'JBSWY3DPEHPK3PXP'));
    }

    public function testEnableTotpReturnsFalseWhenEncryptionFails(): void
    {
        $repository = $this->createRepositoryWithoutEncryption();

        $this->assertFalse($repository->enableTotp(1, 'JBSWY3DPEHPK3PXP'));
    }

    public function testEnableTotpReturnsFalseWhenUserNotFound(): void
    {
        $encryptStmt = $this->createStub(PDOStatement::class);
        $encryptStmt->method('execute')->willReturn(true);
        $encryptStmt->method('fetchColumn')->willReturn('encrypted_secret');

        $updateStmt = $this->createStub(PDOStatement::class);
        $updateStmt->method('execute')->willReturn(true);
        $updateStmt->method('rowCount')->willReturn(0);

        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->exactly(2))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls($encryptStmt, $updateStmt);

        $repository = $this->createRepositoryWithEncryption($pdo);

        $this->assertFalse($repository->enableTotp(999, 'JBSWY3DPEHPK3PXP'));
    }

    // =========================================================================
    // disableTotp() Tests
    // =========================================================================

    public function testDisableTotpReturnsTrueOnSuccess(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
            ->method('execute')
            ->willReturn(true);
        $stmt->expects($this->once())
            ->method('rowCount')
            ->willReturn(1);

        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('totp_secret'))
            ->willReturn($stmt);

        $repository = $this->createRepository($pdo);

        $this->assertTrue($repository->disableTotp(1));
    }

    public function testDisableTotpReturnsFalseWhenUserNotFound(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('rowCount')->willReturn(0);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repository = $this->createRepository($pdo);

        $this->assertFalse($repository->disableTotp(999));
    }

    public function testDisableTotpReturnsFalseOnException(): void
    {
        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')
            ->willThrowException(new PDOException('Update failed'));

        $repository = $this->createRepository($pdo);

        $this->assertFalse($repository->disableTotp(1));
    }

    // =========================================================================
    // getTotpSecret() Tests
    // =========================================================================

    public function testGetTotpSecretReturnsDecryptedSecret(): void
    {
        $selectStmt = $this->createStub(PDOStatement::class);
        $selectStmt->method('execute')->willReturn(true);
        $selectStmt->method('fetchColumn')->willReturn('encrypted_secret');

        $decryptStmt = $this->createStub(PDOStatement::class);
        $decryptStmt->method('execute')->willReturn(true);
        $decryptStmt->method('fetchColumn')->willReturn('JBSWY3DPEHPK3PXP');

        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->exactly(2))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls($selectStmt, $decryptStmt);

        $repository = $this->createRepositoryWithEncryption($pdo);

        $this->assertEquals('JBSWY3DPEHPK3PXP', $repository->getTotpSecret(1));
    }

    public function testGetTotpSecretReturnsNullWhenTotpNotEnabled(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchColumn')->willReturn(false);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repository = $this->createRepository($pdo);

        $this->assertNull($repository->getTotpSecret(1));
    }

    public function testGetTotpSecretReturnsNullOnException(): void
    {
        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')
            ->willThrowException(new PDOException('Query failed'));

        $repository = $this->createRepository($pdo);

        $this->assertNull($repository->getTotpSecret(1));
    }

    // =========================================================================
    // getTable() / getEncryptedFields() Tests
    // =========================================================================

    /**
     */
    public function testGetTableReturnsUsers(): void
    {
        $repository = $this->createRepositoryWithoutConnection();

        $reflection = new ReflectionClass(UserRepository::class);
        $method     = $reflection->getMethod('getTable');

        $this->assertEquals('users', $method->invoke($repository));
    }

    /**
     */
    public function testGetEncryptedFieldsReturnsTotpSecret(): void
    {
        $repository = $this->createRepositoryWithoutConnection();

        $reflection = new ReflectionClass(UserRepository::class);
        $method     = $reflection->getMethod('getEncryptedFields');

        $this->assertEquals(['totp_secret'], $method->invoke($repository));
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
     * Create a testable UserRepository with mocked PDO
     *
     */
    private function createRepository(PDO $pdo): TestableUserRepository
    {
        $config     = $this->createMockMariaDbConfig();
        $repository = new TestableUserRepository($config);
        $repository->setPdo($pdo);

        return $repository;
    }

    /**
     * Create a testable UserRepository with encryption support
     *
     */
    private function createRepositoryWithEncryption(PDO $pdo): TestableUserRepositoryWithEncryption
    {
        $config     = $this->createMockMariaDbConfig();
        $repository = new TestableUserRepositoryWithEncryption($config);
        $repository->setPdo($pdo);

        return $repository;
    }

    /**
     * Create a testable UserRepository without encryption
     */
    private function createRepositoryWithoutEncryption(): TestableUserRepository
    {
        $config = $this->createMockMariaDbConfig();

        return new TestableUserRepository($config);
    }

    /**
     * Create a testable UserRepository without connection
     */
    private function createRepositoryWithoutConnection(): TestableUserRepository
    {
        $config = $this->createMockMariaDbConfig();

        return new TestableUserRepository($config);
    }
}

/**
 * Testable UserRepository that allows PDO injection for testing
 */
class TestableUserRepository extends UserRepository
{
    public function __construct(
        DatabaseConfigInterface $config,
        ?AuditLoggerInterface $auditLogger = null
    ) {
        parent::__construct($auditLogger ?? new NullAuditLogger(), $config);
    }

    /**
     * Inject PDO connection for testing
     */
    public function setPdo(PDO $pdo): void
    {
        $reflection = new ReflectionClass(AbstractPdoRepository::class);
        $property   = $reflection->getProperty('pdo');
        $property->setValue($this, $pdo);
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

    #[Override]
    protected function getEncryptionKey(): ?string
    {
        return null; // No encryption key for basic tests
    }
}

/**
 * Testable UserRepository with encryption support
 */
class TestableUserRepositoryWithEncryption extends UserRepository
{
    public function __construct(
        DatabaseConfigInterface $config,
        ?AuditLoggerInterface $auditLogger = null
    ) {
        parent::__construct($auditLogger ?? new NullAuditLogger(), $config);
    }

    /**
     * Inject PDO connection for testing
     */
    public function setPdo(PDO $pdo): void
    {
        $reflection = new ReflectionClass(AbstractPdoRepository::class);
        $property   = $reflection->getProperty('pdo');
        $property->setValue($this, $pdo);
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

    #[Override]
    protected function getEncryptionKey(): ?string
    {
        return 'test_encryption_key';
    }
}
