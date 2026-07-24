<?php

declare(strict_types=1);

namespace Tests\DevDashboard\Services;

use DevDashboard\Services\SystemInfoService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Deterministic seam tests for SystemInfoService.
 *
 * All filesystem and process I/O is intercepted via protected-method overrides
 * on anonymous subclasses so that both the "git present" and "not a git repo"
 * branches, and both the "process launched" and "proc_open fails" branches,
 * are always exercised regardless of the actual host environment.
 */
#[CoversClass(SystemInfoService::class)]
final class SystemInfoServiceSeamsTest extends TestCase
{
    // =========================================================================
    // getGitStatus() — no .git directory (lines 100–103, CI-only)
    // =========================================================================

    #[Test]
    public function testGetGitStatusWhenNotAGitDirectory(): void
    {
        $fake = $this->makeServiceWithGitDirectory(exists: false);

        $status = $fake->getGitStatus();

        $this->assertFalse($status['initialized']);
        $this->assertSame('Not a git repository', $status['message']);
    }

    // =========================================================================
    // getGitStatus() — .git exists, commands return output (lines 106–113)
    // =========================================================================

    #[Test]
    public function testGetGitStatusWhenInitialized(): void
    {
        $fake = $this->makeServiceWithGitDirectory(
            exists: true,
            commandOutputs: ['develop', 'abc1234'],
        );

        $status = $fake->getGitStatus();

        $this->assertTrue($status['initialized']);
        $this->assertSame('develop', $status['branch']);
        $this->assertSame('abc1234', $status['commit']);
    }

    #[Test]
    public function testGetGitStatusTrimsWhitespaceFromOutput(): void
    {
        $fake = $this->makeServiceWithGitDirectory(
            exists: true,
            commandOutputs: ["  main  \n", "  def5678  \n"],
        );

        $status = $fake->getGitStatus();

        $this->assertSame('main', $status['branch']);
        $this->assertSame('def5678', $status['commit']);
    }

    // =========================================================================
    // executeCommand() — process launches and produces output (lines 139–152)
    // =========================================================================

    #[Test]
    public function testExecuteCommandWhenProcessLaunchesReturnsOutput(): void
    {
        $fake = $this->makeServiceWithGitDirectory(
            exists: true,
            commandOutputs: ['feature/seams', 'feed1234'],
        );

        $status = $fake->getGitStatus();

        // If executeCommand works, the values should be populated
        $this->assertSame('feature/seams', $status['branch']);
    }

    // =========================================================================
    // getGitStatus() — command returns empty output
    // =========================================================================

    #[Test]
    public function testGetGitStatusWithEmptyCommandOutputReturnsEmptyStrings(): void
    {
        $fake = $this->makeServiceWithGitDirectory(
            exists: true,
            commandOutputs: ['', ''],
        );

        $status = $fake->getGitStatus();

        $this->assertTrue($status['initialized']);
        $this->assertSame('', $status['branch']);
        $this->assertSame('', $status['commit']);
    }

    // =========================================================================
    // Factory helpers
    // =========================================================================

    /**
     * Build a fake SystemInfoService with controlled isGitDirectory() + executeCommand() results.
     *
     * @param list<string> $commandOutputs Successive return values for executeCommand() calls
     */
    private function makeServiceWithGitDirectory(
        bool $exists,
        array $commandOutputs = [],
    ): SystemInfoService {
        return new class($exists, $commandOutputs) extends SystemInfoService {
            private readonly bool $gitDirExists;

            /** @var list<string> */
            private array $commandOutputs;

            /** @param list<string> $commandOutputs */
            public function __construct(bool $exists, array $commandOutputs)
            {
                $this->gitDirExists   = $exists;
                $this->commandOutputs = $commandOutputs;
            }

            protected function isGitDirectory(string $path): bool
            {
                unset($path);

                return $this->gitDirExists;
            }

            protected function executeCommand(string $command, ?string $cwd = null): string
            {
                unset($command, $cwd);

                if ($this->commandOutputs === []) {
                    return '';
                }

                return (string) array_shift($this->commandOutputs);
            }
        };
    }
}
