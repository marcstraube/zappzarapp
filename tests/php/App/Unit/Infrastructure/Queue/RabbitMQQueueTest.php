<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Queue;

use App\Infrastructure\Queue\RabbitMQConfig;
use App\Infrastructure\Queue\RabbitMQQueue;
use Exception;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Unit tests for RabbitMQQueue
 *
 * These tests use mocking to test the queue logic without a real RabbitMQ connection.
 * For integration tests with actual RabbitMQ, see tests/php/App/Feature/.
 *
 * @SuppressWarnings("PHPMD.TooManyPublicMethods") Comprehensive tests for queue interface
 * @SuppressWarnings("PHPMD.TooManyMethods") Each method needs success/failure/error tests
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects") Required mocks for AMQP testing
 */
#[CoversClass(RabbitMQQueue::class)]
final class RabbitMQQueueTest extends TestCase
{
    private const string TEST_URL = 'amqp://user:password@localhost:5672/testvhost';

    public function testPublishSendsMessageToQueue(): void
    {
        $mockChannel = $this->createMock(AMQPChannel::class);
        $mockChannel->expects($this->once())
            ->method('queue_declare')
            ->with('test-queue', false, true, false, false, false, []);

        $mockChannel->expects($this->once())
            ->method('basic_publish')
            ->with(
                $this->isInstanceOf(AMQPMessage::class),
                '',
                'test-queue'
            );

        $queue = $this->createQueueWithMockedChannel($mockChannel);

        $this->assertTrue($queue->publish('test-queue', 'test message'));
    }

    public function testPublishReturnsFalseWhenNotConnected(): void
    {
        $queue = new RabbitMQQueue(self::TEST_URL);

        // Without a real connection, publish should return false
        $this->assertFalse($queue->publish('test-queue', 'test message'));
    }

    public function testPublishReturnsFalseOnException(): void
    {
        $mockChannel = $this->createMock(AMQPChannel::class);
        $mockChannel->expects($this->once())
            ->method('queue_declare')
            ->willThrowException(new Exception('Connection lost'));

        $queue = $this->createQueueWithMockedChannel($mockChannel);

        $this->assertFalse($queue->publish('test-queue', 'test message'));
    }

    public function testPublishWithCustomOptions(): void
    {
        $mockChannel = $this->createMock(AMQPChannel::class);
        $mockChannel->expects($this->once())
            ->method('queue_declare');

        $mockChannel->expects($this->once())
            ->method('basic_publish')
            ->with(
                $this->callback(
                    // Check that message has the expected properties
                    fn(AMQPMessage $message): bool => $message->get('delivery_mode') === AMQPMessage::DELIVERY_MODE_NON_PERSISTENT
                    && $message->get('priority') === 5
                    && $message->get('expiration') === '60000'),
                '',
                'test-queue'
            );

        $queue = $this->createQueueWithMockedChannel($mockChannel);

        $this->assertTrue($queue->publish('test-queue', 'test message', [
            'persistent'  => false,
            'priority'    => 5,
            'expiration'  => '60000',
        ]));
    }

    public function testGetReturnMessageFromQueue(): void
    {
        $mockMessage = $this->createMock(AMQPMessage::class);
        $mockMessage->method('getBody')->willReturn('test message body');
        $mockMessage->method('getDeliveryTag')->willReturn(123);

        $mockChannel = $this->createMock(AMQPChannel::class);
        $mockChannel->method('queue_declare');
        $mockChannel->expects($this->once())
            ->method('basic_get')
            ->with('test-queue', false)
            ->willReturn($mockMessage);

        $queue = $this->createQueueWithMockedChannel($mockChannel);

        $result = $queue->get('test-queue');

        $this->assertIsArray($result);
        $this->assertEquals('test message body', $result['body']);
        $this->assertEquals(123, $result['deliveryTag']);
    }

    public function testGetReturnsNullWhenQueueIsEmpty(): void
    {
        $mockChannel = $this->createMock(AMQPChannel::class);
        $mockChannel->method('queue_declare');
        $mockChannel->expects($this->once())
            ->method('basic_get')
            ->with('test-queue', false)
            ->willReturn(null);

        $queue = $this->createQueueWithMockedChannel($mockChannel);

        $this->assertNull($queue->get('test-queue'));
    }

    public function testGetReturnsNullOnException(): void
    {
        $mockChannel = $this->createMock(AMQPChannel::class);
        $mockChannel->method('queue_declare')
            ->willThrowException(new Exception('Connection lost'));

        $queue = $this->createQueueWithMockedChannel($mockChannel);

        $this->assertNull($queue->get('test-queue'));
    }

