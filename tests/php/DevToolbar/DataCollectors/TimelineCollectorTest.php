<?php

declare(strict_types=1);

namespace Tests\DevToolbar\DataCollectors;

use DevToolbar\DataCollectors\TimelineCollector;
use PHPUnit\Framework\TestCase;

/**
 * Test TimelineCollector timeline event tracking
 */
class TimelineCollectorTest extends TestCase
{
    private TimelineCollector $collector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->collector = new TimelineCollector();
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
        $this->assertEquals('TIMELINE', $this->collector->getName());
    }

    public function testTracksEvents(): void
    {
        $this->collector->start();
        $this->collector->addEvent('bootstrap', 'Bootstrap', 15.0, 'bootstrap');
        $this->collector->addEvent('middleware', 'Middleware', 8.0, 'middleware');
        $this->collector->stop();

        $data = $this->collector->getData();

        $this->assertArrayHasKey('events', $data);
        $this->assertArrayHasKey('timeline', $data);
        $this->assertArrayHasKey('total_time', $data);
        $this->assertGreaterThan(0, $data['total_time']);
    }

    public function testDoesNotTrackWhenNotCollecting(): void
    {
        // Don't call start()
        $this->collector->addEvent('test', 'Test Event', 10.0);

        $data = $this->collector->getData();

        $this->assertEmpty($data['events']);
    }

    public function testStartPhaseAndEndPhase(): void
    {
        $this->collector->start();

        $this->collector->startPhase('controller', 'Controller', 'controller');
        usleep(1000); // 1ms delay
        $this->collector->endPhase('controller');

        $this->collector->stop();

        $data = $this->collector->getData();
        $events = $data['events'];

        $this->assertArrayHasKey('controller_start', $events);
        $this->assertNotNull($events['controller_start']['duration']);
        $this->assertGreaterThan(0, $events['controller_start']['duration']);
    }

    public function testAddAggregatedData(): void
    {
        $this->collector->start();

        $this->collector->addAggregatedData(
            'Database Queries',
            10,
            150.5,
            'database'
        );

        $this->collector->stop();

        $data = $this->collector->getData();
        $events = $data['events'];

        $this->assertArrayHasKey('aggregated_database', $events);
        $this->assertEquals(150.5, $events['aggregated_database']['duration']);
    }

    public function testDoesNotAddAggregatedDataWithZeroCount(): void
    {
        $this->collector->start();

        $this->collector->addAggregatedData(
            'No Operations',
            0,
            0.0,
            'none'
        );

        $this->collector->stop();

        $data = $this->collector->getData();
        $events = $data['events'];

        $this->assertArrayNotHasKey('aggregated_none', $events);
    }

    public function testGetRequestStart(): void
    {
        $this->collector->start();

        $requestStart = $this->collector->getRequestStart();

        $this->assertIsFloat($requestStart);
        $this->assertGreaterThan(0, $requestStart);
    }

    public function testGetElapsedTime(): void
    {
        $this->collector->start();

        usleep(1000); // 1ms delay

        $elapsed = $this->collector->getElapsedTime();

        $this->assertIsFloat($elapsed);
        $this->assertGreaterThan(0, $elapsed);
    }

    public function testBuildsTimelineWithCategories(): void
    {
        $this->collector->start();

        $this->collector->addEvent('bootstrap', 'Bootstrap', 15.0, 'bootstrap');
        $this->collector->addEvent('middleware', 'Middleware', 8.0, 'middleware');
        $this->collector->addEvent('controller', 'Controller', 100.0, 'controller');

        $this->collector->stop();

        $data = $this->collector->getData();
        $timeline = $data['timeline'];

        $this->assertIsArray($timeline);
        $this->assertNotEmpty($timeline);

        // Check that timeline items have required fields
        foreach ($timeline as $item) {
            $this->assertArrayHasKey('label', $item);
            $this->assertArrayHasKey('category', $item);
            $this->assertArrayHasKey('duration', $item);
            $this->assertArrayHasKey('percentage', $item);
            $this->assertArrayHasKey('is_bottleneck', $item);
        }
    }

    public function testDetectsBottleneck(): void
    {
        $this->collector->start();

        // Add one event that takes >50% of time
        $this->collector->addEvent('fast', 'Fast', 10.0, 'other');
        $this->collector->addEvent('slow', 'Slow', 200.0, 'controller');

        $this->collector->stop();

        $data = $this->collector->getData();
        $timeline = $data['timeline'];

        // Find the controller category
        $controllerItem = null;
        foreach ($timeline as $item) {
            if ($item['category'] === 'controller') {
                $controllerItem = $item;
                break;
            }
        }

        $this->assertNotNull($controllerItem);
        $this->assertTrue($controllerItem['is_bottleneck']);
    }
}
