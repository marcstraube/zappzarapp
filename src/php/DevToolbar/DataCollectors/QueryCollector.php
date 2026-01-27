<?php

declare(strict_types=1);

namespace DevToolbar\DataCollectors;

/**
 * Collects database query information
 *
 * Tracks SQL queries, execution time, bindings, and stack traces.
 * This class serves as a singleton tracker that PDO wrappers can report to.
 */
class QueryCollector implements CollectorInterface
{
    private static ?self $instance = null;
    private array $queries = [];
    private bool $collecting = false;

    private function __construct()
    {
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function start(): void
    {
        $this->collecting = true;
    }

    public function stop(): void
    {
        $this->collecting = false;
    }

    /**
     * Track a database query
     *
     * @param string $sql SQL query
     * @param array<int|string, mixed> $bindings Query bindings
     * @param float $time Execution time in milliseconds
     * @return void
     */
    public function trackQuery(string $sql, array $bindings, float $time): void
    {
        if (!$this->collecting) {
            return;
        }

        $this->queries[] = [
            'sql' => $sql,
            'bindings' => $bindings,
            'time' => round($time, 2),
            'backtrace' => $this->getRelevantBacktrace(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return [
            'queries' => $this->queries,
            'count' => count($this->queries),
            'total_time' => round(array_sum(array_column($this->queries, 'time')), 2),
        ];
    }

    public function getName(): string
    {
        return 'queries';
    }

    /**
     * Get relevant stack trace (filter out DevToolbar internals)
     *
     * @return array<int, array<string, mixed>>
     */
    private function getRelevantBacktrace(): array
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);

        // Filter out DevToolbar and PDO internals
        $filtered = array_filter($trace, function ($frame) {
            $file = $frame['file'] ?? '';
            return !str_contains($file, 'DevToolbar')
                && !str_contains($file, 'PDO')
                && !str_contains($file, 'vendor/');
        });

        // Return up to 5 most relevant frames
        return array_slice(array_values($filtered), 0, 5);
    }

    /**
     * Reset queries (for testing)
     *
     * @return void
     */
    public function reset(): void
    {
        $this->queries = [];
    }
}
