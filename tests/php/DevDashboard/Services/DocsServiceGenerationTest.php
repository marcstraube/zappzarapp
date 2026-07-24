<?php

declare(strict_types=1);

namespace Tests\DevDashboard\Services;

use DevDashboard\Services\DocsService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for DocsService — docs-age formatting, generation, and recursive scan
 *
 * Covers the docsAge formatting logic (exercised via getApiDocsStatus), the
 * generatePhpDocs() method, and recursive source-file discovery.
 *
 * generatePhpDocs() is made deterministic by injecting the phpdoc path and
 * working directory via constructor parameters so both branches (phar missing
 * and phar present) are always exercised regardless of the environment.
 */
#[CoversClass(DocsService::class)]
final class DocsServiceGenerationTest extends TestCase
{
    private string $baseDir;

    private string $docsDir;

    private string $srcDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->baseDir = sys_get_temp_dir() . '/docs_service_gen_test_' . uniqid();
        $this->docsDir = $this->baseDir . '/docs';
        $this->srcDir  = $this->baseDir . '/src';

        mkdir($this->docsDir, 0755, recursive: true);
        mkdir($this->srcDir, 0755, recursive: true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->baseDir);

        parent::tearDown();
    }

    // ===== docsAge formatting (indirectly via getApiDocsStatus) =====

    public function testDocsAgeShowsJustNow(): void
    {
        $phpSrcDir = $this->srcDir . '/php';
        $phpDocDir = $this->docsDir . '/api/php';
        mkdir($phpSrcDir, 0755, recursive: true);
        mkdir($phpDocDir, 0755, recursive: true);

        file_put_contents($phpSrcDir . '/Foo.php', '<?php // test');
        touch($phpSrcDir . '/Foo.php', time() - 7200); // old src

        // Set docs mtime to "just now" (0-59 seconds ago)
        file_put_contents($phpDocDir . '/index.html', '<html>docs</html>');
        touch($phpDocDir . '/index.html', time() - 10);

        $service = new DocsService($this->docsDir, $this->srcDir);
        $status  = $service->getApiDocsStatus();

        $this->assertSame('just now', $status['php']['docsAge']);
    }

    public function testDocsAgeShowsMinutes(): void
    {
        $phpSrcDir = $this->srcDir . '/php';
        $phpDocDir = $this->docsDir . '/api/php';
        mkdir($phpSrcDir, 0755, recursive: true);
        mkdir($phpDocDir, 0755, recursive: true);

        file_put_contents($phpSrcDir . '/Foo.php', '<?php // test');
        touch($phpSrcDir . '/Foo.php', time() - 7200); // old src

        // Set docs mtime to 5 minutes ago
        file_put_contents($phpDocDir . '/index.html', '<html>docs</html>');
        touch($phpDocDir . '/index.html', time() - 300);

        $service = new DocsService($this->docsDir, $this->srcDir);
        $status  = $service->getApiDocsStatus();

        $this->assertSame('5 mins ago', $status['php']['docsAge']);
    }

    public function testDocsAgeShowsSingleMinute(): void
    {
        $phpSrcDir = $this->srcDir . '/php';
        $phpDocDir = $this->docsDir . '/api/php';
        mkdir($phpSrcDir, 0755, recursive: true);
        mkdir($phpDocDir, 0755, recursive: true);

        file_put_contents($phpSrcDir . '/Foo.php', '<?php // test');
        touch($phpSrcDir . '/Foo.php', time() - 7200);

        // Set docs mtime to exactly 1 minute ago
        file_put_contents($phpDocDir . '/index.html', '<html>docs</html>');
        touch($phpDocDir . '/index.html', time() - 60);

        $service = new DocsService($this->docsDir, $this->srcDir);
        $status  = $service->getApiDocsStatus();

        $this->assertSame('1 min ago', $status['php']['docsAge']);
    }

    public function testDocsAgeShowsHours(): void
    {
        $phpSrcDir = $this->srcDir . '/php';
        $phpDocDir = $this->docsDir . '/api/php';
        mkdir($phpSrcDir, 0755, recursive: true);
        mkdir($phpDocDir, 0755, recursive: true);

        file_put_contents($phpSrcDir . '/Foo.php', '<?php // test');
        touch($phpSrcDir . '/Foo.php', time() - 86500); // older than 1 day

        // Set docs mtime to 3 hours ago
        file_put_contents($phpDocDir . '/index.html', '<html>docs</html>');
        touch($phpDocDir . '/index.html', time() - 10800);

        $service = new DocsService($this->docsDir, $this->srcDir);
        $status  = $service->getApiDocsStatus();

        $this->assertSame('3 hours ago', $status['php']['docsAge']);
    }

    public function testDocsAgeShowsSingleHour(): void
    {
        $phpSrcDir = $this->srcDir . '/php';
        $phpDocDir = $this->docsDir . '/api/php';
        mkdir($phpSrcDir, 0755, recursive: true);
        mkdir($phpDocDir, 0755, recursive: true);

        file_put_contents($phpSrcDir . '/Foo.php', '<?php // test');
        touch($phpSrcDir . '/Foo.php', time() - 86500);

        // Set docs mtime to exactly 1 hour ago
        file_put_contents($phpDocDir . '/index.html', '<html>docs</html>');
        touch($phpDocDir . '/index.html', time() - 3600);

        $service = new DocsService($this->docsDir, $this->srcDir);
        $status  = $service->getApiDocsStatus();

        $this->assertSame('1 hour ago', $status['php']['docsAge']);
    }

    public function testDocsAgeShowsDays(): void
    {
        $phpSrcDir = $this->srcDir . '/php';
        $phpDocDir = $this->docsDir . '/api/php';
        mkdir($phpSrcDir, 0755, recursive: true);
        mkdir($phpDocDir, 0755, recursive: true);

        file_put_contents($phpSrcDir . '/Foo.php', '<?php // test');
        touch($phpSrcDir . '/Foo.php', time() - 864000); // 10 days ago

        // Set docs mtime to 2 days ago
        file_put_contents($phpDocDir . '/index.html', '<html>docs</html>');
        touch($phpDocDir . '/index.html', time() - 172800);

        $service = new DocsService($this->docsDir, $this->srcDir);
        $status  = $service->getApiDocsStatus();

        $this->assertSame('2 days ago', $status['php']['docsAge']);
    }

    public function testDocsAgeShowsSingleDay(): void
    {
        $phpSrcDir = $this->srcDir . '/php';
        $phpDocDir = $this->docsDir . '/api/php';
        mkdir($phpSrcDir, 0755, recursive: true);
        mkdir($phpDocDir, 0755, recursive: true);

        file_put_contents($phpSrcDir . '/Foo.php', '<?php // test');
        touch($phpSrcDir . '/Foo.php', time() - 864000);

        // Set docs mtime to exactly 1 day ago
        file_put_contents($phpDocDir . '/index.html', '<html>docs</html>');
        touch($phpDocDir . '/index.html', time() - 86400);

        $service = new DocsService($this->docsDir, $this->srcDir);
        $status  = $service->getApiDocsStatus();

        $this->assertSame('1 day ago', $status['php']['docsAge']);
    }

    // ===== generatePhpDocs() — both branches always deterministic =====

    public function testGeneratePhpDocsReturnsExpectedStructure(): void
    {
        // Use a nonexistent phar path so the missing-tool branch is always taken
        $service = new DocsService(
            docsPath: $this->docsDir,
            srcPath: $this->srcDir,
            phpdocPath: $this->baseDir . '/nonexistent/phpdoc.phar',
        );
        $result = $service->generatePhpDocs();

        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('output', $result);
        $this->assertIsBool($result['success']);
        $this->assertIsString($result['message']);
        $this->assertIsString($result['output']);
    }

    public function testGeneratePhpDocsReturnsNotFoundWhenPhpdocMissing(): void
    {
        // Always deterministic: inject a path guaranteed not to exist
        $service = new DocsService(
            docsPath: $this->docsDir,
            srcPath: $this->srcDir,
            phpdocPath: $this->baseDir . '/nonexistent/phpdoc.phar',
        );
        $result = $service->generatePhpDocs();

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('phpDocumentor not found', $result['message']);
        $this->assertSame('', $result['output']);
    }

    public function testGeneratePhpDocsRunsCommandWhenPhpdocPresent(): void
    {
        // Create a fake "phar" that is actually a PHP script echoing known output
        // and exiting successfully (exit code 0)
        $fakePhar = $this->baseDir . '/phpdoc.phar';
        file_put_contents($fakePhar, '<?php echo "Documentation generated.\n";');
        chmod($fakePhar, 0755);

        $service = new DocsService(
            docsPath: $this->docsDir,
            srcPath: $this->srcDir,
            phpdocPath: $fakePhar,
            commandWorkingDir: $this->baseDir,
        );
        $result = $service->generatePhpDocs();

        $this->assertTrue($result['success']);
        $this->assertSame('PHP documentation generated successfully', $result['message']);
        $this->assertStringContainsString('Documentation generated.', $result['output']);
    }

    public function testGeneratePhpDocsReturnsFailureWhenCommandFails(): void
    {
        // Create a fake "phar" that exits with a non-zero exit code
        $fakePhar = $this->baseDir . '/phpdoc_fail.phar';
        file_put_contents($fakePhar, '<?php fwrite(STDERR, "Error output.\n"); exit(1);');
        chmod($fakePhar, 0755);

        $service = new DocsService(
            docsPath: $this->docsDir,
            srcPath: $this->srcDir,
            phpdocPath: $fakePhar,
            commandWorkingDir: $this->baseDir,
        );
        $result = $service->generatePhpDocs();

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('phpDocumentor failed with exit code', $result['message']);
        $this->assertStringContainsString('Error output.', $result['output']);
    }

    // ===== getNewestMtime — recursive directory scan =====

    public function testGetApiDocsStatusFindsSourceFileInSubdirectory(): void
    {
        // PHP source file nested in a subdirectory
        $nestedDir = $this->srcDir . '/php/App/Service';
        mkdir($nestedDir, 0755, recursive: true);
        file_put_contents($nestedDir . '/FooService.php', '<?php // service');

        $service = new DocsService($this->docsDir, $this->srcDir);
        $status  = $service->getApiDocsStatus();

        $this->assertTrue($status['php']['hasSource'], 'Nested PHP file should be found');
    }

    // ===== Helpers =====

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = array_diff(scandir($dir) ?: [], ['.', '..']);
        foreach ($items as $item) {
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }

        rmdir($dir);
    }
}
