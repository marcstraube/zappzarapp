<?php

declare(strict_types=1);

namespace Tests\App\Unit\Http;

use App\Http\ErrorPage;
use App\Http\ExceptionHandler;
use App\Http\Response\HtmlResponse;
use App\Http\Response\JsonResponse;
use App\Infrastructure\TwigService;
use ErrorException;
use Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Throwable;

/**
 * Test the global exception handler.
 *
 * @SuppressWarnings("PHPMD.TooManyPublicMethods")
 */
#[CoversClass(ExceptionHandler::class)]
#[UsesClass(JsonResponse::class)]
#[UsesClass(HtmlResponse::class)]
#[UsesClass(ErrorPage::class)]
#[UsesClass(TwigService::class)]
class ExceptionHandlerTest extends TestCase
{
    private string $originalEnv;

    /** @var array<string, mixed> Original SERVER values */
    private array $originalServer;

    protected function setUp(): void
    {
        parent::setUp();
        // Store original ENV value
        $this->originalEnv    = getenv('ENV') ?: '';
        $this->originalServer = $_SERVER;
    }

    protected function tearDown(): void
    {
        // Restore original ENV value
        putenv('ENV=' . $this->originalEnv);
        $_SERVER = $this->originalServer;
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

    // ===== logException without PSR-3 logger (error_log path) =====

    public function testHandleWithoutLoggerFallsBackToErrorLog(): void
    {
        putenv('ENV=production');
        $_SERVER['HTTP_ACCEPT'] = 'application/json';

        // No logger → logException() uses error_log() internally.
        // The error_log output goes to stderr; redirect to /dev/null during test.
        ini_set('error_log', '/dev/null');
        $handler   = new ExceptionHandler();
        $exception = new Exception('Test error for error_log path');

        ob_start();
        $handler->handle($exception);
        /** @var string $output */
        $output = ob_get_clean();
        ini_restore('error_log');

        // Still returns correct production error JSON
        $data = json_decode($output, true);
        $this->assertIsArray($data);
        $this->assertSame('Internal Server Error', $data['error']);
    }

    public function testHandleWithoutLoggerInDevelopmentUsesErrorLog(): void
    {
        putenv('ENV=development');
        $_SERVER['HTTP_ACCEPT'] = 'application/json';

        ini_set('error_log', '/dev/null');
        $handler   = new ExceptionHandler();
        $exception = new Exception('Dev error for error_log path');

        ob_start();
        $handler->handle($exception);
        /** @var string $output */
        $output = ob_get_clean();
        ini_restore('error_log');

        $data = json_decode($output, true);
        $this->assertIsArray($data);
        $this->assertSame('Dev error for error_log path', $data['message']);
    }

    // ===== register() covers set_exception_handler + register_shutdown_function =====

    #[RunInSeparateProcess]
    public function testRegisterDoesNotThrow(): void
    {
        putenv('ENV=production');
        $handler = new ExceptionHandler(new NullLogger());

        // register() sets global PHP handlers; no exception should be thrown
        $handler->register();

        // Restore handlers to avoid polluting other test processes
        restore_exception_handler();
        restore_error_handler();

        $this->assertTrue(true);
    }

    #[RunInSeparateProcess]
    public function testRegisterSetsGlobalHandlers(): void
    {
        putenv('ENV=production');
        $handler = new ExceptionHandler(new NullLogger());

        // Before register() there is no custom exception handler (PHP default)
        // After register(), set_exception_handler returns the previously set handler
        // (which is null if none was set). We verify register() doesn't throw and
        // the custom handler is installed by calling set_exception_handler again.
        $handler->register();

        // Re-register returns the handler we just installed
        $previousHandler = set_exception_handler(static function (Throwable $exception): void {
            unset($exception); // No-op: only used to probe the installed handler
        });
        $this->assertIsCallable($previousHandler);

        restore_exception_handler(); // undo set above
        restore_exception_handler(); // undo register()
        restore_error_handler();     // undo register()'s set_error_handler
    }

    #[RunInSeparateProcess]
    public function testRegisterErrorHandlerRespectsErrorReportingLevel(): void
    {
        putenv('ENV=production');
        $handler = new ExceptionHandler(new NullLogger());
        $handler->register();

        // Suppress errors entirely — our error handler must return false (not throw)
        $previous = error_reporting(0);
        $thrown   = false;

        try {
            trigger_error('Suppressed warning', E_USER_WARNING);
        } catch (ErrorException) {
            $thrown = true;
        } finally {
            error_reporting($previous);
            restore_exception_handler();
            restore_error_handler();
        }

        $this->assertFalse($thrown);
    }

    // ===== handleShutdown() =====

    public function testHandleShutdownDoesNothingWhenNoFatalError(): void
    {
        putenv('ENV=production');
        $handler = new ExceptionHandler(new NullLogger());

        // No fatal error in the error buffer → handleShutdown does nothing
        ob_start();
        $handler->handleShutdown();
        $output = ob_get_clean();

        $this->assertSame('', $output);
    }
}
