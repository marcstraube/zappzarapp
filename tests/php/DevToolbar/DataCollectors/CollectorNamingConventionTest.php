<?php

declare(strict_types=1);

namespace Tests\DevToolbar\DataCollectors;

use DevToolbar\DataCollectors\CacheCollector;
use DevToolbar\DataCollectors\ExceptionCollector;
use DevToolbar\DataCollectors\HistoryCollector;
use DevToolbar\DataCollectors\HttpClientCollector;
use DevToolbar\DataCollectors\MessageCollector;
use DevToolbar\DataCollectors\QueryCollector;
use DevToolbar\DataCollectors\RequestCollector;
use DevToolbar\DataCollectors\TimelineCollector;
use PHPUnit\Framework\TestCase;

/**
 * Test for Collector Naming Convention
 *
 * Enforces that all collector names are lowercase for consistency.
 * This prevents bugs like timeline collector name mismatch (TIMELINE vs timeline).
 */
class CollectorNamingConventionTest extends TestCase
{
    /**
     * Test that all collector names are lowercase
     *
     * Convention: Collector names should be lowercase for consistency.
     * This ensures getCollector('timeline') works correctly.
     */
    public function testAllCollectorNamesAreLowercase(): void
    {
        $collectors = [
            new CacheCollector(),
            ExceptionCollector::getInstance(), // Singleton
            new HistoryCollector(),
            new HttpClientCollector(),
            new MessageCollector(),
            QueryCollector::getInstance(), // Singleton
            new RequestCollector(),
            new TimelineCollector(),
        ];

        foreach ($collectors as $collector) {
            $name = $collector->getName();
            $this->assertSame(
                strtolower($name),
                $name,
                sprintf(
                    'Collector name "%s" in %s must be lowercase. Found: "%s"',
                    $name,
                    get_class($collector),
                    $name
                )
            );
        }
    }

    /**
     * Test that collector names match expected tab names
     */
    public function testCollectorNamesMatchExpectedValues(): void
    {
        $expectedNames = [
            CacheCollector::class      => 'cache',
            ExceptionCollector::class  => 'exceptions',
            HistoryCollector::class    => 'history',
            HttpClientCollector::class => 'http',
            MessageCollector::class    => 'messages',
            QueryCollector::class      => 'queries',
            RequestCollector::class    => 'request',
            TimelineCollector::class   => 'timeline',
        ];

        foreach ($expectedNames as $class => $expectedName) {
            // Handle singletons explicitly
            $collector = match ($class) {
                ExceptionCollector::class => ExceptionCollector::getInstance(),
                QueryCollector::class     => QueryCollector::getInstance(),
                default                   => new $class(),
            };

            $this->assertSame(
                $expectedName,
                $collector->getName(),
                sprintf('Collector %s should return "%s"', $class, $expectedName)
            );
        }
    }
}
