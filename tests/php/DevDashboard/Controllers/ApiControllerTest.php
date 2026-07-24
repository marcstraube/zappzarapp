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
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ApiController — health check, services status, log content, and coverage
 *
 * All service dependencies are stubbed/mocked so tests are fully isolated from
 * the database, filesystem, and Docker environment.
 *
 * ApiController reads from $_GET and php://input — those are set in each
 * test that needs them and reset in tearDown.
 */
#[CoversClass(ApiController::class)]
#[UsesClass(JsonResponse::class)]
final class ApiControllerTest extends TestCase
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

    // ===== healthCheck() =====

    #[Test]
    public function testHealthCheckReturnsResponse(): void
    {
        $healthCheck = $this->createStub(HealthCheckService::class);
        $healthCheck->method('getOverallStatus')
            ->willReturn(['status' => 'healthy', 'healthy_count' => 3, 'unhealthy_count' => 0, 'timestamp' => 'now']);

        $response = $this->buildController(['healthCheck' => $healthCheck])->healthCheck();

        $this->assertInstanceOf(Response::class, $response);
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testHealthCheckOutputsJson(): void
    {
        $data        = ['status' => 'healthy', 'healthy_count' => 2, 'unhealthy_count' => 0, 'timestamp' => '2026-01-01'];
        $healthCheck = $this->createStub(HealthCheckService::class);
        $healthCheck->method('getOverallStatus')->willReturn($data);

        ob_start();
        $this->buildController(['healthCheck' => $healthCheck])->healthCheck()->send();
        $output = ob_get_clean();

        $decoded = json_decode($output ?: '', true);
        $this->assertIsArray($decoded);
        $this->assertSame('healthy', $decoded['status']);
    }

    #[Test]
    public function testHealthCheckDelegatesGetOverallStatus(): void
    {
        $healthCheck = $this->createMock(HealthCheckService::class);
        $healthCheck->expects($this->once())
            ->method('getOverallStatus')
            ->willReturn(['status' => 'healthy', 'healthy_count' => 0, 'unhealthy_count' => 0, 'timestamp' => '']);

        $this->buildController(['healthCheck' => $healthCheck])->healthCheck();
    }

    // ===== servicesStatus() =====

    #[Test]
    public function testServicesStatusReturnsResponse(): void
    {
        $healthCheck = $this->createStub(HealthCheckService::class);
        $healthCheck->method('getServices')
            ->willReturn(['core' => [], 'data' => [], 'optional' => []]);

        $response = $this->buildController(['healthCheck' => $healthCheck])->servicesStatus();

        $this->assertInstanceOf(Response::class, $response);
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testServicesStatusOutputsServicesJson(): void
    {
        $services    = ['core' => ['nginx' => ['status' => 'running']], 'data' => [], 'optional' => []];
        $healthCheck = $this->createStub(HealthCheckService::class);
        $healthCheck->method('getServices')->willReturn($services);

        ob_start();
        $this->buildController(['healthCheck' => $healthCheck])->servicesStatus()->send();
        $output = ob_get_clean();

        $decoded = json_decode($output ?: '', true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('core', $decoded);
    }

    #[Test]
    public function testServicesStatusDelegatesGetServices(): void
    {
        $healthCheck = $this->createMock(HealthCheckService::class);
        $healthCheck->expects($this->once())
            ->method('getServices')
            ->willReturn([]);

        $this->buildController(['healthCheck' => $healthCheck])->servicesStatus();
    }

    // ===== logContent() =====

    #[Test]
    public function testLogContentReturnsBadRequestWhenNoFilename(): void
    {
        $_GET = [];

        ob_start();
        $this->buildController()->logContent()->send();
        $output = ob_get_clean();

        $decoded = json_decode($output ?: '', true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('error', $decoded);
        $this->assertStringContainsString('No filename provided', $decoded['error']);
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testLogContentReturnsBadRequestStatus400WhenNoFilename(): void
    {
        $_GET = [];

        ob_start();
        $this->buildController()->logContent()->send();
        ob_get_clean();

        $this->assertSame(400, http_response_code());
    }

    #[Test]
    public function testLogContentCallsLogServiceWithFilenameAndDefaultLines(): void
    {
        $_GET = ['file' => 'app.log'];

        $log = $this->createMock(LogService::class);
        $log->expects($this->once())
            ->method('readLogFile')
            ->with('app.log', 100)
            ->willReturn(['filename' => 'app.log', 'content' => 'log data', 'lines' => 100, 'size' => 100, 'modified' => 0]);

        $this->buildController(['log' => $log])->logContent();
    }

    #[Test]
    public function testLogContentCallsLogServiceWithSpecifiedLineCount(): void
    {
        $_GET = ['file' => 'error.log', 'lines' => '50'];

        $log = $this->createMock(LogService::class);
        $log->expects($this->once())
            ->method('readLogFile')
            ->with('error.log', 50)
            ->willReturn(['filename' => 'error.log', 'content' => '', 'lines' => 50, 'size' => 0, 'modified' => 0]);

        $this->buildController(['log' => $log])->logContent();
    }

    #[Test]
    public function testLogContentCapsLinesAt500(): void
    {
        $_GET = ['file' => 'big.log', 'lines' => '9999'];

        $log = $this->createMock(LogService::class);
        $log->expects($this->once())
            ->method('readLogFile')
            ->with('big.log', 500)
            ->willReturn(['filename' => 'big.log', 'content' => '', 'lines' => 500, 'size' => 0, 'modified' => 0]);

        $this->buildController(['log' => $log])->logContent();
    }

    // ===== generateCoverage() =====

    #[RunInSeparateProcess]
    #[Test]
    public function testGenerateCoverageReturnsBadRequestForNonPhpType(): void
    {
        $_GET = ['type' => 'node'];

        ob_start();
        $this->buildController()->generateCoverage()->send();
        ob_get_clean();

        $this->assertSame(400, http_response_code());
    }

    #[Test]
    public function testGenerateCoverageReturnsErrorMessageForNonPhpType(): void
    {
        $_GET = ['type' => 'node'];

        ob_start();
        $this->buildController()->generateCoverage()->send();
        $output = ob_get_clean();

        $decoded = json_decode($output ?: '', true);
        $this->assertIsArray($decoded);
        $this->assertFalse($decoded['success']);
        $this->assertStringContainsString('make test-coverage-node', $decoded['message']);
    }

    #[Test]
    public function testGenerateCoverageDefaultsToPhpType(): void
    {
        $_GET = []; // no 'type' key → defaults to 'php'

        $quality = $this->createMock(QualityService::class);
        $quality->expects($this->once())
            ->method('runPhpCoverage')
            ->willReturn(['success' => true, 'message' => 'done', 'output' => '']);

        $this->buildController(['quality' => $quality])->generateCoverage();
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testGenerateCoverageReturns200OnSuccess(): void
    {
        $_GET = ['type' => 'php'];

        $quality = $this->createStub(QualityService::class);
        $quality->method('runPhpCoverage')
            ->willReturn(['success' => true, 'message' => 'Coverage generated', 'output' => '']);

        ob_start();
        $this->buildController(['quality' => $quality])->generateCoverage()->send();
        ob_get_clean();

        $this->assertSame(200, http_response_code());
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testGenerateCoverageReturns500OnFailure(): void
    {
        $_GET = ['type' => 'php'];

        $quality = $this->createStub(QualityService::class);
        $quality->method('runPhpCoverage')
            ->willReturn(['success' => false, 'message' => 'Failed', 'output' => 'error details']);

        ob_start();
        $this->buildController(['quality' => $quality])->generateCoverage()->send();
        ob_get_clean();

        $this->assertSame(500, http_response_code());
    }
}
