<?php

declare(strict_types=1);

namespace DevToolbar\Renderers;

use DevToolbar\Security\NonceHelper;
use RuntimeException;

/**
 * Renders inline CSS and JavaScript assets
 *
 * All assets are self-contained and independent of Vite/build process.
 * Uses CSP nonce for inline script/style tags.
 */
class AssetsRenderer implements RendererInterface
{
    private const ASSETS_DIR = __DIR__ . '/../assets/';

    public function render(): string
    {
        return $this->renderCSS() . $this->renderJavaScript();
    }

    /**
     * Render inline CSS
     *
     * @return string CSS in <style> tag
     */
    private function renderCSS(): string
    {
        $css = $this->loadAsset('devtoolbar.css');
        $nonce = NonceHelper::get();

        return sprintf(
            '<style nonce="%s">%s</style>',
            htmlspecialchars($nonce, ENT_QUOTES, 'UTF-8'),
            $css
        );
    }

    /**
     * Render inline JavaScript
     *
     * @return string JavaScript in <script> tag
     */
    private function renderJavaScript(): string
    {
        $js = $this->loadAsset('devtoolbar.js');
        $nonce = NonceHelper::get();

        return sprintf(
            '<script nonce="%s">%s</script>',
            htmlspecialchars($nonce, ENT_QUOTES, 'UTF-8'),
            $js
        );
    }

    /**
     * Load asset file content
     *
     * @param string $filename Asset filename (e.g., 'devtoolbar.css')
     * @return string Asset content
     * @throws RuntimeException If asset file cannot be loaded
     */
    private function loadAsset(string $filename): string
    {
        $path = self::ASSETS_DIR . $filename;

        if (!file_exists($path) || !is_readable($path)) {
            throw new RuntimeException(sprintf(
                'DevToolbar asset file not found or not readable: %s',
                $filename
            ));
        }

        $content = file_get_contents($path);

        if ($content === false) {
            throw new RuntimeException(sprintf(
                'Failed to read DevToolbar asset file: %s',
                $filename
            ));
        }

        return $content;
    }
}
