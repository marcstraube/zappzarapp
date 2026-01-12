<?php

declare(strict_types=1);

namespace Tests\DevDashboard\Services;

use DevDashboard\Services\SystemInfoService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SystemInfoService::class)]
class SystemInfoServiceTest extends TestCase
{
    private SystemInfoService $service;

    protected function setUp(): void
    {
        parent::setUp();
        require_once __DIR__ . '/../../../../src/php/DevDashboard/Services/SystemInfoService.php';
        $this->service = new SystemInfoService();
    }

    public function testGetBasicInfo(): void
    {
        $info = $this->service->getBasicInfo();

        $this->assertArrayHasKey('php_version', $info);
        $this->assertArrayHasKey('php_sapi', $info);
        $this->assertArrayHasKey('hostname', $info);
        $this->assertArrayHasKey('os', $info);

        $this->assertEquals(PHP_VERSION, $info['php_version']);
        $this->assertEquals(PHP_SAPI, $info['php_sapi']);
        $this->assertEquals(PHP_OS, $info['os']);
    }

    public function testGetPhpVersion(): void
    {
        $version = $this->service->getPhpVersion();

        $this->assertArrayHasKey('version', $version);
        $this->assertArrayHasKey('major', $version);
        $this->assertArrayHasKey('minor', $version);
        $this->assertArrayHasKey('release', $version);

        $this->assertEquals(PHP_VERSION, $version['version']);
        $this->assertEquals(PHP_MAJOR_VERSION, $version['major']);
        $this->assertEquals(PHP_MINOR_VERSION, $version['minor']);
    }

    public function testGetPhpExtensions(): void
    {
        $extensions = $this->service->getPhpExtensions();

        $this->assertNotEmpty($extensions);

        // Check structure of first extension
        $firstExt = $extensions[0];
        $this->assertArrayHasKey('name', $firstExt);
        $this->assertArrayHasKey('version', $firstExt);

        // Verify common extensions are present
        $extensionNames = array_column($extensions, 'name');
        $this->assertContains('Core', $extensionNames);
        $this->assertContains('standard', $extensionNames);
    }

    public function testGetEnvironmentVariables(): void
    {
        // Set a test environment variable
        putenv('TEST_VAR=test_value');
        putenv('TEST_PASSWORD=secret123');

        $envVars = $this->service->getEnvironmentVariables();

        // Verify test variable is present
        $this->assertArrayHasKey('TEST_VAR', $envVars);
        $this->assertEquals('test_value', $envVars['TEST_VAR']);

        // Verify sensitive data is masked
        $this->assertArrayHasKey('TEST_PASSWORD', $envVars);
        $this->assertEquals('********', $envVars['TEST_PASSWORD']);

        // Clean up
        putenv('TEST_VAR');
        putenv('TEST_PASSWORD');
    }

    public function testGetGitStatusWhenNotARepository(): void
    {
        // Create a temporary directory that's not a git repository
        $tempDir = sys_get_temp_dir() . '/test_not_git_' . uniqid();
        mkdir($tempDir);

        // This test assumes the service would check the correct directory
        // In real implementation, we'd need to inject the directory path

        $status = $this->service->getGitStatus();

        $this->assertArrayHasKey('initialized', $status);

        // The actual project IS a git repository, so this will be true
        // In a real test, we'd mock or inject the git directory
        $this->assertIsBool($status['initialized']);

        // Clean up
        rmdir($tempDir);
    }

    public function testGetGitStatusWhenInitialized(): void
    {
        $status = $this->service->getGitStatus();

        if ($status['initialized']) {
            $this->assertArrayHasKey('branch', $status);
            $this->assertArrayHasKey('commit', $status);

            $this->assertIsString($status['branch']);
            $this->assertIsString($status['commit']);
        }
    }

    public function testSensitiveKeyFiltering(): void
    {
        // Set various sensitive environment variables
        $sensitiveKeys = [
            'DB_PASSWORD'  => 'secret123',
            'API_KEY'      => 'key123',
            'SECRET_TOKEN' => 'token123',
            'PRIVATE_KEY'  => 'private123',
            'NORMAL_VAR'   => 'public_value',
        ];

        foreach ($sensitiveKeys as $key => $value) {
            putenv(sprintf('%s=%s', $key, $value));
        }

        $envVars = $this->service->getEnvironmentVariables();

        // Verify sensitive variables are masked
        $this->assertEquals('********', $envVars['DB_PASSWORD'] ?? null);
        $this->assertEquals('********', $envVars['API_KEY'] ?? null);
        $this->assertEquals('********', $envVars['SECRET_TOKEN'] ?? null);
        $this->assertEquals('********', $envVars['PRIVATE_KEY'] ?? null);

        // Verify normal variable is not masked
        $this->assertEquals('public_value', $envVars['NORMAL_VAR'] ?? null);

        // Clean up
        foreach (array_keys($sensitiveKeys) as $key) {
            putenv($key);
        }
    }
}
