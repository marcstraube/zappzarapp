<?php

declare(strict_types=1);

namespace Tests\App\Unit\Infrastructure;

use App\Infrastructure\TlsConfig;
use PHPUnit\Framework\TestCase;

class TlsConfigTest extends TestCase
{
    protected function tearDown(): void
    {
        // Reset environment after each test
        putenv('ENV');
        putenv('TLS_VERIFY_INTERNAL');
        putenv('TLS_CA_PATH');
        unset($_ENV['ENV'], $_ENV['TLS_CA_PATH']);
    }

    public function testShouldVerifyInProduction(): void
    {
        $_ENV['ENV'] = 'production';
        putenv('TLS_VERIFY_INTERNAL');

        $this->assertTrue(TlsConfig::shouldVerify());
    }

    public function testShouldNotVerifyInDevelopment(): void
    {
        $_ENV['ENV'] = 'development';
        putenv('TLS_VERIFY_INTERNAL');

        $this->assertFalse(TlsConfig::shouldVerify());
    }

    public function testExplicitOverrideToDisable(): void
    {
        $_ENV['ENV'] = 'production';
        putenv('TLS_VERIFY_INTERNAL=false');

        $this->assertFalse(TlsConfig::shouldVerify());
    }

    public function testExplicitOverrideToEnable(): void
    {
        $_ENV['ENV'] = 'development';
        putenv('TLS_VERIFY_INTERNAL=true');

        $this->assertTrue(TlsConfig::shouldVerify());
    }

    public function testDefaultsToProductionWhenEnvNotSet(): void
    {
        // No ENV set - should default to production (secure by default)
        $this->assertTrue(TlsConfig::shouldVerify());
    }

    public function testGetCaPathReturnsDefault(): void
    {
        putenv('TLS_CA_PATH');
        unset($_ENV['TLS_CA_PATH']);

        $this->assertSame('/etc/ssl/certs/internal-ca.crt', TlsConfig::getCaPath());
    }

    public function testGetCaPathReturnsCustomPath(): void
    {
        $_ENV['TLS_CA_PATH'] = '/custom/ca.crt';

        $this->assertSame('/custom/ca.crt', TlsConfig::getCaPath());
    }

    public function testGetSslContextOptionsInDevelopment(): void
    {
        $_ENV['ENV'] = 'development';
        putenv('TLS_VERIFY_INTERNAL');

        $options = TlsConfig::getSslContextOptions();

        $this->assertFalse($options['verify_peer']);
        $this->assertFalse($options['verify_peer_name']);
        $this->assertTrue($options['allow_self_signed']);
        $this->assertArrayNotHasKey('cafile', $options);
    }

    public function testGetSslContextOptionsInProduction(): void
    {
        $_ENV['ENV'] = 'production';
        putenv('TLS_VERIFY_INTERNAL');

        $options = TlsConfig::getSslContextOptions();

        $this->assertTrue($options['verify_peer']);
        $this->assertTrue($options['verify_peer_name']);
        $this->assertFalse($options['allow_self_signed']);
        // cafile only included if file exists
    }

    public function testGetRedisStreamOptionsStructure(): void
    {
        $_ENV['ENV'] = 'development';
        putenv('TLS_VERIFY_INTERNAL');

        $options = TlsConfig::getRedisStreamOptions();

        $this->assertArrayHasKey('stream', $options);
        $this->assertArrayHasKey('verify_peer', $options['stream']);
        $this->assertArrayHasKey('verify_peer_name', $options['stream']);
        $this->assertArrayHasKey('allow_self_signed', $options['stream']);
    }
}
