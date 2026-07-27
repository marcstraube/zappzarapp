<?php

declare(strict_types=1);

namespace App\Infrastructure\Mercure;

/**
 * Mercure Interface for publishing real-time updates
 *
 * Provides a thin, type-safe abstraction over the Mercure hub's publish
 * endpoint. Updates are pushed to subscribers over Server-Sent Events (SSE).
 *
 * When to use:
 * - Live notifications, presence, activity feeds
 * - Pushing server-side changes to browsers without polling
 * - Broadcasting to many subscribers on a topic
 *
 * When NOT to use:
 * - Request/response messaging (use HTTP or a queue)
 * - Guaranteed delivery / work queues (use RabbitMQ)
 * - Bidirectional streaming (Mercure is server-to-client only)
 *
 * Usage:
 * <code>
 * // Get from DI container
 * $mercure = $container->get(MercureInterface::class);
 *
 * // Publish a public update to a single topic
 * $mercure->publish('https://example.com/books/1', json_encode(['status' => 'sold']));
 *
 * // Publish a private update to several topics with SSE metadata
 * $mercure->publish(
 *     ['https://example.com/users/1', 'https://example.com/users/2'],
 *     json_encode(['type' => 'message', 'body' => 'Hello']),
 *     ['private' => true, 'type' => 'chat', 'id' => 'msg-42'],
 * );
 * </code>
 *
 * @package Infrastructure\Mercure
 */
interface MercureInterface
{
    /**
     * Publish an update to one or more topics
     *
     * @param string|list<string> $topics One topic or a list of topics (IRIs)
     * @param string $data The update payload (already serialized, e.g. JSON)
     * @param array{private?: bool, id?: string, type?: string, retry?: int} $options
     *   - private: bool - Dispatch as a private update (authorized subscribers only)
     *   - id: string - SSE event id (the hub generates one when omitted)
     *   - type: string - SSE event type
     *   - retry: int - SSE reconnection time in milliseconds
     * @return string The event id assigned by the hub
     * @throws MercureException If the hub is unreachable or returns a non-2xx status
     */
    public function publish(string|array $topics, string $data, array $options = []): string;

    /**
     * Check whether the Mercure hub is reachable
     *
     * Use this for health checks or graceful degradation.
     *
     * @return bool True if the hub responds
     */
    public function isAvailable(): bool;
}
