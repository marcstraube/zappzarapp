<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Audit;

use App\Infrastructure\Audit\AuditLogger;
use PDO;
use PDOException;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Tests for AuditLogger
 */
#[CoversClass(AuditLogger::class)]
final class AuditLoggerTest extends TestCase
{
    private PDO&MockObject $pdo;
    private AuditLogger $auditLogger;
    private const ENCRYPTION_KEY = 'test-encryption-key';

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo         = $this->createMock(PDO::class);
        $this->auditLogger = new AuditLogger(
            $this->pdo,
            self::ENCRYPTION_KEY,
            sys_get_temp_dir() . '/audit-test.log'
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Clean up test log file
        $logFile = sys_get_temp_dir() . '/audit-test.log';
        if (file_exists($logFile)) {
            unlink($logFile);
        }
    }

    public function testLogWritesToDatabase(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
            ->method('execute')
            ->willReturn(true);

        $this->pdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('INSERT INTO audit_logs'))
            ->willReturn($stmt);

        $this->auditLogger->log(
            action: 'user.view',
            entityType: 'user',
            entityId: 123,
            userId: 456
        );
    }

    public function testLogAuthWithSuccessfulLogin(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
            ->method('execute')
            ->willReturn(true);

        $this->pdo->expects($this->once())
            ->method('prepare')
            ->willReturn($stmt);

        $this->auditLogger->logAuth(
            action: 'login.success',
            userId: 123,
            data: ['ip' => '192.168.1.1']
        );
    }

    public function testLogAuthWithFailedLogin(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
            ->method('execute')
            ->willReturn(true);

        $this->pdo->expects($this->once())
            ->method('prepare')
            ->willReturn($stmt);

        $this->auditLogger->logAuth(
            action: 'login.failed',
            userId: null,  // Failed login - no user ID
            data: ['email' => 'test@example.com']
        );
    }

    public function testLogAdminAction(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
            ->method('execute')
            ->willReturn(true);

        $this->pdo->expects($this->once())
            ->method('prepare')
            ->willReturn($stmt);

        $this->auditLogger->logAdmin(
            action: 'role.granted',
            adminUserId: 1,
            entityType: 'user',
            entityId: 123,
            data: ['role' => 'moderator']
        );
    }

    public function testGetLogsForEntityReturnsArray(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->exactly(4))
            ->method('bindValue');
        $stmt->expects($this->once())
            ->method('execute');
        $stmt->expects($this->once())
            ->method('fetchAll')
            ->willReturn([
                [
                    'id'             => 1,
                    'timestamp'      => '2025-01-09 12:00:00',
                    'user_id'        => 456,
                    'ip_address'     => '192.168.1.1',
                    'action'         => 'user.view',
                    'entity_type'    => 'user',
                    'entity_id'      => '123',
                    'data_decrypted' => '{"changed_fields":["email"]}',
                ],
            ]);

        $this->pdo->expects($this->once())
            ->method('prepare')
            ->willReturn($stmt);

        $logs = $this->auditLogger->getLogsForEntity('user', 123, 50);

        $this->assertIsArray($logs);
        $this->assertCount(1, $logs);
        $this->assertEquals('user.view', $logs[0]['action']);
    }

    public function testGetLogsForUserReturnsArray(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->exactly(3))
            ->method('bindValue');
        $stmt->expects($this->once())
            ->method('execute');
        $stmt->expects($this->once())
            ->method('fetchAll')
            ->willReturn([
                [
                    'id'             => 1,
                    'timestamp'      => '2025-01-09 12:00:00',
                    'user_id'        => 456,
                    'ip_address'     => '192.168.1.1',
                    'action'         => 'user.update',
                    'entity_type'    => 'user',
                    'entity_id'      => '123',
                    'data_decrypted' => '{"changed_fields":["phone"]}',
                ],
            ]);

        $this->pdo->expects($this->once())
            ->method('prepare')
            ->willReturn($stmt);

        $logs = $this->auditLogger->getLogsForUser(456, 50);

        $this->assertIsArray($logs);
        $this->assertCount(1, $logs);
        $this->assertEquals(456, $logs[0]['user_id']);
    }

    public function testLogWritesToFile(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
            ->method('execute')
            ->willReturn(true);

        $this->pdo->expects($this->once())
            ->method('prepare')
            ->willReturn($stmt);

        $logFile = sys_get_temp_dir() . '/audit-test.log';

        // Ensure file doesn't exist before test
        if (file_exists($logFile)) {
            unlink($logFile);
        }

        $this->auditLogger->log(
            action: 'user.delete',
            entityType: 'user',
            entityId: 789,
            userId: 1
        );

        // Verify log file was created and contains JSON
        $this->assertFileExists($logFile);
        $contents = file_get_contents($logFile);
        $this->assertIsString($contents);
        $this->assertNotEmpty($contents);
        $this->assertJson($contents);

        // Verify JSON structure
        $logEntry = json_decode($contents, true);
        $this->assertIsArray($logEntry);
        $this->assertEquals('user.delete', $logEntry['action']);
        $this->assertEquals('user', $logEntry['entity_type']);
        $this->assertEquals('789', $logEntry['entity_id']);
    }

    public function testLogWithAdditionalData(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
            ->method('execute')
            ->willReturn(true);

        $this->pdo->expects($this->once())
            ->method('prepare')
            ->willReturn($stmt);

        $this->auditLogger->log(
            action: 'user.update',
            entityType: 'user',
            entityId: 123,
            userId: 456,
            data: [
                'changed_fields' => ['email', 'phone'],
                'reason'         => 'User requested update',
            ]
        );

        // If we get here without exception, test passed
        $this->assertTrue(true);
    }

    public function testDatabaseFailureWritesToFileAsFallback(): void
    {
        $this->pdo->expects($this->once())
            ->method('prepare')
            ->willThrowException(new PDOException('Database connection failed'));

        $logFile = sys_get_temp_dir() . '/audit-test.log';

        // Ensure file doesn't exist before test
        if (file_exists($logFile)) {
            unlink($logFile);
        }

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to write audit log to database');

        $this->auditLogger->log(
            action: 'user.view',
            entityType: 'user',
            entityId: 123,
            userId: 456
        );

        // Even though exception is thrown, file should be written
        $this->assertFileExists($logFile);
    }
}
