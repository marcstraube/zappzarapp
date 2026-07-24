<?php

declare(strict_types=1);

namespace Tests\App\Unit\Http\Controller;

use App\Http\Controller\ExampleController;
use App\Http\Response\JsonResponse;
use App\Infrastructure\HealthCheck;
use App\Infrastructure\TlsConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Unit tests for ExampleController.
 *
 * No real network I/O: the protected fetchNodeHealth() seam is overridden
 * by FakeExampleController (inner test subclass) to return controlled values.
 *
 * @SuppressWarnings("PHPMD.TooManyPublicMethods")
 */
#[CoversClass(ExampleController::class)]
#[UsesClass(HealthCheck::class)]
#[UsesClass(JsonResponse::class)]
#[UsesClass(TlsConfig::class)]
final class ExampleControllerTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Build a FakeExampleController whose fetchNodeHealth() always returns $fetchResult.
     */
    private function makeController(string|false $fetchResult): ExampleController
    {
        // No custom constructor: PHPMD/PDepend misreport promoted constructor
        // properties in anonymous classes as unused parameters, while Rector
        // enforces the promotion — a plain public property avoids both tools.
        $controller = new class (new HealthCheck()) extends ExampleController {
            public string|false $fakeResponse = false;

            protected function fetchNodeHealth(string $url, mixed $context = null): string|false
            {
                unset($url, $context);

                return $this->fakeResponse;
            }
        };
        $controller->fakeResponse = $fetchResult;

        return $controller;
    }

    private function clearEnvVars(): void
    {
        foreach (['ENABLE_NODE', 'NODE_MODE'] as $var) {
            putenv($var);
            unset($_ENV[$var]);
        }
    }

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

    // =========================================================================
    // index()
    // =========================================================================

    #[Test]
    public function testIndexReturnsJsonResponseInstance(): void
    {
        $controller = new ExampleController(new HealthCheck());

        $response = $controller->index();

        $this->assertInstanceOf(JsonResponse::class, $response);
    }

    #[Test]
    public function testIndexOutputContainsHelloMessage(): void
    {
        $controller = new ExampleController(new HealthCheck());

        ob_start();
        $controller->index()->send();
        $output = ob_get_clean();

        $data = json_decode((string) $output, true);
        $this->assertIsArray($data);
        $this->assertSame('Hello from PHP!', $data['message']);
    }

    #[Test]
    public function testIndexOutputContainsPhpVersion(): void
    {
        $controller = new ExampleController(new HealthCheck());

        ob_start();
        $controller->index()->send();
        $output = ob_get_clean();

        $data = json_decode((string) $output, true);
        $this->assertIsArray($data);
        $this->assertSame(PHP_VERSION, $data['php_version']);
    }

    #[Test]
    public function testIndexOutputContainsTimestamp(): void
    {
        $controller = new ExampleController(new HealthCheck());

        ob_start();
        $controller->index()->send();
        $output = ob_get_clean();

        $data = json_decode((string) $output, true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('timestamp', $data);
        $this->assertIsInt($data['timestamp']);
    }

    // =========================================================================
    // health() — Node disabled (default environment)
    // =========================================================================

    #[Test]
    public function testHealthReturnsJsonResponseWhenNodeDisabled(): void
    {
        // Default: ENABLE_NODE not set → Node disabled
        $controller = new ExampleController(new HealthCheck());

        $response = $controller->health();

        $this->assertInstanceOf(JsonResponse::class, $response);
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testHealthReturnsStatus200WhenNodeDisabled(): void
    {
        $controller = new ExampleController(new HealthCheck());

        ob_start();
        $controller->health()->send();
        ob_get_clean();

        $this->assertSame(200, http_response_code());
    }

    #[Test]
    public function testHealthOutputContainsOkStatusWhenNodeDisabled(): void
    {
        $controller = new ExampleController(new HealthCheck());

        ob_start();
        $controller->health()->send();
        $output = ob_get_clean();

        $data = json_decode((string) $output, true);
        $this->assertIsArray($data);
        $this->assertSame('ok', $data['status']);
    }

    #[Test]
    public function testHealthOutputContainsPhpBackendOk(): void
    {
        $controller = new ExampleController(new HealthCheck());

        ob_start();
        $controller->health()->send();
        $output = ob_get_clean();

        $data = json_decode((string) $output, true);
        $this->assertIsArray($data);
        $this->assertSame('ok', $data['backends']['php']['status']);
        $this->assertSame(0, $data['backends']['php']['latency_ms']);
    }

    #[Test]
    public function testHealthOutputContainsNodeDisabledWhenNodeOff(): void
    {
        // ENABLE_NODE defaults to false — Node backend should report disabled
        $controller = new ExampleController(new HealthCheck());

        ob_start();
        $controller->health()->send();
        $output = ob_get_clean();

        $data = json_decode((string) $output, true);
        $this->assertIsArray($data);
        $this->assertSame('disabled', $data['backends']['node']['status']);
    }

    #[Test]
    public function testHealthOutputContainsTimestamp(): void
    {
        $controller = new ExampleController(new HealthCheck());

        ob_start();
        $controller->health()->send();
        $output = ob_get_clean();

        $data = json_decode((string) $output, true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('timestamp', $data);
        $this->assertIsString($data['timestamp']);
    }

    // =========================================================================
    // health() — Node enabled, fetch succeeds
    // =========================================================================

    #[RunInSeparateProcess]
    #[Test]
    public function testHealthReturnsOkWhenNodeReachable(): void
    {
        putenv('ENABLE_NODE=true');
        putenv('NODE_MODE=api');

        $controller = $this->makeController('{"status":"ok"}');

        ob_start();
        $controller->health()->send();
        $output = ob_get_clean();

        $data = json_decode((string) $output, true);
        $this->assertIsArray($data);
        $this->assertSame('ok', $data['status']);
        $this->assertSame('ok', $data['backends']['node']['status']);
        $this->assertArrayHasKey('latency_ms', $data['backends']['node']);
        $this->assertSame(200, http_response_code());
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testHealthReturnsDegradedWhenNodeUnreachable(): void
    {
        putenv('ENABLE_NODE=true');
        putenv('NODE_MODE=api');

        // fetchNodeHealth returns false → node unreachable
        $controller = $this->makeController(false);

        ob_start();
        $controller->health()->send();
        $output = ob_get_clean();

        $data = json_decode((string) $output, true);
        $this->assertIsArray($data);
        $this->assertSame('degraded', $data['status']);
        $this->assertSame('unhealthy', $data['backends']['node']['status']);
        $this->assertSame('Node backend not reachable', $data['backends']['node']['message']);
        $this->assertSame(503, http_response_code());
    }

    // =========================================================================
    // health() — Node enabled but NODE_MODE not in backend list
    // =========================================================================

    #[RunInSeparateProcess]
    #[Test]
    public function testHealthReturnsDisabledWhenNodeModeIsAssets(): void
    {
        putenv('ENABLE_NODE=true');
        putenv('NODE_MODE=assets'); // not in the backend modes list

        $controller = $this->makeController('{"status":"ok"}');

        ob_start();
        $controller->health()->send();
        $output = ob_get_clean();

        $data = json_decode((string) $output, true);
        $this->assertIsArray($data);
        // assets mode is not in backendModes → node shows as disabled
        $this->assertSame('disabled', $data['backends']['node']['status']);
        $this->assertSame('ok', $data['status']);
        $this->assertSame(200, http_response_code());
    }

    // =========================================================================
    // health() — backend mode variants that activate Node health check
    // =========================================================================

    #[RunInSeparateProcess]
    #[Test]
    public function testHealthActivatesNodeCheckForBackendMode(): void
    {
        putenv('ENABLE_NODE=true');
        putenv('NODE_MODE=backend');

        $controller = $this->makeController('{"status":"ok"}');

        ob_start();
        $controller->health()->send();
        $output = ob_get_clean();

        $data = json_decode((string) $output, true);
        $this->assertIsArray($data);
        $this->assertSame('ok', $data['backends']['node']['status']);
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testHealthActivatesNodeCheckForAssetsApiMode(): void
    {
        putenv('ENABLE_NODE=true');
        putenv('NODE_MODE=assets-api');

        $controller = $this->makeController('{"status":"ok"}');

        ob_start();
        $controller->health()->send();
        $output = ob_get_clean();

        $data = json_decode((string) $output, true);
        $this->assertIsArray($data);
        $this->assertSame('ok', $data['backends']['node']['status']);
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testHealthActivatesNodeCheckForFrameworkApiMode(): void
    {
        putenv('ENABLE_NODE=true');
        putenv('NODE_MODE=framework-api');

        $controller = $this->makeController('{"status":"ok"}');

        ob_start();
        $controller->health()->send();
        $output = ob_get_clean();

        $data = json_decode((string) $output, true);
        $this->assertIsArray($data);
        $this->assertSame('ok', $data['backends']['node']['status']);
    }

    // =========================================================================
    // health() — exception path
    // =========================================================================

    #[RunInSeparateProcess]
    #[Test]
    public function testHealthReturnsDegradedOnFetchException(): void
    {
        putenv('ENABLE_NODE=true');
        putenv('NODE_MODE=api');

        // Subclass where fetchNodeHealth() throws to exercise the catch branch
        $controller = new class (new HealthCheck()) extends ExampleController {
            protected function fetchNodeHealth(string $url, mixed $context = null): string|false
            {
                unset($url, $context);
                throw new RuntimeException('Simulated network error');
            }
        };

        ob_start();
        $controller->health()->send();
        $output = ob_get_clean();

        $data = json_decode((string) $output, true);
        $this->assertIsArray($data);
        $this->assertSame('degraded', $data['status']);
        $this->assertSame('unhealthy', $data['backends']['node']['status']);
        $this->assertSame('Simulated network error', $data['backends']['node']['message']);
        $this->assertSame(503, http_response_code());
    }

    // =========================================================================
    // fetchNodeHealth() — real implementation (no network I/O: URL is invalid)
    // =========================================================================

    #[Test]
    public function testFetchNodeHealthReturnsFalseForUnreachableUrl(): void
    {
        // Call the real fetchNodeHealth() via a real ExampleController subclass
        // that exposes the protected method. An invalid URL returns false.
        $controller = new class (new HealthCheck()) extends ExampleController {
            public function callFetchNodeHealth(string $url): string|false
            {
                return $this->fetchNodeHealth($url);
            }
        };

        // 'http://127.0.0.1:1' is an invalid port — connection refused, no network I/O to external hosts
        $result = $controller->callFetchNodeHealth('http://127.0.0.1:1/nonexistent');

        $this->assertFalse($result);
    }
}
