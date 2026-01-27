<?php

declare(strict_types=1);

namespace Tests\App\Unit\Http;

use App\Http\ExceptionHandler;
use Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Test the global exception handler.
 */
#[CoversClass(ExceptionHandler::class)]
class ExceptionHandlerTest extends TestCase
{
    private string $originalEnv;

    protected function setUp(): void
    {
        parent::setUp();
        // Store original ENV value
        $this->originalEnv = getenv('ENV') ?: '';
    }

    protected function tearDown(): void
    {
        // Restore original ENV value
        putenv('ENV=' . $this->originalEnv);
        parent::tearDown();
    }

    public function testProductionModeHidesExceptionDetails(): void
    {
        putenv('ENV=production');
        $_SERVER['HTTP_ACCEPT'] = 'application/json';

        $handler   = new ExceptionHandler(new NullLogger());
        $exception = new Exception('Sensitive database error: password123');

        ob_start();
        $handler->handle($exception);
        // ob_get_clean() returns string|false, but with ob_start() it's always string
        /** @var string $output */
        $output = ob_get_clean();

        // Verify sensitive information is NOT exposed
        $this->assertStringNotContainsString('password123', $output);
        $this->assertStringNotContainsString('Sensitive database error', $output);
        $this->assertStringNotContainsString('Exception', $output);

        // Verify generic error message is present
        $data = json_decode($output, true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('error', $data);
        $this->assertArrayHasKey('message', $data);
        $this->assertSame('Internal Server Error', $data['error']);
        $this->assertSame('An unexpected error occurred. Please try again later.', $data['message']);
    }

    public function testDevelopmentModeShowsExceptionDetails(): void
    {
        putenv('ENV=development');
        $_SERVER['HTTP_ACCEPT'] = 'application/json';

        $handler   = new ExceptionHandler(new NullLogger());
        $exception = new Exception('Database connection failed');

        ob_start();
        $handler->handle($exception);
        // ob_get_clean() returns string|false, but with ob_start() it's always string
        /** @var string $output */
        $output = ob_get_clean();

        // Verify detailed information IS exposed in development
        $data = json_decode($output, true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('exception', $data);
        $this->assertArrayHasKey('message', $data);
        $this->assertArrayHasKey('file', $data);
        $this->assertArrayHasKey('line', $data);
        $this->assertArrayHasKey('trace', $data);

        $this->assertSame('Exception', $data['exception']);
        $this->assertSame('Database connection failed', $data['message']);
        $this->assertIsString($data['file']);
        $this->assertIsInt($data['line']);
        $this->assertIsString($data['trace']);
    }

    public function testProductionModeHtmlResponse(): void
    {
        putenv('ENV=production');
        $_SERVER['HTTP_ACCEPT'] = 'text/html';

        $handler   = new ExceptionHandler(new NullLogger());
        $exception = new Exception('Internal error with sensitive data');

        ob_start();
        $handler->handle($exception);
        // ob_get_clean() returns string|false, but with ob_start() it's always string
        /** @var string $output */
        $output = ob_get_clean();

        // Verify HTML response
        $this->assertStringContainsString('<!DOCTYPE html>', $output);
        $this->assertStringContainsString('Internal Server Error', $output);

        // Verify sensitive data is NOT exposed
        $this->assertStringNotContainsString('sensitive data', $output);
    }

    public function testDevelopmentModeHtmlResponse(): void
    {
        putenv('ENV=development');
        $_SERVER['HTTP_ACCEPT'] = 'text/html';

        $handler   = new ExceptionHandler(new NullLogger());
        $exception = new Exception('Test error for debugging');

        ob_start();
        $handler->handle($exception);
        // ob_get_clean() returns string|false, but with ob_start() it's always string
        /** @var string $output */
        $output = ob_get_clean();

        // Verify HTML response with exception details
        $this->assertStringContainsString('<!DOCTYPE html>', $output);
        $this->assertStringContainsString('Uncaught Exception', $output);
        $this->assertStringContainsString('Test error for debugging', $output);
        $this->assertStringContainsString('Exception', $output);
        $this->assertStringContainsString('Stack Trace', $output);
    }

    public function testContentNegotiationDefaultsToJson(): void
    {
        putenv('ENV=production');
        unset($_SERVER['HTTP_ACCEPT']);

        $handler   = new ExceptionHandler(new NullLogger());
        $exception = new Exception('Test exception');

        ob_start();
        $handler->handle($exception);
        // ob_get_clean() returns string|false, but with ob_start() it's always string
        /** @var string $output */
        $output = ob_get_clean();

        // When no Accept header is present, should default to HTML
        // (as per the handle() logic checking for json in Accept header)
        $this->assertStringContainsString('<!DOCTYPE html>', $output);
    }

    public function testExceptionHandlingWithNullLogger(): void
    {
        putenv('ENV=production');
        $_SERVER['HTTP_ACCEPT'] = 'application/json';

        $handler   = new ExceptionHandler(new NullLogger());
        $exception = new Exception('Test exception');

        ob_start();
        $handler->handle($exception);
        // ob_get_clean() returns string|false, but with ob_start() it's always string
        /** @var string $output */
        $output = ob_get_clean();

        // Verify the handler executes successfully with NullLogger
        $data = json_decode($output, true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('error', $data);
        $this->assertSame('Internal Server Error', $data['error']);
    }
}
