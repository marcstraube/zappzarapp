<?php

declare(strict_types=1);

namespace DevDashboard\Services;

/**
 * System Information Service
 *
 * Provides system information like PHP version, extensions, git status, etc.
      *
     * @return array<string, mixed>
     */
class SystemInfoService
{
    /**
     * Get basic system information
          *
     * @return array<string, mixed>
     */
    public function getBasicInfo(): array
    {
        return [
            'php_version'     => PHP_VERSION,
            'php_sapi'        => PHP_SAPI,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'document_root'   => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
            'hostname'        => gethostname(),
            'os'              => PHP_OS,
        ];
    }

    /**
     * Get PHP version information
          *
     * @return array<string, mixed>
     */
    public function getPhpVersion(): array
    {
        return [
            'version'      => PHP_VERSION,
            'version_id'   => PHP_VERSION_ID,
            'major'        => PHP_MAJOR_VERSION,
            'minor'        => PHP_MINOR_VERSION,
            'release'      => PHP_RELEASE_VERSION,
            'extra'        => PHP_EXTRA_VERSION,
            'sapi'         => PHP_SAPI,
            'zend_version' => zend_version(),
        ];
    }

    /**
     * Get loaded PHP extensions
          *
     * @return array<int, array<string, mixed>>
     */
    public function getPhpExtensions(): array
    {
        $extensions = get_loaded_extensions();
        sort($extensions);

        return array_map(fn(string $ext): array => [
            'name'    => $ext,
            'version' => phpversion($ext) ?: 'N/A',
        ], $extensions);
    }

    /**
     * Get environment variables (filtered for security)
          *
     * @return array<string, mixed>
     */
    public function getEnvironmentVariables(): array
    {
        $env      = getenv();
        $filtered = [];

        // Filter sensitive data
        $sensitiveKeys = ['PASSWORD', 'SECRET', 'KEY', 'TOKEN', 'PRIVATE'];

        foreach ($env as $key => $value) {
            $isSensitive    = array_any($sensitiveKeys, fn($sensitive): bool => stripos($key, (string) $sensitive) !== false);
            $filtered[$key] = $isSensitive ? '********' : $value;
        }

        ksort($filtered);
        return $filtered;
    }

    /**
     * Get Git repository status
          *
     * @return array<string, mixed>
     */
    public function getGitStatus(): array
    {
        $gitDir = __DIR__ . '/../../../..';  // Project root

        if (!is_dir($gitDir . '/.git')) {
            return [
                'initialized' => false,
                'message'     => 'Not a git repository',
            ];
        }

        $branch = $this->executeCommand('git rev-parse --abbrev-ref HEAD', $gitDir);
        $commit = $this->executeCommand('git rev-parse --short HEAD', $gitDir);

        return [
            'initialized' => true,
            'branch'      => trim($branch),
            'commit'      => trim($commit),
        ];
    }

    /**
     * Get Node.js version (if available)
     */
    public function getNodeVersion(): ?string
    {
        $version = $this->executeCommand('docker compose exec -T node node --version');
        return $version !== '' && $version !== '0' ? trim($version) : null;
    }

    /**
     * Get Composer version
     */
    public function getComposerVersion(): ?string
    {
        $version = $this->executeCommand('docker compose exec -T php composer --version');
        return $version !== '' && $version !== '0' ? trim($version) : null;
    }

    /**
     * Execute a shell command and return output
     */
    private function executeCommand(string $command, ?string $cwd = null): string
    {
        $descriptorspec = [
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w'],  // stderr
        ];

        $process = proc_open($command, $descriptorspec, $pipes, $cwd);

        if (is_resource($process)) {
            $output = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);

            return $output ?: '';
        }

        return '';
    }
}
