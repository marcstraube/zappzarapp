<?php

/** @noinspection PhpDeprecationInspection AMQPSSLConnection deprecated but no replacement yet */

declare(strict_types=1);

namespace App\Infrastructure\Queue;

use Exception;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPSSLConnection;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

/**
 * RabbitMQ Queue Implementation
 *
 * Thread-safe, lazy-connected RabbitMQ client with automatic reconnection.
 * Supports both TLS (amqps://) and plain (amqp://) connections.
 *
 * Configuration via environment variables:
 * - RABBITMQ_URL: Full connection URL (overrides individual settings)
 * - RABBITMQ_HOST: Hostname (default: rabbitmq)
 * - RABBITMQ_PORT: Port (default: 5672, or 5671 for TLS)
 * - RABBITMQ_VHOST: Virtual host (default: /)
 *
 * Credentials are loaded from Docker secrets or environment:
 * - /run/secrets/rabbitmq_user or RABBITMQ_USER
 * - /run/secrets/rabbitmq_password or RABBITMQ_PASSWORD
 *
 * Error Handling:
 * - Connection failures return false/null (no exceptions thrown to caller)
 * - Use isAvailable() to check connection status
 * - Automatic reconnection on next operation after failure
 *
 * Usage:
 * <code>
 * $queue = new RabbitMQQueue();
 *
 * // Publish a task
 * $queue->publish('tasks', json_encode(['type' => 'email', 'to' => 'user@example.com']));
 *
 * // Consume tasks (blocking)
 * $queue->consume('tasks', function(string $message): bool {
 *     $task = json_decode($message, true);
 *     return processTask($task); // Return true to ACK, false to NACK
 * });
 * </code>
 *
 * @package Infrastructure\Queue
 *
 * @SuppressWarnings("PHPMD.ExcessiveClassComplexity") Complexity from QueueInterface methods + type-safe option handling
 */
final class RabbitMQQueue implements QueueInterface
{
    private ?AMQPStreamConnection $connection = null;

    private ?AMQPChannel $channel = null;

    private readonly RabbitMQConfig $config;

    public function __construct(
        ?string $url = null,
        private readonly int $timeout = 5
    ) {
        $this->config = new RabbitMQConfig($url);
    }

    public function publish(string $queue, string $message, array $options = []): bool
    {
        $channel = $this->getChannel();
        if (!$channel instanceof AMQPChannel) {
            return false;
        }

        try {
            if (!$this->declareQueue($queue)) {
                return false;
            }

            $amqpMessage = new AMQPMessage($message, $this->buildMessageProperties($options));
            $channel->basic_publish($amqpMessage, '', $queue);

            return true;
        } catch (Exception) {
            $this->disconnect();
            return false;
        }
    }

    public function consume(string $queue, callable $callback, array $options = []): void
    {
        $channel = $this->getChannel();
        if (!$channel instanceof AMQPChannel) {
            return;
        }

        try {
            if (!$this->declareQueue($queue)) {
                return;
            }

            $prefetch = $this->getIntOption($options, 'prefetch', 1);
            $noAck    = $this->getBoolOption($options, 'noAck', false);
            $timeout  = $this->getNumericOption($options, 'timeout', 0);

            $channel->basic_qos(0, $prefetch, false);
            $channel->basic_consume(
                $queue,
                '',    // consumer tag (auto-generated)
                false, // no_local
                $noAck,
                false, // exclusive
                false, // nowait
                $this->createConsumerCallback($callback, $noAck)
            );

            while ($channel->is_consuming()) {
                $channel->wait(null, false, $timeout);
            }
        } catch (Exception) {
            $this->disconnect();
        }
    }

