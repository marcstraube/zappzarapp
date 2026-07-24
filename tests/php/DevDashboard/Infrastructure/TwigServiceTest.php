<?php

declare(strict_types=1);

namespace Tests\DevDashboard\Infrastructure;

use DevDashboard\Infrastructure\TwigService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Error\LoaderError;

/**
 * Unit tests for DevDashboard TwigService
 *
 * Uses a temp directory with a minimal template so no real template files
 * from the project are required. Cache is always disabled to avoid write
 * permission issues in CI.
 */
#[CoversClass(TwigService::class)]
final class TwigServiceTest extends TestCase
{
    private string $templateDir;

    private string $cacheDir;

    protected function setUp(): void
    {
        parent::setUp();

        // Build isolated temp dirs for each test run
        $base              = sys_get_temp_dir() . '/twig_service_test_' . uniqid();
        $this->templateDir = $base . '/templates';
        $this->cacheDir    = $base . '/cache';

        mkdir($this->templateDir, 0755, recursive: true);
        mkdir($this->cacheDir, 0755, recursive: true);

        // Write a minimal template
        file_put_contents($this->templateDir . '/hello.html.twig', 'Hello {{ name }}!');
        file_put_contents($this->templateDir . '/empty.html.twig', '');
        file_put_contents($this->templateDir . '/nonce.html.twig', '{{ myNonce }}');
    }

    protected function tearDown(): void
    {
        $this->removeDir(sys_get_temp_dir() . '/twig_service_test_' . basename($this->templateDir, '/templates'));
        $this->removeDir($this->templateDir);
        $this->removeDir($this->cacheDir);

        parent::tearDown();
    }

    // ===== Factory methods =====

    public function testCreateForDevelopmentReturnsTwigService(): void
    {
        $service = TwigService::createForDevelopment($this->templateDir, $this->cacheDir);

        $this->assertInstanceOf(TwigService::class, $service);
    }

    public function testCreateForProductionReturnsTwigService(): void
    {
        $service = TwigService::createForProduction($this->templateDir, $this->cacheDir);

        $this->assertInstanceOf(TwigService::class, $service);
    }

    // ===== render() =====

    public function testRenderReturnsRenderedString(): void
    {
        $service = TwigService::createForDevelopment($this->templateDir, $this->cacheDir);

        $output = $service->render('hello.html.twig', ['name' => 'World']);

        $this->assertSame('Hello World!', $output);
    }

    public function testRenderWithEmptyContextReturnsTemplate(): void
    {
        $service = TwigService::createForDevelopment($this->templateDir, $this->cacheDir);

        $output = $service->render('empty.html.twig');

        $this->assertSame('', $output);
    }

    public function testRenderEscapesHtmlByDefault(): void
    {
        // Twig auto-escape is 'html', so < > & " ' must be escaped
        $service = TwigService::createForDevelopment($this->templateDir, $this->cacheDir);

        $output = $service->render('hello.html.twig', ['name' => '<script>']);

        $this->assertStringNotContainsString('<script>', $output);
        $this->assertStringContainsString('&lt;script&gt;', $output);
    }

    public function testRenderThrowsOnMissingTemplate(): void
    {
        $service = TwigService::createForDevelopment($this->templateDir, $this->cacheDir);

        $this->expectException(LoaderError::class);
        $service->render('does-not-exist.html.twig');
    }

    // ===== addFunction() =====

    public function testAddFunctionMakesCallableAvailableInTemplate(): void
    {
        file_put_contents($this->templateDir . '/fn.html.twig', '{{ greet("Alice") }}');

        $service = TwigService::createForDevelopment($this->templateDir, $this->cacheDir);
        $service->addFunction('greet', fn (string $name): string => 'Hi ' . $name);

        $output = $service->render('fn.html.twig');

        $this->assertSame('Hi Alice', $output);
    }

    // ===== addGlobal() =====

    public function testAddGlobalMakesVariableAvailableInTemplate(): void
    {
        file_put_contents($this->templateDir . '/global.html.twig', '{{ appVersion }}');

        $service = TwigService::createForDevelopment($this->templateDir, $this->cacheDir);
        $service->addGlobal('appVersion', '1.2.3');

        $output = $service->render('global.html.twig');

        $this->assertSame('1.2.3', $output);
    }

    // ===== getEnvironment() =====

    public function testGetEnvironmentReturnsEnvironmentInstance(): void
    {
        $service = TwigService::createForDevelopment($this->templateDir, $this->cacheDir);

        $env = $service->getEnvironment();

        $this->assertInstanceOf(Environment::class, $env);
    }

    public function testGetEnvironmentReturnsSameInstance(): void
    {
        $service = TwigService::createForDevelopment($this->templateDir, $this->cacheDir);

        $env1 = $service->getEnvironment();
        $env2 = $service->getEnvironment();

        $this->assertSame($env1, $env2);
    }

    // ===== Helpers =====

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = array_diff(scandir($dir) ?: [], ['.', '..']);
        foreach ($items as $item) {
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }

        rmdir($dir);
    }
}
