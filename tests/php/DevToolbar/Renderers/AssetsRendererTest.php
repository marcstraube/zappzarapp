<?php

declare(strict_types=1);

namespace Tests\DevToolbar\Renderers;

use DevToolbar\Renderers\AssetsRenderer;
use PHPUnit\Framework\TestCase;

/**
 * Test AssetsRenderer inline asset generation
 */
class AssetsRendererTest extends TestCase
{
    private AssetsRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderer = new AssetsRenderer();
    }

    public function testRendersInlineAssets(): void
    {
        $output = $this->renderer->render();

        $this->assertStringContainsString('<style>', $output);
        $this->assertStringContainsString('</style>', $output);
        $this->assertStringContainsString('<script>', $output);
        $this->assertStringContainsString('</script>', $output);
    }

    public function testContainsCSSVariables(): void
    {
        $output = $this->renderer->render();

        $this->assertStringContainsString('--toolbar-bg-dark', $output);
        $this->assertStringContainsString('--color-success', $output);
        $this->assertStringContainsString('.dev-toolbar-mini', $output);
        $this->assertStringContainsString('.dev-toolbar-panel', $output);
    }

    public function testContainsJavaScript(): void
    {
        $output = $this->renderer->render();

        $this->assertStringContainsString('DevToolbar', $output);
        $this->assertStringContainsString('init()', $output);
        $this->assertStringContainsString('togglePanel', $output);
        $this->assertStringContainsString('setActiveTab', $output);
    }

    public function testIsSelfContained(): void
    {
        $output = $this->renderer->render();

        // Should not reference external files
        $this->assertStringNotContainsString('href=', $output);
        $this->assertStringNotContainsString('src=', $output);
        $this->assertStringNotContainsString('import ', $output);
        $this->assertStringNotContainsString('require(', $output);
    }
}
