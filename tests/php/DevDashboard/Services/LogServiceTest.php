<?php

declare(strict_types=1);

namespace Tests\DevDashboard\Services;

use DevDashboard\Services\LogService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(LogService::class)]
class LogServiceTest extends TestCase
{
    private LogService $service;

    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/log_service_test_' . uniqid();
        mkdir($this->tempDir, 0o755, recursive: true);

        $this->service = new LogService(logsDir: $this->tempDir . '/');
    }

    protected function tearDown(): void
    {
        $files = glob($this->tempDir . '/*');
        if ($files !== false) {
            foreach ($files as $file) {
                unlink($file);
            }
        }

        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }

        parent::tearDown();
    }

    private function createLogFile(string $name, string $content = ''): string
    {
        $path   = $this->tempDir . '/' . $name;
        $result = file_put_contents($path, $content);

        $this->assertNotFalse($result, sprintf('Failed to write fixture file "%s" — check temp dir permissions', $path));

        return $path;
    }

    // ==================== readLogFile ====================

    #[Test]
    public function testReadLogFileReturnsErrorWhenFileNotFound(): void
    {
        $result = $this->service->readLogFile('nonexistent_test_file.log');

        $this->assertArrayHasKey('error', $result);
        $this->assertArrayHasKey('filename', $result);
        $this->assertSame('Log file not found', $result['error']);
    }

    #[Test]
    public function testReadLogFileReturnsContentForExistingFile(): void
    {
        $content = "line 1\nline 2\nline 3\n";
        $this->createLogFile('test_read.log', $content);

        $result = $this->service->readLogFile('test_read.log');

        $this->assertArrayHasKey('filename', $result);
        $this->assertArrayHasKey('lines', $result);
        $this->assertArrayHasKey('content', $result);
        $this->assertArrayHasKey('size', $result);
        $this->assertArrayHasKey('modified', $result);

        $this->assertSame('test_read.log', $result['filename']);
        $this->assertStringContainsString('line 1', $result['content']);
        $this->assertStringContainsString('line 3', $result['content']);
    }

    #[Test]
    public function testReadLogFileDefaultLines(): void
    {
        $this->createLogFile('test_default_lines.log', "content\n");

        $result = $this->service->readLogFile('test_default_lines.log');

        $this->assertSame(100, $result['lines']);
    }

    #[Test]
    public function testReadLogFileCustomLines(): void
    {
        $this->createLogFile('test_custom_lines.log', "content\n");

        $result = $this->service->readLogFile('test_custom_lines.log', 50);

        $this->assertSame(50, $result['lines']);
    }

    #[Test]
    public function testReadLogFileTailBehaviourWithManyLines(): void
    {
        // Create a file with 200 lines
        $lines = [];
        for ($i = 1; $i <= 200; $i++) {
            $lines[] = 'line ' . $i;
        }

        $this->createLogFile('test_tail.log', implode("\n", $lines) . "\n");

        // Read only last 10 lines
        $result = $this->service->readLogFile('test_tail.log', 10);

        $content = $result['content'];

        // Last lines should be present
        $this->assertStringContainsString('line 200', $content);
        $this->assertStringContainsString('line 191', $content);

        // First lines should NOT be present (tail behaviour)
        $this->assertStringNotContainsString('line 1' . PHP_EOL, $content);
    }

    #[Test]
    public function testReadLogFilePreventsPathTraversal(): void
    {
        // Path traversal attempt — the service uses basename() so '../etc/passwd'
        // becomes 'passwd' which won't exist in the logs dir
        $result = $this->service->readLogFile('../etc/passwd');

        $this->assertArrayHasKey('error', $result);
        $this->assertSame('Log file not found', $result['error']);
    }

    #[Test]
    public function testReadLogFileSizeIsCorrect(): void
    {
        $content = "hello world\n";
        $path    = $this->createLogFile('test_size.log', $content);

        $result = $this->service->readLogFile('test_size.log');

        $this->assertSame(filesize($path), $result['size']);
    }

    // ==================== getLogCommands ====================

    #[Test]
    public function testGetLogCommandsReturnsNonEmptyArray(): void
    {
        $commands = $this->service->getLogCommands();

        $this->assertIsArray($commands);
        $this->assertNotEmpty($commands);
    }

    #[Test]
    public function testGetLogCommandsHaveRequiredKeys(): void
    {
        $commands = $this->service->getLogCommands();

        foreach ($commands as $command) {
            $this->assertArrayHasKey('label', $command);
            $this->assertArrayHasKey('command', $command);
            $this->assertArrayHasKey('description', $command);
            $this->assertIsString($command['label']);
            $this->assertIsString($command['command']);
            $this->assertIsString($command['description']);
        }
    }

    #[Test]
    public function testGetLogCommandsIncludesMakeLogs(): void
    {
        $commands     = $this->service->getLogCommands();
        $commandTexts = array_column($commands, 'command');

        $this->assertContains('make logs', $commandTexts);
    }
}
