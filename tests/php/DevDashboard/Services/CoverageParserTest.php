<?php

declare(strict_types=1);

namespace Tests\DevDashboard\Services;

use DevDashboard\Services\CoverageParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for CoverageParser — HTML format parsing and parseCoverageMetrics()
 */
#[CoversClass(CoverageParser::class)]
class CoverageParserTest extends TestCase
{
    private CoverageParser $parser;

    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser  = new CoverageParser();
        $this->tempDir = sys_get_temp_dir() . '/coverage_parser_test_' . uniqid();
        mkdir($this->tempDir, 0755, recursive: true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
        parent::tearDown();
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir) ?: [], ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }

        rmdir($dir);
    }

    // ==================== parseNodeCoverageFormat ====================

    public function testParseNodeCoverageFormatExtractsAllMetrics(): void
    {
        $html = <<<'HTML'
            <span class="strong">78.08% </span><span class="quiet">Statements</span>
            <span class="strong">65.50% </span><span class="quiet">Branches</span>
            <span class="strong">82.33% </span><span class="quiet">Functions</span>
            <span class="strong">77.91% </span><span class="quiet">Lines</span>
            HTML;

        $metrics = $this->parser->parseNodeCoverageFormat($html);

        $this->assertSame(78.08, $metrics['statements']);
        $this->assertSame(65.50, $metrics['branches']);
        $this->assertSame(82.33, $metrics['functions']);
        $this->assertSame(77.91, $metrics['lines']);
    }

    public function testParseNodeCoverageFormatReturnsEmptyArrayWhenNoMatch(): void
    {
        $metrics = $this->parser->parseNodeCoverageFormat('<html><body>no coverage data</body></html>');

        $this->assertSame([], $metrics);
    }

    public function testParseNodeCoverageFormatPartialMetrics(): void
    {
        $html = '<span class="strong">90.00% </span><span class="quiet">Statements</span>';

        $metrics = $this->parser->parseNodeCoverageFormat($html);

        $this->assertSame(90.0, $metrics['statements']);
        $this->assertArrayNotHasKey('branches', $metrics);
        $this->assertArrayNotHasKey('functions', $metrics);
        $this->assertArrayNotHasKey('lines', $metrics);
    }

    public function testParseNodeCoverageFormatHandlesZeroPercent(): void
    {
        $html = '<span class="strong">0.00% </span><span class="quiet">Statements</span>';

        $metrics = $this->parser->parseNodeCoverageFormat($html);

        $this->assertSame(0.0, $metrics['statements']);
    }

    public function testParseNodeCoverageFormatHandlesHundredPercent(): void
    {
        $html = '<span class="strong">100.00% </span><span class="quiet">Lines</span>';

        $metrics = $this->parser->parseNodeCoverageFormat($html);

        $this->assertSame(100.0, $metrics['lines']);
    }

    public function testParseNodeCoverageFormatIsCaseInsensitive(): void
    {
        $html = '<SPAN CLASS="STRONG">55.55% </SPAN><SPAN CLASS="QUIET">Statements</SPAN>';

        $metrics = $this->parser->parseNodeCoverageFormat($html);

        $this->assertSame(55.55, $metrics['statements']);
    }

    // ==================== parsePhpUnitCoverageFormat ====================

    public function testParsePhpUnitCoverageFormatExtractsLines(): void
    {
        $html = '<div class="progress-bar bg-success" role="progressbar" aria-valuenow="25.27" aria-valuemin="0" aria-valuemax="100"></div>';

        $metrics = $this->parser->parsePhpUnitCoverageFormat($html);

        $this->assertArrayHasKey('lines', $metrics);
        $this->assertArrayHasKey('statements', $metrics);
        $this->assertSame(25.27, $metrics['lines']);
        $this->assertSame(25.27, $metrics['statements']);
    }

    public function testParsePhpUnitCoverageFormatReturnsEmptyArrayWhenNoMatch(): void
    {
        $metrics = $this->parser->parsePhpUnitCoverageFormat('<html><body>no coverage data</body></html>');

        $this->assertSame([], $metrics);
    }

    public function testParsePhpUnitCoverageFormatHandlesZero(): void
    {
        $html = '<div class="progress-bar" aria-valuenow="0.00"></div>';

        $metrics = $this->parser->parsePhpUnitCoverageFormat($html);

        $this->assertSame(0.0, $metrics['lines']);
    }

    public function testParsePhpUnitCoverageFormatHandlesHundred(): void
    {
        $html = '<div class="progress-bar" aria-valuenow="100.00"></div>';

        $metrics = $this->parser->parsePhpUnitCoverageFormat($html);

        $this->assertSame(100.0, $metrics['lines']);
    }

    // ==================== parseCoverageMetrics ====================

    public function testParseCoverageMetricsReturnsNullForNonExistentFile(): void
    {
        // file_get_contents emits a PHP warning for missing files.
        // Temporarily silence it via set_error_handler so failOnWarning=true does not fail
        // the test before the method returns false and we can assert null.
        set_error_handler(static fn(): bool => true);
        try {
            $result = $this->parser->parseCoverageMetrics('/nonexistent/path/index.html');
        } finally {
            restore_error_handler();
        }

        $this->assertNull($result);
    }

    public function testParseCoverageMetricsDetectsNodeFormat(): void
    {
        $html = <<<'HTML'
            <span class="strong">78.08% </span><span class="quiet">Statements</span>
            <span class="strong">65.50% </span><span class="quiet">Branches</span>
            <span class="strong">82.33% </span><span class="quiet">Functions</span>
            <span class="strong">77.91% </span><span class="quiet">Lines</span>
            HTML;

        $file = $this->tempDir . '/index.html';
        file_put_contents($file, $html);

        $result = $this->parser->parseCoverageMetrics($file);

        $this->assertIsArray($result);
        $this->assertSame(78.08, $result['statements']);
        $this->assertSame(77.91, $result['lines']);
    }

    public function testParseCoverageMetricsDetectsPhpUnitFormat(): void
    {
        $html = '<div class="progress-bar bg-success" aria-valuenow="92.50" aria-valuemin="0" aria-valuemax="100"></div>';

        $file = $this->tempDir . '/index.html';
        file_put_contents($file, $html);

        $result = $this->parser->parseCoverageMetrics($file);

        $this->assertIsArray($result);
        $this->assertSame(92.50, $result['lines']);
    }

    public function testParseCoverageMetricsReturnsNullForEmptyMatchingHtml(): void
    {
        $file = $this->tempDir . '/index.html';
        file_put_contents($file, '<html><body>nothing useful here</body></html>');

        $result = $this->parser->parseCoverageMetrics($file);

        $this->assertNull($result);
    }
}