    /**
     * @SuppressWarnings("PHPMD.BooleanArgumentFlag") Standard AMQP API pattern
     */
    public function get(string $queue, bool $autoAck = false): ?array
    {
        $channel = $this->getChannel();
        if (!$channel instanceof AMQPChannel) {
            return null;
        }

        try {
            if (!$this->declareQueue($queue)) {
                return null;
            }

            $message = $channel->basic_get($queue, $autoAck);

            if ($message === null) {
                return null;
            }

            return [
                'body'        => $message->getBody(),
                'deliveryTag' => $message->getDeliveryTag(),
            ];
        } catch (Exception) {
            $this->disconnect();
            return null;
        }
    }

    public function ack(int $deliveryTag): bool
    {
        $channel = $this->getChannel();
        if (!$channel instanceof AMQPChannel) {
            return false;
        }

        try {
            $channel->basic_ack($deliveryTag);
            return true;
        } catch (Exception) {
            $this->disconnect();
            return false;
        }
    }

    /**
     * @SuppressWarnings("PHPMD.BooleanArgumentFlag") Standard AMQP API pattern
     */
    public function nack(int $deliveryTag, bool $requeue = false): bool
    {
        $channel = $this->getChannel();
        if (!$channel instanceof AMQPChannel) {
            return false;
        }

        try {
            $channel->basic_nack($deliveryTag, false, $requeue);
            return true;
        } catch (Exception) {
            $this->disconnect();
            return false;
        }
    }

    public function declareQueue(string $queue, array $options = []): bool
    {
        $channel = $this->getChannel();
        if (!$channel instanceof AMQPChannel) {
            return false;
        }

        try {
            $channel->queue_declare(
                $queue,
                false, // passive
                $this->getBoolOption($options, 'durable', true),
                $this->getBoolOption($options, 'exclusive', false),
                $this->getBoolOption($options, 'autoDelete', false),
                false, // nowait
                $this->buildQueueArguments($options)
            );

            return true;
        } catch (Exception) {
            $this->disconnect();
            return false;
        }
    }

    public function getMessageCount(string $queue): ?int
    {
        $channel = $this->getChannel();
        if (!$channel instanceof AMQPChannel) {
            return null;
        }

        try {
            $result = $channel->queue_declare($queue, true);

            if (!is_array($result) || !isset($result[1])) {
                return null;
            }

            /** @var int $messageCount */
            $messageCount = $result[1];
            return $messageCount;
        } catch (Exception) {
            $this->disconnect();
            return null;
        }
    }

    public function purgeQueue(string $queue): int
    {
        $channel = $this->getChannel();
        if (!$channel instanceof AMQPChannel) {
            return 0;
        }

        try {
            $result = $channel->queue_purge($queue);
            return is_int($result) ? $result : 0;
        } catch (Exception) {
            $this->disconnect();
            return 0;
        }
    }

    public function isAvailable(): bool
    {
        $channel = $this->getChannel();
        if (!$channel instanceof AMQPChannel) {
            return false;
        }

        try {
            return $this->connection instanceof AMQPStreamConnection
                && $this->connection->isConnected()
                && $channel->is_open();
        } catch (Exception) {
            return false;
        }
    }

    /**
     * Build message properties from options
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function buildMessageProperties(array $options): array
    {
        $persistent = $this->getBoolOption($options, 'persistent', true);

        $properties = [
            'delivery_mode' => $persistent
                ? AMQPMessage::DELIVERY_MODE_PERSISTENT
                : AMQPMessage::DELIVERY_MODE_NON_PERSISTENT,
        ];

        if (isset($options['priority']) && is_numeric($options['priority'])) {
            $properties['priority'] = min(9, max(0, (int) $options['priority']));
        }

        if (isset($options['expiration']) && (is_string($options['expiration']) || is_numeric($options['expiration']))) {
            $properties['expiration'] = (string) $options['expiration'];
        }

        if (isset($options['headers']) && is_array($options['headers'])) {
            $properties['application_headers'] = new AMQPTable($options['headers']);
        }

        return $properties;
    }

    /**
     * Build queue arguments from options
     *
     * @param array<string, mixed> $options
     * @return AMQPTable|array<string, mixed>
     */
    private function buildQueueArguments(array $options): AMQPTable|array
    {
        if (isset($options['arguments']) && is_array($options['arguments'])) {
            return new AMQPTable($options['arguments']);
        }

        return [];
    }

