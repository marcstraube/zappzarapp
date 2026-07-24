<?php

declare(strict_types=1);

namespace Tests\DevDashboard\Services;

use DevDashboard\Services\DocsService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for DocsService — status and availability logic
 *
 * Uses temp fixture directories so tests run without the Docker container
 * filesystem (/var/www/html/...). The DocsService constructor accepts optional
 * path overrides for exactly this purpose.
 */
#[CoversClass(DocsService::class)]
final class DocsServiceTest extends TestCase
{
    private string $baseDir;

    private string $docsDir;

    private string $srcDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->baseDir = sys_get_temp_dir() . '/docs_service_test_' . uniqid();
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

    // ===== Default constructor =====

    #[Test]
    public function testDefaultConstructorUsesContainerPaths(): void
    {
        $service = new DocsService();

        // With no container filesystem the result is "no source" → satisfied
        $status = $service->getApiDocsStatus();

        $this->assertArrayHasKey('available', $status);
        $this->assertIsBool($status['available']);
    }

    // ===== getApiDocsStatus() — no source dirs exist =====

    #[Test]
    public function testGetApiDocsStatusReturnsMandatoryKeys(): void
    {
        $service = new DocsService($this->docsDir, $this->srcDir);

        $status = $service->getApiDocsStatus();

        $this->assertArrayHasKey('available', $status);
        $this->assertArrayHasKey('allFresh', $status);
        $this->assertArrayHasKey('php', $status);
        $this->assertArrayHasKey('node_backend', $status);
        $this->assertArrayHasKey('node_frontend', $status);
        $this->assertArrayHasKey('commands', $status);
    }

    #[Test]
    public function testGetApiDocsStatusWhenNoSourceDirsExist(): void
    {
        // No src/php, no src/node/backend, no src/node/frontend created
        $service = new DocsService($this->docsDir, $this->srcDir);

        $status = $service->getApiDocsStatus();

        // No source = nothing to document = available & fresh
        $this->assertTrue($status['available'], 'No source dirs → available (nothing to document)');
        $this->assertTrue($status['allFresh'], 'No source dirs → allFresh');
    }

    #[Test]
    public function testGetApiDocsStatusPhpSectionStructure(): void
    {
        $service = new DocsService($this->docsDir, $this->srcDir);

        $status  = $service->getApiDocsStatus();
        $phpDocs = $status['php'];

        $this->assertArrayHasKey('exists', $phpDocs);
        $this->assertArrayHasKey('outdated', $phpDocs);
        $this->assertArrayHasKey('hasSource', $phpDocs);
        $this->assertArrayHasKey('path', $phpDocs);
        $this->assertArrayHasKey('name', $phpDocs);
        $this->assertArrayHasKey('url', $phpDocs);
        $this->assertArrayHasKey('docsAge', $phpDocs);
    }

    // ===== getApiDocsStatus() — source exists, no docs =====

    #[Test]
    public function testGetApiDocsStatusUnavailableWhenSourceExistsButNoDocs(): void
    {
        // Create a PHP source file so hasSource=true
        $phpSrcDir = $this->srcDir . '/php';
        mkdir($phpSrcDir, 0755, recursive: true);
        file_put_contents($phpSrcDir . '/Foo.php', '<?php // test');

        $service = new DocsService($this->docsDir, $this->srcDir);
        $status  = $service->getApiDocsStatus();

        $this->assertFalse($status['available'], 'Source exists but docs missing → not available');
        $this->assertFalse($status['php']['exists']);
        $this->assertTrue($status['php']['hasSource']);
        $this->assertNull($status['php']['url']);
    }

    #[Test]
    public function testGetApiDocsStatusIncludesMakeDocsCommandWhenPhpAndNodeNeedRegen(): void
    {
        // Create source files for both PHP and node
        $phpSrcDir     = $this->srcDir . '/php';
        $backendSrcDir = $this->srcDir . '/node/backend';
        mkdir($phpSrcDir, 0755, recursive: true);
        mkdir($backendSrcDir, 0755, recursive: true);
        file_put_contents($phpSrcDir . '/Foo.php', '<?php // test');
        file_put_contents($backendSrcDir . '/index.ts', '// ts');

        $service = new DocsService($this->docsDir, $this->srcDir);
        $status  = $service->getApiDocsStatus();

        // Both PHP and node backend need regen → suggest 'make docs'
        $this->assertArrayHasKey('all', $status['commands']);
        $this->assertSame('make docs', $status['commands']['all']);
    }

    #[Test]
    public function testGetApiDocsStatusIncludesMakeDocsPhpWhenOnlyPhpNeedsRegen(): void
    {
        // Create PHP source only
        $phpSrcDir = $this->srcDir . '/php';
        mkdir($phpSrcDir, 0755, recursive: true);
        file_put_contents($phpSrcDir . '/Foo.php', '<?php // test');

        $service = new DocsService($this->docsDir, $this->srcDir);
        $status  = $service->getApiDocsStatus();

        $this->assertArrayHasKey('php', $status['commands']);
        $this->assertSame('make docs-php', $status['commands']['php']);
        $this->assertArrayNotHasKey('all', $status['commands']);
    }

