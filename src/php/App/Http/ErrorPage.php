<?php

declare(strict_types=1);

namespace App\Http;

use App\Http\Response\HtmlResponse;
use App\Http\Response\Response;
use App\Infrastructure\TwigService;
use Zappzarapp\Security\Csp\Nonce\NonceRegistry;

/**
 * Dynamic Error Page Renderer
 *
 * Renders styled error pages for browser requests using Twig templates.
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

    /**
     * Create an error response.
     */
    public static function render(int $statusCode, ?string $path = null): Response
    {
        return new HtmlResponse(self::renderHtml($statusCode, $path), $statusCode);
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
     * Render the error template using Twig.
     *
     * @param array{code: int, title: string, message: string, path: ?string} $data
     */
    private static function renderTemplate(array $data): string
    {
        // Instantiate Twig inline (static method, no DI available)
        $isDevelopment = getenv('ENV') === 'development';
        $twig          = $isDevelopment
            ? TwigService::createForDevelopment(
                __DIR__ . '/../../../../templates',
                __DIR__ . '/../../../../build/cache/twig'
            )
            : TwigService::createForProduction(
                __DIR__ . '/../../../../templates',
                __DIR__ . '/../../../../build/cache/twig'
            );

        // Register CSP nonce function
        $twig->addFunction('nonce', NonceRegistry::get(...));

        return $twig->render('app/error.html.twig', $data);
    }
}
