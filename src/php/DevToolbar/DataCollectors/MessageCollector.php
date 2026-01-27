<?php

declare(strict_types=1);

namespace DevToolbar\DataCollectors;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;

/**
 * Collects log messages from Monolog
 *
 * Extends Monolog handler to capture log messages during request lifecycle.
 */
class MessageCollector extends AbstractProcessingHandler implements CollectorInterface
{
    private array $messages = [];
    private bool $collecting = false;

    public function start(): void
    {
        $this->collecting = true;
    }

    public function stop(): void
    {
        $this->collecting = false;
    }

    /**
     * Process log record from Monolog
     *
     * @param LogRecord $record
     * @return void
     */
    protected function write(LogRecord $record): void
    {
        if (!$this->collecting) {
            return;
        }

        $this->messages[] = [
            'level' => strtolower($record->level->getName()),
            'level_name' => $record->level->getName(),
            'message' => $record->message,
            'context' => $record->context,
            'datetime' => $record->datetime->format('H:i:s.u'),
            'channel' => $record->channel,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return [
            'messages' => $this->messages,
            'count' => count($this->messages),
            'by_level' => $this->groupByLevel(),
        ];
    }

    public function getName(): string
    {
        return 'messages';
    }

    /**
     * Group messages by log level
     *
     * @return array<string, int>
     */
    private function groupByLevel(): array
    {
        $grouped = [];

        foreach ($this->messages as $message) {
            $level = $message['level'];
            $grouped[$level] = ($grouped[$level] ?? 0) + 1;
        }

        return $grouped;
    }

    /**
     * Reset messages (for testing)
     *
     * @return void
     */
    public function reset(): void
    {
        $this->messages = [];
    }
}
