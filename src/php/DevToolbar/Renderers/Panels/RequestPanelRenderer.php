<?php

declare(strict_types=1);

namespace DevToolbar\Renderers\Panels;

/**
 * Renders the REQUEST panel content
 *
 * Displays current request information including:
 * - HTTP method, URI, status code
 * - Execution time and peak memory usage
 * - Request headers
 * - Export functionality
 */
class RequestPanelRenderer extends AbstractPanelRenderer
{
    /**
     * Get panel identifier
     *
     * @return string Panel name
     */
    public function getPanelName(): string
    {
        return 'request';
    }

    /**
     * Render REQUEST tab content
     *
     * @param array<string, mixed> $data Request data from collector
     * @return string Rendered HTML
     */
    public function renderTab(array $data): string
    {
        $method = $this->escapeHtml($data['method'] ?? 'GET');
        $uri = $this->escapeHtml($data['uri'] ?? '/');
        $statusCode = $data['status_code'] ?? 200;
        $time = $data['execution_time'] ?? 0;
        $memory = $data['memory_peak'] ?? 0;

        $html = sprintf(
            '<div class="dev-toolbar-section">
                <div class="dev-toolbar-section-title">Current Request</div>
                <table class="dev-toolbar-kv-table">
                    <tr><td>Method</td><td>%s</td></tr>
                    <tr><td>URI</td><td>%s</td></tr>
                    <tr><td>Status</td><td>%d</td></tr>
                    <tr><td>Time</td><td>%.2fms</td></tr>
                    <tr><td>Memory</td><td>%.2fMB</td></tr>
                </table>
            </div>',
            $method,
            $uri,
            $statusCode,
            $time,
            $memory
        );

        // Render headers
        if (!empty($data['headers'])) {
            $html .= '<div class="dev-toolbar-section">
                <div class="dev-toolbar-section-title">Headers</div>
                <table class="dev-toolbar-kv-table">';

            foreach ($data['headers'] as $key => $value) {
                $html .= sprintf(
                    '<tr><td>%s</td><td>%s</td></tr>',
                    $this->escapeHtml($key),
                    $this->escapeHtml(is_array($value) ? (json_encode($value) ?: '[]') : (string)$value)
                );
            }

            $html .= '</table></div>';
        }

        // Xdebug debugging controls
        $xdebugEnabled = extension_loaded('xdebug');
        $xdebugActive = isset($_COOKIE['XDEBUG_SESSION']);
        $xdebugSessionName = $_COOKIE['XDEBUG_SESSION'] ?? '';

        $html .= '<div class="dev-toolbar-section">';
        $html .= '<div class="dev-toolbar-section-title">Xdebug Step Debugging</div>';

        if ($xdebugEnabled) {
            $statusClass = $xdebugActive ? 'active' : 'inactive';
            $statusText = $xdebugActive ? "Active (IDE Key: {$xdebugSessionName})" : 'Inactive';
            $statusIcon = $xdebugActive ? '●' : '○';

            $html .= sprintf(
                '<div class="dev-toolbar-xdebug-status dev-toolbar-xdebug-status-%s">
                    <span class="dev-toolbar-xdebug-indicator">%s</span>
                    <span>%s</span>
                </div>',
                $statusClass,
                $statusIcon,
                $this->escapeHtml($statusText)
            );

            if ($xdebugActive) {
                $html .= '<button class="dev-toolbar-btn dev-toolbar-btn-secondary" data-action="xdebug-disable">
                    ⏹ Disable Debugging
                </button>';
            } else {
                $html .= '<div class="dev-toolbar-xdebug-ide-select">
                    <button class="dev-toolbar-btn dev-toolbar-btn-secondary" data-action="xdebug-enable" data-ide="PHPSTORM">
                        ▶ Enable for PhpStorm
                    </button>
                    <button class="dev-toolbar-btn dev-toolbar-btn-secondary" data-action="xdebug-enable" data-ide="VSCODE">
                        ▶ Enable for VSCode
                    </button>
                </div>';
            }
        } else {
            $html .= '<p style="color: #6b7280; font-size: 0.875rem;">
                Xdebug extension not installed. Install via <code>pecl install xdebug</code>
            </p>';
        }

        $html .= '</div>';

        // Export button for current request
        $html .= '<div class="dev-toolbar-section">
            <button class="dev-toolbar-export-btn" data-action="export-current">
                ⬇ Export Current Request
            </button>
        </div>';

        return $html;
    }
}
