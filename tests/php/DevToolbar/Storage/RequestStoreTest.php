<?php

declare(strict_types=1);

namespace Tests\DevToolbar\Storage;

use DevToolbar\Storage\RequestStore;
use PHPUnit\Framework\TestCase;

/**
 * Test RequestStore request history storage
 */
class RequestStoreTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Ensure session is started for tests
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        // Clear any existing data
        RequestStore::clear();
    }

    protected function tearDown(): void
    {
        RequestStore::clear();
        parent::tearDown();
    }

    public function testStoresRequestInSession(): void
    {
        RequestStore::store('req-1', [
            'request' => [
                'method' => 'GET',
                'uri' => '/test',
                'status_code' => 200,
                'time' => 100,
                'memory' => 1_000_000,
            ],
            'queries' => ['queries' => []],
            'http' => ['count' => 0],
            'cache' => ['count' => 0],
        ]);

        $history = RequestStore::getAll();

        $this->assertCount(1, $history);
        $this->assertEquals('GET', $history['req-1']['method']);
    }

    public function testLimitsHistoryTo20Requests(): void
    {
        for ($i = 0; $i < 25; $i++) {
            RequestStore::store("req-$i", [
                'request' => [
                    'method' => 'GET',
                    'uri' => "/test-$i",
                    'status_code' => 200,
                    'time' => 100,
                    'memory' => 1_000_000,
                ],
                'queries' => ['queries' => []],
                'http' => ['count' => 0],
                'cache' => ['count' => 0],
            ]);
        }

        $history = RequestStore::getAll();

        $this->assertCount(20, $history);
    }

    public function testGetReturnsSpecificRequest(): void
    {
        RequestStore::store('req-test', [
            'request' => [
                'method' => 'POST',
                'uri' => '/api/test',
                'status_code' => 201,
                'time' => 150,
                'memory' => 2_000_000,
            ],
            'queries' => ['queries' => []],
            'http' => ['count' => 0],
            'cache' => ['count' => 0],
        ]);

        $request = RequestStore::get('req-test');

        $this->assertNotNull($request);
        $this->assertEquals('POST', $request['method']);
        $this->assertEquals('/api/test', $request['uri']);
    }

    public function testGetReturnsNullForNonExistent(): void
    {
        $request = RequestStore::get('non-existent');

        $this->assertNull($request);
    }

    public function testClearRemovesAllRequests(): void
    {
        RequestStore::store('req-1', [
            'request' => ['method' => 'GET', 'uri' => '/', 'status_code' => 200, 'time' => 100, 'memory' => 1_000_000],
            'queries' => ['queries' => []],
            'http' => ['count' => 0],
            'cache' => ['count' => 0],
        ]);

        RequestStore::clear();
        $history = RequestStore::getAll();

        $this->assertEmpty($history);
    }

    public function testGetStatistics(): void
    {
        RequestStore::store('req-1', [
            'request' => ['method' => 'GET', 'uri' => '/', 'status_code' => 200, 'time' => 100, 'memory' => 10_000_000],
            'queries' => ['queries' => [[], []]],
            'http' => ['count' => 0],
            'cache' => ['count' => 0],
        ]);

        RequestStore::store('req-2', [
            'request' => ['method' => 'GET', 'uri' => '/', 'status_code' => 200, 'time' => 200, 'memory' => 20_000_000],
            'queries' => ['queries' => [[], [], [], []]],
            'http' => ['count' => 0],
            'cache' => ['count' => 0],
        ]);

        $stats = RequestStore::getStatistics();

        $this->assertEquals(2, $stats['total_requests']);
        $this->assertEquals(150, $stats['avg_time']); // (100+200)/2
        $this->assertEquals(14.31, $stats['avg_memory']); // ((10+20)/2)/1024/1024
        $this->assertEquals(3, $stats['avg_queries']); // (2+4)/2
        $this->assertEquals(200, $stats['slowest_time']);
        $this->assertEquals(100, $stats['fastest_time']);
    }

    public function testFilterByMethod(): void
    {
        RequestStore::store('req-1', [
            'request' => ['method' => 'GET', 'uri' => '/', 'status_code' => 200, 'time' => 100, 'memory' => 1_000_000],
            'queries' => ['queries' => []],
            'http' => ['count' => 0],
            'cache' => ['count' => 0],
        ]);

        RequestStore::store('req-2', [
            'request' => ['method' => 'POST', 'uri' => '/', 'status_code' => 201, 'time' => 100, 'memory' => 1_000_000],
            'queries' => ['queries' => []],
            'http' => ['count' => 0],
            'cache' => ['count' => 0],
        ]);

        $filtered = RequestStore::filter(['method' => 'POST']);

        $this->assertCount(1, $filtered);
        $this->assertEquals('POST', array_values($filtered)[0]['method']);
    }

    public function testFilterByStatus(): void
    {
        RequestStore::store('req-1', [
            'request' => ['method' => 'GET', 'uri' => '/', 'status_code' => 200, 'time' => 100, 'memory' => 1_000_000],
            'queries' => ['queries' => []],
            'http' => ['count' => 0],
            'cache' => ['count' => 0],
        ]);

        RequestStore::store('req-2', [
            'request' => ['method' => 'GET', 'uri' => '/', 'status_code' => 404, 'time' => 100, 'memory' => 1_000_000],
            'queries' => ['queries' => []],
            'http' => ['count' => 0],
            'cache' => ['count' => 0],
        ]);

        $filtered = RequestStore::filter(['status' => '4']); // 4xx codes

        $this->assertCount(1, $filtered);
        $this->assertEquals(404, array_values($filtered)[0]['status']);
    }

    public function testFilterByUri(): void
    {
        RequestStore::store('req-1', [
            'request' => ['method' => 'GET', 'uri' => '/api/users', 'status_code' => 200, 'time' => 100, 'memory' => 1_000_000],
            'queries' => ['queries' => []],
            'http' => ['count' => 0],
            'cache' => ['count' => 0],
        ]);

        RequestStore::store('req-2', [
            'request' => ['method' => 'GET', 'uri' => '/api/posts', 'status_code' => 200, 'time' => 100, 'memory' => 1_000_000],
            'queries' => ['queries' => []],
            'http' => ['count' => 0],
            'cache' => ['count' => 0],
        ]);

        $filtered = RequestStore::filter(['uri' => 'users']);

        $this->assertCount(1, $filtered);
        $this->assertStringContainsString('users', array_values($filtered)[0]['uri']);
    }

    public function testGenerateIdCreatesUniqueIds(): void
    {
        $id1 = RequestStore::generateId();
        $id2 = RequestStore::generateId();

        $this->assertNotEquals($id1, $id2);
        $this->assertStringStartsWith('req_', $id1);
        $this->assertStringStartsWith('req_', $id2);
    }

    public function testGetStatusDisplay(): void
    {
        $display2xx = RequestStore::getStatusDisplay(200);
        $this->assertEquals('green', $display2xx['color']);
        $this->assertEquals('🟢', $display2xx['icon']);

        $display3xx = RequestStore::getStatusDisplay(301);
        $this->assertEquals('blue', $display3xx['color']);
        $this->assertEquals('🔵', $display3xx['icon']);

        $display4xx = RequestStore::getStatusDisplay(404);
        $this->assertEquals('yellow', $display4xx['color']);
        $this->assertEquals('🟡', $display4xx['icon']);

        $display5xx = RequestStore::getStatusDisplay(500);
        $this->assertEquals('red', $display5xx['color']);
        $this->assertEquals('🔴', $display5xx['icon']);
    }

    public function testTimeAgo(): void
    {
        $now = time();

        $this->assertEquals('0s ago', RequestStore::timeAgo($now));
        $this->assertEquals('5s ago', RequestStore::timeAgo($now - 5));
        $this->assertEquals('1m ago', RequestStore::timeAgo($now - 60));
        $this->assertEquals('1h ago', RequestStore::timeAgo($now - 3600));
        $this->assertEquals('1d ago', RequestStore::timeAgo($now - 86400));
    }
}
