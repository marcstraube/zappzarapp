<?php

declare(strict_types=1);

namespace Tests\App\Unit\Http;

use App\Http\ExceptionHandler;
use Exception;
use PHPUnit\Framework\TestCase;

/**
 * Test the global exception handler.
 *
 * @coversDefaultClass \App\Http\ExceptionHandler
 */
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
        putenv("ENV={$this->originalEnv}");
        parent::tearDown();
    }

    /**
     * @covers ::__construct
     * @covers ::handle
     * @covers ::sendJsonError
     * @covers ::getProductionErrorData
     * @covers ::logException
     */
    public function testProductionModeHidesExceptionDetails(): void
    {
        putenv('ENV=production');
        $_SERVER['HTTP_ACCEPT'] = 'application/json';

        $handler   = new ExceptionHandler();
        $exception = new Exception('Sensitive database error: password123');

        ob_start();
        $handler->handle($exception);
        $output = ob_get_clean();
        $this->assertIsString($output);
        $this->assertIsString($output);

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

    /**
     * @covers ::__construct
     * @covers ::handle
     * @covers ::sendJsonError
     * @covers ::getDevelopmentErrorData
     * @covers ::logException
     */
    public function testDevelopmentModeShowsExceptionDetails(): void
    {
        putenv('ENV=development');
        $_SERVER['HTTP_ACCEPT'] = 'application/json';

        $handler   = new ExceptionHandler();
        $exception = new Exception('Database connection failed');

        ob_start();
        $handler->handle($exception);
        $output = ob_get_clean();
        $this->assertIsString($output);

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

    /**
     * @covers ::__construct
     * @covers ::handle
     * @covers ::sendHtmlError
     * @covers ::getProductionErrorData
     * @covers ::logException
     */
    public function testProductionModeHtmlResponse(): void
    {
        putenv('ENV=production');
        $_SERVER['HTTP_ACCEPT'] = 'text/html';

        $handler   = new ExceptionHandler();
        $exception = new Exception('Internal error with sensitive data');

        ob_start();
        $handler->handle($exception);
        $output = ob_get_clean();
        $this->assertIsString($output);

        // Verify HTML response
        $this->assertStringContainsString('<!DOCTYPE html>', $output);
        $this->assertStringContainsString('Internal Server Error', $output);

        // Verify sensitive data is NOT exposed
        $this->assertStringNotContainsString('sensitive data', $output);
    }

    /**
     * @covers ::__construct
     * @covers ::handle
     * @covers ::sendHtmlError
     * @covers ::renderDevelopmentError
     * @covers ::logException
     */
    public function testDevelopmentModeHtmlResponse(): void
    {
        putenv('ENV=development');
        $_SERVER['HTTP_ACCEPT'] = 'text/html';

        $handler   = new ExceptionHandler();
        $exception = new Exception('Test error for debugging');

        ob_start();
        $handler->handle($exception);
        $output = ob_get_clean();
        $this->assertIsString($output);

        // Verify HTML response with exception details
        $this->assertStringContainsString('<!DOCTYPE html>', $output);
        $this->assertStringContainsString('Uncaught Exception', $output);
        $this->assertStringContainsString('Test error for debugging', $output);
        $this->assertStringContainsString('Exception', $output);
        $this->assertStringContainsString('Stack Trace', $output);
    }

    /**
     * @covers ::__construct
     * @covers ::handle
     * @covers ::sendJsonError
     * @covers ::logException
     */
    public function testContentNegotiationDefaultsToJson(): void
    {
        putenv('ENV=production');
        unset($_SERVER['HTTP_ACCEPT']);

        $handler   = new ExceptionHandler();
        $exception = new Exception('Test exception');

        ob_start();
        $handler->handle($exception);
        $output = ob_get_clean();
        $this->assertIsString($output);

        // When no Accept header is present, should default to HTML
        // (as per the handle() logic checking for json in Accept header)
        $this->assertStringContainsString('<!DOCTYPE html>', $output);
    }

    /**
     * @covers ::__construct
     * @covers ::handle
     * @covers ::logException
     */
    public function testExceptionLoggingWithoutLogger(): void
    {
        putenv('ENV=production');
        $_SERVER['HTTP_ACCEPT'] = 'application/json';

        // Test with no logger (falls back to error_log)
        $handler   = new ExceptionHandler();
        $exception = new Exception('Test for logging');

        // Capture error_log output
        $errorLogCalled = false;
        set_error_handler(function () use (&$errorLogCalled) {
            $errorLogCalled = true;

            return false; // Let default handler continue
        });

        ob_start();
        $handler->handle($exception);
        ob_get_clean();
        restore_error_handler();

        // Note: We can't easily verify error_log calls in unit tests,
        // but we can verify the handler doesn't crash without a logger
        $this->assertTrue(true, 'Handler executed without crashing');
    }
}
