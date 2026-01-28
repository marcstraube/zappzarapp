<?php

declare(strict_types=1);

namespace DevToolbar\DataCollectors;

use DevToolbar\Storage\RequestStore;

/**
 * History Collector (Pseudo-Collector Pattern)
 *
 * Provides request history for HISTORY tab.
 * Does not actively collect - reads from RequestStore.
 */
class HistoryCollector implements CollectorInterface
{
    public function start(): void
    {
        // No-op: Data collected by RequestStore
    }

    public function stop(): void
    {
        // No-op: Nothing to stop
    }

    public function getData(): array
    {
        // With localStorage migration, history is now populated client-side
        // Return minimal structure - JavaScript will populate from localStorage
        return [
            'count' => 0,
            'requests' => [],
            'statistics' => [
                'total_requests' => 0,
                'avg_time' => 0,
                'avg_memory' => 0,
                'avg_queries' => 0,
                'fastest_time' => 0,
                'slowest_time' => 0,
            ],
            'trends' => [
                'time' => [],
                'memory' => [],
                'queries' => [],
            ],
        ];
    }

    public function getName(): string
    {
        return 'history';
    }
}
