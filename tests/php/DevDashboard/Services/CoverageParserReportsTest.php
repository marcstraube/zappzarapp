<?php

declare(strict_types=1);

namespace Tests\DevDashboard\Services;

use DevDashboard\Services\CoverageParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for CoverageParser — getPhpTestCoverage(), getNodeTestCoverage(), formatAge()
 *
 * Uses a temp-dir fixture as projectRoot so both "report exists" and "report missing"
 * branches are exercised deterministically in every environment.
 */
#[CoversClass(CoverageParser::class)]
class CoverageParserReportsTest extends TestCase
{
    private string $tempRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempRoot = sys_get_temp_dir() . '/coverage_parser_reports_test_' . uniqid() . '/';
        mkdir($this->tempRoot, 0755, recursive: true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempRoot);
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

    // ==================== getPhpTestCoverage — "report missing" branch ====================

    #[Test]
    public function testGetPhpTestCoverageUnavailableWhenNoReport(): void
    {
        // projectRoot has no coverage-php/ directory → file_exists() returns false → lines 110-113
        $parser = new CoverageParser($this->tempRoot);
        $result = $parser->getPhpTestCoverage();

        $this->assertSame(false, $result['available']);
        $this->assertStringContainsString('make test-coverage-php', $result['message']);
    }

    // ==================== getPhpTestCoverage — "report present" branch ====================

    #[Test]
    public function testGetPhpTestCoverageAvailableWhenReportExists(): void
    {
        // Create a minimal PHPUnit-style coverage report → lines 116-127
        $coverageDir = $this->tempRoot . 'build/coverage-php/';
        mkdir($coverageDir, 0755, recursive: true);

        $html = '<div class="progress-bar" aria-valuenow="85.00"></div>';
        file_put_contents($coverageDir . 'index.html', $html);

        $parser = new CoverageParser($this->tempRoot);
        $result = $parser->getPhpTestCoverage();

        $this->assertTrue($result['available']);
        $this->assertArrayHasKey('metrics', $result);
        $this->assertArrayHasKey('generated_at', $result);
        $this->assertArrayHasKey('outdated', $result);
        $this->assertSame('/build/coverage-php/index.html', $result['report_path']);
    }

    #[Test]
    public function testGetPhpTestCoverageMetricsAreCorrectlyParsed(): void
    {
        $coverageDir = $this->tempRoot . 'build/coverage-php/';
        mkdir($coverageDir, 0755, recursive: true);

        $html = '<div class="progress-bar" aria-valuenow="72.50"></div>';
        file_put_contents($coverageDir . 'index.html', $html);

        $parser  = new CoverageParser($this->tempRoot);
        $result  = $parser->getPhpTestCoverage();

        $this->assertTrue($result['available']);
        $this->assertSame(72.50, $result['metrics']['lines']);
    }

    #[Test]
    public function testGetPhpTestCoverageOutdatedWhenSourceIsNewer(): void
    {
        // Create coverage report, then create a newer PHP source file → outdated = true (line 119)
        $coverageDir = $this->tempRoot . 'build/coverage-php/';
        $phpSrcDir   = $this->tempRoot . 'src/php/';
        mkdir($coverageDir, 0755, recursive: true);
        mkdir($phpSrcDir, 0755, recursive: true);

        $htmlFile = $coverageDir . 'index.html';
        file_put_contents($htmlFile, '<div class="progress-bar" aria-valuenow="80.00"></div>');
        // Set report mtime to 1 hour ago, source file mtime to now
        touch($htmlFile, time() - 3600);

        $srcFile = $phpSrcDir . 'Dummy.php';
        file_put_contents($srcFile, '<?php // dummy');
        touch($srcFile, time());

        $parser = new CoverageParser($this->tempRoot);
        $result = $parser->getPhpTestCoverage();

        $this->assertTrue($result['available']);
        $this->assertTrue($result['outdated']);
    }

    // ==================== getNodeTestCoverage — "report missing" branch ====================

