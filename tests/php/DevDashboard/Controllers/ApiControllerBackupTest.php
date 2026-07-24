<?php

declare(strict_types=1);

namespace Tests\DevDashboard\Controllers;

use DevDashboard\Controllers\ApiController;
use DevDashboard\Response\JsonResponse;
use DevDashboard\Response\Response;
use DevDashboard\Services\DatabaseService;
use DevDashboard\Services\HealthCheckService;
use DevDashboard\Services\LogService;
use DevDashboard\Services\QualityService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ApiController — backup operations (list, create, restore, delete)
 *
 * All service dependencies are stubbed/mocked so tests are fully isolated from
 * the database, filesystem, and Docker environment.
 */
#[CoversClass(ApiController::class)]
#[UsesClass(JsonResponse::class)]
final class ApiControllerBackupTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Reset superglobals before each test
        $_GET = [];
    }

    protected function tearDown(): void
    {
        $_GET = [];

        parent::tearDown();
    }

    // ===== Helper: build a controller with stubs, with one optional mock override =====

    /**
     * @param array{healthCheck?: HealthCheckService, quality?: QualityService, log?: LogService, database?: DatabaseService} $overrides
     */
    private function buildController(array $overrides = []): ApiController
    {
        return new ApiController(
            $overrides['healthCheck'] ?? $this->createStub(HealthCheckService::class),
            $overrides['quality'] ?? $this->createStub(QualityService::class),
            $overrides['log'] ?? $this->createStub(LogService::class),
            $overrides['database'] ?? $this->createStub(DatabaseService::class),
        );
    }

    // ===== listBackups() =====

    public function testListBackupsReturnsResponse(): void
    {
        $database = $this->createStub(DatabaseService::class);
        $database->method('listBackups')->willReturn(['backups' => [], 'count' => 0]);

        $response = $this->buildController(['database' => $database])->listBackups();

        $this->assertInstanceOf(Response::class, $response);
    }

    #[RunInSeparateProcess]
    public function testListBackupsReturns200(): void
    {
        $database = $this->createStub(DatabaseService::class);
        $database->method('listBackups')->willReturn(['backups' => [], 'count' => 0]);

        ob_start();
        $this->buildController(['database' => $database])->listBackups()->send();
        ob_get_clean();

        $this->assertSame(200, http_response_code());
    }

    public function testListBackupsDelegatesListBackups(): void
    {
        $database = $this->createMock(DatabaseService::class);
        $database->expects($this->once())
            ->method('listBackups')
            ->willReturn(['backups' => []]);

        $this->buildController(['database' => $database])->listBackups();
    }

    // ===== createBackup() =====

    public function testCreateBackupCallsDatabaseServiceWithDefaults(): void
    {
        $_GET = [];

        $database = $this->createMock(DatabaseService::class);
        $database->expects($this->once())
            ->method('createBackup')
            ->with(null, false)
            ->willReturn(['success' => true, 'message' => 'Backup created', 'filename' => 'backup.sql']);

        $this->buildController(['database' => $database])->createBackup();
    }

    public function testCreateBackupPassesRetentionAndEncryptParams(): void
    {
        $_GET = ['retention' => '7', 'encrypt' => 'true'];

        $database = $this->createMock(DatabaseService::class);
        $database->expects($this->once())
            ->method('createBackup')
            ->with(7, true)
            ->willReturn(['success' => true, 'message' => 'Backup created', 'filename' => 'backup.sql']);

        $this->buildController(['database' => $database])->createBackup();
    }

    public function testCreateBackupEncryptFalseWhenNotTrue(): void
    {
        $_GET = ['encrypt' => 'false'];

        $database = $this->createMock(DatabaseService::class);
        $database->expects($this->once())
            ->method('createBackup')
            ->with(null, false)
            ->willReturn(['success' => true, 'message' => 'done', 'filename' => 'f.sql']);

        $this->buildController(['database' => $database])->createBackup();
    }

    #[RunInSeparateProcess]
    public function testCreateBackupReturns200OnSuccess(): void
    {
        $_GET = [];

        $database = $this->createStub(DatabaseService::class);
        $database->method('createBackup')
            ->willReturn(['success' => true, 'message' => 'Created', 'filename' => 'backup.sql']);

        ob_start();
        $this->buildController(['database' => $database])->createBackup()->send();
        ob_get_clean();

        $this->assertSame(200, http_response_code());
    }

    #[RunInSeparateProcess]
    public function testCreateBackupReturns500OnFailure(): void
    {
        $_GET = [];

        $database = $this->createStub(DatabaseService::class);
        $database->method('createBackup')
            ->willReturn(['success' => false, 'message' => 'Failed']);

        ob_start();
        $this->buildController(['database' => $database])->createBackup()->send();
        ob_get_clean();

        $this->assertSame(500, http_response_code());
    }

    // ===== restoreBackup() =====

    public function testRestoreBackupReturnsBadRequestWhenFilenameNotProvided(): void
    {
        // php://input is empty → json_decode returns [] → no 'filename' key
        ob_start();
        $this->buildController()->restoreBackup()->send();
        $output = ob_get_clean();

        $decoded = json_decode($output ?: '', true);
        $this->assertIsArray($decoded);
        $this->assertFalse($decoded['success']);
        $this->assertStringContainsString('filename', $decoded['message']);
    }

    // ===== deleteBackup() =====

    public function testDeleteBackupReturnsBadRequestWhenFilenameNotProvided(): void
    {
        ob_start();
        $this->buildController()->deleteBackup()->send();
        $output = ob_get_clean();

        $decoded = json_decode($output ?: '', true);
        $this->assertIsArray($decoded);
        $this->assertFalse($decoded['success']);
        $this->assertStringContainsString('filename', $decoded['message']);
    }
}
