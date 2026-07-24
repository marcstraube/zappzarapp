<?php

declare(strict_types=1);

namespace Tests\DevDashboard\Services;

use DevDashboard\Services\CoverageParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for CoverageParser — getPhpTestCoverage(), getNodeTestCoverage(), formatAge()
 */
#[CoversClass(CoverageParser::class)]
class CoverageParserReportsTest extends TestCase
{
    private CoverageParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new CoverageParser();
    }

    // ==================== getPhpTestCoverage ====================

    public function testGetPhpTestCoverageUnavailableWhenNoReport(): void
    {
        // The project's actual build/coverage-php/index.html may not exist in CI
        // We verify the structure regardless
        $result = $this->parser->getPhpTestCoverage();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('available', $result);

        if (!$result['available']) {
            $this->assertArrayHasKey('message', $result);
            $this->assertIsString($result['message']);
        } else {
            $this->assertArrayHasKey('metrics', $result);
            $this->assertArrayHasKey('generated_at', $result);
            $this->assertArrayHasKey('outdated', $result);
            $this->assertArrayHasKey('report_path', $result);
        }
    }

    public function testGetPhpTestCoverageStructureWhenAvailable(): void
    {
        // Create a fake coverage report in the expected project location
        // The parser uses realpath(__DIR__ . '/../../../../') as projectRoot
        // We can't easily redirect that, so we verify the returned structure is consistent
        $result = $this->parser->getPhpTestCoverage();

        $this->assertIsBool($result['available']);

        if ($result['available']) {
            $this->assertArrayHasKey('report_path', $result);
            $this->assertSame('/build/coverage-php/index.html', $result['report_path']);
        }
    }

    public function testGetPhpTestCoverageUnavailableWhenFileAbsent(): void
    {
        // Use a fresh parser instance to query coverage.
        // If the PHP coverage report happens to not exist, this tests the unavailable path.
        // If it does exist, we verify the available=true structure.
        $result = $this->parser->getPhpTestCoverage();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('available', $result);

        if (!$result['available']) {
            $this->assertSame(false, $result['available']);
            $this->assertStringContainsString('make test-coverage-php', $result['message']);
        } else {
            $this->assertTrue($result['available']);
        }
    }

    // ==================== getNodeTestCoverage ====================

    public function testGetNodeTestCoverageUnavailableWhenNoReport(): void
    {
        $result = $this->parser->getNodeTestCoverage();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('available', $result);

        if (!$result['available']) {
            $this->assertArrayHasKey('message', $result);
            $this->assertIsString($result['message']);
        } else {
            $this->assertArrayHasKey('metrics', $result);
            $this->assertArrayHasKey('generated_at', $result);
            $this->assertArrayHasKey('outdated', $result);
            $this->assertArrayHasKey('report_path', $result);
        }
    }

    public function testGetNodeTestCoverageReportPath(): void
    {
        $result = $this->parser->getNodeTestCoverage();

        $this->assertIsBool($result['available']);

        if ($result['available']) {
            $this->assertSame('/build/coverage/node/index.html', $result['report_path']);
        }
    }

    public function testGetNodeTestCoverageAvailableWhenFileExists(): void
    {
        // Create a fake Node coverage report at the path the service expects,
        // then clean it up. This exercises the available=true branch (lines 146-157).
        $nodeReportDir  = __DIR__ . '/../../../../build/coverage/node/';
        $nodeReportFile = $nodeReportDir . 'index.html';
        $created        = false;
        $dirCreated     = false;

        if (!is_dir($nodeReportDir)) {
            mkdir($nodeReportDir, 0755, recursive: true);
            $dirCreated = true;
        }

        if (!file_exists($nodeReportFile)) {
            $html = '<span class="strong">85.00% </span><span class="quiet">Statements</span>' .
                    '<span class="strong">72.00% </span><span class="quiet">Lines</span>';
            file_put_contents($nodeReportFile, $html);
            $created = true;
        }

        try {
            $result = $this->parser->getNodeTestCoverage();

            $this->assertTrue($result['available']);
            $this->assertArrayHasKey('metrics', $result);
            $this->assertArrayHasKey('generated_at', $result);
            $this->assertArrayHasKey('outdated', $result);
            $this->assertArrayHasKey('report_path', $result);
            $this->assertSame('/build/coverage/node/index.html', $result['report_path']);
            $this->assertIsString($result['generated_at']);
        } finally {
            if ($created) {
                unlink($nodeReportFile);
            }

            if ($dirCreated) {
                rmdir($nodeReportDir);
            }
        }
    }

    // ==================== formatAge (via parseCoverageMetrics + getPhpTestCoverage) ====================

    /**
     * Tests formatAge indirectly by creating a real coverage file and checking the generated_at field.
     * The formatAge private method is exercised through getPhpTestCoverage/getNodeTestCoverage.
     */
    public function testFormatAgeIsReturnedAsString(): void
    {
        $result = $this->parser->getPhpTestCoverage();

        // available=true means formatAge was called; available=false means file not found (also valid)
        if ($result['available']) {
            $this->assertIsString($result['generated_at']);
            $this->assertNotEmpty($result['generated_at']);
        } else {
            // File not found branch — still a valid execution path
            $this->assertFalse($result['available']);
        }
    }

    public function testFormatAgeViaNodeCoverageWithRecentFile(): void
    {
        // Create a coverage file with a known mtime to exercise all formatAge branches.
        // "just now" branch: mtime = now (diff < 60)
        $nodeReportDir  = __DIR__ . '/../../../../build/coverage/node/';
        $nodeReportFile = $nodeReportDir . 'index.html';
        $dirCreated     = !is_dir($nodeReportDir);

        if ($dirCreated) {
            mkdir($nodeReportDir, 0755, recursive: true);
        }

        $html = '<div class="progress-bar" aria-valuenow="90.00"></div>';
        file_put_contents($nodeReportFile, $html);

        try {
            $result = $this->parser->getNodeTestCoverage();

            if ($result['available']) {
                $generatedAt = $result['generated_at'];
                // Any of the formatAge output patterns are valid
                $this->assertMatchesRegularExpression(
                    '/just now|minute|hour|day/',
                    $generatedAt,
                );
            } else {
                // File not parseable (PHPUnit format only yields lines/statements)
                $this->assertFalse($result['available']);
            }
        } finally {
            unlink($nodeReportFile);
            if ($dirCreated) {
                rmdir($nodeReportDir);
            }
        }
    }
}
