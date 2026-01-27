<?php

declare(strict_types=1);

namespace App\Http;

use App\Http\Response\JsonResponse;
use ErrorException;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Global Exception Handler
 *
 * Catches all uncaught exceptions and provides environment-aware error responses:
 * - Production: Generic error page, detailed server-side logging
 * - Development: Detailed error information for debugging
 *
 * Security: Prevents information disclosure (CWE-550) by hiding stack traces
 * and internal details from end users in production environments.
 */
class ExceptionHandler
{
    private readonly bool $isDevelopment;

    public function __construct(private readonly ?LoggerInterface $logger = null)
    {
        $this->isDevelopment = getenv('ENV') === 'development';
    }

    /**
     * Register this handler as the global exception and error handler.
     *
     * This method should be called early in the application bootstrap to catch:
     * - Uncaught exceptions (via set_exception_handler)
     * - Fatal errors (via register_shutdown_function)
     * - PHP errors converted to exceptions (via set_error_handler)
     */
    public function register(): void
    {
        // Catch uncaught exceptions
        set_exception_handler($this->handle(...));

        // Catch fatal errors during shutdown
        register_shutdown_function([$this, 'handleShutdown']);

        // Convert PHP errors to ErrorException (to be caught by exception handler)
        set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
            // Don't throw exception if error reporting is disabled
            if ((error_reporting() & $severity) === 0) {
                return false;
            }

            throw new ErrorException($message, 0, $severity, $file, $line);
        });
    }

    /**
     * Handle fatal errors during shutdown.
     *
     * This catches errors that occur too late for the exception handler,
     * such as parse errors, out of memory errors, etc.
     */
    public function handleShutdown(): void
    {
        $error = error_get_last();

        // Check if this was a fatal error
        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            // Create an exception-like error for consistent handling
            $exception = new ErrorException(
                $error['message'],
                0,
                $error['type'],
                $error['file'],
                $error['line']
            );

            $this->handle($exception);
        }
    }

    /**
     * Handle an uncaught exception.
     *
     * This method:
     * - Logs the exception details server-side
     * - Returns a safe error response based on environment
     * - Uses content negotiation (HTML vs JSON)
     */
    public function handle(Throwable $exception): void
    {
        // Log exception server-side (always, regardless of environment)
        $this->logException($exception);

        // Prevent any previous output from interfering
        if (ob_get_level() > 0) {
            ob_clean();
        }

        // Content negotiation: JSON for API clients, HTML for browsers
        $acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';
        $wantsJson    = str_contains((string) $acceptHeader, 'application/json');

        if ($wantsJson) {
            $this->sendJsonError($exception);
        } else {
            $this->sendHtmlError($exception);
        }
    }

    /**
     * Send JSON error response.
     */
    private function sendJsonError(Throwable $exception): void
    {
        $data = $this->isDevelopment
            ? $this->getDevelopmentErrorData($exception)
            : $this->getProductionErrorData();

        $response = new JsonResponse($data, 500);
        $response->send();
    }

    /**
     * Send HTML error response.
     */
    private function sendHtmlError(Throwable $exception): void
    {
        if ($this->isDevelopment) {
            // Development: Show detailed error information
            $this->renderDevelopmentError($exception);
        } else {
            // Production: Show generic error page
            $response = ErrorPage::render(500);
            $response->send();
        }
    }

    /**
     * Get production-safe error data (no sensitive information).
     *
     * @return array{error: string, message: string}
     */
    private function getProductionErrorData(): array
    {
        return [
            'error'   => 'Internal Server Error',
            'message' => 'An unexpected error occurred. Please try again later.',
        ];
    }

    /**
     * Get development error data (includes exception details).
     *
     * @return array{error: string, message: string, exception: string, file: string, line: int, trace: string}
     */
    private function getDevelopmentErrorData(Throwable $exception): array
    {
        return [
            'error'     => 'Internal Server Error',
            'message'   => $exception->getMessage(),
            'exception' => $exception::class,
            'file'      => $exception->getFile(),
            'line'      => $exception->getLine(),
            'trace'     => $exception->getTraceAsString(),
        ];
    }

    /**
     * Render development error page with full exception details.
     */
    private function renderDevelopmentError(Throwable $exception): void
    {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');

        // Escape data for HTML output
        $class   = htmlspecialchars($exception::class, ENT_QUOTES, 'UTF-8');
        $message = htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8');
        $file    = htmlspecialchars($exception->getFile(), ENT_QUOTES, 'UTF-8');
        $line    = $exception->getLine();
        $trace   = htmlspecialchars($exception->getTraceAsString(), ENT_QUOTES, 'UTF-8');

        // @phpstan-ignore-next-line - Exception handler requires direct output
        echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exception: {$class}</title>
    <style>
        body { font-family: monospace; margin: 20px; background: #1e1e1e; color: #d4d4d4; }
        .error-box { background: #252526; border-left: 4px solid #f48771; padding: 20px; margin-bottom: 20px; }
        .error-box h1 { margin: 0 0 10px 0; color: #f48771; font-size: 18px; }
        .error-box p { margin: 5px 0; }
        .trace { background: #1e1e1e; padding: 15px; border: 1px solid #3e3e42; overflow-x: auto; }
        .trace pre { margin: 0; white-space: pre-wrap; word-wrap: break-word; }
        .label { color: #9cdcfe; }
    </style>
</head>
<body>
    <div class="error-box">
        <h1>⚠️ Uncaught Exception</h1>
        <p><span class="label">Type:</span> {$class}</p>
        <p><span class="label">Message:</span> {$message}</p>
        <p><span class="label">File:</span> {$file}</p>
        <p><span class="label">Line:</span> {$line}</p>
    </div>
    <div class="trace">
        <h2 style="margin-top: 0; color: #9cdcfe;">Stack Trace</h2>
        <pre>{$trace}</pre>
    </div>
    <p style="color: #858585; font-size: 12px; margin-top: 20px;">
        💡 This detailed error is only shown in development mode (ENV=development)
    </p>
</body>
</html>
HTML;
    }

    /**
     * Log exception details server-side.
     */
    private function logException(Throwable $exception): void
    {
        if (!$this->logger instanceof LoggerInterface) {
            // Fallback to error_log if no PSR-3 logger available
            error_log(sprintf(
                '[EXCEPTION] %s: %s in %s:%d',
                $exception::class,
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine()
            ));
            error_log('Stack trace: ' . $exception->getTraceAsString());
        } else {
            $this->logger->error('Uncaught exception', [
                'exception' => $exception::class,
                'message'   => $exception->getMessage(),
                'file'      => $exception->getFile(),
                'line'      => $exception->getLine(),
                'trace'     => $exception->getTraceAsString(),
            ]);
        }
    }
}