    // ===== getApiDocsStatus() — docs exist, fresh =====

    #[Test]
    public function testGetApiDocsStatusFreshWhenDocsNewerThanSource(): void
    {
        // Create PHP source file with old mtime
        $phpSrcDir = $this->srcDir . '/php';
        mkdir($phpSrcDir, 0755, recursive: true);
        file_put_contents($phpSrcDir . '/Foo.php', '<?php // test');
        touch($phpSrcDir . '/Foo.php', time() - 7200); // 2 hours ago

        // Create docs index.html with newer mtime
        $phpDocsDir = $this->docsDir . '/api/php';
        mkdir($phpDocsDir, 0755, recursive: true);
        file_put_contents($phpDocsDir . '/index.html', '<html>docs</html>');
        touch($phpDocsDir . '/index.html', time() - 3600); // 1 hour ago (newer than source)

        $service = new DocsService($this->docsDir, $this->srcDir);
        $status  = $service->getApiDocsStatus();

        $this->assertTrue($status['php']['exists']);
        $this->assertFalse($status['php']['outdated']);
        $this->assertNotNull($status['php']['url']);
        $this->assertStringContainsString('api/php', $status['php']['url']);
        $this->assertNotNull($status['php']['docsAge'], 'docsAge should be set when docs exist');
    }

    #[Test]
    public function testGetApiDocsStatusOutdatedWhenSourceNewerThanDocs(): void
    {
        // Create docs first (old)
        $phpDocsDir = $this->docsDir . '/api/php';
        mkdir($phpDocsDir, 0755, recursive: true);
        file_put_contents($phpDocsDir . '/index.html', '<html>docs</html>');
        touch($phpDocsDir . '/index.html', time() - 7200); // 2 hours ago

        // Create PHP source with newer mtime
        $phpSrcDir = $this->srcDir . '/php';
        mkdir($phpSrcDir, 0755, recursive: true);
        file_put_contents($phpSrcDir . '/Foo.php', '<?php // updated');
        touch($phpSrcDir . '/Foo.php', time() - 3600); // 1 hour ago (newer than docs)

        $service = new DocsService($this->docsDir, $this->srcDir);
        $status  = $service->getApiDocsStatus();

        $this->assertTrue($status['php']['exists']);
        $this->assertTrue($status['php']['outdated']);
        $this->assertFalse($status['allFresh']);
    }

    // ===== getApiDocsStatus() — node-specific commands =====

    #[Test]
    public function testGetApiDocsStatusMakeDocsNodeWhenBothNodeSrcExistNoRegen(): void
    {
        // Create node/backend and node/frontend sources, but NOT php
        $backendSrcDir  = $this->srcDir . '/node/backend';
        $frontendSrcDir = $this->srcDir . '/node/frontend';
        mkdir($backendSrcDir, 0755, recursive: true);
        mkdir($frontendSrcDir, 0755, recursive: true);
        file_put_contents($backendSrcDir . '/index.ts', '// ts');
        file_put_contents($frontendSrcDir . '/index.tsx', '// tsx');

        $service = new DocsService($this->docsDir, $this->srcDir);
        $status  = $service->getApiDocsStatus();

        // Both node backend and frontend need regen → suggest 'make docs-node'
        $this->assertArrayHasKey('node', $status['commands']);
        $this->assertSame('make docs-node', $status['commands']['node']);
        $this->assertArrayNotHasKey('all', $status['commands']);
    }

    #[Test]
    public function testGetApiDocsStatusMakeDocsNodeBackendWhenOnlyBackendNeedsRegen(): void
    {
        // Create node/backend source only (frontend has docs)
        $backendSrcDir  = $this->srcDir . '/node/backend';
        $frontendSrcDir = $this->srcDir . '/node/frontend';
        mkdir($backendSrcDir, 0755, recursive: true);
        mkdir($frontendSrcDir, 0755, recursive: true);
        file_put_contents($backendSrcDir . '/index.ts', '// ts');
        file_put_contents($frontendSrcDir . '/main.ts', '// ts');
        touch($backendSrcDir . '/index.ts', time() - 3600);

        // Provide fresh frontend docs
        $frontendDocsDir = $this->docsDir . '/api/node-frontend';
        mkdir($frontendDocsDir, 0755, recursive: true);
        file_put_contents($frontendDocsDir . '/index.html', '<html>docs</html>');
        touch($frontendDocsDir . '/index.html', time()); // fresh

        $service = new DocsService($this->docsDir, $this->srcDir);
        $status  = $service->getApiDocsStatus();

        $this->assertArrayHasKey('node_backend', $status['commands']);
        $this->assertSame('make docs-node-backend', $status['commands']['node_backend']);
        $this->assertArrayNotHasKey('node_frontend', $status['commands']);
    }

