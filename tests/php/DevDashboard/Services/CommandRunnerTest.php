<?php

declare(strict_types=1);

namespace Tests\DevDashboard\Services;

use DevDashboard\Services\CommandRunner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CommandRunner::class)]
class CommandRunnerTest extends TestCase
{
    private CommandRunner $runner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->runner = new CommandRunner();
    }

    #[Test]
    public function testRunEchoCommandReturnsOutput(): void
    {
        $result = $this->runner->run('echo hello');

        $this->assertArrayHasKey('exitCode', $result);
        $this->assertArrayHasKey('output', $result);
        $this->assertSame(0, $result['exitCode']);
        $this->assertStringContainsString('hello', $result['output']);
    }

    #[Test]
    public function testRunSuccessfulCommandHasZeroExitCode(): void
    {
        $result = $this->runner->run('true');

        $this->assertSame(0, $result['exitCode']);
    }

    #[Test]
    public function testRunFailingCommandHasNonZeroExitCode(): void
    {
        $result = $this->runner->run('false');

        $this->assertNotSame(0, $result['exitCode']);
    }

    #[Test]
    public function testRunCombinesStdoutAndStderr(): void
    {
        // Write to both stdout and stderr
        $result = $this->runner->run('echo out && echo err >&2');

        $this->assertSame(0, $result['exitCode']);
        $this->assertStringContainsString('out', $result['output']);
        $this->assertStringContainsString('err', $result['output']);
    }

    #[Test]
    public function testRunReturnsStringOutput(): void
    {
        $result = $this->runner->run('echo test_value');

        $this->assertIsString($result['output']);
        $this->assertIsInt($result['exitCode']);
    }

    #[Test]
    public function testRunCommandWithNoOutput(): void
    {
        $result = $this->runner->run('true');

        $this->assertSame('', $result['output']);
        $this->assertSame(0, $result['exitCode']);
    }

    #[Test]
    public function testRunCommandOutputContainsActualText(): void
    {
        $result = $this->runner->run('echo "zappzarapp_test_marker"');

        $this->assertStringContainsString('zappzarapp_test_marker', $result['output']);
    }

    #[Test]
    public function testRunExitCodeReflectsCommandStatus(): void
    {
        // exit 42 should yield exit code 42
        $result = $this->runner->run('exit 42');

        $this->assertSame(42, $result['exitCode']);
    }
}
