<?php

declare(strict_types=1);

namespace Tests\App\Unit\Infrastructure\Config;

use App\Infrastructure\Config\CredentialLoader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Zappzarapp\Security\Secrets\Exception\SecretLoadException;
use Zappzarapp\Security\Secrets\FileSecretSource;
use Zappzarapp\Security\Secrets\SecretLoader;

/**
 * Tests for CredentialLoader (secret -> env -> explicit default chain)
 *
 * Uses a per-test temporary secrets directory instead of /run/secrets so the
 * tests control exactly which secrets exist.
 */
#[CoversClass(CredentialLoader::class)]
final class CredentialLoaderTest extends TestCase
{
    private const string ENV_NAME = 'CREDENTIAL_LOADER_TEST_VALUE';

    private const string ENV_NAME_SECONDARY = 'CREDENTIAL_LOADER_TEST_FALLBACK';

    private string $secretsDir;

    protected function setUp(): void
    {
        $this->secretsDir = sys_get_temp_dir() . '/credential-loader-test-' . bin2hex(random_bytes(4));
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
        putenv(self::ENV_NAME);
        putenv(self::ENV_NAME_SECONDARY);
        unset($_ENV[self::ENV_NAME], $_ENV[self::ENV_NAME_SECONDARY]);
    }

    private function loader(): CredentialLoader
    {
        return new CredentialLoader(new SecretLoader(new FileSecretSource($this->secretsDir)));
    }

    private function writeSecret(string $name, string $value): void
    {
        file_put_contents($this->secretsDir . '/' . $name, $value);
    }

    #[Test]
    public function testTryLoadReadsSecretFile(): void
    {
        $this->writeSecret('service_password.txt', "from-secret\n");

        $this->assertSame('from-secret', $this->loader()->tryLoad('service_password'));
    }

    #[Test]
    public function testTryLoadPrefersSecretOverEnvironment(): void
    {
        $this->writeSecret('service_password', 'from-secret');
        $_ENV[self::ENV_NAME] = 'from-env';

        $this->assertSame('from-secret', $this->loader()->tryLoad('service_password', self::ENV_NAME));
    }

    #[Test]
    public function testTryLoadFallsBackToFirstNonEmptyEnvVariable(): void
    {
        $_ENV[self::ENV_NAME]           = '';
        $_ENV[self::ENV_NAME_SECONDARY] = 'from-fallback-env';

        $value = $this->loader()->tryLoad('service_password', self::ENV_NAME, self::ENV_NAME_SECONDARY);

        $this->assertSame('from-fallback-env', $value);
    }

    #[Test]
    public function testTryLoadReturnsNullWithoutAnySource(): void
    {
        $this->assertNull($this->loader()->tryLoad('service_password', self::ENV_NAME));
    }

    #[Test]
    public function testLoadWithInsecureDefaultReturnsSecretWhenPresent(): void
    {
        $this->writeSecret('service_password.txt', 'from-secret');

        $value = $this->loader()->loadWithInsecureDefault('service_password', [self::ENV_NAME], 'dev-default');

        $this->assertSame('from-secret', $value);
    }

    #[Test]
    public function testLoadWithInsecureDefaultFallsBackToDefault(): void
    {
        $value = $this->loader()->loadWithInsecureDefault('service_password', [self::ENV_NAME], 'dev-default');

        $this->assertSame('dev-default', $value);
    }

    #[Test]
    public function testEmptySecretFileThrowsInsteadOfFallingThrough(): void
    {
        $this->writeSecret('service_password.txt', '');
        $_ENV[self::ENV_NAME] = 'from-env';

        $this->expectException(SecretLoadException::class);

        $this->loader()->tryLoad('service_password', self::ENV_NAME);
    }

    #[Test]
    public function testDockerFactoryCreatesLoader(): void
    {
        $this->assertInstanceOf(CredentialLoader::class, CredentialLoader::docker());
    }
}