    #[Test]
    public function testGetNodeTestCoverageUnavailableWhenNoReport(): void
    {
        // tempRoot has no coverage/node directory → file_exists() returns false → lines 140-143
        $parser = new CoverageParser($this->tempRoot);
        $result = $parser->getNodeTestCoverage();

        $this->assertSame(false, $result['available']);
        $this->assertStringContainsString('make test-coverage-node', $result['message']);
    }

    // ==================== getNodeTestCoverage — "report present" branch ====================

    #[Test]
    public function testGetNodeTestCoverageAvailableWhenReportExists(): void
    {
        // Create a minimal Vitest-style coverage report → lines 146-157
        $nodeDir = $this->tempRoot . 'build/coverage/node/';
        mkdir($nodeDir, 0755, recursive: true);

        $html = '<span class="strong">85.00% </span><span class="quiet">Statements</span>' .
                '<span class="strong">72.00% </span><span class="quiet">Lines</span>';
        file_put_contents($nodeDir . 'index.html', $html);

        $parser = new CoverageParser($this->tempRoot);
        $result = $parser->getNodeTestCoverage();

        $this->assertTrue($result['available']);
        $this->assertArrayHasKey('metrics', $result);
        $this->assertArrayHasKey('generated_at', $result);
        $this->assertArrayHasKey('outdated', $result);
        $this->assertSame('/build/coverage/node/index.html', $result['report_path']);
    }

    #[Test]
    public function testGetNodeTestCoverageMetricsAreCorrectlyParsed(): void
    {
        $nodeDir = $this->tempRoot . 'build/coverage/node/';
        mkdir($nodeDir, 0755, recursive: true);

        $html = '<span class="strong">91.00% </span><span class="quiet">Statements</span>' .
                '<span class="strong">88.50% </span><span class="quiet">Lines</span>';
        file_put_contents($nodeDir . 'index.html', $html);

        $parser = new CoverageParser($this->tempRoot);
        $result = $parser->getNodeTestCoverage();

        $this->assertTrue($result['available']);
        $this->assertSame(91.0, $result['metrics']['statements']);
        $this->assertSame(88.5, $result['metrics']['lines']);
    }

    #[Test]
    public function testGetNodeTestCoverageReportPath(): void
    {
        $nodeDir = $this->tempRoot . 'build/coverage/node/';
        mkdir($nodeDir, 0755, recursive: true);
        file_put_contents($nodeDir . 'index.html', '<div class="progress-bar" aria-valuenow="90.00"></div>');

        $parser = new CoverageParser($this->tempRoot);
        $result = $parser->getNodeTestCoverage();

        $this->assertTrue($result['available']);
        $this->assertSame('/build/coverage/node/index.html', $result['report_path']);
    }

    // ==================== formatAge — all branches via getPhpTestCoverage ====================

    #[Test]
    public function testFormatAgeJustNow(): void
    {
        // Report mtime = now → diff < 60 → "just now" (line 210)
        $coverageDir = $this->tempRoot . 'build/coverage-php/';
        mkdir($coverageDir, 0755, recursive: true);

        $htmlFile = $coverageDir . 'index.html';
        file_put_contents($htmlFile, '<div class="progress-bar" aria-valuenow="90.00"></div>');
        touch($htmlFile, time());

        $parser = new CoverageParser($this->tempRoot);
        $result = $parser->getPhpTestCoverage();

        $this->assertTrue($result['available']);
        $this->assertSame('just now', $result['generated_at']);
    }

    #[Test]
    public function testFormatAgeMinutesAgo(): void
    {
        // Report mtime = 5 minutes ago → "5 minutes ago" (line 213-215)
        $coverageDir = $this->tempRoot . 'build/coverage-php/';
        mkdir($coverageDir, 0755, recursive: true);

        $htmlFile = $coverageDir . 'index.html';
        file_put_contents($htmlFile, '<div class="progress-bar" aria-valuenow="90.00"></div>');
        touch($htmlFile, time() - 300);

        $parser = new CoverageParser($this->tempRoot);
        $result = $parser->getPhpTestCoverage();

        $this->assertTrue($result['available']);
        $this->assertSame('5 minutes ago', $result['generated_at']);
    }

