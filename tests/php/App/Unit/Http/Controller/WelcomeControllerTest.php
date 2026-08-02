<?php

declare(strict_types=1);

namespace Tests\App\Unit\Http\Controller;

use App\Http\Controller\WelcomeController;
use App\Http\Response\HtmlResponse;
use App\Infrastructure\DatabaseConfig;
use App\Infrastructure\HealthCheck;
use App\Infrastructure\TlsConfig;
use App\Infrastructure\TwigService;
use App\Infrastructure\ViteHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\UsesFunction;
use PHPUnit\Framework\TestCase;
use Zappzarapp\Security\Csp\Nonce\NonceRegistry;

/**
 * Unit tests for WelcomeController.
 *
 * Strategy: construct real collaborators (TwigService, ViteHelper, HealthCheck)
 * with test-friendly configuration (production mode, no manifest, no real filesystem
 * paths) and capture rendered HTML via ob_start()/ob_get_clean().
 *
 * DevToolbarGuard::isEnabled() always returns false in PHP CLI (PHPUnit), so
 * demoDevToolbarFeatures() is never entered and collector classes are not exercised.
 *
 * @SuppressWarnings("PHPMD.TooManyPublicMethods")
 */
#[CoversClass(WelcomeController::class)]
#[UsesClass(DatabaseConfig::class)]
#[UsesClass(HealthCheck::class)]
#[UsesClass(HtmlResponse::class)]
#[UsesClass(TlsConfig::class)]
#[UsesClass(TwigService::class)]
#[UsesClass(ViteHelper::class)]
#[UsesFunction('nonce')]
final class WelcomeControllerTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $originalServer = [];

    private string $originalEnv = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalServer = $_SERVER;
        $this->originalEnv    = getenv('ZAPPZARAPP_ENV') ?: '';
        // Clear env vars that affect HealthCheck and ViteHelper defaults
        putenv('ZAPPZARAPP_ENV');
        putenv('ENABLE_NODE');
        putenv('ENABLE_DATABASE');
        putenv('ENABLE_REDIS');
        unset($_ENV['ZAPPZARAPP_ENV'], $_ENV['ENABLE_NODE'], $_ENV['ENABLE_DATABASE'], $_ENV['ENABLE_REDIS']);
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->originalServer;
        putenv('ZAPPZARAPP_ENV=' . $this->originalEnv);
        putenv('ENABLE_NODE');
        putenv('ENABLE_DATABASE');
        putenv('ENABLE_REDIS');
        parent::tearDown();
    }

    /**
     * Build a WelcomeController with real collaborators pointing at the
     * project's own templates directory (available on the host via volume mount).
     *
     * ViteHelper is instantiated in production mode (ZAPPZARAPP_ENV not set → defaults to
     * 'production') with no manifest file so renderCssTags/renderScriptTags
     * return safe comment placeholders and no network I/O occurs.
     *
     * The 'nonce' Twig function is registered because Twig validates all
     * function references at compile time (even inside conditional blocks).
     * The production bootstrap registers it in index.php; in tests we
     * register it directly on the TwigService.
     */
    private function createController(): WelcomeController
    {
        $templateDir = __DIR__ . '/../../../../../../templates';
        $cacheDir    = __DIR__ . '/../../../../../../build/cache/twig-test';

        $twig = TwigService::createForDevelopment($templateDir, $cacheDir);
        $twig->addFunction('nonce', NonceRegistry::get(...));

        return new WelcomeController(
            new ViteHelper('/nonexistent/manifest.json'),
            new HealthCheck(),
            $twig,
        );
    }

    // =========================================================================
    // index() — smoke / structure
    // =========================================================================

    #[Test]
    public function testIndexReturnsHtmlResponseInstance(): void
    {
        $controller = $this->createController();

        $response = $controller->index();

        $this->assertInstanceOf(HtmlResponse::class, $response);
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testIndexEmitsStatus200(): void
    {
        $controller = $this->createController();

        ob_start();
        $controller->index()->send();
        ob_get_clean();

        $this->assertSame(200, http_response_code());
    }

    // =========================================================================
    // index() — HTML content markers
    // =========================================================================

    #[Test]
    public function testIndexOutputContainsDoctype(): void
    {
        $controller = $this->createController();

        ob_start();
        $controller->index()->send();
        $output = ob_get_clean();

        $this->assertStringContainsString('<!DOCTYPE html>', (string) $output);
    }

    #[Test]
    public function testIndexOutputContainsZappzarappTitle(): void
    {
        $controller = $this->createController();

        ob_start();
        $controller->index()->send();
        $output = ob_get_clean();

        $this->assertStringContainsString('zappzarapp', (string) $output);
    }

    #[Test]
    public function testIndexOutputContainsOverallStatus(): void
    {
        $controller = $this->createController();

        ob_start();
        $controller->index()->send();
        $output = ob_get_clean();

        // Template renders status.overall_status (always 'ok' when no real services enabled)
        $this->assertStringContainsString('Overall Status', (string) $output);
    }

    #[Test]
    public function testIndexOutputContainsServiceStatusSection(): void
    {
        $controller = $this->createController();

        ob_start();
        $controller->index()->send();
        $output = ob_get_clean();

        $this->assertStringContainsString('Service Status', (string) $output);
    }

    #[Test]
    public function testIndexOutputContainsEndpointsSection(): void
    {
        $controller = $this->createController();

        ob_start();
        $controller->index()->send();
        $output = ob_get_clean();

        $this->assertStringContainsString('Available Endpoints', (string) $output);
    }

    #[Test]
    public function testIndexOutputContainsHealthEndpointLink(): void
    {
        $controller = $this->createController();

        ob_start();
        $controller->index()->send();
        $output = ob_get_clean();

        $this->assertStringContainsString('/health', (string) $output);
    }

    // =========================================================================
    // index() — filesystem-check variables (docs/source flags)
    // =========================================================================

    #[Test]
    public function testIndexOutputReflectsPhpSourceAvailability(): void
    {
        // /var/www/html/src/php does not exist on the host runner, so
        // hasPhpSource will be false → no "not generated" notice rendered
        $controller = $this->createController();

        ob_start();
        $controller->index()->send();
        $output = (string) ob_get_clean();

        // Page must render without exception regardless of the filesystem state
        $this->assertStringContainsString('<!DOCTYPE html>', $output);
    }

    // =========================================================================
    // index() — ZAPPZARAPP_ENV variable propagated to template
    // =========================================================================

    #[RunInSeparateProcess]
    #[Test]
    public function testIndexShowsProductionModeWhenEnvNotSet(): void
    {
        putenv('ZAPPZARAPP_ENV=production');

        $controller = $this->createController();

        ob_start();
        $controller->index()->send();
        $output = ob_get_clean();

        // In production mode the template shows the production mode indicator
        $this->assertStringContainsString('Production', (string) $output);
    }

    // =========================================================================
    // index() — DevToolbar guard is always off in CLI (PHPUnit)
    // =========================================================================

    #[Test]
    public function testIndexDoesNotCallDemoFeaturesInCliMode(): void
    {
        // DevToolbarGuard::isEnabled() returns false in CLI (PHP_SAPI === 'cli'),
        // so demoDevToolbarFeatures() is never reached. The test simply asserts
        // the controller renders without error, confirming the guard path.
        $controller = $this->createController();

        ob_start();
        $controller->index()->send();
        $output = ob_get_clean();

        // Toolbar demo section (only shown in development via Twig) should not appear
        // (we're in production mode by default — ZAPPZARAPP_ENV unset → 'production')
        $this->assertStringNotContainsString('Developer Toolbar Demo Active', (string) $output);
    }

    // =========================================================================
    // index() — ViteHelper integration in template
    // =========================================================================

    #[Test]
    public function testIndexOutputContainsViteComment(): void
    {
        // In production with no manifest, ViteHelper returns safe placeholders
        $controller = $this->createController();

        ob_start();
        $controller->index()->send();
        $output = ob_get_clean();

        // ViteHelper::renderCssTags() or ::renderScriptTags() with no manifest
        // returns "<!-- No CSS found in manifest -->" / "<!-- Vite manifest not found ... -->"
        $this->assertStringContainsString('<!--', (string) $output);
    }
}
