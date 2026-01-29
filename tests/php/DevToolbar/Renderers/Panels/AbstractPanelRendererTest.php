<?php

declare(strict_types=1);

namespace Tests\DevToolbar\Renderers\Panels;

use DevToolbar\Renderers\Panels\AbstractPanelRenderer;
use PHPUnit\Framework\TestCase;

/**
 * Test AbstractPanelRenderer utility methods
 */
class AbstractPanelRendererTest extends TestCase
{
    private TestPanelRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new TestPanelRenderer();
    }

    // ========== formatTime() tests ==========

    public function testFormatTimeInMilliseconds(): void
    {
        $this->assertEquals('45.23ms', $this->renderer->publicFormatTime(45.23));
        $this->assertEquals('0.00ms', $this->renderer->publicFormatTime(0));
        $this->assertEquals('999.99ms', $this->renderer->publicFormatTime(999.99));
    }

    public function testFormatTimeInSeconds(): void
    {
        $this->assertEquals('1.00s', $this->renderer->publicFormatTime(1000));
        $this->assertEquals('1.50s', $this->renderer->publicFormatTime(1500));
        $this->assertEquals('123.45s', $this->renderer->publicFormatTime(123450));
    }

    public function testFormatTimeNegative(): void
    {
        $this->assertEquals('-10.00ms', $this->renderer->publicFormatTime(-10));
    }

    // ========== formatMemory() tests ==========

    public function testFormatMemoryInMegabytes(): void
    {
        $this->assertEquals('45.23MB', $this->renderer->publicFormatMemory(45.23));
        $this->assertEquals('0.00MB', $this->renderer->publicFormatMemory(0));
        $this->assertEquals('1023.99MB', $this->renderer->publicFormatMemory(1023.99));
    }

    public function testFormatMemoryInGigabytes(): void
    {
        $this->assertEquals('1.00GB', $this->renderer->publicFormatMemory(1024));
        $this->assertEquals('1.50GB', $this->renderer->publicFormatMemory(1536));
        $this->assertEquals('10.25GB', $this->renderer->publicFormatMemory(10496));
    }

    public function testFormatMemoryNegative(): void
    {
        $this->assertEquals('-10.00MB', $this->renderer->publicFormatMemory(-10));
    }

    // ========== formatBytes() tests ==========

    public function testFormatBytesInBytes(): void
    {
        $this->assertEquals('0B', $this->renderer->publicFormatBytes(0));
        $this->assertEquals('512B', $this->renderer->publicFormatBytes(512));
        $this->assertEquals('1023B', $this->renderer->publicFormatBytes(1023));
    }

    public function testFormatBytesInKilobytes(): void
    {
        $this->assertEquals('1.00KB', $this->renderer->publicFormatBytes(1024));
        $this->assertEquals('1.50KB', $this->renderer->publicFormatBytes(1536));
        $this->assertEquals('500.00KB', $this->renderer->publicFormatBytes(512000));
    }

    public function testFormatBytesInMegabytes(): void
    {
        $this->assertEquals('1.00MB', $this->renderer->publicFormatBytes(1048576));
        $this->assertEquals('2.50MB', $this->renderer->publicFormatBytes(2621440));
    }

    public function testFormatBytesInGigabytes(): void
    {
        $this->assertEquals('1.00GB', $this->renderer->publicFormatBytes(1073741824));
        $this->assertEquals('5.25GB', $this->renderer->publicFormatBytes(5637144576));
    }

    public function testFormatBytesInTerabytes(): void
    {
        $this->assertEquals('1.00TB', $this->renderer->publicFormatBytes(1099511627776));
        $this->assertEquals('2.50TB', $this->renderer->publicFormatBytes(2748779069440));
    }

    public function testFormatBytesNegative(): void
    {
        $this->assertEquals('0B', $this->renderer->publicFormatBytes(-1024));
    }

    public function testFormatBytesLarge(): void
    {
        // Should cap at TB
        $result = $this->renderer->publicFormatBytes(PHP_INT_MAX);
        $this->assertStringEndsWith('TB', $result);
    }

    // ========== escapeHtml() tests ==========

    public function testEscapeHtmlBasic(): void
    {
        $this->assertEquals('Hello World', $this->renderer->publicEscapeHtml('Hello World'));
    }

    public function testEscapeHtmlSpecialChars(): void
    {
        $this->assertEquals('&lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;', $this->renderer->publicEscapeHtml('<script>alert("XSS")</script>'));
        $this->assertEquals('Tom &amp; Jerry', $this->renderer->publicEscapeHtml('Tom & Jerry'));
        $this->assertEquals('Price: 5 &lt; 10', $this->renderer->publicEscapeHtml('Price: 5 < 10'));
    }

    public function testEscapeHtmlQuotes(): void
    {
        $this->assertEquals('It&apos;s working', $this->renderer->publicEscapeHtml("It's working"));
        $this->assertEquals('&quot;Quoted text&quot;', $this->renderer->publicEscapeHtml('"Quoted text"'));
    }

    public function testEscapeHtmlEmpty(): void
    {
        $this->assertEquals('', $this->renderer->publicEscapeHtml(''));
    }

    // ========== renderSection() tests ==========

    public function testRenderSection(): void
    {
        $result = $this->renderer->publicRenderSection('My Title', '<p>Content</p>');

        $this->assertStringContainsString('dev-toolbar-section', $result);
        $this->assertStringContainsString('dev-toolbar-section-title', $result);
        $this->assertStringContainsString('My Title', $result);
        $this->assertStringContainsString('<p>Content</p>', $result);
    }

    public function testRenderSectionEscapesTitle(): void
    {
        $result = $this->renderer->publicRenderSection('<script>alert("XSS")</script>', '<p>Safe</p>');

        $this->assertStringContainsString('&lt;script&gt;', $result);
        $this->assertStringNotContainsString('<script>', $result);
    }

    public function testRenderSectionDoesNotEscapeContent(): void
    {
        $result = $this->renderer->publicRenderSection('Title', '<strong>Bold</strong>');

        $this->assertStringContainsString('<strong>Bold</strong>', $result);
    }

    // ========== renderEmptyState() tests ==========

    public function testRenderEmptyState(): void
    {
        $result = $this->renderer->publicRenderEmptyState('No data available');

        $this->assertStringContainsString('<p>', $result);
        $this->assertStringContainsString('No data available', $result);
    }

    public function testRenderEmptyStateEscapesMessage(): void
    {
        $result = $this->renderer->publicRenderEmptyState('<script>alert("XSS")</script>');

        $this->assertStringContainsString('&lt;script&gt;', $result);
        $this->assertStringNotContainsString('<script>', $result);
    }

    // ========== renderKeyValueTable() tests ==========

    public function testRenderKeyValueTableEmpty(): void
    {
        $result = $this->renderer->publicRenderKeyValueTable([]);

        $this->assertEquals('', $result);
    }

    public function testRenderKeyValueTableSimple(): void
    {
        $data = [
            'name' => 'John',
            'age'  => '25',
        ];

        $result = $this->renderer->publicRenderKeyValueTable($data);

        $this->assertStringContainsString('dev-toolbar-kv-table', $result);
        $this->assertStringContainsString('name', $result);
        $this->assertStringContainsString('John', $result);
        $this->assertStringContainsString('age', $result);
        $this->assertStringContainsString('25', $result);
    }

    public function testRenderKeyValueTableEscapesValues(): void
    {
        $data = [
            'title' => '<script>alert("XSS")</script>',
        ];

        $result = $this->renderer->publicRenderKeyValueTable($data);

        $this->assertStringContainsString('&lt;script&gt;', $result);
        $this->assertStringNotContainsString('<script>alert', $result);
    }

    public function testRenderKeyValueTableArrayValue(): void
    {
        $data = [
            'items' => ['apple', 'banana', 'orange'],
        ];

        $result = $this->renderer->publicRenderKeyValueTable($data);

        $this->assertStringContainsString('items', $result);
        $this->assertStringContainsString('apple', $result);
        $this->assertStringContainsString('banana', $result);
        $this->assertStringContainsString('orange', $result);
    }

    public function testRenderKeyValueTableIntegerValue(): void
    {
        $data = [
            'count' => 42,
        ];

        $result = $this->renderer->publicRenderKeyValueTable($data);

        $this->assertStringContainsString('count', $result);
        $this->assertStringContainsString('42', $result);
    }

    // ========== getPerformanceClass() tests ==========

    public function testGetPerformanceClassFast(): void
    {
        $this->assertEquals('fast', $this->renderer->publicGetPerformanceClass(50, 100, 500));
        $this->assertEquals('fast', $this->renderer->publicGetPerformanceClass(0, 100, 500));
        $this->assertEquals('fast', $this->renderer->publicGetPerformanceClass(99.99, 100, 500));
    }

    public function testGetPerformanceClassSlow(): void
    {
        $this->assertEquals('slow', $this->renderer->publicGetPerformanceClass(100, 100, 500));
        $this->assertEquals('slow', $this->renderer->publicGetPerformanceClass(250, 100, 500));
        $this->assertEquals('slow', $this->renderer->publicGetPerformanceClass(499.99, 100, 500));
    }

    public function testGetPerformanceClassVerySlow(): void
    {
        $this->assertEquals('very-slow', $this->renderer->publicGetPerformanceClass(500, 100, 500));
        $this->assertEquals('very-slow', $this->renderer->publicGetPerformanceClass(1000, 100, 500));
    }

    public function testGetPerformanceClassNegativeValue(): void
    {
        $this->assertEquals('fast', $this->renderer->publicGetPerformanceClass(-10, 100, 500));
    }
}

