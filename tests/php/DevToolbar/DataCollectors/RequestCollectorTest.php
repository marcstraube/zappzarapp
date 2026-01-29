<?php

declare(strict_types=1);

namespace Tests\DevToolbar\DataCollectors;

use DevToolbar\DataCollectors\RequestCollector;
use PHPUnit\Framework\TestCase;

/**
 * Test RequestCollector data collection and filtering
 */
class RequestCollectorTest extends TestCase
{
    private RequestCollector $collector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->collector = new RequestCollector();

        // Set up basic request environment
        $_SERVER['REQUEST_METHOD']  = 'GET';
        $_SERVER['REQUEST_URI']     = '/test';
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
        $_GET                       = [];
        $_POST                      = [];
        $_COOKIE                    = [];
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        // Clean up globals
        $_GET    = [];
        $_POST   = [];
        $_COOKIE = [];
    }

    public function testCollectsBasicRequestData(): void
    {
        $this->collector->start();
        $this->collector->stop();

        $data = $this->collector->getData();

        $this->assertEquals('GET', $data['method']);
        $this->assertEquals('/test', $data['uri']);
        $this->assertEquals('HTTP/1.1', $data['protocol']);
        $this->assertArrayHasKey('execution_time', $data);
        $this->assertArrayHasKey('memory_peak', $data);
    }

    public function testFiltersSensitivePostData(): void
    {
        $_POST = [
            'username'  => 'john',
            'password'  => 'secret123',
            'api_token' => 'abc123',
            'email'     => 'john@example.com',
        ];

        $this->collector->start();
        $this->collector->stop();

        $data = $this->collector->getData();

        $this->assertEquals('john', $data['post']['username']);
        $this->assertEquals('[FILTERED]', $data['post']['password']);
        $this->assertEquals('[FILTERED]', $data['post']['api_token']);
        $this->assertEquals('john@example.com', $data['post']['email']);
    }

    public function testFiltersSensitiveDataInNestedArrays(): void
    {
        $testData = [
            'user' => [
                'name'     => 'John',
                'password' => 'secret',
            ],
            'api_key' => 'xyz789',
        ];

        $filtered = RequestCollector::filterSensitiveData($testData);

        $this->assertEquals('John', $filtered['user']['name']);
        $this->assertEquals('[FILTERED]', $filtered['user']['password']);
        $this->assertEquals('[FILTERED]', $filtered['api_key']);
    }

    public function testGetName(): void
    {
        $this->assertEquals('request', $this->collector->getName());
    }

    public function testTracksExecutionTime(): void
    {
        $this->collector->start();
        usleep(10000); // Sleep 10ms
        $this->collector->stop();

        $data = $this->collector->getData();

        $this->assertGreaterThan(5, $data['execution_time']);
    }
}
