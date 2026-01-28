<?php

declare(strict_types=1);

namespace Tests\DevToolbar\Renderers\Panels;

use DevToolbar\Renderers\Panels\HistoryPanelRenderer;
use PHPUnit\Framework\TestCase;

/**
 * Tests for HistoryPanelRenderer
 *
 * Covers:
 * - Main tab rendering with all sections
 * - Filter controls rendering
 * - Statistics placeholder rendering (client-side populated)
 * - Trends with sparklines (various data patterns)
 * - Request list placeholder rendering
 * - Export controls rendering
 * - Sparkline generation algorithm
 * - Edge cases (empty data, missing keys, null values)
 */
class HistoryPanelRendererTest extends TestCase
{
    private HistoryPanelRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new HistoryPanelRenderer();
    }

    public function testGetPanelName(): void
    {
        $this->assertSame('history', $this->renderer->getPanelName());
    }

    public function testRenderTabWithEmptyData(): void
    {
        $output = $this->renderer->renderTab([]);

        // Should contain all main sections even with empty data
        $this->assertStringContainsString('dev-toolbar-history-filters', $output);
        $this->assertStringContainsString('Statistics', $output);
        $this->assertStringContainsString('Request History', $output);
        $this->assertStringContainsString('Actions', $output);

        // Should NOT contain trends section when no trend data
        $this->assertStringNotContainsString('Response Time Trend', $output);
    }

    public function testRenderTabWithTrendData(): void
    {
        $data = [
            'trends' => [
                'time' => [100.5, 150.2, 200.8, 120.3, 180.5],
            ],
        ];

        $output = $this->renderer->renderTab($data);

        // Should contain trends section with sparkline
        $this->assertStringContainsString('Response Time Trend', $output);
        $this->assertStringContainsString('dev-toolbar-history-sparkline', $output);

        // Should contain sparkline characters (Unicode block elements)
        $this->assertMatchesRegularExpression('/[▁▂▃▄▅▆▇█]+/', $output);
    }

    public function testRenderFilters(): void
    {
        $output = $this->renderer->renderTab([]);

        // Method filter
        $this->assertStringContainsString('history-filter-method', $output);
        $this->assertStringContainsString('<option value="GET">GET</option>', $output);
        $this->assertStringContainsString('<option value="POST">POST</option>', $output);
        $this->assertStringContainsString('<option value="PUT">PUT</option>', $output);
        $this->assertStringContainsString('<option value="DELETE">DELETE</option>', $output);
        $this->assertStringContainsString('<option value="PATCH">PATCH</option>', $output);

        // Status filter
        $this->assertStringContainsString('history-filter-status', $output);
        $this->assertStringContainsString('<option value="2">2xx Success</option>', $output);
        $this->assertStringContainsString('<option value="3">3xx Redirect</option>', $output);
        $this->assertStringContainsString('<option value="4">4xx Client Error</option>', $output);
        $this->assertStringContainsString('<option value="5">5xx Server Error</option>', $output);

        // URI filter
        $this->assertStringContainsString('history-filter-uri', $output);
        $this->assertStringContainsString('placeholder="Search URI..."', $output);

        // Min time filter
        $this->assertStringContainsString('history-filter-min-time', $output);
        $this->assertStringContainsString('type="number"', $output);
        $this->assertStringContainsString('min="0"', $output);
        $this->assertStringContainsString('step="10"', $output);

        // Reset button
        $this->assertStringContainsString('history-filter-reset', $output);
        $this->assertStringContainsString('Reset Filters', $output);
    }

    public function testRenderStatisticsPlaceholders(): void
    {
        $output = $this->renderer->renderTab([]);

        // Statistics section
        $this->assertStringContainsString('Statistics', $output);
        $this->assertStringContainsString('dev-toolbar-history-stats-grid', $output);

        // All stat cards with data attributes (JavaScript will populate)
        $this->assertStringContainsString('data-history-stat="total"', $output);
        $this->assertStringContainsString('data-history-stat="avg_time"', $output);
        $this->assertStringContainsString('data-history-stat="avg_memory"', $output);
        $this->assertStringContainsString('data-history-stat="avg_queries"', $output);
        $this->assertStringContainsString('data-history-stat="fastest"', $output);
        $this->assertStringContainsString('data-history-stat="slowest"', $output);

        // Default placeholder values
        $this->assertMatchesRegularExpression('/data-history-stat="total">0</', $output);
        $this->assertMatchesRegularExpression('/data-history-stat="avg_time">0ms</', $output);
        $this->assertMatchesRegularExpression('/data-history-stat="avg_memory">0MB</', $output);
        $this->assertMatchesRegularExpression('/data-history-stat="avg_queries">0</', $output);
        $this->assertMatchesRegularExpression('/data-history-stat="fastest">0ms</', $output);
        $this->assertMatchesRegularExpression('/data-history-stat="slowest">0ms</', $output);

        // Stat labels
        $this->assertStringContainsString('Total Requests', $output);
        $this->assertStringContainsString('Avg Time', $output);
        $this->assertStringContainsString('Avg Memory', $output);
        $this->assertStringContainsString('Avg Queries', $output);
        $this->assertStringContainsString('Fastest', $output);
        $this->assertStringContainsString('Slowest', $output);
    }

    public function testRenderTrendsWithEmptyData(): void
    {
        $data = [
            'trends' => [],
        ];

        $output = $this->renderer->renderTab($data);

        // Should NOT render trends section when trends array is empty
        $this->assertStringNotContainsString('Response Time Trend', $output);
        $this->assertStringNotContainsString('dev-toolbar-history-sparkline', $output);
    }

    public function testRenderTrendsWithEmptyTimeArray(): void
    {
        $data = [
            'trends' => [
                'time' => [],
            ],
        ];

        $output = $this->renderer->renderTab($data);

        // Should NOT render trends section when time array is empty
        $this->assertStringNotContainsString('Response Time Trend', $output);
    }

    public function testRenderTrendsWithSingleValue(): void
    {
        $data = [
            'trends' => [
                'time' => [100.5],
            ],
        ];

        $output = $this->renderer->renderTab($data);

        // Should render trends section
        $this->assertStringContainsString('Response Time Trend', $output);

        // Single value should generate single tick (middle height since range is 0)
        $this->assertMatchesRegularExpression('/[▁▂▃▄▅▆▇█]/', $output);
    }

    public function testRenderTrendsWithMultipleValues(): void
    {
        $data = [
            'trends' => [
                'time' => [50.0, 100.0, 150.0, 200.0, 250.0],
            ],
        ];

        $output = $this->renderer->renderTab($data);

        // Should render trends section with sparkline
        $this->assertStringContainsString('Response Time Trend', $output);

        // Should contain multiple sparkline characters
        $this->assertMatchesRegularExpression('/[▁▂▃▄▅▆▇█]{5}/', $output);
    }

    public function testRenderTrendsWithIdenticalValues(): void
    {
        $data = [
            'trends' => [
                'time' => [100.0, 100.0, 100.0, 100.0],
            ],
        ];

        $output = $this->renderer->renderTab($data);

        // All identical values should produce middle tick (▄) repeated
        $this->assertStringContainsString('▄▄▄▄', $output);
    }

    public function testRenderRequestListPlaceholder(): void
    {
        $output = $this->renderer->renderTab([]);

        // Request list section
        $this->assertStringContainsString('Request History', $output);
        $this->assertStringContainsString('history-list-title', $output);
        $this->assertStringContainsString('history-list-count', $output);
        $this->assertStringContainsString('history-request-list-container', $output);

        // Loading placeholder message
        $this->assertStringContainsString('Loading history from localStorage...', $output);

        // Count placeholder (JavaScript will update)
        $this->assertMatchesRegularExpression('/<span id="history-list-count">0<\/span>/', $output);
    }

    public function testRenderExportControls(): void
    {
        $output = $this->renderer->renderTab([]);

        // Actions section
        $this->assertStringContainsString('Actions', $output);
        $this->assertStringContainsString('dev-toolbar-history-export', $output);

        // Export buttons
        $this->assertStringContainsString('history-export-json', $output);
        $this->assertStringContainsString('Export JSON', $output);
        $this->assertStringContainsString('history-export-csv', $output);
        $this->assertStringContainsString('Export CSV', $output);

        // Clear button
        $this->assertStringContainsString('history-clear', $output);
        $this->assertStringContainsString('Clear History', $output);
        $this->assertStringContainsString('dev-toolbar-btn-danger', $output);
    }

    public function testSparklineGenerationWithEmptyArray(): void
    {
        $data = [
            'trends' => [
                'time' => [],
            ],
        ];

        $output = $this->renderer->renderTab($data);

        // Should not render trends section at all
        $this->assertStringNotContainsString('Response Time Trend', $output);
    }

    public function testSparklineGenerationWithAscendingValues(): void
    {
        $data = [
            'trends' => [
                'time' => [10.0, 20.0, 30.0, 40.0, 50.0],
            ],
        ];

        $output = $this->renderer->renderTab($data);

        // Should contain sparkline with ascending pattern
        // Each value should map to progressively higher ticks
        $this->assertMatchesRegularExpression('/[▁▂▃▄▅▆▇█]{5}/', $output);
        $this->assertStringContainsString('Response Time Trend', $output);
    }

    public function testSparklineGenerationWithDescendingValues(): void
    {
        $data = [
            'trends' => [
                'time' => [50.0, 40.0, 30.0, 20.0, 10.0],
            ],
        ];

        $output = $this->renderer->renderTab($data);

        // Should contain sparkline with descending pattern
        $this->assertMatchesRegularExpression('/[▁▂▃▄▅▆▇█]{5}/', $output);
    }

    public function testSparklineGenerationWithFloatingPointValues(): void
    {
        $data = [
            'trends' => [
                'time' => [123.456, 234.567, 345.678, 456.789],
            ],
        ];

        $output = $this->renderer->renderTab($data);

        // Should handle floating point values correctly
        $this->assertMatchesRegularExpression('/[▁▂▃▄▅▆▇█]{4}/', $output);
    }

    public function testSparklineGenerationWithMixedValues(): void
    {
        $data = [
            'trends' => [
                'time' => [100.0, 50.0, 200.0, 75.0, 150.0],
            ],
        ];

        $output = $this->renderer->renderTab($data);

        // Should create varied sparkline pattern
        $this->assertMatchesRegularExpression('/[▁▂▃▄▅▆▇█]{5}/', $output);
    }

    public function testSparklineGenerationWithExtremeValues(): void
    {
        $data = [
            'trends' => [
                'time' => [1.0, 1000.0, 500.0, 1500.0, 100.0],
            ],
        ];

        $output = $this->renderer->renderTab($data);

        // Should normalize extreme ranges properly
        // Min (1.0) should be lowest tick, Max (1500.0) should be highest tick
        $this->assertMatchesRegularExpression('/[▁▂▃▄▅▆▇█]{5}/', $output);

        // Should contain both low and high ticks
        $this->assertMatchesRegularExpression('/▁/', $output); // Lowest value
        $this->assertMatchesRegularExpression('/█/', $output); // Highest value
    }

    public function testSparklineGenerationWithTwoIdenticalValues(): void
    {
        $data = [
            'trends' => [
                'time' => [100.0, 100.0],
            ],
        ];

        $output = $this->renderer->renderTab($data);

        // Two identical values should produce middle ticks (range is 0)
        $this->assertStringContainsString('▄▄', $output);
    }

    public function testCompleteTabStructure(): void
    {
        $data = [
            'trends' => [
                'time' => [100.0, 150.0, 200.0],
            ],
        ];

        $output = $this->renderer->renderTab($data);

        // Verify all major sections are present in correct order
        $filterPos = strpos($output, 'dev-toolbar-history-filters');
        $statsPos = strpos($output, 'Statistics');
        $trendsPos = strpos($output, 'Response Time Trend');
        $listPos = strpos($output, 'Request History');
        $actionsPos = strpos($output, 'Actions');

        // Ensure all sections exist
        $this->assertNotFalse($filterPos);
        $this->assertNotFalse($statsPos);
        $this->assertNotFalse($trendsPos);
        $this->assertNotFalse($listPos);
        $this->assertNotFalse($actionsPos);

        // Ensure sections appear in correct order
        $this->assertLessThan($statsPos, $filterPos, 'Filters should come before Statistics');
        $this->assertLessThan($trendsPos, $statsPos, 'Statistics should come before Trends');
        $this->assertLessThan($listPos, $trendsPos, 'Trends should come before Request List');
        $this->assertLessThan($actionsPos, $listPos, 'Request List should come before Actions');
    }

    public function testDataStructureWithMissingKeys(): void
    {
        // Test with trends key missing
        $data = [];

        $output = $this->renderer->renderTab($data);

        // Should render filters and stats but no trends
        $this->assertStringContainsString('dev-toolbar-history-filters', $output);
        $this->assertStringContainsString('Statistics', $output);
        $this->assertStringNotContainsString('Response Time Trend', $output);
    }

    public function testDataStructureWithNullValues(): void
    {
        $data = [
            'trends' => null,
        ];

        $output = $this->renderer->renderTab($data);

        // Should handle null trends gracefully (no trends section)
        $this->assertStringNotContainsString('Response Time Trend', $output);
    }

    public function testAllSectionsUseCorrectCssClasses(): void
    {
        $data = [
            'trends' => [
                'time' => [100.0, 200.0],
            ],
        ];

        $output = $this->renderer->renderTab($data);

        // Verify standard DevToolbar CSS classes are used
        $this->assertStringContainsString('dev-toolbar-section', $output);
        $this->assertStringContainsString('dev-toolbar-section-title', $output);
        $this->assertStringContainsString('dev-toolbar-filter-group', $output);
        $this->assertStringContainsString('dev-toolbar-filter-select', $output);
        $this->assertStringContainsString('dev-toolbar-filter-input', $output);
        $this->assertStringContainsString('dev-toolbar-btn', $output);
        $this->assertStringContainsString('dev-toolbar-btn-primary', $output);
        $this->assertStringContainsString('dev-toolbar-btn-secondary', $output);
        $this->assertStringContainsString('dev-toolbar-btn-danger', $output);
    }

    public function testSparklineNormalizationLogic(): void
    {
        // Test specific normalization: values should map to 0-7 tick index
        $data = [
            'trends' => [
                'time' => [0.0, 12.5, 25.0, 37.5, 50.0, 62.5, 75.0, 87.5, 100.0],
            ],
        ];

        $output = $this->renderer->renderTab($data);

        // Should contain sparkline with 9 characters
        $this->assertMatchesRegularExpression('/[▁▂▃▄▅▆▇█]{9}/', $output);

        // First should be lowest (▁), last should be highest (█)
        $sparklineMatch = [];
        preg_match('/<div class="dev-toolbar-history-sparkline">([▁▂▃▄▅▆▇█]+)<\/div>/', $output, $sparklineMatch);

        $this->assertNotEmpty($sparklineMatch);
        $sparkline = $sparklineMatch[1];

        // First character should be ▁ (min value)
        $this->assertSame('▁', mb_substr($sparkline, 0, 1));

        // Last character should be █ (max value)
        $this->assertSame('█', mb_substr($sparkline, -1, 1));
    }
}
