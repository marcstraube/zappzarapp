<?php

declare(strict_types=1);

namespace App\Infrastructure\Queue;

/**
 * Queue Interface for message queue operations
 *
 * Provides a simple, type-safe interface for publishing and consuming messages.
 * Implementations can use RabbitMQ, Redis Streams, Amazon SQS, etc.
 *
 * When to use:
 * - Asynchronous task processing (background jobs)
 * - Event-driven architecture (microservices communication)
 * - Work distribution across multiple workers
 * - Rate limiting and throttling
 * - Decoupling producers from consumers
 *
 * When NOT to use:
 * - Synchronous request/response (use HTTP/RPC)
 * - Real-time streaming (use WebSockets/SSE)
 * - Simple caching (use CacheInterface)
 *
 * Usage:
 * <code>
 * // Get from DI container
 * $queue = $container->get(QueueInterface::class);
 *
 * // Publish a message
 * $queue->publish('orders', json_encode(['orderId' => 123, 'action' => 'process']));
 *
 * // Publish with custom options
 * $queue->publish('emails', $emailData, [
 *     'persistent' => true,
 *     'priority' => 5,
 * ]);
 *
 * // Consume messages (blocking)
 * $queue->consume('orders', function(string $message): bool {
 *     $data = json_decode($message, true);
 *     processOrder($data);
 *     return true; // ACK - remove from queue
 * });
 * </code>
 *
 * @package Infrastructure\Queue
 */
interface QueueInterface
{
    /**
     * Publish a message to a queue
     *
     * @param string $queue Queue name
     * @param string $message Message content (serialize objects to JSON)
     * @param array<string, mixed> $options Optional publishing options:
     *   - persistent: bool - Survive broker restart (default: true)
     *   - priority: int - Message priority 0-9 (default: 0)
     *   - expiration: int - Message TTL in milliseconds
     *   - headers: array - Custom message headers
     * @return bool True if published successfully
     */
    public function publish(string $queue, string $message, array $options = []): bool;

    /**
     * Consume messages from a queue
     *
     * This method blocks and processes messages until stopped.
     * The callback should return true to ACK (acknowledge) the message,
     * or false to NACK (negative acknowledge) and requeue.
     *
     * @param string $queue Queue name to consume from
     * @param callable(string): bool $callback Function to process each message
     * @param array<string, mixed> $options Optional consumer options:
     *   - prefetch: int - Number of unacked messages to prefetch (default: 1)
     *   - noAck: bool - Auto-acknowledge messages (default: false)
     *   - timeout: int - Consumer timeout in seconds (0 = no timeout)
     */
    public function consume(string $queue, callable $callback, array $options = []): void;

    /**
     * Get a single message from a queue without blocking
     *
     * Unlike consume(), this returns immediately with one message or null.
     * Use this for polling or when you need non-blocking behavior.
     *
     * @param string $queue Queue name
     * @param bool $autoAck Whether to automatically acknowledge the message
     * @return array{body: string, deliveryTag: int}|null Message data or null if queue is empty
     *
     * @SuppressWarnings("PHPMD.BooleanArgumentFlag") Standard AMQP API pattern
     */
    public function get(string $queue, bool $autoAck = false): ?array;

    /**
     * Acknowledge a message (mark as processed)
     *
     * @param int $deliveryTag Delivery tag from get() response
     * @return bool True if acknowledged successfully
     */
    public function ack(int $deliveryTag): bool;

    /**
     * Reject a message
     *
     * @param int $deliveryTag Delivery tag from get() response
     * @param bool $requeue Whether to requeue the message (default: false)
     * @return bool True if rejected successfully
     *
     * @SuppressWarnings("PHPMD.BooleanArgumentFlag") Standard AMQP API pattern
     */
    public function nack(int $deliveryTag, bool $requeue = false): bool;

    /**
     * Declare a queue (create if not exists)
     *
     * @param string $queue Queue name
     * @param array<string, mixed> $options Queue options:
     *   - durable: bool - Survive broker restart (default: true)
     *   - exclusive: bool - Used by only one connection (default: false)
     *   - autoDelete: bool - Delete when last consumer disconnects (default: false)
     *   - arguments: array - Additional queue arguments (x-max-priority, x-message-ttl, etc.)
     * @return bool True if declared successfully
     */
    public function declareQueue(string $queue, array $options = []): bool;

    /**
     * Get the number of messages in a queue
     *
     * @param string $queue Queue name
     * @return int|null Message count or null if queue doesn't exist
     */
    public function getMessageCount(string $queue): ?int;

    /**
     * Purge all messages from a queue
     *
     * Warning: This permanently deletes all messages!
     *
     * @param string $queue Queue name
     * @return int Number of messages purged
     */
    public function purgeQueue(string $queue): int;

    /**
     * Check if queue backend is available
     *
     * Use this for health checks or graceful degradation.
     *
     * @return bool True if connected and responsive
     */
    public function isAvailable(): bool;
}