    public function testAckAcknowledgesMessage(): void
    {
        $mockChannel = $this->createMock(AMQPChannel::class);
        $mockChannel->expects($this->once())
            ->method('basic_ack')
            ->with(123);

        $queue = $this->createQueueWithMockedChannel($mockChannel);

        $this->assertTrue($queue->ack(123));
    }

    public function testAckReturnsFalseOnException(): void
    {
        $mockChannel = $this->createMock(AMQPChannel::class);
        $mockChannel->expects($this->once())
            ->method('basic_ack')
            ->willThrowException(new Exception('Connection lost'));

        $queue = $this->createQueueWithMockedChannel($mockChannel);

        $this->assertFalse($queue->ack(123));
    }

    public function testNackRejectsMessage(): void
    {
        $mockChannel = $this->createMock(AMQPChannel::class);
        $mockChannel->expects($this->once())
            ->method('basic_nack')
            ->with(123, false, false);

        $queue = $this->createQueueWithMockedChannel($mockChannel);

        $this->assertTrue($queue->nack(123));
    }

    public function testNackRequeuesMessageWhenRequested(): void
    {
        $mockChannel = $this->createMock(AMQPChannel::class);
        $mockChannel->expects($this->once())
            ->method('basic_nack')
            ->with(123, false, true);

        $queue = $this->createQueueWithMockedChannel($mockChannel);

        $this->assertTrue($queue->nack(123, true));
    }

    public function testNackReturnsFalseOnException(): void
    {
        $mockChannel = $this->createMock(AMQPChannel::class);
        $mockChannel->expects($this->once())
            ->method('basic_nack')
            ->willThrowException(new Exception('Connection lost'));

        $queue = $this->createQueueWithMockedChannel($mockChannel);

        $this->assertFalse($queue->nack(123));
    }

    public function testDeclareQueueCreatesQueue(): void
    {
        $mockChannel = $this->createMock(AMQPChannel::class);
        $mockChannel->expects($this->once())
            ->method('queue_declare')
            ->with(
                'test-queue',
                false,
                true,  // durable
                false, // exclusive
                false, // autoDelete
                false,
                []
            );

        $queue = $this->createQueueWithMockedChannel($mockChannel);

        $this->assertTrue($queue->declareQueue('test-queue'));
    }

    public function testDeclareQueueWithCustomOptions(): void
    {
        $mockChannel = $this->createMock(AMQPChannel::class);
        $mockChannel->expects($this->once())
            ->method('queue_declare')
            ->with(
                'test-queue',
                false,
                false, // durable
                true,  // exclusive
                true,  // autoDelete
                false,
                $this->anything()
            );

        $queue = $this->createQueueWithMockedChannel($mockChannel);

        $this->assertTrue($queue->declareQueue('test-queue', [
            'durable'    => false,
            'exclusive'  => true,
            'autoDelete' => true,
            'arguments'  => ['x-max-priority' => 10],
        ]));
    }

    public function testDeclareQueueReturnsFalseOnException(): void
    {
        $mockChannel = $this->createMock(AMQPChannel::class);
        $mockChannel->expects($this->once())
            ->method('queue_declare')
            ->willThrowException(new Exception('Connection lost'));

        $queue = $this->createQueueWithMockedChannel($mockChannel);

        $this->assertFalse($queue->declareQueue('test-queue'));
    }

    public function testGetMessageCountReturnsCount(): void
    {
        $mockChannel = $this->createMock(AMQPChannel::class);
        $mockChannel->expects($this->once())
            ->method('queue_declare')
            ->with('test-queue', true) // passive = true
            ->willReturn(['test-queue', 42, 0]);

        $queue = $this->createQueueWithMockedChannel($mockChannel);

        $this->assertEquals(42, $queue->getMessageCount('test-queue'));
    }

    public function testGetMessageCountReturnsNullOnException(): void
    {
        $mockChannel = $this->createMock(AMQPChannel::class);
        $mockChannel->expects($this->once())
            ->method('queue_declare')
            ->willThrowException(new Exception('Queue not found'));

        $queue = $this->createQueueWithMockedChannel($mockChannel);

        $this->assertNull($queue->getMessageCount('test-queue'));
    }

    public function testPurgeQueueRemovesAllMessages(): void
    {
        $mockChannel = $this->createMock(AMQPChannel::class);
        $mockChannel->expects($this->once())
            ->method('queue_purge')
            ->with('test-queue')
            ->willReturn(15);

        $queue = $this->createQueueWithMockedChannel($mockChannel);

        $this->assertEquals(15, $queue->purgeQueue('test-queue'));
    }

