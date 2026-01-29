<?php

declare(strict_types=1);

namespace DevToolbar\DataCollectors;

/**
 * Interface for all data collectors
 *
 * Data collectors gather debugging information during request lifecycle.
 */
interface CollectorInterface
{
    /**
     * Start collecting data
     *
     * @return void
     */
    public function start(): void;

    /**
     * Stop collecting data
     *
     * @return void
     */
    public function stop(): void;

    /**
     * Get collected data
     *
     * @return array<string, mixed> Collected data
     */
    public function getData(): array;

    /**
     * Get collector name (for tab label)
     *
     * @return string Collector name
     */
    public function getName(): string;
}