/**
 * Concrete test implementation of AbstractPanelRenderer
 *
 * Exposes protected methods for testing
 */
class TestPanelRenderer extends AbstractPanelRenderer
{
    public function renderTab(array $data): string
    {
        return '<div>Test Panel</div>';
    }

    public function getPanelName(): string
    {
        return 'test';
    }

    // Public wrappers for protected methods

    public function publicFormatTime(float $ms): string
    {
        return $this->formatTime($ms);
    }

    public function publicFormatMemory(float $mb): string
    {
        return $this->formatMemory($mb);
    }

    public function publicFormatBytes(int $bytes): string
    {
        return $this->formatBytes($bytes);
    }

    public function publicEscapeHtml(string $value): string
    {
        return $this->escapeHtml($value);
    }

    public function publicRenderSection(string $title, string $content): string
    {
        return $this->renderSection($title, $content);
    }

    public function publicRenderEmptyState(string $message): string
    {
        return $this->renderEmptyState($message);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function publicRenderKeyValueTable(array $data): string
    {
        return $this->renderKeyValueTable($data);
    }

    public function publicGetPerformanceClass(float $value, float $warningThreshold, float $criticalThreshold): string
    {
        return $this->getPerformanceClass($value, $warningThreshold, $criticalThreshold);
    }
}
