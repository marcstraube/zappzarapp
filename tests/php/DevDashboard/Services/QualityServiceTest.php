<?php

declare(strict_types=1);

namespace Tests\DevDashboard\Services;

use DevDashboard\Services\QualityService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

#[CoversClass(QualityService::class)]
class QualityServiceTest extends TestCase
{
    private QualityService $service;

    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        require_once __DIR__ . '/../../../../src/php/DevDashboard/Services/QualityService.php';
        $this->service = new QualityService();

        // Create temporary directory for test files
        $this->tempDir = sys_get_temp_dir() . '/quality_service_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        // Clean up temporary files
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
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

    public function testFormatAge(): void
    {
        $method = $this->getPrivateMethod('formatAge');

        // Just now
        $result = $method->invoke($this->service, time());
        $this->assertEquals('just now', $result);

        // Minutes ago
        $result = $method->invoke($this->service, time() - 300);
        $this->assertEquals('5 minutes ago', $result);

        $result = $method->invoke($this->service, time() - 60);
        $this->assertEquals('1 minute ago', $result);

        // Hours ago
        $result = $method->invoke($this->service, time() - 10800);
        $this->assertEquals('3 hours ago', $result);

        $result = $method->invoke($this->service, time() - 3600);
        $this->assertEquals('1 hour ago', $result);

        // Days ago
        $result = $method->invoke($this->service, time() - 432000);
        $this->assertEquals('5 days ago', $result);

        $result = $method->invoke($this->service, time() - 86400);
        $this->assertEquals('1 day ago', $result);
    }

    public function testParseCoverageMetricsNodeFormat(): void
    {
        $htmlContent = <<<'HTML'
<!DOCTYPE html>
<html>
<body>
    <div class="summary">
        <span class="strong">76.91% </span>
        <span class="quiet">Statements</span>
        <span class="strong">67.28% </span>
        <span class="quiet">Branches</span>
        <span class="strong">78.65% </span>
        <span class="quiet">Functions</span>
        <span class="strong">76.94% </span>
        <span class="quiet">Lines</span>
    </div>
</body>
</html>
HTML;

        $htmlFile = $this->tempDir . '/node-coverage.html';
        file_put_contents($htmlFile, $htmlContent);

        $method  = $this->getPrivateMethod('parseCoverageMetrics');
        $metrics = $method->invoke($this->service, $htmlFile);

        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('statements', $metrics);
        $this->assertArrayHasKey('branches', $metrics);
        $this->assertArrayHasKey('functions', $metrics);
        $this->assertArrayHasKey('lines', $metrics);

        $this->assertEquals(76.91, $metrics['statements']);
        $this->assertEquals(67.28, $metrics['branches']);
        $this->assertEquals(78.65, $metrics['functions']);
        $this->assertEquals(76.94, $metrics['lines']);
    }

    public function testParseCoverageMetricsPhpUnitFormat(): void
    {
        $htmlContent = <<<'HTML'
<!DOCTYPE html>
<html>
<body>
    <table>
        <tr>
            <td class="danger">Total</td>
            <td class="danger big">
                <div class="progress">
                    <div class="progress-bar bg-danger" style="width: 26.55%">
                        <span class="visually-hidden">26.55% covered</span>
                    </div>
                </div>
            </td>
            <td class="danger small"><div align="right">26.55%</div></td>
            <td class="danger small"><div align="right">998 / 3759</div></td>
            <td class="danger big">
                <div class="progress">
                    <div class="progress-bar bg-danger" style="width: 23.65%">
                        <span class="visually-hidden">23.65% covered</span>
                    </div>
                </div>
            </td>
            <td class="danger small"><div align="right">23.65%</div></td>
            <td class="danger small"><div align="right">96 / 406</div></td>
        </tr>
    </table>
</body>
</html>
HTML;

        $htmlFile = $this->tempDir . '/php-coverage.html';
        file_put_contents($htmlFile, $htmlContent);

        $method  = $this->getPrivateMethod('parseCoverageMetrics');
        $metrics = $method->invoke($this->service, $htmlFile);

        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('statements', $metrics);
        $this->assertArrayHasKey('lines', $metrics);
        $this->assertArrayHasKey('functions', $metrics);

        // PHPUnit: statements should equal lines, functions should be parsed
        $this->assertEquals(26.55, $metrics['lines']);
        $this->assertEquals(26.55, $metrics['statements']);
        $this->assertEquals(23.65, $metrics['functions']);
    }

    public function testParseCoverageMetricsErrorCases(): void
    {
        $method = $this->getPrivateMethod('parseCoverageMetrics');

        // Non-existent file
        $metrics = $method->invoke($this->service, '/non/existent/file.html');
        $this->assertNull($metrics);

        // Invalid HTML (no coverage data)
        $htmlFile = $this->tempDir . '/invalid-coverage.html';
        file_put_contents($htmlFile, '<html><body>No coverage data here</body></html>');
        $metrics = $method->invoke($this->service, $htmlFile);
        $this->assertNull($metrics);

        // Empty file
        $htmlFile = $this->tempDir . '/empty-coverage.html';
        file_put_contents($htmlFile, '');
        $metrics = $method->invoke($this->service, $htmlFile);
        $this->assertNull($metrics);
    }

    public function testParseCoverageMetricsPartialNodeData(): void
    {
        $htmlContent = <<<'HTML'
<!DOCTYPE html>
<html>
<body>
    <div class="summary">
        <span class="strong">80.50% </span>
        <span class="quiet">Statements</span>
        <span class="strong">90.25% </span>
        <span class="quiet">Lines</span>
    </div>
</body>
</html>
HTML;

        $htmlFile = $this->tempDir . '/partial-coverage.html';
        file_put_contents($htmlFile, $htmlContent);

        $method  = $this->getPrivateMethod('parseCoverageMetrics');
        $metrics = $method->invoke($this->service, $htmlFile);

        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('statements', $metrics);
        $this->assertArrayHasKey('lines', $metrics);
        $this->assertEquals(80.50, $metrics['statements']);
        $this->assertEquals(90.25, $metrics['lines']);
    }

    public function testGetQualityMetrics(): void
    {
        $metrics = $this->service->getQualityMetrics();

        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('php', $metrics);
        $this->assertArrayHasKey('node', $metrics);
        $this->assertArrayHasKey('code_stats', $metrics);
        $this->assertArrayHasKey('test_coverage', $metrics);

        // Test coverage should have php and node keys
        $this->assertArrayHasKey('php', $metrics['test_coverage']);
        $this->assertArrayHasKey('node', $metrics['test_coverage']);
    }

    public function testGetQuickActions(): void
    {
        $actions = $this->service->getQuickActions();

        $this->assertIsArray($actions);
        $this->assertNotEmpty($actions);

        // Check structure of first action
        $firstAction = $actions[0];
        $this->assertArrayHasKey('label', $firstAction);
        $this->assertArrayHasKey('command', $firstAction);
        $this->assertArrayHasKey('description', $firstAction);
    }

    private function getPrivateMethod(string $methodName): ReflectionMethod
    {
        $reflection = new ReflectionClass($this->service);

        return $reflection->getMethod($methodName);
    }
}
