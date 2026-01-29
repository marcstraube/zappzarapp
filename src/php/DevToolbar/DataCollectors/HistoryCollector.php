<?php

declare(strict_types=1);

namespace DevToolbar\DataCollectors;

/**
 * History Collector (Client-Side Only)
 *
 * This collector exists ONLY to enable the History tab in the UI.
 * All actual history functionality is implemented client-side in localStorage.
 *
 * Purpose:
 * - Registers the "history" collector so PanelRenderer creates the tab
 * - Returns empty data structure (history is populated by JavaScript)
 *
 * Implementation:
 * - Start/stop are no-ops (no server-side collection)
 * - getData() returns empty structure matching HistoryPanelRenderer expectations
 * - JavaScript populates history from localStorage (StorageManager)
 */
class HistoryCollector implements CollectorInterface
{
    public function getName(): string
    {
        return 'history';
    }

    public function start(): void
    {
        // No-op: History is managed client-side
    }

    public function stop(): void
    {
        // No-op: History is managed client-side
    }

    public function getData(): array
    {
        // Return empty structure - JavaScript will populate from localStorage
        return [
            'count'      => 0,
            'requests'   => [],
            'statistics' => [
                'total'       => 0,
                'avg_time'    => 0,
                'avg_memory'  => 0,
                'avg_queries' => 0,
            ],
        ];
    }
}
