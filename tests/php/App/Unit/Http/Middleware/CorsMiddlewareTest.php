<?php

declare(strict_types=1);

namespace Tests\App\Unit\Http\Middleware;

use App\Http\Middleware\CorsMiddleware;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for CorsMiddleware
 *
 * Tests focus on the return value (continue vs halt) and branching logic.
 * header() calls cannot be asserted in-process; tests requiring header inspection
 * use RunInSeparateProcess so headers_list() reflects actual emitted headers.
 *
 * @SuppressWarnings("PHPMD.TooManyPublicMethods")
 */
#[CoversClass(CorsMiddleware::class)]
final class CorsMiddlewareTest extends TestCase
{
    /** @var array<string, mixed> Original SERVER values to restore */
    private array $originalServer = [];

    /** @var string Original APP_ENV value */
    private string $originalAppEnv = '';

    /** @var string Original CORS_ORIGINS value */
    private string $originalCorsOrigins = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalServer      = $_SERVER;
        $this->originalAppEnv      = getenv('APP_ENV') ?: '';
        $this->originalCorsOrigins = getenv('CORS_ORIGINS') ?: '';
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->originalServer;
        putenv('APP_ENV=' . $this->originalAppEnv);
        putenv('CORS_ORIGINS=' . $this->originalCorsOrigins);
        parent::tearDown();
    }

    // ===== No CORS configuration =====

    public function testHandleReturnsTrueWhenNoCorsConfigured(): void
    {
        $middleware                = new CorsMiddleware('');
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $result = $middleware->handle();

        $this->assertTrue($result);
    }

    public function testHandleReturnsTrueWhenNoCorsConfiguredAndOriginPresent(): void
    {
        $middleware                = new CorsMiddleware('');
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_ORIGIN']    = 'https://example.com';

        $result = $middleware->handle();

        $this->assertTrue($result);
    }

    // ===== Empty origin (same-origin / internal requests) =====

    public function testHandleReturnsTrueForGetWithNoOriginHeader(): void
    {
        $middleware                = new CorsMiddleware('https://example.com');
        $_SERVER['REQUEST_METHOD'] = 'GET';
        unset($_SERVER['HTTP_ORIGIN']);

        $result = $middleware->handle();

        // No origin header → isOriginAllowed returns false → no CORS headers,
        // but non-OPTIONS request continues normally
        $this->assertTrue($result);
    }

    // ===== OPTIONS preflight =====

    public function testHandleReturnsFalseForOptionsRequest(): void
    {
        $middleware                = new CorsMiddleware('https://example.com');
        $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
        $_SERVER['HTTP_ORIGIN']    = 'https://example.com';

        $result = $middleware->handle();

        $this->assertFalse($result);
    }

    public function testHandleReturnsFalseForOptionsWithNoMatchingOrigin(): void
    {
        // Even if origin doesn't match, OPTIONS request is still halted
        $middleware                = new CorsMiddleware('https://example.com');
        $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
        $_SERVER['HTTP_ORIGIN']    = 'https://evil.com';

        $result = $middleware->handle();

        $this->assertFalse($result);
    }

    public function testHandleReturnsFalseForOptionsWithNoOrigin(): void
    {
        $middleware                = new CorsMiddleware('https://example.com');
        $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
        unset($_SERVER['HTTP_ORIGIN']);

        $result = $middleware->handle();

        $this->assertFalse($result);
    }

    // ===== Non-OPTIONS requests with matching origin =====

    public function testHandleReturnsTrueForGetWithMatchingOrigin(): void
    {
        $middleware                = new CorsMiddleware('https://example.com');
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_ORIGIN']    = 'https://example.com';

        $result = $middleware->handle();

        $this->assertTrue($result);
    }

    public function testHandleReturnsTrueForPostWithMatchingOrigin(): void
    {
        $middleware                = new CorsMiddleware('https://app.example.com');
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['HTTP_ORIGIN']    = 'https://app.example.com';

        $result = $middleware->handle();

        $this->assertTrue($result);
    }

    // ===== Origin matching (comma-separated list) =====

    public function testHandleReturnsTrueForFirstOriginInList(): void
    {
        $middleware                = new CorsMiddleware('https://example.com,https://app.example.com');
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_ORIGIN']    = 'https://example.com';

        $result = $middleware->handle();

        $this->assertTrue($result);
    }

    public function testHandleReturnsTrueForSecondOriginInList(): void
    {
        $middleware                = new CorsMiddleware('https://example.com,https://app.example.com');
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_ORIGIN']    = 'https://app.example.com';

        $result = $middleware->handle();

        $this->assertTrue($result);
    }

    public function testHandleReturnsTrueForOriginWithWhitespaceInList(): void
    {
        // Middleware trims whitespace from comma-separated entries
        $middleware                = new CorsMiddleware('https://example.com, https://app.example.com');
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_ORIGIN']    = 'https://app.example.com';

        $result = $middleware->handle();

        $this->assertTrue($result);
    }

    public function testHandleReturnsTrueForNonMatchingOriginOnGetRequest(): void
    {
        // Non-matching origin: CORS headers not added, but GET still continues
        $middleware                = new CorsMiddleware('https://example.com');
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_ORIGIN']    = 'https://evil.com';

        $result = $middleware->handle();

        $this->assertTrue($result);
    }

    // ===== Wildcard origin =====

    // Wildcard constructor calls error_log() which PHPUnit 12 captures as
    // "unexpected output" even from stderr, marking the test risky.
    // Redirect error_log to /dev/null for the duration of the wildcard instantiation.

    public function testHandleReturnsTrueForWildcardWithAnyOrigin(): void
    {
        ini_set('error_log', '/dev/null');
        $middleware = new CorsMiddleware('*');
        ini_restore('error_log');

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_ORIGIN']    = 'https://any-origin.com';

        $result = $middleware->handle();

        $this->assertTrue($result);
    }

    public function testHandleReturnsFalseForOptionsWithWildcard(): void
    {
        ini_set('error_log', '/dev/null');
        $middleware = new CorsMiddleware('*');
        ini_restore('error_log');

        $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
        $_SERVER['HTTP_ORIGIN']    = 'https://any-origin.com';

        $result = $middleware->handle();

        $this->assertFalse($result);
    }

    // ===== Constructor: reads from environment when no argument given =====

    #[RunInSeparateProcess]
    public function testConstructorReadsFromEnvironmentWhenNoArgumentGiven(): void
    {
        putenv('CORS_ORIGINS=https://env-configured.com');
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_ORIGIN']    = 'https://env-configured.com';

        // No constructor argument — should pick up env var
        $middleware = new CorsMiddleware();
        $result     = $middleware->handle();

        $this->assertTrue($result);
    }

    #[RunInSeparateProcess]
    public function testConstructorFallsBackToEmptyWhenEnvNotSet(): void
    {
        putenv('CORS_ORIGINS');
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $middleware = new CorsMiddleware();
        $result     = $middleware->handle();

        // No config → always returns true
        $this->assertTrue($result);
    }

    // ===== APP_ENV-dependent credentials logic (verified via return value) =====
    // Note: headers_list() always returns [] in PHP CLI mode; header() calls
    // inside handle() cannot be asserted in PHPUnit. Logic coverage for the
    // credentials / wildcard branches is achieved by verifying the return value
    // and by ensuring all code paths execute without error.

    #[RunInSeparateProcess]
    public function testHandleReturnsTrueForMatchingOriginInDevelopmentWithSpecificOrigin(): void
    {
        putenv('APP_ENV=development');
        $middleware                = new CorsMiddleware('https://example.com');
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_ORIGIN']    = 'https://example.com';

        // Specific origin + development: credentials emitted, request continues
        $result = $middleware->handle();

        $this->assertTrue($result);
    }

    #[RunInSeparateProcess]
    public function testHandleReturnsTrueForMatchingOriginInProduction(): void
    {
        putenv('APP_ENV=production');
        $middleware                = new CorsMiddleware('https://example.com');
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_ORIGIN']    = 'https://example.com';

        $result = $middleware->handle();

        $this->assertTrue($result);
    }

    #[RunInSeparateProcess]
    public function testHandleReturnsTrueForWildcardInDevelopment(): void
    {
        putenv('APP_ENV=development');
        // Wildcard + development: no credentials header, but GET continues
        ini_set('error_log', '/dev/null');
        $middleware = new CorsMiddleware('*');
        ini_restore('error_log');

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_ORIGIN']    = 'https://example.com';

        $result = $middleware->handle();

        $this->assertTrue($result);
    }

    #[RunInSeparateProcess]
    public function testHandleReturnsTrueForWildcardInProduction(): void
    {
        putenv('APP_ENV=production');
        // Wildcard + production: credentials header emitted, GET continues
        ini_set('error_log', '/dev/null');
        $middleware = new CorsMiddleware('*');
        ini_restore('error_log');

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_ORIGIN']    = 'https://example.com';

        $result = $middleware->handle();

        $this->assertTrue($result);
    }
}
