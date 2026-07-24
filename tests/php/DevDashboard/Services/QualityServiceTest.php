<?php

declare(strict_types=1);

namespace Tests\DevDashboard\Services;

use DevDashboard\Services\CommandRunner;
use DevDashboard\Services\CoverageParser;
use DevDashboard\Services\QualityService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for QualityService
 *
 * Uses a temp-dir as projectRoot so config-file "exists" and "missing" branches
 * are exercised deterministically in every environment.
 */
#[CoversClass(QualityService::class)]
#[UsesClass(CommandRunner::class)]
#[UsesClass(CoverageParser::class)]
class QualityServiceTest extends TestCase
{
    private string $tempRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempRoot = sys_get_temp_dir() . '/quality_service_test_' . uniqid() . '/';
        mkdir($this->tempRoot, 0755, recursive: true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempRoot)) {
            $this->removeDirectory($this->tempRoot);
        }

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

    /** Build a QualityService pointed at a controlled temp root. */
    private function makeService(?string $root = null): QualityService
    {
        return new QualityService(
            new CoverageParser($root ?? $this->tempRoot),
            new CommandRunner(),
            $root ?? $this->tempRoot,
        );
    }

    // ==================== getQualityMetrics — structure ====================

    public function testGetQualityMetricsReturnsExpectedKeys(): void
    {
        $service = $this->makeService();
        $metrics = $service->getQualityMetrics();

        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('php', $metrics);
        $this->assertArrayHasKey('node', $metrics);
        $this->assertArrayHasKey('code_stats', $metrics);
        $this->assertArrayHasKey('test_coverage', $metrics);
        $this->assertArrayHasKey('php', $metrics['test_coverage']);
        $this->assertArrayHasKey('node', $metrics['test_coverage']);
    }

    // ==================== getPhpStanStatus — "not configured" branch ====================

    public function testPhpStanNotConfiguredWhenFileAbsent(): void
    {
        $service = $this->makeService();
        $metrics = $service->getQualityMetrics();

        $phpstan = $metrics['php']['phpstan'];
        $this->assertFalse($phpstan['enabled']);
        $this->assertSame('PHPStan not configured', $phpstan['message']);
    }

    // ==================== getPhpStanStatus — "configured" branch ====================

    public function testPhpStanConfiguredWhenFilePresent(): void
    {
        file_put_contents($this->tempRoot . 'phpstan.neon', "parameters:\n    level: 9\n");

        $service = $this->makeService();
        $metrics = $service->getQualityMetrics();

        $phpstan = $metrics['php']['phpstan'];
        $this->assertTrue($phpstan['enabled']);
        $this->assertSame(9, $phpstan['level']);
        $this->assertSame('configured', $phpstan['status']);
    }

    // ==================== getPhpMdStatus — "not configured" branch ====================

    public function testPhpMdNotConfiguredWhenFileAbsent(): void
    {
        $service = $this->makeService();
        $metrics = $service->getQualityMetrics();

        $phpmd = $metrics['php']['phpmd'];
        $this->assertFalse($phpmd['enabled']);
    }

    // ==================== getPhpMdStatus — "configured" branch ====================

    public function testPhpMdConfiguredWhenFilePresent(): void
    {
        file_put_contents($this->tempRoot . 'phpmd.xml.dist', '<ruleset/>');

        $service = $this->makeService();
        $metrics = $service->getQualityMetrics();

        $phpmd = $metrics['php']['phpmd'];
        $this->assertTrue($phpmd['enabled']);
        $this->assertSame('configured', $phpmd['status']);
    }

    // ==================== getCsFixerStatus — "not configured" branch ====================

    public function testCsFixerNotConfiguredWhenFileAbsent(): void
    {
        $service = $this->makeService();
        $metrics = $service->getQualityMetrics();

        $csFixer = $metrics['php']['cs_fixer'];
        $this->assertFalse($csFixer['enabled']);
    }

    // ==================== getCsFixerStatus — "configured" branch ====================

    public function testCsFixerConfiguredWhenFilePresent(): void
    {
        file_put_contents($this->tempRoot . '.php-cs-fixer.dist.php', '<?php return [];');

        $service = $this->makeService();
        $metrics = $service->getQualityMetrics();

        $csFixer = $metrics['php']['cs_fixer'];
        $this->assertTrue($csFixer['enabled']);
        $this->assertSame('configured', $csFixer['status']);
    }

    // ==================== getEslintStatus — "not configured" branch (CI-only lines 242-245) ====================

    public function testEslintNotConfiguredWhenFileAbsent(): void
    {
        // tempRoot has no eslint.config.js → "not configured" branch
        $service = $this->makeService();
        $metrics = $service->getQualityMetrics();

        $eslint = $metrics['node']['eslint'];
        $this->assertFalse($eslint['enabled']);
        $this->assertSame('ESLint not configured', $eslint['message']);
    }

    // ==================== getEslintStatus — "configured" branch (local-only lines 248-253) ====================

    public function testEslintConfiguredWhenFilePresent(): void
    {
        file_put_contents($this->tempRoot . 'eslint.config.js', 'export default [];');

        $service = $this->makeService();
        $metrics = $service->getQualityMetrics();

        $eslint = $metrics['node']['eslint'];
        $this->assertTrue($eslint['enabled']);
        $this->assertSame('eslint.config.js', $eslint['config_file']);
        $this->assertSame('configured', $eslint['status']);
    }

    // ==================== getPrettierStatus — "not configured" branch (CI-only lines 266-269) ====================

    public function testPrettierNotConfiguredWhenFileAbsent(): void
    {
        $service = $this->makeService();
        $metrics = $service->getQualityMetrics();

        $prettier = $metrics['node']['prettier'];
        $this->assertFalse($prettier['enabled']);
        $this->assertSame('Prettier not configured', $prettier['message']);
    }

    // ==================== getPrettierStatus — "configured" branch (local-only lines 272-277) ====================

    public function testPrettierConfiguredWhenFilePresent(): void
    {
        file_put_contents($this->tempRoot . '.prettierrc.json', '{}');

        $service = $this->makeService();
        $metrics = $service->getQualityMetrics();

        $prettier = $metrics['node']['prettier'];
        $this->assertTrue($prettier['enabled']);
        $this->assertSame('.prettierrc.json', $prettier['config_file']);
        $this->assertSame('configured', $prettier['status']);
    }

    // ==================== getTypeScriptStatus — "not configured" branch (CI-only lines 290-293) ====================

    public function testTypeScriptNotConfiguredWhenFileAbsent(): void
    {
        $service = $this->makeService();
        $metrics = $service->getQualityMetrics();

        $typescript = $metrics['node']['typescript'];
        $this->assertFalse($typescript['enabled']);
        $this->assertSame('TypeScript not configured', $typescript['message']);
    }

    // ==================== getTypeScriptStatus — "configured" branch (local-only lines 296-301) ====================

    public function testTypeScriptConfiguredWhenFilePresent(): void
    {
        file_put_contents($this->tempRoot . 'tsconfig.json', '{}');

        $service = $this->makeService();
        $metrics = $service->getQualityMetrics();

        $typescript = $metrics['node']['typescript'];
        $this->assertTrue($typescript['enabled']);
        $this->assertSame('tsconfig.json', $typescript['config_file']);
        $this->assertSame('configured', $typescript['status']);
    }

    // ==================== countFiles — "dir missing" branch (CI-only line 336) ====================

    public function testCodeStatsZeroWhenSourceDirsAbsent(): void
    {
        // tempRoot has no src/php, src/node, tests/php, tests/node → all counts 0
        $service = $this->makeService();
        $metrics = $service->getQualityMetrics();

        $codeStats = $metrics['code_stats'];
        $this->assertSame(0, $codeStats['total_source_files']);
        $this->assertSame(0, $codeStats['total_test_files']);
        $this->assertFalse($codeStats['files']['php']['exists']);
        $this->assertFalse($codeStats['files']['typescript']['exists']);
    }

    // ==================== countFiles — "dir exists with files" branch ====================

    public function testCodeStatsCountsFilesWhenDirsExist(): void
    {
        $phpSrcDir = $this->tempRoot . 'src/php/';
        mkdir($phpSrcDir, 0755, recursive: true);
        file_put_contents($phpSrcDir . 'Foo.php', '<?php');
        file_put_contents($phpSrcDir . 'Bar.php', '<?php');

        $service = $this->makeService();
        $metrics = $service->getQualityMetrics();

        $phpFiles = $metrics['code_stats']['files']['php'];
        $this->assertTrue($phpFiles['exists']);
        $this->assertSame(2, $phpFiles['count']);
        $this->assertSame(2, $metrics['code_stats']['total_source_files']);
    }

    // ==================== getQuickActions — structure ====================

    public function testGetQuickActions(): void
    {
        $service = $this->makeService();
        $actions = $service->getQuickActions();

        $this->assertIsArray($actions);
        $this->assertNotEmpty($actions);

        $firstAction = $actions[0];
        $this->assertArrayHasKey('label', $firstAction);
        $this->assertArrayHasKey('command', $firstAction);
        $this->assertArrayHasKey('description', $firstAction);
    }

    // ==================== getPhpStanStatus — unreadable config (false branch at line 168) ====================

    public function testPhpStanLevelUnknownWhenConfigUnreadable(): void
    {
        // Write a phpstan.neon with content that has no "level:" line → level becomes 'unknown'
        file_put_contents($this->tempRoot . 'phpstan.neon', "parameters:\n    strict: true\n");

        $service = $this->makeService();
        $metrics = $service->getQualityMetrics();

        $phpstan = $metrics['php']['phpstan'];
        $this->assertTrue($phpstan['enabled']);
        // No level: key in config → matches[1] is absent → 'unknown' → (int)'unknown' = 0
        $this->assertSame(0, $phpstan['level']);
    }
}
