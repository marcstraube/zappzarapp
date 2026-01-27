<?php

declare(strict_types=1);

namespace DevToolbar\Renderers;

use DevToolbar\DataCollectors\CollectorInterface;

/**
 * Renders expandable panel with tabs
 */
class PanelRenderer implements RendererInterface
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

    public function render(): string
    {
        $tabs = $this->renderTabs();
        $content = $this->renderTabContents();

        return sprintf(
            '<div class="dev-toolbar-panel">
                <div class="dev-toolbar-panel-header">
                    %s
                    <button class="dev-toolbar-panel-close">▼</button>
                </div>
                <div class="dev-toolbar-panel-content">
                    %s
                </div>
            </div>',
            $tabs,
            $content
        );
    }

    /**
     * Render tab buttons
     *
     * @return string Tab buttons HTML
     */
    private function renderTabs(): string
    {
        $tabs = '';

        foreach ($this->collectors as $name => $collector) {
            $data = $collector->getData();
            $count = $data['count'] ?? 0;
            $label = strtoupper($name);

            $tabs .= sprintf(
                '<button class="dev-toolbar-panel-tab" data-tab="%s">
                    %s<span class="dev-toolbar-panel-tab-badge">%d</span>
                </button>',
                $name,
                $label,
                $count
            );
        }

        return $tabs;
    }

    /**
     * Render tab content panes
     *
     * @return string Tab content HTML
     */
    private function renderTabContents(): string
    {
        $contents = '';

        foreach ($this->collectors as $name => $collector) {
            $data = $collector->getData();
            $content = match ($name) {
                'request' => $this->renderRequestTab($data),
                'queries' => $this->renderQueriesTab($data),
                'messages' => $this->renderMessagesTab($data),
                'exceptions' => $this->renderExceptionsTab($data),
                default => '<p>No data</p>',
            };

            $contents .= sprintf(
                '<div class="dev-toolbar-panel-tab-pane" data-tab="%s">%s</div>',
                $name,
                $content
            );
        }

        return $contents;
    }

    /**
     * Render REQUEST tab content
     *
     * @param array<string, mixed> $data
     * @return string HTML
     */
    private function renderRequestTab(array $data): string
    {
        $method = htmlspecialchars($data['method'] ?? 'GET');
        $uri = htmlspecialchars($data['uri'] ?? '/');
        $statusCode = $data['status_code'] ?? 200;
        $time = $data['execution_time'] ?? 0;
        $memory = $data['memory_peak'] ?? 0;

        $html = sprintf(
            '<div class="dev-toolbar-section">
                <div class="dev-toolbar-section-title">Request</div>
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
                    htmlspecialchars($key),
                    htmlspecialchars(is_array($value) ? json_encode($value) : $value)
                );
            }

            $html .= '</table></div>';
        }

        return $html;
    }

    /**
     * Render QUERIES tab content
     *
     * @param array<string, mixed> $data
     * @return string HTML
     */
    private function renderQueriesTab(array $data): string
    {
        $queries = $data['queries'] ?? [];
        $totalTime = $data['total_time'] ?? 0;

        if (empty($queries)) {
            return '<p>No queries executed</p>';
        }

        $html = sprintf(
            '<div class="dev-toolbar-section">
                <div class="dev-toolbar-section-title">Summary: %d queries in %.2fms</div>
            </div>',
            count($queries),
            $totalTime
        );

        foreach ($queries as $query) {
            $time = $query['time'] ?? 0;
            $timeClass = $time < 100 ? 'fast' : ($time < 500 ? 'slow' : 'very-slow');
            $queryClass = $timeClass;

            $sql = htmlspecialchars($query['sql'] ?? '');
            $bindings = json_encode($query['bindings'] ?? [], JSON_PRETTY_PRINT);

            $html .= sprintf(
                '<div class="dev-toolbar-query %s">
                    <div class="dev-toolbar-query-header">
                        <span class="dev-toolbar-query-time %s">%.2fms</span>
                    </div>
                    <div class="dev-toolbar-query-sql">%s</div>',
                $queryClass,
                $timeClass,
                $time,
                $sql
            );

            if (!empty($query['bindings'])) {
                $html .= sprintf(
                    '<div class="dev-toolbar-query-bindings">Bindings: %s</div>',
                    htmlspecialchars($bindings)
                );
            }

            $html .= '</div>';
        }

        return $html;
    }

    /**
     * Render MESSAGES tab content
     *
     * @param array<string, mixed> $data
     * @return string HTML
     */
    private function renderMessagesTab(array $data): string
    {
        $messages = $data['messages'] ?? [];

        if (empty($messages)) {
            return '<p>No log messages</p>';
        }

        $html = '';

        foreach ($messages as $message) {
            $level = $message['level'] ?? 'info';
            $levelName = htmlspecialchars($message['level_name'] ?? 'INFO');
            $text = htmlspecialchars($message['message'] ?? '');
            $time = htmlspecialchars($message['datetime'] ?? '');

            $html .= sprintf(
                '<div class="dev-toolbar-message %s">
                    <div class="dev-toolbar-message-header">
                        <span class="dev-toolbar-message-level">%s</span>
                        <span class="dev-toolbar-message-time">%s</span>
                    </div>
                    <div class="dev-toolbar-message-text">%s</div>
                </div>',
                $level,
                $levelName,
                $time,
                $text
            );
        }

        return $html;
    }

    /**
     * Render EXCEPTIONS tab content
     *
     * @param array<string, mixed> $data
     * @return string HTML
     */
    private function renderExceptionsTab(array $data): string
    {
        $exceptions = $data['exceptions'] ?? [];

        if (empty($exceptions)) {
            return '<p>No exceptions thrown</p>';
        }

        $html = '';

        foreach ($exceptions as $exception) {
            $handled = $exception['handled'] ?? true;
            $class = htmlspecialchars($exception['class'] ?? 'Exception');
            $message = htmlspecialchars($exception['message'] ?? '');
            $file = htmlspecialchars($exception['file'] ?? '');
            $line = $exception['line'] ?? 0;

            $html .= sprintf(
                '<div class="dev-toolbar-exception %s">
                    <div class="dev-toolbar-exception-class">%s</div>
                    <div class="dev-toolbar-exception-message">%s</div>
                    <div class="dev-toolbar-exception-location">%s:%d</div>
                </div>',
                $handled ? 'handled' : '',
                $class,
                $message,
                $file,
                $line
            );
        }

        return $html;
    }
}