    /**
     * Create consumer callback with ack/nack handling
     *
     * @param callable(string): bool $callback
     */
    private function createConsumerCallback(callable $callback, bool $noAck): callable
    {
        return function (AMQPMessage $amqpMessage) use ($callback, $noAck): void {
            $result = $callback($amqpMessage->getBody());

            if ($noAck) {
                return;
            }

            $deliveryTag = $amqpMessage->getDeliveryTag();
            $channel     = $amqpMessage->getChannel();

            if ($result) {
                $channel?->basic_ack($deliveryTag);
                return;
            }

            $channel?->basic_nack($deliveryTag, false, true);
        };
    }

    /**
     * Get boolean option with type checking
     *
     * @param array<string, mixed> $options
     */
    private function getBoolOption(array $options, string $key, bool $default): bool
    {
        if (!isset($options[$key]) || !is_bool($options[$key])) {
            return $default;
        }

        return $options[$key];
    }

    /**
     * Get integer option with type checking
     *
     * @param array<string, mixed> $options
     *
     * @noinspection PhpSameParameterValueInspection Helper method for consistency, may be reused
     */
    private function getIntOption(array $options, string $key, int $default): int
    {
        if (!isset($options[$key]) || !is_int($options[$key])) {
            return $default;
        }

        return $options[$key];
    }

    /**
     * Get numeric option (int or float) with type checking
     *
     * @param array<string, mixed> $options
     *
     * @noinspection PhpSameParameterValueInspection Helper method for consistency, may be reused
     */
    private function getNumericOption(array $options, string $key, int|float $default): int|float
    {
        if (!isset($options[$key])) {
            return $default;
        }

        if (is_int($options[$key]) || is_float($options[$key])) {
            return $options[$key];
        }

        return $default;
    }

    /**
     * Get or create channel (lazy initialization)
     */
    private function getChannel(): ?AMQPChannel
    {
        if ($this->channel instanceof AMQPChannel && $this->channel->is_open()) {
            return $this->channel;
        }

        try {
            $this->connection = $this->createConnection();

            if (!$this->connection->isConnected()) {
                return null;
            }

            $this->channel = $this->connection->channel();

            return $this->channel;
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Create connection based on TLS setting
     *
     * @noinspection PhpUnhandledExceptionInspection Exceptions handled in getChannel()
     * @noinspection PhpDeprecationInspection AMQPSSLConnection deprecated but no replacement yet (php-amqplib 3.x)
     *
     * @todo Replace AMQPSSLConnection when php-amqplib provides alternative TLS connection method
     */
    private function createConnection(): AMQPStreamConnection
    {
        if ($this->config->useTls) {
            return new AMQPSSLConnection(
                $this->config->host,
                $this->config->port,
                $this->config->user,
                $this->config->password,
                $this->config->vhost,
                [
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true,
                ],
                [
                    'connection_timeout' => $this->timeout,
                    'read_write_timeout' => 30,
                ]
            );
        }

        return new AMQPStreamConnection(
            $this->config->host,
            $this->config->port,
            $this->config->user,
            $this->config->password,
            $this->config->vhost,
            false,
            'AMQPLAIN',
            null,
            'en_US',
            $this->timeout
        );
    }

    /**
     * Close connection (for reconnection on next use)
     */
    private function disconnect(): void
    {
        try {
            $this->channel?->close();
        } catch (Exception) {
            // Ignore close errors
        }

        try {
            $this->connection?->close();
        } catch (Exception) {
            // Ignore close errors
        }

        $this->channel    = null;
        $this->connection = null;
    }
}
