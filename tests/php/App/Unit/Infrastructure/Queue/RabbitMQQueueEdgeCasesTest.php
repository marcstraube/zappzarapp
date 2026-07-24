<?php

declare(strict_types=1);

namespace Tests\App\Unit\Infrastructure\Queue;

use App\Infrastructure\Queue\RabbitMQConfig;
use App\Infrastructure\Queue\RabbitMQQueue;
use Exception;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Edge case tests for RabbitMQQueue — error paths and branches not covered by
 * RabbitMQQueueTest (kept separate to respect the 20-method limit).
 *
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects") Required mocks for AMQP testing
 */
#[CoversClass(RabbitMQQueue::class)]
#[UsesClass(RabbitMQConfig::class)]
final class RabbitMQQueueEdgeCasesTest extends TestCase
{
    private const string TEST_URL = 'amqp://user:password@localhost:5672/testvhost';

    #[Test]
    public function testConsumeDoesNothingWhenNoChannel(): void
    {
        // Without a real connection the channel cannot be obtained;
        // consume() must return early without throwing.
        $queue = new RabbitMQQueue(self::TEST_URL);

        // consume() must exit before invoking the callback — no exception thrown
        $queue->consume('test-queue', static fn(): bool => true);

        $this->addToAssertionCount(1); // confirm no exception
    }

    #[Test]
    public function testConsumeReturnsEarlyWhenDeclareQueueFails(): void
    {
        $mockChannel = $this->createMock(AMQPChannel::class);

        // declareQueue() calls queue_declare — throw to simulate failure
        $mockChannel->expects($this->once())
            ->method('queue_declare')
            ->willThrowException(new Exception('Queue error'));

        $queue = $this->createQueueWithMockedChannel($mockChannel);

        // consume() disconnects and returns early — no exception reaches the test
        $queue->consume('test-queue', static fn(): bool => true);

        $this->addToAssertionCount(1); // confirm no exception
    }

    #[Test]
    public function testConsumeSetupRunsWhenChannelNotConsuming(): void
    {
        $mockChannel = $this->createMock(AMQPChannel::class);

        // queue_declare for declareQueue()
        $mockChannel->expects($this->once())
            ->method('queue_declare')
            ->with('test-queue', false, true, false, false, false, []);

        $mockChannel->expects($this->once())
            ->method('basic_qos')
            ->with(0, 1, false);

        $mockChannel->expects($this->once())
            ->method('basic_consume');

        // Loop exits immediately because is_consuming() returns false
        $mockChannel->expects($this->once())
            ->method('is_consuming')
            ->willReturn(false);

        $queue = $this->createQueueWithMockedChannel($mockChannel);

        // Callback is registered but never invoked (is_consuming() returns false immediately)
        $queue->consume('test-queue', static fn(): bool => true);

        // No assertion needed — PHPUnit verifies expectations above.
        $this->assertTrue(true);
    }

    #[Test]
    public function testPublishWithHeadersOptionAddsAMQPTable(): void
    {
        $mockChannel = $this->createMock(AMQPChannel::class);
        $mockChannel->expects($this->once())->method('queue_declare');

        $mockChannel->expects($this->once())
            ->method('basic_publish')
            ->with(
                $this->callback(
                    static fn(AMQPMessage $msg): bool => $msg->has('application_headers')
                ),
                '',
                'test-queue'
            );

        $queue = $this->createQueueWithMockedChannel($mockChannel);

        $this->assertTrue($queue->publish('test-queue', 'payload', [
            'headers' => ['x-trace-id' => 'abc123'],
        ]));
    }

    #[Test]
    public function testConsumerCallbackAcksMessageOnSuccessfulCallback(): void
    {
        $mockChannel = $this->createMock(AMQPChannel::class);
        $mockChannel->expects($this->once())
            ->method('basic_ack')
            ->with(42);

        $mockMessage = $this->createStub(AMQPMessage::class);
        $mockMessage->method('getBody')->willReturn('hello');
        $mockMessage->method('getDeliveryTag')->willReturn(42);
        $mockMessage->method('getChannel')->willReturn($mockChannel);

        $receivedBody = null;
        $queue        = new RabbitMQQueue(self::TEST_URL);
        $callback     = $this->invokeCreateConsumerCallback(
            $queue,
            static function (string $body) use (&$receivedBody): bool {
                $receivedBody = $body;
                return true;
            },
            false
        );

        $callback($mockMessage);

        $this->assertSame('hello', $receivedBody);
    }

    #[Test]
    public function testConsumerCallbackNacksMessageOnFailedCallback(): void
    {
        $mockChannel = $this->createMock(AMQPChannel::class);
        $mockChannel->expects($this->once())
            ->method('basic_nack')
            ->with(7, false, true);

        $mockMessage = $this->createStub(AMQPMessage::class);
        $mockMessage->method('getBody')->willReturn('bad');
        $mockMessage->method('getDeliveryTag')->willReturn(7);
        $mockMessage->method('getChannel')->willReturn($mockChannel);

        $receivedBody = null;
        $queue        = new RabbitMQQueue(self::TEST_URL);
        $callback     = $this->invokeCreateConsumerCallback(
            $queue,
            static function (string $body) use (&$receivedBody): bool {
                $receivedBody = $body;
                return false;
            },
            false
        );

        $callback($mockMessage);

        $this->assertSame('bad', $receivedBody);
    }

    #[Test]
    public function testConsumerCallbackSkipsAckNackWhenNoAck(): void
    {
        $mockChannel = $this->createMock(AMQPChannel::class);
        $mockChannel->expects($this->never())->method('basic_ack');
        $mockChannel->expects($this->never())->method('basic_nack');

        $mockMessage = $this->createStub(AMQPMessage::class);
        $mockMessage->method('getBody')->willReturn('msg');
        $mockMessage->method('getChannel')->willReturn($mockChannel);

        $receivedBody = null;
        $queue        = new RabbitMQQueue(self::TEST_URL);
        $callback     = $this->invokeCreateConsumerCallback(
            $queue,
            static function (string $body) use (&$receivedBody): bool {
                $receivedBody = $body;
                return true;
            },
            true
        );

        $callback($mockMessage);

        $this->assertSame('msg', $receivedBody);
    }

    /**
     * Invoke the private createConsumerCallback method via reflection.
     *
     * @param callable(string): bool $callback
     */
    private function invokeCreateConsumerCallback(
        RabbitMQQueue $queue,
        callable $callback,
        bool $noAck
    ): callable {
        $reflection = new ReflectionClass($queue);
        $method     = $reflection->getMethod('createConsumerCallback');

        /** @var callable $result */
        $result = $method->invoke($queue, $callback, $noAck);

        return $result;
    }

    /**
     * Create a RabbitMQQueue with a mocked channel injected via reflection.
     *
     * @param AMQPChannel&(MockObject|Stub) $mockChannel
     */
    private function createQueueWithMockedChannel(AMQPChannel $mockChannel): RabbitMQQueue
    {
        $queue = new RabbitMQQueue(self::TEST_URL);

        $mockChannel->method('is_open')->willReturn(true);

        $mockConnection = $this->createStub(AMQPStreamConnection::class);
        $mockConnection->method('isConnected')->willReturn(true);

        $reflection = new ReflectionClass($queue);

        $reflection->getProperty('channel')->setValue($queue, $mockChannel);
        $reflection->getProperty('connection')->setValue($queue, $mockConnection);

        return $queue;
    }
}
