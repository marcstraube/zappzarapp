<?php

declare(strict_types=1);

namespace Tests\App\Unit\Infrastructure;

use App\Infrastructure\TlsConfig;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TlsConfigTest extends TestCase
{
    protected function tearDown(): void
    {
        // Reset environment after each test
        putenv('ZAPPZARAPP_ENV');
        putenv('TLS_VERIFY_INTERNAL');
        putenv('TLS_CA_PATH');
        unset($_ENV['ZAPPZARAPP_ENV'], $_ENV['TLS_CA_PATH']);
    }

    #[Test]
    public function testShouldVerifyInProduction(): void
    {
        $_ENV['ZAPPZARAPP_ENV'] = 'production';
        putenv('TLS_VERIFY_INTERNAL');

        $this->assertTrue(TlsConfig::shouldVerify());
    }

    #[Test]
    public function testShouldNotVerifyInDevelopment(): void
    {
        $_ENV['ZAPPZARAPP_ENV'] = 'development';
        putenv('TLS_VERIFY_INTERNAL');

        $this->assertFalse(TlsConfig::shouldVerify());
    }

    #[Test]
    public function testExplicitOverrideToDisable(): void
    {
        $_ENV['ZAPPZARAPP_ENV'] = 'production';
        putenv('TLS_VERIFY_INTERNAL=false');

        $this->assertFalse(TlsConfig::shouldVerify());
    }

    #[Test]
    public function testExplicitOverrideToEnable(): void
    {
        $_ENV['ZAPPZARAPP_ENV'] = 'development';
        putenv('TLS_VERIFY_INTERNAL=true');

        $this->assertTrue(TlsConfig::shouldVerify());
    }

    #[Test]
    public function testDefaultsToProductionWhenEnvNotSet(): void
    {
        // No ZAPPZARAPP_ENV set - should default to production (secure by default)
        $this->assertTrue(TlsConfig::shouldVerify());
    }

    #[Test]
    public function testGetCaPathReturnsDefault(): void
    {
        putenv('TLS_CA_PATH');
        unset($_ENV['TLS_CA_PATH']);

        $this->assertSame('/etc/ssl/certs/internal-ca.crt', TlsConfig::getCaPath());
    }

    #[Test]
    public function testGetCaPathReturnsCustomPath(): void
    {
        $_ENV['TLS_CA_PATH'] = '/custom/ca.crt';

        $this->assertSame('/custom/ca.crt', TlsConfig::getCaPath());
    }

    #[Test]
    public function testGetSslContextOptionsInDevelopment(): void
    {
        $_ENV['ZAPPZARAPP_ENV'] = 'development';
        putenv('TLS_VERIFY_INTERNAL');

        $options = TlsConfig::getSslContextOptions();

        $this->assertFalse($options['verify_peer']);
        $this->assertFalse($options['verify_peer_name']);
        $this->assertTrue($options['allow_self_signed']);
        $this->assertArrayNotHasKey('cafile', $options);
    }

    #[Test]
    public function testGetSslContextOptionsInProduction(): void
    {
        $_ENV['ZAPPZARAPP_ENV'] = 'production';
        putenv('TLS_VERIFY_INTERNAL');

        $options = TlsConfig::getSslContextOptions();

        $this->assertTrue($options['verify_peer']);
        $this->assertTrue($options['verify_peer_name']);
        $this->assertFalse($options['allow_self_signed']);
        // cafile only included if file exists
    }

    #[Test]
    public function testGetRedisStreamOptionsStructure(): void
    {
        $_ENV['ZAPPZARAPP_ENV'] = 'development';
        putenv('TLS_VERIFY_INTERNAL');

        $options = TlsConfig::getRedisStreamOptions();

        $this->assertArrayHasKey('stream', $options);
        $this->assertArrayHasKey('verify_peer', $options['stream']);
        $this->assertArrayHasKey('verify_peer_name', $options['stream']);
        $this->assertArrayHasKey('allow_self_signed', $options['stream']);
    }
}
