<?php

declare(strict_types=1);

namespace Tests\App\Unit\Http\Controller;

use App\Http\Controller\StatusController;
use App\Http\Response\JsonResponse;
use App\Infrastructure\DatabaseConfig;
use App\Infrastructure\HealthCheck;
use App\Infrastructure\TlsConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for StatusController.
 *
 * Both endpoints delegate entirely to HealthCheck — tests verify correct HTTP
 * status codes, JSON structure, and key fields without making real network or
 * database connections (all services are disabled by default in the test env).
 */
#[CoversClass(StatusController::class)]
#[UsesClass(DatabaseConfig::class)]
#[UsesClass(HealthCheck::class)]
#[UsesClass(JsonResponse::class)]
#[UsesClass(TlsConfig::class)]
final class StatusControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->clearEnvVars();
    }

    protected function tearDown(): void
    {
        $this->clearEnvVars();
        parent::tearDown();
    }

    private function clearEnvVars(): void
    {
        foreach (['ENV', 'ENABLE_NODE', 'ENABLE_DATABASE', 'ENABLE_REDIS'] as $var) {
            putenv($var);
            unset($_ENV[$var]);
        }
    }

    // =========================================================================
    // index() — GET /status
    // =========================================================================

    #[Test]
    public function testIndexReturnsJsonResponseInstance(): void
    {
        $controller = new StatusController(new HealthCheck());

        $response = $controller->index();

        $this->assertInstanceOf(JsonResponse::class, $response);
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testIndexReturnsStatus200WhenAllServicesOk(): void
    {
        // All services disabled by default → overall_status is 'ok'
        $controller = new StatusController(new HealthCheck());

        ob_start();
        $controller->index()->send();
        ob_get_clean();

        $this->assertSame(200, http_response_code());
    }

    #[Test]
    public function testIndexOutputContainsOverallStatus(): void
    {
        $controller = new StatusController(new HealthCheck());

        ob_start();
        $controller->index()->send();
        $output = ob_get_clean();

        $data = json_decode((string) $output, true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('overall_status', $data);
        $this->assertSame('ok', $data['overall_status']);
    }

    #[Test]
    public function testIndexOutputContainsServices(): void
    {
        $controller = new StatusController(new HealthCheck());

        ob_start();
        $controller->index()->send();
        $output = ob_get_clean();

        $data = json_decode((string) $output, true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('services', $data);
        $this->assertIsArray($data['services']);
        $this->assertArrayHasKey('php-fpm', $data['services']);
        $this->assertSame('ok', $data['services']['php-fpm']['status']);
    }

    #[Test]
    public function testIndexOutputContainsTimestamp(): void
    {
        $controller = new StatusController(new HealthCheck());

        ob_start();
        $controller->index()->send();
        $output = ob_get_clean();

        $data = json_decode((string) $output, true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('timestamp', $data);
        $this->assertIsString($data['timestamp']);
    }

    #[Test]
    public function testIndexOutputIsPrettyPrinted(): void
    {
        // StatusController uses JSON_PRETTY_PRINT flag
        $controller = new StatusController(new HealthCheck());

        ob_start();
        $controller->index()->send();
        $output = (string) ob_get_clean();

        // JSON_PRETTY_PRINT produces newlines
        $this->assertStringContainsString("\n", $output);
    }

    // =========================================================================
    // ready() — GET /ready
    // =========================================================================

    #[Test]
    public function testReadyReturnsJsonResponseInstance(): void
    {
        $controller = new StatusController(new HealthCheck());

        $response = $controller->ready();

        $this->assertInstanceOf(JsonResponse::class, $response);
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testReadyReturnsStatus200WhenAllServicesOk(): void
    {
        // All services disabled → readiness status is 'ok'
        $controller = new StatusController(new HealthCheck());

        ob_start();
        $controller->ready()->send();
        ob_get_clean();

        $this->assertSame(200, http_response_code());
    }

    #[Test]
    public function testReadyOutputContainsStatus(): void
    {
        $controller = new StatusController(new HealthCheck());

        ob_start();
        $controller->ready()->send();
        $output = ob_get_clean();

        $data = json_decode((string) $output, true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('status', $data);
        $this->assertSame('ok', $data['status']);
    }

    #[Test]
    public function testReadyOutputContainsChecks(): void
    {
        $controller = new StatusController(new HealthCheck());

        ob_start();
        $controller->ready()->send();
        $output = ob_get_clean();

        $data = json_decode((string) $output, true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('checks', $data);
        $this->assertIsArray($data['checks']);
    }

    #[Test]
    public function testReadyOutputContainsServiceField(): void
    {
        $controller = new StatusController(new HealthCheck());

        ob_start();
        $controller->ready()->send();
        $output = ob_get_clean();

        $data = json_decode((string) $output, true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('service', $data);
        $this->assertSame('php-backend', $data['service']);
    }

    #[Test]
    public function testReadyOutputContainsTimestamp(): void
    {
        $controller = new StatusController(new HealthCheck());

        ob_start();
        $controller->ready()->send();
        $output = ob_get_clean();

        $data = json_decode((string) $output, true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('timestamp', $data);
        $this->assertIsString($data['timestamp']);
    }
}
