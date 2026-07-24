<?php

declare(strict_types=1);

namespace Tests\DevDashboard\Services;

use DevDashboard\Services\BackupFileUtils;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for BackupFileUtils — age formatting (formatAge)
 */
#[CoversClass(BackupFileUtils::class)]
class BackupFileUtilsFormatAgeTest extends TestCase
{
    private BackupFileUtils $utils;

    protected function setUp(): void
    {
        parent::setUp();
        $this->utils = new BackupFileUtils();
    }

    // ==================== formatAge ====================

    #[Test]
    public function testFormatAgeSeconds(): void
    {
        $timestamp = time() - 30;
        $result    = $this->utils->formatAge($timestamp);
        $this->assertStringContainsString('second', $result);
        $this->assertStringContainsString('ago', $result);
    }

    #[Test]
    public function testFormatAgeSingularSecond(): void
    {
        $timestamp = time() - 1;
        $result    = $this->utils->formatAge($timestamp);
        $this->assertSame('1 second ago', $result);
    }

    #[Test]
    public function testFormatAgePluralSeconds(): void
    {
        $timestamp = time() - 45;
        $result    = $this->utils->formatAge($timestamp);
        $this->assertStringContainsString('seconds ago', $result);
    }

    #[Test]
    public function testFormatAgeMinutes(): void
    {
        $timestamp = time() - 120;
        $result    = $this->utils->formatAge($timestamp);
        $this->assertStringContainsString('minute', $result);
        $this->assertStringContainsString('ago', $result);
    }

    #[Test]
    public function testFormatAgeSingularMinute(): void
    {
        $timestamp = time() - 60;
        $result    = $this->utils->formatAge($timestamp);
        $this->assertSame('1 minute ago', $result);
    }

    #[Test]
    public function testFormatAgePluralMinutes(): void
    {
        $timestamp = time() - 180;
        $result    = $this->utils->formatAge($timestamp);
        $this->assertStringContainsString('minutes ago', $result);
    }

    #[Test]
    public function testFormatAgeHours(): void
    {
        $timestamp = time() - 7200;
        $result    = $this->utils->formatAge($timestamp);
        $this->assertStringContainsString('hour', $result);
        $this->assertStringContainsString('ago', $result);
    }

    #[Test]
    public function testFormatAgeSingularHour(): void
    {
        $timestamp = time() - 3600;
        $result    = $this->utils->formatAge($timestamp);
        $this->assertSame('1 hour ago', $result);
    }

    #[Test]
    public function testFormatAgePluralHours(): void
    {
        $timestamp = time() - 10800;
        $result    = $this->utils->formatAge($timestamp);
        $this->assertStringContainsString('hours ago', $result);
    }

    #[Test]
    public function testFormatAgeDays(): void
    {
        $timestamp = time() - 172800;
        $result    = $this->utils->formatAge($timestamp);
        $this->assertStringContainsString('day', $result);
        $this->assertStringContainsString('ago', $result);
    }

    #[Test]
    public function testFormatAgeSingularDay(): void
    {
        $timestamp = time() - 86400;
        $result    = $this->utils->formatAge($timestamp);
        $this->assertSame('1 day ago', $result);
    }

    #[Test]
    public function testFormatAgePluralDays(): void
    {
        $timestamp = time() - 259200;
        $result    = $this->utils->formatAge($timestamp);
        $this->assertStringContainsString('days ago', $result);
    }
}