    #[Test]
    public function testFormatAgeOneMinuteAgo(): void
    {
        // Exactly 1 minute → "1 minute ago" (singular branch in line 215)
        $coverageDir = $this->tempRoot . 'build/coverage-php/';
        mkdir($coverageDir, 0755, recursive: true);

        $htmlFile = $coverageDir . 'index.html';
        file_put_contents($htmlFile, '<div class="progress-bar" aria-valuenow="90.00"></div>');
        touch($htmlFile, time() - 60);

        $parser = new CoverageParser($this->tempRoot);
        $result = $parser->getPhpTestCoverage();

        $this->assertTrue($result['available']);
        $this->assertSame('1 minute ago', $result['generated_at']);
    }

    #[Test]
    public function testFormatAgeHoursAgo(): void
    {
        // Report mtime = 3 hours ago → "3 hours ago" (line 218-220)
        $coverageDir = $this->tempRoot . 'build/coverage-php/';
        mkdir($coverageDir, 0755, recursive: true);

        $htmlFile = $coverageDir . 'index.html';
        file_put_contents($htmlFile, '<div class="progress-bar" aria-valuenow="90.00"></div>');
        touch($htmlFile, time() - 10800);

        $parser = new CoverageParser($this->tempRoot);
        $result = $parser->getPhpTestCoverage();

        $this->assertTrue($result['available']);
        $this->assertSame('3 hours ago', $result['generated_at']);
    }

    #[Test]
    public function testFormatAgeOneHourAgo(): void
    {
        // Exactly 1 hour → "1 hour ago" (singular branch)
        $coverageDir = $this->tempRoot . 'build/coverage-php/';
        mkdir($coverageDir, 0755, recursive: true);

        $htmlFile = $coverageDir . 'index.html';
        file_put_contents($htmlFile, '<div class="progress-bar" aria-valuenow="90.00"></div>');
        touch($htmlFile, time() - 3600);

        $parser = new CoverageParser($this->tempRoot);
        $result = $parser->getPhpTestCoverage();

        $this->assertTrue($result['available']);
        $this->assertSame('1 hour ago', $result['generated_at']);
    }

    #[Test]
    public function testFormatAgeDaysAgo(): void
    {
        // Report mtime = 2 days ago → "2 days ago" (line 223-224)
        $coverageDir = $this->tempRoot . 'build/coverage-php/';
        mkdir($coverageDir, 0755, recursive: true);

        $htmlFile = $coverageDir . 'index.html';
        file_put_contents($htmlFile, '<div class="progress-bar" aria-valuenow="90.00"></div>');
        touch($htmlFile, time() - 172800);

        $parser = new CoverageParser($this->tempRoot);
        $result = $parser->getPhpTestCoverage();

        $this->assertTrue($result['available']);
        $this->assertSame('2 days ago', $result['generated_at']);
    }

    #[Test]
    public function testFormatAgeOneDayAgo(): void
    {
        // Exactly 1 day → "1 day ago" (singular branch)
        $coverageDir = $this->tempRoot . 'build/coverage-php/';
        mkdir($coverageDir, 0755, recursive: true);

        $htmlFile = $coverageDir . 'index.html';
        file_put_contents($htmlFile, '<div class="progress-bar" aria-valuenow="90.00"></div>');
        touch($htmlFile, time() - 86400);

        $parser = new CoverageParser($this->tempRoot);
        $result = $parser->getPhpTestCoverage();

        $this->assertTrue($result['available']);
        $this->assertSame('1 day ago', $result['generated_at']);
    }

    // ==================== getNewestFileMtime — directory not found ====================

