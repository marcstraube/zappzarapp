<?php

declare(strict_types=1);

namespace Tests\App\Unit\Infrastructure\Audit;

use App\Infrastructure\Audit\HasAuditLogging;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Zappzarapp\AuditLogger\AuditLogEntry;
use Zappzarapp\AuditLogger\AuditLoggerInterface;

/**
 * Tests for HasAuditLogging trait
 *
 * Uses PHPUnit 12's CoversTrait attribute so trait lines are attributed
 * correctly to HasAuditLogging.php in coverage reports.
 */
#[CoversTrait(HasAuditLogging::class)]
final class HasAuditLoggingTest extends TestCase
{
    #[Test]
    public function testAuditLogCallsLogger(): void
    {
        $_SERVER['REMOTE_ADDR']     = '10.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'TestBrowser/1.0';

        $logger = $this->createMock(AuditLoggerInterface::class);
        $logger->expects($this->once())
            ->method('log')
            ->with($this->equalTo(new AuditLogEntry(
                action: 'user.view',
                entityType: 'user',
                entityId: 123,
                userId: 456,
                ipAddress: '10.0.0.1',
                userAgent: 'TestBrowser/1.0',
                data: ['context' => 'test'],
            )));

        $service = new TestServiceWithAuditLogging($logger);
        $service->testAuditLog();

        unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
    }

    #[Test]
    public function testAuditLogAuthCallsLogger(): void
    {
        $_SERVER['REMOTE_ADDR']     = '10.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'TestBrowser/1.0';

        $logger = $this->createMock(AuditLoggerInterface::class);
        $logger->expects($this->once())
            ->method('logAuth')
            ->with(
                'login.success',
                123,
                ['ip' => '192.168.1.1'],
                '10.0.0.1',
                'TestBrowser/1.0'
            );

        $service = new TestServiceWithAuditLogging($logger);
        $service->testAuditLogAuth();

        unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
    }

    #[Test]
    public function testAuditLogAdminCallsLogger(): void
    {
        $_SERVER['REMOTE_ADDR']     = '10.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'TestBrowser/1.0';

        $logger = $this->createMock(AuditLoggerInterface::class);
        $logger->expects($this->once())
            ->method('logAdmin')
            ->with(
                'role.granted',
                1,
                'user',
                123,
                ['role' => 'moderator'],
                '10.0.0.1',
                'TestBrowser/1.0'
            );

        $service = new TestServiceWithAuditLogging($logger);
        $service->testAuditLogAdmin();

        unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
    }

    #[Test]
    public function testAuditLogFallsBackToUnknownWhenServerVarsNotSet(): void
    {
        unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);

        $logger = $this->createMock(AuditLoggerInterface::class);
        $logger->expects($this->once())
            ->method('log')
            ->with($this->equalTo(new AuditLogEntry(
                action: 'user.view',
                entityType: 'user',
                entityId: 123,
                userId: 456,
                ipAddress: 'unknown',
                userAgent: 'unknown',
                data: ['context' => 'test'],
            )));

        $service = new TestServiceWithAuditLogging($logger);
        $service->testAuditLog();
    }

    #[Test]
    public function testAuditLogThrowsExceptionWhenLoggerNotInitialized(): void
    {
        $service = new TestServiceWithoutLogger();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('AuditLogger not initialized');

        $service->testAuditLogWithoutLogger();
    }

    #[Test]
    public function testAuditLogUsesSessionUserIdIfNotProvided(): void
    {
        $_SESSION['user_id']        = 789;
        $_SERVER['REMOTE_ADDR']     = '10.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'TestBrowser/1.0';

        $logger = $this->createMock(AuditLoggerInterface::class);
        $logger->expects($this->once())
            ->method('log')
            ->with($this->equalTo(new AuditLogEntry(
                action: 'user.view',
                entityType: 'user',
                entityId: 123,
                userId: 789,
                ipAddress: '10.0.0.1',
                userAgent: 'TestBrowser/1.0',
            )));

        $service = new TestServiceWithAuditLogging($logger);
        $service->testAuditLogWithoutUserId();

        unset($_SESSION['user_id'], $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
    }

    #[Test]
    public function testAuditLogLeavesUserIdNullWhenNoSessionAndNoArgument(): void
    {
        unset($_SESSION['user_id']);
        $_SERVER['REMOTE_ADDR']     = '10.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'TestBrowser/1.0';

        $logger = $this->createMock(AuditLoggerInterface::class);
        $logger->expects($this->once())
            ->method('log')
            ->with($this->equalTo(new AuditLogEntry(
                action: 'user.view',
                entityType: 'user',
                entityId: 123,
                ipAddress: '10.0.0.1',
                userAgent: 'TestBrowser/1.0',
            )));

        $service = new TestServiceWithAuditLogging($logger);
        $service->testAuditLogWithoutUserId();

        unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
    }

    #[Test]
    public function testAuditLogAuthThrowsExceptionWhenLoggerNotInitialized(): void
    {
        $service = new TestServiceWithoutLogger();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('AuditLogger not initialized');

        $service->testAuditLogAuthWithoutLogger();
    }

    #[Test]
    public function testAuditLogAdminThrowsExceptionWhenLoggerNotInitialized(): void
    {
        $service = new TestServiceWithoutLogger();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('AuditLogger not initialized');

        $service->testAuditLogAdminWithoutLogger();
    }
}

/**
 * Test class that uses the HasAuditLogging trait
 */
class TestServiceWithAuditLogging
{
    use HasAuditLogging;

    /** @noinspection PhpPropertyCanBeReadonlyInspection readonly breaks PHPStan isset() analysis in trait */
    public function __construct(private AuditLoggerInterface $auditLogger) // @phpstan-ignore property.onlyWritten (read by HasAuditLogging trait via isset)
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

    public function testAuditLogAuthWithoutLogger(): void
    {
        $this->auditLogAuth(action: 'login.success');
    }

    public function testAuditLogAdminWithoutLogger(): void
    {
        $this->auditLogAdmin(
            action: 'role.granted',
            adminUserId: 1,
            entityType: 'user',
            entityId: 99
        );
    }
}
