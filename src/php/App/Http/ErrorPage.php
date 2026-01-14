<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Dynamic Error Page Renderer
 *
 * Renders styled error pages for browser requests.
 * Can be extended to include session data, logging, suggestions, etc.
 */
class ErrorPage
{
    private const array ERRORS = [
        400 => ['title' => 'Bad Request', 'message' => 'The request could not be understood by the server.'],
        401 => ['title' => 'Unauthorized', 'message' => 'Authentication is required to access this resource.'],
        403 => ['title' => 'Forbidden', 'message' => "You don't have permission to access this resource."],
        404 => ['title' => 'Page Not Found', 'message' => "The page you're looking for doesn't exist or has been moved."],
        500 => ['title' => 'Internal Server Error', 'message' => 'Something went wrong on our end. Please try again later.'],
        502 => ['title' => 'Bad Gateway', 'message' => 'The server received an invalid response from an upstream server.'],
        503 => ['title' => 'Service Unavailable', 'message' => 'The service is temporarily unavailable. Please try again later.'],
    ];

    private const string TEMPLATE_PATH = __DIR__ . '/../../../../templates/app/error.php';

    /**
     * Render an error page and send HTTP response.
     */
    public static function render(int $statusCode, ?string $path = null): void
    {
        http_response_code($statusCode);
        header('Content-Type: text/html; charset=UTF-8');

        echo self::renderHtml($statusCode, $path);
    }

    /**
     * Render error page HTML without sending headers.
     * Useful for testing or embedding.
     */
    public static function renderHtml(int $statusCode, ?string $path = null): string
    {
        $errorInfo = self::getErrorInfo($statusCode);

        return self::renderTemplate([
            'code'    => $statusCode,
            'title'   => $errorInfo['title'],
            'message' => $errorInfo['message'],
            'path'    => $path,
        ]);
    }

    /**
     * Get error information for a status code.
     *
     * @return array{title: string, message: string}
     */
    public static function getErrorInfo(int $statusCode): array
    {
        return self::ERRORS[$statusCode] ?? [
            'title'   => 'Error',
            'message' => 'An unexpected error occurred.',
        ];
    }

    /**
     * Check if a status code has defined error info.
     */
    public static function hasErrorInfo(int $statusCode): bool
    {
        return isset(self::ERRORS[$statusCode]);
    }

    /**
     * Get all supported error codes.
     *
     * @return int[]
     */
    public static function getSupportedCodes(): array
    {
        return array_keys(self::ERRORS);
    }

    /**
     * Render the error template.
     *
     * @param array{code: int, title: string, message: string, path: ?string} $data
     */
    private static function renderTemplate(array $data): string
    {
        extract($data);
        ob_start();
        include self::TEMPLATE_PATH;

        return (string) ob_get_clean();
    }
}
