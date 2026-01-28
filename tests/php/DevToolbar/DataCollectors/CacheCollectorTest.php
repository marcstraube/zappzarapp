<?php

declare(strict_types=1);

namespace Tests\DevToolbar\DataCollectors;

use DevToolbar\DataCollectors\CacheCollector;
use PHPUnit\Framework\TestCase;

/**
 * Test CacheCollector cache operation tracking
 */
class CacheCollectorTest extends TestCase
{
    private CacheCollector $collector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->collector = new CacheCollector();
    }

    public function testStartStopCollecting(): void
    {
        $this->assertFalse($this->collector->isCollecting());

        $this->collector->start();
        $this->assertTrue($this->collector->isCollecting());

        $this->collector->stop();
        $this->assertFalse($this->collector->isCollecting());
    }

    public function testGetName(): void
    {
        $this->assertEquals('CACHE', $this->collector->getName());
    }

    public function testTracksCacheHit(): void
    {
        $this->collector->start();
        $this->collector->trackOperation(
            'get',
            'user:123',
            2.5,
            '{"id":123,"name":"John"}',
            true,
            3600
        );
        $this->collector->stop();

        $data = $this->collector->getData();

        $this->assertEquals(1, $data['count']);
        $this->assertEquals(1, $data['hits']);
        $this->assertEquals(0, $data['misses']);
        $this->assertEquals(100.0, $data['hit_rate']);
        $this->assertEquals(2.5, $data['total_time']);
    }

    public function testTracksCacheMiss(): void
    {
        $this->collector->start();
        $this->collector->trackOperation(
            'get',
            'user:999',
            1.8,
            null,
            false
        );
        $this->collector->stop();

        $data = $this->collector->getData();

        $this->assertEquals(1, $data['count']);
        $this->assertEquals(0, $data['hits']);
        $this->assertEquals(1, $data['misses']);
        $this->assertEquals(0.0, $data['hit_rate']);
    }

    public function testCalculatesHitRate(): void
    {
        $this->collector->start();

        // 3 hits
        $this->collector->trackOperation('get', 'key1', 1.0, 'value1', true);
        $this->collector->trackOperation('get', 'key2', 1.0, 'value2', true);
        $this->collector->trackOperation('get', 'key3', 1.0, 'value3', true);

        // 1 miss
        $this->collector->trackOperation('get', 'key4', 1.0, null, false);

        $this->collector->stop();

        $data = $this->collector->getData();

        $this->assertEquals(4, $data['count']);
        $this->assertEquals(3, $data['hits']);
        $this->assertEquals(1, $data['misses']);
        $this->assertEquals(75.0, $data['hit_rate']); // 3/4 = 75%
    }

    public function testTracksSetOperation(): void
    {
        $this->collector->start();
        $this->collector->trackOperation(
            'set',
            'session:abc',
            3.2,
            ['user_id' => 123],
            false,
            7200
        );
        $this->collector->stop();

        $data = $this->collector->getData();
        $operation = $data['operations'][0];

        $this->assertEquals('set', $operation['type']);
        $this->assertEquals('session:abc', $operation['key']);
        $this->assertEquals(3.2, $operation['time']);
        $this->assertEquals(7200, $operation['ttl']);
        $this->assertArrayHasKey('size', $operation);
    }

    public function testTracksDeleteOperation(): void
    {
        $this->collector->start();
        $this->collector->trackOperation(
            'delete',
            'old_cache:*',
            5.5,
            15 // Number of keys deleted
        );
        $this->collector->stop();

        $data = $this->collector->getData();
        $operation = $data['operations'][0];

        $this->assertEquals('delete', $operation['type']);
        $this->assertEquals('old_cache:*', $operation['key']);
        $this->assertEquals(5.5, $operation['time']);
    }

    public function testDoesNotTrackWhenNotCollecting(): void
    {
        // Don't call start()
        $this->collector->trackOperation('get', 'key', 1.0, 'value', true);

        $data = $this->collector->getData();

        $this->assertEquals(0, $data['count']);
    }

    public function testTracksMultipleOperations(): void
    {
        $this->collector->start();

        $this->collector->trackOperation('get', 'key1', 1.0, 'value1', true);
        $this->collector->trackOperation('set', 'key2', 2.0, 'value2', false, 3600);
        $this->collector->trackOperation('delete', 'key3', 1.5, 1);

        $this->collector->stop();

        $data = $this->collector->getData();

        $this->assertEquals(3, $data['count']);
        $this->assertEquals(4.5, $data['total_time']);
        $this->assertCount(3, $data['operations']);
    }

    public function testFiltersSensitiveValues(): void
    {
        $this->collector->start();

        $sensitiveData = ['password' => 'secret', 'token' => 'abc123', 'name' => 'John'];
        $this->collector->trackOperation('get', 'user:1', 1.0, $sensitiveData, true);

        $this->collector->stop();

        $data = $this->collector->getData();
        $operation = $data['operations'][0];
        $value = $operation['value'];

        $this->assertEquals('[FILTERED]', $value['password']);
        $this->assertEquals('[FILTERED]', $value['token']);
        $this->assertEquals('John', $value['name']); // Not filtered
    }

    public function testCapturesBacktrace(): void
    {
        $this->collector->start();
        $this->collector->trackOperation('get', 'key', 1.0, 'value', true);
        $this->collector->stop();

        $data = $this->collector->getData();
        $operation = $data['operations'][0];

        $this->assertIsArray($operation['backtrace']);
    }
}
