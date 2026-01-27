<?php

declare(strict_types=1);

namespace Tests\App\Unit\Infrastructure;

use App\Infrastructure\ViteHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

#[CoversClass(ViteHelper::class)]
final class ViteHelperTest extends TestCase
{
    protected function setUp(): void
    {
        putenv('ENV');
        unset($_ENV['ENV']);
    }

    protected function tearDown(): void
    {
        putenv('ENV');
        unset($_ENV['ENV']);
    }

    #[RunInSeparateProcess]
    public function testIsDevelopmentReturnsTrueInDevelopmentMode(): void
    {
        putenv('ENV=development');

        $vite = new ViteHelper();

        $this->assertTrue($vite->isDevelopment());
        $this->assertEquals('development', $vite->getEnv());
    }

    #[RunInSeparateProcess]
    public function testIsDevelopmentReturnsFalseInProductionMode(): void
    {
        putenv('ENV=production');

        $vite = new ViteHelper();

        $this->assertFalse($vite->isDevelopment());
        $this->assertEquals('production', $vite->getEnv());
    }

    #[RunInSeparateProcess]
    public function testDefaultsToProductionWhenEnvNotSet(): void
    {
        $vite = new ViteHelper();

        $this->assertFalse($vite->isDevelopment());
        $this->assertEquals('production', $vite->getEnv());
    }

    #[RunInSeparateProcess]
    public function testRenderScriptTagsInDevelopmentMode(): void
    {
        putenv('ENV=development');

        $vite   = new ViteHelper();
        $output = $vite->renderScriptTags('js/app.js');

        $this->assertStringContainsString('@vite/client', $output);
        $this->assertStringContainsString('js/app.js', $output);
        $this->assertStringContainsString('type="module"', $output);
    }

    #[RunInSeparateProcess]
    public function testRenderCssTagsInDevelopmentMode(): void
    {
        putenv('ENV=development');

        $vite   = new ViteHelper();
        $output = $vite->renderCssTags('js/app.js');

        $this->assertStringContainsString('CSS loaded via Vite HMR', $output);
    }

    #[RunInSeparateProcess]
    public function testRenderScriptTagsInProductionWithoutManifest(): void
    {
        putenv('ENV=production');

        // Use non-existent path to test missing manifest behavior
        $vite   = new ViteHelper('/nonexistent/manifest.json');
        $output = $vite->renderScriptTags('js/app.js');

        $this->assertStringContainsString('manifest not found', $output);
    }

    #[RunInSeparateProcess]
    public function testRenderCssTagsInProductionWithoutManifest(): void
    {
        putenv('ENV=production');

        // Use non-existent path to test missing manifest behavior
        $vite   = new ViteHelper('/nonexistent/manifest.json');
        $output = $vite->renderCssTags('js/app.js');

        $this->assertStringContainsString('No CSS found', $output);
    }

    #[RunInSeparateProcess]
    public function testIsViteDevServerRunningReturnsFalseInProduction(): void
    {
        putenv('ENV=production');

        $vite = new ViteHelper();

        $this->assertFalse($vite->isViteDevServerRunning());
    }

    #[RunInSeparateProcess]
    public function testAreAssetsAvailableReturnsFalseWhenNoManifestInProduction(): void
    {
        putenv('ENV=production');

        // Use non-existent path to test missing manifest behavior
        $vite = new ViteHelper('/nonexistent/manifest.json');

        // Without a manifest file, assets are not available
        $this->assertFalse($vite->areAssetsAvailable());
    }
}