    public function testPurgeQueueReturnsZeroOnException(): void
    {
        $mockChannel = $this->createMock(AMQPChannel::class);
        $mockChannel->expects($this->once())
            ->method('queue_purge')
            ->willThrowException(new Exception('Connection lost'));

        $queue = $this->createQueueWithMockedChannel($mockChannel);

        $this->assertEquals(0, $queue->purgeQueue('test-queue'));
    }

    public function testIsAvailableReturnsTrueWhenConnected(): void
    {
        $mockConnection = $this->createMock(AMQPStreamConnection::class);
        $mockConnection->method('isConnected')->willReturn(true);

        $mockChannel = $this->createMock(AMQPChannel::class);
        $mockChannel->method('is_open')->willReturn(true);

        $queue = $this->createQueueWithMockedChannel($mockChannel, $mockConnection);

        $this->assertTrue($queue->isAvailable());
    }

    public function testIsAvailableReturnsFalseWhenNotConnected(): void
    {
        $queue = new RabbitMQQueue(self::TEST_URL);

        $this->assertFalse($queue->isAvailable());
    }

    public function testIsAvailableReturnsFalseOnException(): void
    {
        $mockConnection = $this->createMock(AMQPStreamConnection::class);
        $mockConnection->method('isConnected')->willThrowException(new Exception('Error'));

        $mockChannel = $this->createMock(AMQPChannel::class);

        $queue = $this->createQueueWithMockedChannel($mockChannel, $mockConnection);

        $this->assertFalse($queue->isAvailable());
    }

    public function testConstructorParsesAmqpUrl(): void
    {
        $queue = new RabbitMQQueue('amqp://testuser:testpass@testhost:5673/testvhost');

        $config = $this->getConfigFromQueue($queue);

        $this->assertEquals('testhost', $config->host);
        $this->assertEquals(5673, $config->port);
        $this->assertEquals('testuser', $config->user);
        $this->assertEquals('testpass', $config->password);
        $this->assertEquals('testvhost', $config->vhost);
        $this->assertFalse($config->useTls);
    }

    public function testConstructorParsesAmqpsUrlWithTls(): void
    {
        $queue = new RabbitMQQueue('amqps://user:pass@host:5671/vhost');

        $config = $this->getConfigFromQueue($queue);

        $this->assertTrue($config->useTls);
        $this->assertEquals(5671, $config->port);
    }

    public function testConstructorUsesDefaultValuesForMinimalUrl(): void
    {
        $queue = new RabbitMQQueue('amqp://localhost');

        $config = $this->getConfigFromQueue($queue);

        $this->assertEquals(5672, $config->port);
        $this->assertEquals('/', $config->vhost);
    }

    public function testConstructorHandlesUrlEncodedCredentials(): void
    {
        // URL with special characters that need encoding
        $queue = new RabbitMQQueue('amqp://user%40domain:pass%2Fword@host:5672/');

        $config = $this->getConfigFromQueue($queue);

        $this->assertEquals('user@domain', $config->user);
        $this->assertEquals('pass/word', $config->password);
    }

    /**
     * Create a RabbitMQQueue instance with mocked channel and connection
     */
    private function createQueueWithMockedChannel(
        AMQPChannel&MockObject $mockChannel,
        ?AMQPStreamConnection $mockConnection = null
    ): RabbitMQQueue {
        $queue = new RabbitMQQueue(self::TEST_URL);

        $mockChannel->method('is_open')->willReturn(true);

        if (!$mockConnection instanceof AMQPStreamConnection) {
            $mockConnection = $this->createMock(AMQPStreamConnection::class);
            $mockConnection->method('isConnected')->willReturn(true);
        }

        // Inject mock via reflection
        $reflection = new ReflectionClass($queue);

        $channelProperty = $reflection->getProperty('channel');
        $channelProperty->setValue($queue, $mockChannel);

        $connectionProperty = $reflection->getProperty('connection');
        $connectionProperty->setValue($queue, $mockConnection);

        return $queue;
    }

    /**
     * Get the config object from a RabbitMQQueue instance via reflection
     */
    private function getConfigFromQueue(RabbitMQQueue $queue): RabbitMQConfig
    {
        $reflection     = new ReflectionClass($queue);
        $configProperty = $reflection->getProperty('config');

        /** @var RabbitMQConfig $config */
        $config = $configProperty->getValue($queue);

        return $config;
    }
}