    #[Test]
    public function testGetNewestFileMtimeWithMissingSourceDir(): void
    {
        // src/php does not exist in tempRoot → getNewestFileMtime returns null → outdated = false (line 167-169)
        $coverageDir = $this->tempRoot . 'build/coverage-php/';
        mkdir($coverageDir, 0755, recursive: true);
        file_put_contents($coverageDir . 'index.html', '<div class="progress-bar" aria-valuenow="80.00"></div>');

        $parser = new CoverageParser($this->tempRoot);
        $result = $parser->getPhpTestCoverage();

        $this->assertTrue($result['available']);
        // sourceMtime is null → isOutdated must be false
        $this->assertFalse($result['outdated']);
    }

    // ==================== getNewestFileMtime — directory exists with matching files ====================

    #[Test]
    public function testGetNewestFileMtimeWithExistingSrcDir(): void
    {
        // src/php dir exists with .php files → getNewestFileMtime returns a timestamp (lines 171-199)
        $coverageDir = $this->tempRoot . 'build/coverage-php/';
        $phpSrcDir   = $this->tempRoot . 'src/php/';
        mkdir($coverageDir, 0755, recursive: true);
        mkdir($phpSrcDir, 0755, recursive: true);

        $htmlFile = $coverageDir . 'index.html';
        file_put_contents($htmlFile, '<div class="progress-bar" aria-valuenow="80.00"></div>');
        touch($htmlFile, time() - 7200);

        file_put_contents($phpSrcDir . 'Example.php', '<?php // dummy');
        touch($phpSrcDir . 'Example.php', time() - 3600);

        $parser = new CoverageParser($this->tempRoot);
        $result = $parser->getPhpTestCoverage();

        $this->assertTrue($result['available']);
        // Source file is newer than report by 3600s — wait, report is 7200s old, src 3600s old → src newer → outdated
        $this->assertTrue($result['outdated']);
    }

    // ==================== getNewestFileMtime — non-file entries skipped (line 185) ====================

    #[Test]
    public function testGetNewestFileMtimeSkipsNonFileEntries(): void
    {
        // Empty subdirectory inside src/php — LEAVES_ONLY yields it, isFile() → false → continue (line 185)
        $coverageDir = $this->tempRoot . 'build/coverage-php/';
        $phpSrcDir   = $this->tempRoot . 'src/php/';
        $subDir      = $phpSrcDir . 'subdir/';
        mkdir($coverageDir, 0755, recursive: true);
        mkdir($subDir, 0755, recursive: true);

        $htmlFile = $coverageDir . 'index.html';
        file_put_contents($htmlFile, '<div class="progress-bar" aria-valuenow="80.00"></div>');
        touch($htmlFile, time() - 7200);

        $parser = new CoverageParser($this->tempRoot);
        $result = $parser->getPhpTestCoverage();

        // Directory has no PHP files → newestMtime = null → outdated = false
        $this->assertTrue($result['available']);
        $this->assertFalse($result['outdated']);
    }

    // ==================== getNewestFileMtime — wrong extension skipped (line 190) ====================

    #[Test]
    public function testGetNewestFileMtimeSkipsWrongExtension(): void
    {
        // A .txt file in src/php should be skipped; no .php files → outdated = false (line 190 hit)
        $coverageDir = $this->tempRoot . 'build/coverage-php/';
        $phpSrcDir   = $this->tempRoot . 'src/php/';
        mkdir($coverageDir, 0755, recursive: true);
        mkdir($phpSrcDir, 0755, recursive: true);

        $htmlFile = $coverageDir . 'index.html';
        file_put_contents($htmlFile, '<div class="progress-bar" aria-valuenow="80.00"></div>');
        touch($htmlFile, time() - 7200);

        // A recent .txt file — wrong extension, should be ignored
        $txtFile = $phpSrcDir . 'notes.txt';
        file_put_contents($txtFile, 'not php');
        touch($txtFile, time());

        $parser = new CoverageParser($this->tempRoot);
        $result = $parser->getPhpTestCoverage();

        // .txt ignored → newestMtime = null → outdated = false
        $this->assertTrue($result['available']);
        $this->assertFalse($result['outdated']);
    }
}
