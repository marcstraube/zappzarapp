<?php

declare(strict_types=1);

namespace DevToolbar\Renderers;

use DevToolbar\DataCollectors\CollectorInterface;

/**
 * Renders mini bar (always visible widget in bottom-right)
 */
class MiniBarRenderer implements RendererInterface
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
        $requestData = $this->collectors['request']->getData();
        $queriesData = $this->collectors['queries']->getData();

        $time = $requestData['execution_time'] ?? 0;
        $memory = $requestData['memory_peak'] ?? 0;
        $queryCount = $queriesData['count'] ?? 0;
        $env = getenv('ENV') ?: 'dev';

        $envClass = match ($env) {
            'staging' => 'staging',
            'production' => 'production',
            default => 'dev',
        };

        return sprintf(
            '<div class="dev-toolbar-mini">
                <span class="dev-toolbar-mini-env %s">%s</span>
                <span class="dev-toolbar-mini-metric">%dms</span>
                <span class="dev-toolbar-mini-metric">%.1fMB</span>
                <span class="dev-toolbar-mini-metric">%d queries</span>
                <span class="dev-toolbar-mini-expand">↗</span>
            </div>',
            $envClass,
            strtoupper($env),
            (int)$time,
            $memory,
            $queryCount
        );
    }
}
