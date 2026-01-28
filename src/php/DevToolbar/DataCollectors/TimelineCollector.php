<?php

declare(strict_types=1);

namespace DevToolbar\DataCollectors;

/**
 * Timeline Collector
 *
 * Visual representation of request lifecycle with timing information
 */
class TimelineCollector implements CollectorInterface
{
    /** @var array<string, array<string, mixed>> */
    private array $events = [];
    private float $requestStart;
    private bool $collecting = false;
    private bool $started = false;

    /**
     * Start collecting timeline events
     */
    public function start(): void
    {
        $this->collecting = true;
        $this->events = [];
        $this->requestStart = $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true);
        $this->started = true;

        // Add initial event
        $this->addEvent('request_start', 'Request Start', null, 'bootstrap');
    }

    /**
     * Stop collecting timeline events
     */
    public function stop(): void
    {
        if ($this->collecting) {
            $this->addEvent('request_end', 'Request End', null, 'response');
        }
        $this->collecting = false;
    }

    /**
     * Get collector name
     */
    public function getName(): string
    {
        return 'TIMELINE';
    }

    /**
     * Get collected timeline data
     */
    public function getData(): array
    {
        $totalTime = $this->started ? (microtime(true) - $this->requestStart) * 1000 : 0;

        // Build timeline from events
        $timeline = $this->buildTimeline();

        return [
            'timeline' => $timeline,
            'total_time' => round($totalTime, 2),
            'events' => $this->events,
            'count' => count($timeline), // Number of timeline items for badge
        ];
    }

    /**
     * Add a timeline event
     *
     * @param string $key Event key (unique identifier)
     * @param string $label Event label (display name)
     * @param float|null $duration Optional explicit duration in ms
     * @param string $category Event category (bootstrap, middleware, controller, view, response)
     * @return void
     */
    public function addEvent(
        string $key,
        string $label,
        ?float $duration = null,
        string $category = 'other'
    ): void {
        if (!$this->collecting) {
            return;
        }

        $this->events[$key] = [
            'label' => $label,
            'time' => microtime(true),
            'duration' => $duration,
            'category' => $category,
        ];
    }

    /**
     * Mark start of a phase
     *
     * @param string $key Phase key
     * @param string $label Phase label
     * @param string $category Phase category
     * @return void
     */
    public function startPhase(string $key, string $label, string $category = 'other'): void
    {
        $this->addEvent($key . '_start', $label, null, $category);
    }

    /**
     * Mark end of a phase and calculate duration
     *
     * @param string $key Phase key (must match startPhase key)
     * @return void
     */
    public function endPhase(string $key): void
    {
        $startKey = $key . '_start';
        if (!isset($this->events[$startKey])) {
            return;
        }

        $startTime = $this->events[$startKey]['time'];
        $duration = (microtime(true) - $startTime) * 1000; // Convert to ms

        // Update the start event with duration
        $this->events[$startKey]['duration'] = round($duration, 2);
        $this->events[$startKey]['label'] = rtrim($this->events[$startKey]['label'], ' Start');
    }

    /**
     * Build timeline data from events
     *
     * @return array<int, array<string, mixed>> Timeline items
     */
    private function buildTimeline(): array
    {
        if (empty($this->events)) {
            return [];
        }

        $totalTime = (microtime(true) - $this->requestStart) * 1000;
        $timeline = [];
        $prevTime = $this->requestStart;

        // Group events by category
        $categorized = [];
        foreach ($this->events as $key => $event) {
            $category = $event['category'];
            if (!isset($categorized[$category])) {
                $categorized[$category] = [];
            }
            $categorized[$category][] = array_merge($event, ['key' => $key]);
        }

        // Build timeline items from categorized events
        foreach ($categorized as $category => $events) {
            $categoryTime = 0;
            $categoryEvents = [];

            foreach ($events as $event) {
                if ($event['duration'] !== null) {
                    $duration = $event['duration'];
                } else {
                    $duration = ($event['time'] - $prevTime) * 1000;
                }

                $categoryTime += $duration;
                $categoryEvents[] = [
                    'label' => $event['label'],
                    'duration' => round($duration, 2),
                ];

                $prevTime = $event['time'];
            }

            if ($categoryTime > 0) {
                $timeline[] = [
                    'label' => ucfirst($category),
                    'category' => $category,
                    'duration' => round($categoryTime, 2),
                    'percentage' => $totalTime > 0 ? round(($categoryTime / $totalTime) * 100, 1) : 0,
                    'events' => $categoryEvents,
                    'is_bottleneck' => $totalTime > 0 && ($categoryTime / $totalTime) > 0.5,
                ];
            }
        }

        return $timeline;
    }

    /**
     * Add aggregated data from other collectors
     *
     * @param string $label Label for the aggregated data
     * @param int $count Number of operations
     * @param float $totalTime Total time in milliseconds
     * @param string $category Category (e.g., 'database', 'http', 'cache')
     * @return void
     */
    public function addAggregatedData(
        string $label,
        int $count,
        float $totalTime,
        string $category = 'other'
    ): void {
        if (!$this->collecting || $count === 0) {
            return;
        }

        $key = 'aggregated_' . $category;
        $this->addEvent(
            $key,
            sprintf('%s (%d %s)', $label, $count, $count === 1 ? 'operation' : 'operations'),
            $totalTime,
            $category
        );
    }

    /**
     * Check if collecting is active
     *
     * @return bool True if collecting
     */
    public function isCollecting(): bool
    {
        return $this->collecting;
    }

    /**
     * Get request start time
     *
     * @return float Start timestamp
     */
    public function getRequestStart(): float
    {
        return $this->requestStart;
    }

    /**
     * Get elapsed time since request start
     *
     * @return float Elapsed time in milliseconds
     */
    public function getElapsedTime(): float
    {
        return ($this->started ? microtime(true) - $this->requestStart : 0) * 1000;
    }
}
