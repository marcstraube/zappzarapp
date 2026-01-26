<?php

declare(strict_types=1);

namespace DevDashboard\Services;

/**
 * Command Runner
 *
 * Executes shell commands safely using proc_open
 */
class CommandRunner
{
    /**
     * Run a shell command using proc_open
     *
     * @return array{exitCode: int, output: string}
     */
    public function run(string $command): array
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        // @phpstan-ignore ekinoBannedCode.function (DevDashboard is development-only, needs command execution)
        $process = proc_open($command, $descriptors, $pipes);

        if (!is_resource($process)) {
            return ['exitCode' => -1, 'output' => 'Failed to start process'];
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        return [
            'exitCode' => $exitCode,
            'output'   => ($stdout ?: '') . ($stderr ?: ''),
        ];
    }
}
