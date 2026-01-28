<?php

declare(strict_types=1);

namespace DevToolbar\Renderers\Panels;

/**
 * Renders the HTTP panel content
 *
 * Displays HTTP client request information including:
 * - Request method, URL, status code
 * - Response time and performance indicators
 * - Request headers and body (when available)
 * - Call location (backtrace)
 */
class HttpPanelRenderer extends AbstractPanelRenderer
{
    /**
     * Get panel identifier
     *
     * @return string Panel name
     */
    public function getPanelName(): string
    {
        return 'http';
    }

    /**
     * Render HTTP tab content
     *
     * @param array<string, mixed> $data HTTP data from collector
     * @return string Rendered HTML
     */
    public function renderTab(array $data): string
    {
        $requests = $data['requests'] ?? [];
        $totalTime = $data['total_time'] ?? 0;
        $count = $data['count'] ?? 0;

        if ($count === 0) {
            return $this->renderEmptyState('No HTTP requests made');
        }

        $html = sprintf(
            '<div class="dev-toolbar-section">
                <div class="dev-toolbar-section-title">HTTP Client (%d %s, %.2fms total)</div>
            </div>',
            $count,
            $count === 1 ? 'request' : 'requests',
            $totalTime
        );

        foreach ($requests as $request) {
            $html .= $this->renderHttpRequest($request);
        }

        return $html;
    }

    /**
     * Render individual HTTP request
     *
     * @param array<string, mixed> $request Request data
     * @return string Rendered HTML
     */
    private function renderHttpRequest(array $request): string
    {
        $method = $this->escapeHtml($request['method'] ?? 'GET');
        $url = $this->escapeHtml($request['url'] ?? '');
        $time = $request['time'] ?? 0;
        $status = $request['status'] ?? 0;
        $perfLevel = $request['performance_level'] ?? 'good';

        $icon = match ($perfLevel) {
            'good' => '🟢',
            'warning' => '🟡',
            'critical' => '🔴',
            default => '⚪',
        };

        $html = sprintf(
            '<div class="dev-toolbar-http-request %s">
                <div class="dev-toolbar-http-header">
                    %s <strong>%s</strong> %s <span class="dev-toolbar-http-time">(%.2fms)</span>
                </div>
                <div class="dev-toolbar-http-status">Status: %d</div>',
            $this->escapeHtml($perfLevel),
            $icon,
            $method,
            $url,
            $time,
            $status
        );

        // Show location if available
        if (!empty($request['backtrace'])) {
            $location = $request['backtrace'][0];
            $html .= sprintf(
                '<div class="dev-toolbar-http-location">Called at: %s:%d</div>',
                $this->escapeHtml($location['file'] ?? ''),
                $location['line'] ?? 0
            );
        }

        $html .= '</div>';

        return $html;
    }
}
