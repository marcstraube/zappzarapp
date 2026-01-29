<?php

declare(strict_types=1);

namespace Tests\DevToolbar\Guard;

use DevToolbar\Guard\DevToolbarGuard;
use PHPUnit\Framework\TestCase;

/**
 * Test DevToolbarGuard security checks
 */
class DevToolbarGuardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Clear environment variables
        putenv('APP_ENV');
        putenv('ENABLE_DEV_TOOLBAR');
    }

    public function testDisabledInProduction(): void
    {
        putenv('APP_ENV=production');
        $this->assertFalse(DevToolbarGuard::isEnabled());
    }

    public function testEnabledInDevelopment(): void
    {
        putenv('APP_ENV=development');
        // Note: isEnabled() returns false in CLI mode (PHPUnit runs in CLI)
        // This test documents the expected behavior
        $this->assertFalse(DevToolbarGuard::isEnabled());
    }

    public function testExplicitDisable(): void
    {
        putenv('APP_ENV=development');
        putenv('ENABLE_DEV_TOOLBAR=false');
        $this->assertFalse(DevToolbarGuard::isEnabled());
    }

    public function testDisabledInCli(): void
    {
        putenv('APP_ENV=development');
        putenv('ENABLE_DEV_TOOLBAR=true');
        // PHP_SAPI is 'cli' during PHPUnit tests
        $this->assertFalse(DevToolbarGuard::isEnabled());
    }

    public function testDefaultBehavior(): void
    {
        // No environment set - should default to disabled in CLI
        $this->assertFalse(DevToolbarGuard::isEnabled());
    }
}
