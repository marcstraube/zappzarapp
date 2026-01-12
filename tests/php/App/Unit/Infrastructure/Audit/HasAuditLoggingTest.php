<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Audit;

use App\Infrastructure\Audit\AuditLoggerInterface;
use App\Infrastructure\Audit\HasAuditLogging;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Tests for HasAuditLogging trait
 *
 * Note: Trait coverage is measured through the test classes that use it
 * (TestServiceWithAuditLogging, TestServiceWithoutLogger)
 */
#[CoversNothing]
final class HasAuditLoggingTest extends TestCase
{
    public function testAuditLogCallsLogger(): void
    {
        $logger = $this->createMock(AuditLoggerInterface::class);
        $logger->expects($this->once())
            ->method('log')
            ->with(
                'user.view',
                'user',
                123,
                456,
                ['context' => 'test']
            );

        $service = new TestServiceWithAuditLogging($logger);
        $service->testAuditLog();
    }

    public function testAuditLogAuthCallsLogger(): void
    {
        $logger = $this->createMock(AuditLoggerInterface::class);
        $logger->expects($this->once())
            ->method('logAuth')
            ->with(
                'login.success',
                123,
                ['ip' => '192.168.1.1']
            );

        $service = new TestServiceWithAuditLogging($logger);
        $service->testAuditLogAuth();
    }

    public function testAuditLogAdminCallsLogger(): void
    {
        $logger = $this->createMock(AuditLoggerInterface::class);
        $logger->expects($this->once())
            ->method('logAdmin')
            ->with(
                'role.granted',
                1,
                'user',
                123,
                ['role' => 'moderator']
            );

        $service = new TestServiceWithAuditLogging($logger);
        $service->testAuditLogAdmin();
    }

    public function testAuditLogThrowsExceptionWhenLoggerNotInitialized(): void
    {
        $service = new TestServiceWithoutLogger();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('AuditLogger not initialized');

        $service->testAuditLogWithoutLogger();
    }

    public function testAuditLogUsesSessionUserIdIfNotProvided(): void
    {
        // Simulate session
        $_SESSION['user_id'] = 789;

        $logger = $this->createMock(AuditLoggerInterface::class);
        $logger->expects($this->once())
            ->method('log')
            ->with(
                'user.view',
                'user',
                123,
                789,  // Should use session user_id
                []
            );

        $service = new TestServiceWithAuditLogging($logger);
        $service->testAuditLogWithoutUserId();

        // Clean up session
        unset($_SESSION['user_id']);
    }
}

/**
 * Test class that uses the HasAuditLogging trait
 */
class TestServiceWithAuditLogging
{
    use HasAuditLogging;

    public function __construct(private AuditLoggerInterface $auditLogger)
    {
    }

    public function testAuditLog(): void
    {
        $this->auditLog(
            action: 'user.view',
            entityType: 'user',
            entityId: 123,
            userId: 456,
            data: ['context' => 'test']
        );
    }

    public function testAuditLogAuth(): void
    {
        $this->auditLogAuth(
            action: 'login.success',
            userId: 123,
            data: ['ip' => '192.168.1.1']
        );
    }

    public function testAuditLogAdmin(): void
    {
        $this->auditLogAdmin(
            action: 'role.granted',
            adminUserId: 1,
            entityType: 'user',
            entityId: 123,
            data: ['role' => 'moderator']
        );
    }

    public function testAuditLogWithoutUserId(): void
    {
        $this->auditLog(
            action: 'user.view',
            entityType: 'user',
            entityId: 123
            // userId not provided - should use $_SESSION['user_id']
        );
    }
}

/**
 * Test class without AuditLogger (should throw exception)
 */
class TestServiceWithoutLogger
{
    use HasAuditLogging;

    public function testAuditLogWithoutLogger(): void
    {
        $this->auditLog(
            action: 'user.view',
            entityType: 'user',
            entityId: 123
        );
    }
}
