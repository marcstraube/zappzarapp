<?php

declare(strict_types=1);

namespace DevToolbar\DataCollectors;

/**
 * Builds the canonical set of data collectors.
 *
 * Extracted so the DevToolbar orchestrator doesn't have to import every
 * concrete collector — keeping it focused on lifecycle and composition
 * rather than the collector inventory.
 */
final class CollectorFactory
{
    /**
     * @return array<string, CollectorInterface>
     */
    public static function createDefault(): array
    {
        return [
            // Phase 1 collectors
            'request'    => new RequestCollector(),
            'queries'    => QueryCollector::getInstance(),
            'messages'   => new MessageCollector(),
            'exceptions' => ExceptionCollector::getInstance(),

            // Phase 2 collectors
            'http'     => new HttpClientCollector(),
            'cache'    => new CacheCollector(),
            'timeline' => new TimelineCollector(),
            'history'  => new HistoryCollector(), // Client-side only (localStorage)
        ];
    }
}
