<?php

declare(strict_types=1);

namespace Tests\App\Unit\Infrastructure\Mail;

use App\Infrastructure\Config\CredentialLoader;
use App\Infrastructure\Mail\MailConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Zappzarapp\Security\Secrets\FileSecretSource;
use Zappzarapp\Security\Secrets\SecretLoader;

/**
 * Tests for MailConfig (DSN + sender resolution)
 *
 * Uses a per-test temporary secrets directory so the tests control exactly
 * which secrets exist, independent of /run/secrets.
 */
#[CoversClass(MailConfig::class)]
#[UsesClass(CredentialLoader::class)]
final class MailConfigTest extends TestCase
{
    private string $secretsDir;

    protected function setUp(): void
    {
        $this->secretsDir = sys_get_temp_dir() . '/mail-config-test-' . bin2hex(random_bytes(4));
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
        foreach ([
            'MAIL_DSN',
            'MAILER_DSN',
            'MAIL_HOST',
            'MAIL_PORT',
            'MAIL_USER',
            'MAIL_PASSWORD',
            'MAIL_FROM',
        ] as $name) {
            putenv($name);
            unset($_ENV[$name]);
        }
    }

    private function loader(): CredentialLoader
    {
        return new CredentialLoader(new SecretLoader(new FileSecretSource($this->secretsDir)));
    }

    #[Test]
    public function itDefaultsToTheMailpitCatchAll(): void
    {
        $config = new MailConfig(null, $this->loader());

        self::assertSame('smtp://mailpit:1025', $config->dsn);
        self::assertSame('no-reply@localhost', $config->defaultFrom);
    }

    #[Test]
    public function itPrefersTheConstructorDsn(): void
    {
        putenv('MAIL_DSN=smtp://env-host:2525');

        $config = new MailConfig('smtp://given-host:1025', $this->loader());

        self::assertSame('smtp://given-host:1025', $config->dsn);
    }

    #[Test]
    public function itReadsTheDsnFromMailDsnEnv(): void
    {
        putenv('MAIL_DSN=smtp://user:pass@smtp.example.com:587');

        $config = new MailConfig(null, $this->loader());

        self::assertSame('smtp://user:pass@smtp.example.com:587', $config->dsn);
    }

    #[Test]
    public function itFallsBackToMailerDsnEnv(): void
    {
        putenv('MAILER_DSN=smtp://fallback:1025');

        $config = new MailConfig(null, $this->loader());

        self::assertSame('smtp://fallback:1025', $config->dsn);
    }

    #[Test]
    public function itBuildsTheDsnFromDiscretePartsWithCredentials(): void
    {
        putenv('MAIL_HOST=smtp.example.com');
        putenv('MAIL_PORT=587');
        putenv('MAIL_USER=me@example.com');
        file_put_contents($this->secretsDir . '/mail_password.txt', 's3cr3t');

        $config = new MailConfig(null, $this->loader());

        self::assertSame('smtp://me%40example.com:s3cr3t@smtp.example.com:587', $config->dsn);
    }

    #[Test]
    public function itUsesTheConfiguredSenderAddress(): void
    {
        putenv('MAIL_FROM=hello@example.com');

        $config = new MailConfig(null, $this->loader());

        self::assertSame('hello@example.com', $config->defaultFrom);
    }
}