    #[Test]
    public function testGetApiDocsStatusMakeDocsNodeFrontendWhenOnlyFrontendNeedsRegen(): void
    {
        // Create both node sources but only provide fresh backend docs
        $backendSrcDir  = $this->srcDir . '/node/backend';
        $frontendSrcDir = $this->srcDir . '/node/frontend';
        mkdir($backendSrcDir, 0755, recursive: true);
        mkdir($frontendSrcDir, 0755, recursive: true);
        file_put_contents($backendSrcDir . '/index.ts', '// ts');
        file_put_contents($frontendSrcDir . '/main.tsx', '// tsx');
        touch($backendSrcDir . '/index.ts', time() - 3600);

        // Provide fresh backend docs only
        $backendDocsDir = $this->docsDir . '/api/node-backend';
        mkdir($backendDocsDir, 0755, recursive: true);
        file_put_contents($backendDocsDir . '/index.html', '<html>docs</html>');
        touch($backendDocsDir . '/index.html', time()); // fresh

        $service = new DocsService($this->docsDir, $this->srcDir);
        $status  = $service->getApiDocsStatus();

        $this->assertArrayHasKey('node_frontend', $status['commands']);
        $this->assertSame('make docs-node-frontend', $status['commands']['node_frontend']);
        $this->assertArrayNotHasKey('node_backend', $status['commands']);
    }

    // ===== getApiDocsStatus() — all sources have fresh docs =====

    #[Test]
    public function testGetApiDocsStatusAllFreshWhenAllDocsExistAndFresh(): void
    {
        // PHP
        $phpSrcDir = $this->srcDir . '/php';
        $phpDocDir = $this->docsDir . '/api/php';
        mkdir($phpSrcDir, 0755, recursive: true);
        mkdir($phpDocDir, 0755, recursive: true);
        file_put_contents($phpSrcDir . '/Foo.php', '<?php // test');
        touch($phpSrcDir . '/Foo.php', time() - 7200);
        file_put_contents($phpDocDir . '/index.html', '<html>docs</html>');
        touch($phpDocDir . '/index.html', time() - 1800); // newer

        // Node backend
        $backendSrcDir = $this->srcDir . '/node/backend';
        $backendDocDir = $this->docsDir . '/api/node-backend';
        mkdir($backendSrcDir, 0755, recursive: true);
        mkdir($backendDocDir, 0755, recursive: true);
        file_put_contents($backendSrcDir . '/index.ts', '// ts');
        touch($backendSrcDir . '/index.ts', time() - 7200);
        file_put_contents($backendDocDir . '/index.html', '<html>docs</html>');
        touch($backendDocDir . '/index.html', time() - 1800);

        // Node frontend
        $frontendSrcDir = $this->srcDir . '/node/frontend';
        $frontendDocDir = $this->docsDir . '/api/node-frontend';
        mkdir($frontendSrcDir, 0755, recursive: true);
        mkdir($frontendDocDir, 0755, recursive: true);
        file_put_contents($frontendSrcDir . '/main.tsx', '// tsx');
        touch($frontendSrcDir . '/main.tsx', time() - 7200);
        file_put_contents($frontendDocDir . '/index.html', '<html>docs</html>');
        touch($frontendDocDir . '/index.html', time() - 1800);

        $service = new DocsService($this->docsDir, $this->srcDir);
        $status  = $service->getApiDocsStatus();

        $this->assertTrue($status['available']);
        $this->assertTrue($status['allFresh']);
        $this->assertEmpty($status['commands'], 'No commands needed when all docs are fresh');
    }

    // ===== getNewestMtime via getApiDocsStatus — extension filtering =====

    #[Test]
    public function testGetApiDocsStatusIgnoresFilesWithNonMatchingExtensions(): void
    {
        // Put only non-PHP files in src/php — should yield hasSource=false
        $phpSrcDir = $this->srcDir . '/php';
        mkdir($phpSrcDir, 0755, recursive: true);
        file_put_contents($phpSrcDir . '/README.md', '# docs');
        file_put_contents($phpSrcDir . '/data.json', '{}');

        $service = new DocsService($this->docsDir, $this->srcDir);
        $status  = $service->getApiDocsStatus();

        $this->assertFalse($status['php']['hasSource'], 'Non-PHP files should not count as source');
    }

    #[Test]
    public function testGetApiDocsStatusFindsJsFilesInNodeBackend(): void
    {
        // .js files should count for node/backend
        $backendSrcDir = $this->srcDir . '/node/backend';
        mkdir($backendSrcDir, 0755, recursive: true);
        file_put_contents($backendSrcDir . '/server.js', '// js');

        $service = new DocsService($this->docsDir, $this->srcDir);
        $status  = $service->getApiDocsStatus();

        $this->assertTrue($status['node_backend']['hasSource']);
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
