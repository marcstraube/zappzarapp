<?php

declare(strict_types=1);

namespace DevToolbar\Middleware;

use DevToolbar\DataCollectors\CollectorInterface;
use DevToolbar\Renderers\AssetsRenderer;
use DevToolbar\Renderers\DataInjectionRenderer;
use DevToolbar\Renderers\MiniBarRenderer;
use DevToolbar\Renderers\PanelRenderer;

/**
 * Middleware that injects toolbar HTML into response
 *
 * Finds </body> tag and injects toolbar markup before it.
 * All assets (CSS/JS) are self-contained and inline.
 */
class DevToolbarMiddleware
{
    /** @var array<string, CollectorInterface> */
    private array $collectors;

    /**
     * @param array<string, CollectorInterface> $collectors
     */
    public function __construct(array $collectors)
    {
        $this->collectors = $collectors;
    }

    /**
     * Inject toolbar HTML into response output
     *
     * @param string $output Response HTML
     * @return string Modified HTML with toolbar injected
     */
    public function inject(string $output): string
    {
        // Only inject if HTML response (contains </body>)
        if (!str_contains($output, '</body>')) {
            return $output;
        }

        $toolbarHtml = $this->renderToolbar();

        // Inject before </body>
        return str_replace('</body>', $toolbarHtml . '</body>', $output);
    }

    /**
     * Render complete toolbar HTML (mini bar + panel + assets)
     *
     * All assets are inline and self-contained (no external dependencies).
     *
     * @return string Toolbar HTML
     */
    private function renderToolbar(): string
    {
        $miniBarRenderer = new MiniBarRenderer($this->collectors);
        $panelRenderer = new PanelRenderer($this->collectors);
        $assetsRenderer = new AssetsRenderer();
        $dataInjectionRenderer = new DataInjectionRenderer($this->collectors);

        $miniBar = $miniBarRenderer->render();
        $panel = $panelRenderer->render();
        $assets = $assetsRenderer->render();
        $dataScript = $dataInjectionRenderer->render();

        return $miniBar . $panel . $assets . $dataScript;
    }
}
