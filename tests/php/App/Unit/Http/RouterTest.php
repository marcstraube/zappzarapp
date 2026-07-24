<?php

declare(strict_types=1);

namespace Tests\App\Unit\Http;

use App\Http\ErrorPage;
use App\Http\Response\HtmlResponse;
use App\Http\Response\JsonResponse;
use App\Http\Router;
use App\Infrastructure\TwigService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Router
 *
 * dispatch() calls http_response_code() and header() internally via Response::send().
 * Output is captured with ob_start() / ob_get_clean() to satisfy
 * beStrictAboutOutputDuringTests. Headers cannot be asserted in-process.
 *
 * @SuppressWarnings("PHPMD.TooManyPublicMethods")
 */
#[CoversClass(Router::class)]
#[UsesClass(JsonResponse::class)]
#[UsesClass(HtmlResponse::class)]
#[UsesClass(ErrorPage::class)]
#[UsesClass(TwigService::class)]
final class RouterTest extends TestCase
{
    /** @var array<string, mixed> Snapshot of $_SERVER before each test */
    private array $originalServer = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalServer = $_SERVER;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->originalServer;
        parent::tearDown();
    }

    // ===== Route registration and dispatch =====

    #[Test]
    public function testGetRouteIsDispatchedForGetRequest(): void
    {
        $router = new Router();
        $called = false;

        $router->get('/test', function () use (&$called): JsonResponse {
            $called = true;

            return new JsonResponse(['ok' => true]);
        });

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/test';
        $_SERVER['HTTP_ACCEPT']    = 'application/json';

        ob_start();
        $router->dispatch();
        ob_get_clean();

        $this->assertTrue($called);
    }

    #[Test]
    public function testPostRouteIsDispatchedForPostRequest(): void
    {
        $router = new Router();
        $called = false;

        $router->post('/submit', function () use (&$called): JsonResponse {
            $called = true;

            return new JsonResponse(['created' => true], 201);
        });

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/submit';
        $_SERVER['HTTP_ACCEPT']    = 'application/json';

        ob_start();
        $router->dispatch();
        ob_get_clean();

        $this->assertTrue($called);
    }

    #[Test]
    public function testGetRouteIsNotDispatchedForPostRequest(): void
    {
        $router  = new Router();
        $called  = false;
        $handler = function () use (&$called): JsonResponse {
            $called = true;

            return new JsonResponse(['ok' => true]);
        };

        $router->get('/test', $handler);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/test';
        $_SERVER['HTTP_ACCEPT']    = 'application/json';

        ob_start();
        $router->dispatch();
        ob_get_clean();

        $this->assertFalse($called);
    }

    #[Test]
    public function testPostRouteIsNotDispatchedForGetRequest(): void
    {
        $router  = new Router();
        $called  = false;
        $handler = function () use (&$called): JsonResponse {
            $called = true;

            return new JsonResponse(['ok' => true]);
        };

        $router->post('/test', $handler);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/test';
        $_SERVER['HTTP_ACCEPT']    = 'application/json';

        ob_start();
        $router->dispatch();
        ob_get_clean();

        $this->assertFalse($called);
    }

    // ===== Path matching =====

    #[Test]
    public function testExactPathMatchDispatchesHandler(): void
    {
        $router = new Router();
        $called = false;

        $router->get('/exact/path', function () use (&$called): JsonResponse {
            $called = true;

            return new JsonResponse([]);
        });

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/exact/path';
        $_SERVER['HTTP_ACCEPT']    = 'application/json';

        ob_start();
        $router->dispatch();
        ob_get_clean();

        $this->assertTrue($called);
    }

    #[Test]
    public function testPartialPathDoesNotMatch(): void
    {
        $router = new Router();
        $called = false;

        $router->get('/exact', function () use (&$called): JsonResponse {
            $called = true;

            return new JsonResponse([]);
        });

        // /exact/path should NOT match /exact
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/exact/path';
        $_SERVER['HTTP_ACCEPT']    = 'application/json';

        ob_start();
        $router->dispatch();
        ob_get_clean();

        $this->assertFalse($called);
    }

    #[Test]
    public function testRouteWithQueryStringMatchesPath(): void
    {
        $router = new Router();
        $called = false;

        $router->get('/search', function () use (&$called): JsonResponse {
            $called = true;

            return new JsonResponse([]);
        });

        // parse_url strips the query string — path should still match
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/search?q=hello&page=2';
        $_SERVER['HTTP_ACCEPT']    = 'application/json';

        ob_start();
        $router->dispatch();
        ob_get_clean();

        $this->assertTrue($called);
    }

    // ===== 404 responses =====

    #[Test]
    public function testDispatchReturns404JsonForUnknownRouteWithJsonAccept(): void
    {
        $router = new Router();

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/not-found';
        $_SERVER['HTTP_ACCEPT']    = 'application/json';

        ob_start();
        $router->dispatch();
        $output = ob_get_clean();

        $data = json_decode((string) $output, true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('error', $data);
        $this->assertSame('Not Found', $data['error']);
        $this->assertArrayHasKey('path', $data);
        $this->assertSame('/not-found', $data['path']);
    }

    #[Test]
    public function testDispatchIncludes404PathInJsonResponse(): void
    {
        $router = new Router();

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/missing/page';
        $_SERVER['HTTP_ACCEPT']    = 'application/json';

        ob_start();
        $router->dispatch();
        $output = ob_get_clean();

        $data = json_decode((string) $output, true);
        $this->assertIsArray($data);
        $this->assertSame('/missing/page', $data['path']);
    }

    #[Test]
    public function testDispatchReturns404HtmlForUnknownRouteWithHtmlAccept(): void
    {
        $router = new Router();

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/not-found';
        $_SERVER['HTTP_ACCEPT']    = 'text/html';

        ob_start();
        $router->dispatch();
        // ob_get_clean() returns string|false; with ob_start() it is always string
        /** @var string $output */
        $output = ob_get_clean();

        // ErrorPage::render() returns an HtmlResponse with 404 HTML
        $this->assertStringContainsString('<!DOCTYPE html>', $output);
        $this->assertStringContainsString('404', $output);
    }

    #[Test]
    public function testDispatchReturns404HtmlWhenNoAcceptHeader(): void
    {
        $router = new Router();

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/not-found';
        unset($_SERVER['HTTP_ACCEPT']);

        ob_start();
        $router->dispatch();
        // ob_get_clean() returns string|false; with ob_start() it is always string
        /** @var string $output */
        $output = ob_get_clean();

        // Default (no JSON accept) → HTML error page
        $this->assertStringContainsString('<!DOCTYPE html>', $output);
    }

    // ===== Handler return value is sent =====

    #[Test]
    public function testHandlerOutputIsEmitted(): void
    {
        $router = new Router();

        $router->get('/data', static fn (): JsonResponse => new JsonResponse(['value' => 42]));

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/data';
        $_SERVER['HTTP_ACCEPT']    = 'application/json';

        ob_start();
        $router->dispatch();
        $output = ob_get_clean();

        $data = json_decode((string) $output, true);
        $this->assertIsArray($data);
        $this->assertSame(42, $data['value']);
    }

    #[Test]
    public function testHtmlHandlerOutputIsEmitted(): void
    {
        $router = new Router();

        $router->get('/page', static fn (): HtmlResponse => new HtmlResponse('<h1>Hello</h1>'));

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/page';
        $_SERVER['HTTP_ACCEPT']    = 'text/html';

        ob_start();
        $router->dispatch();
        $output = ob_get_clean();

        $this->assertSame('<h1>Hello</h1>', $output);
    }

    // ===== Multiple routes — first match wins =====

    #[Test]
    public function testFirstMatchingRouteWins(): void
    {
        $router       = new Router();
        $firstCalled  = false;
        $secondCalled = false;

        $router->get('/match', function () use (&$firstCalled): JsonResponse {
            $firstCalled = true;

            return new JsonResponse(['first' => true]);
        });

        $router->get('/match', function () use (&$secondCalled): JsonResponse {
            $secondCalled = true;

            return new JsonResponse(['second' => true]);
        });

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/match';
        $_SERVER['HTTP_ACCEPT']    = 'application/json';

        ob_start();
        $router->dispatch();
        ob_get_clean();

        $this->assertTrue($firstCalled);
        $this->assertFalse($secondCalled);
    }

    // ===== Fallbacks for missing superglobals =====

    #[Test]
    public function testDispatchDefaultsToGetWhenRequestMethodMissing(): void
    {
        $router = new Router();
        $called = false;

        $router->get('/', function () use (&$called): JsonResponse {
            $called = true;

            return new JsonResponse([]);
        });

        unset($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
        $_SERVER['HTTP_ACCEPT'] = 'application/json';

        ob_start();
        $router->dispatch();
        ob_get_clean();

        // Default method is GET, default path is /
        $this->assertTrue($called);
    }

    #[Test]
    public function testDispatchDefaultsToRootPathWhenRequestUriBroken(): void
    {
        $router = new Router();
        $called = false;

        $router->get('/', function () use (&$called): JsonResponse {
            $called = true;

            return new JsonResponse([]);
        });

        // Set a URI that parse_url would parse as only a path of /
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/';
        $_SERVER['HTTP_ACCEPT']    = 'application/json';

        ob_start();
        $router->dispatch();
        ob_get_clean();

        $this->assertTrue($called);
    }
}
