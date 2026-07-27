<?php

declare(strict_types=1);

namespace Tests\App\Unit\Infrastructure\Mercure;

use App\Infrastructure\Config\CredentialLoader;
use App\Infrastructure\Mercure\MercureConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Zappzarapp\Security\Secrets\FileSecretSource;
use Zappzarapp\Security\Secrets\SecretLoader;

/**
 * Tests for MercureConfig (URL + JWT key resolution)
 *
 * Uses a per-test temporary secrets directory so the tests control exactly
 * which secrets exist, independent of /run/secrets.
 */
#[CoversClass(MercureConfig::class)]
#[UsesClass(CredentialLoader::class)]
final class MercureConfigTest extends TestCase
{
    private string $secretsDir;

    protected function setUp(): void
    {
        $this->secretsDir = sys_get_temp_dir() . '/mercure-config-test-' . bin2hex(random_bytes(4));
        mkdir($this->secretsDir, 0o700, true);
        $this->clearEnvVars();
    }

    protected function tearDown(): void
    {
        $files = glob($this->secretsDir . '/*');
        foreach ($files === false ? [] : $files as $file) {
            unlink($file);
        }
        rmdir($this->secretsDir);
        $this->clearEnvVars();
    }

    private function clearEnvVars(): void
    {
        foreach (['MERCURE_URL', 'MERCURE_PUBLISH_URL', 'MERCURE_JWT_SECRET', 'MERCURE_PUBLISHER_JWT_KEY'] as $name) {
            putenv($name);
            unset($_ENV[$name]);
        }
    }

    private function loader(): CredentialLoader
    {
        return new CredentialLoader(new SecretLoader(new FileSecretSource($this->secretsDir)));
    }

    #[Test]
    public function itDefaultsToTheInternalHubUrl(): void
    {
        $config = new MercureConfig(null, $this->loader());

        self::assertSame('https://mercure/.well-known/mercure', $config->url);
        self::assertTrue($config->useTls);
    }

    #[Test]
    public function itPrefersTheConstructorUrl(): void
    {
        putenv('MERCURE_URL=https://env-hub/.well-known/mercure');

        $config = new MercureConfig('http://custom-hub/.well-known/mercure', $this->loader());

        self::assertSame('http://custom-hub/.well-known/mercure', $config->url);
        self::assertFalse($config->useTls);
    }

    #[Test]
    public function itReadsTheUrlFromMercureUrlEnv(): void
    {
        putenv('MERCURE_URL=https://env-hub/.well-known/mercure');

        $config = new MercureConfig(null, $this->loader());

        self::assertSame('https://env-hub/.well-known/mercure', $config->url);
    }

    #[Test]
    public function itFallsBackToMercurePublishUrlEnv(): void
    {
        putenv('MERCURE_PUBLISH_URL=https://publish-hub/.well-known/mercure');

        $config = new MercureConfig(null, $this->loader());

        self::assertSame('https://publish-hub/.well-known/mercure', $config->url);
    }

    #[Test]
    public function itLoadsTheJwtKeyFromTheDockerSecret(): void
    {
        file_put_contents($this->secretsDir . '/mercure_jwt_secret.txt', 'secret-from-file');

        $config = new MercureConfig(null, $this->loader());

        self::assertSame('secret-from-file', $config->jwtKey);
    }

    #[Test]
    public function itFallsBackToTheJwtKeyEnvVariable(): void
    {
        putenv('MERCURE_JWT_SECRET=secret-from-env');

        $config = new MercureConfig(null, $this->loader());

        self::assertSame('secret-from-env', $config->jwtKey);
    }

    #[Test]
    public function itLeavesTheJwtKeyEmptyWhenNothingIsConfigured(): void
    {
        $config = new MercureConfig(null, $this->loader());

        self::assertSame('', $config->jwtKey);
    }
}
