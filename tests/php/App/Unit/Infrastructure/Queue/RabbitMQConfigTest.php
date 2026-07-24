<?php

declare(strict_types=1);

namespace Tests\App\Unit\Infrastructure\Queue;

use App\Infrastructure\Queue\RabbitMQConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests for RabbitMQConfig (12-Factor App compliant configuration)
 *
 * NOTE: The PHP container mounts real Docker secrets at /run/secrets/.
 * Secrets take priority over env vars in the credential resolution chain.
 * Tests that verify credential loading from env vars must use the URL-with-
 * credentials path, which skips the secret lookup entirely when the URL
 * already contains a username.
 *
 * @SuppressWarnings("PHPMD.TooManyMethods")
 * @SuppressWarnings("PHPMD.TooManyPublicMethods")
 */
#[CoversClass(RabbitMQConfig::class)]
final class RabbitMQConfigTest extends TestCase
{
    protected function setUp(): void
    {
        $this->clearEnvVars();
    }

    protected function tearDown(): void
    {
        $this->clearEnvVars();
    }

    private function clearEnvVars(): void
    {
        putenv('RABBITMQ_URL');
        putenv('RABBITMQ_HOST');
        putenv('RABBITMQ_PORT');
        putenv('RABBITMQ_USER');
        putenv('RABBITMQ_PASSWORD');
        putenv('RABBITMQ_VHOST');
        unset(
            $_ENV['RABBITMQ_URL'],
            $_ENV['RABBITMQ_HOST'],
            $_ENV['RABBITMQ_PORT'],
            $_ENV['RABBITMQ_USER'],
            $_ENV['RABBITMQ_PASSWORD'],
            $_ENV['RABBITMQ_VHOST'],
        );
    }

    // =========================================================================
    // Default values (secrets are present in the test container, so
    // credentials come from /run/secrets rather than 'guest' defaults)
    // =========================================================================

    #[Test]
    public function testDefaultHostPortVhostAndTls(): void
    {
        $config = new RabbitMQConfig();

        // Non-credential defaults are not overridden by secrets
        $this->assertEquals('rabbitmq', $config->host);
        $this->assertEquals(5672, $config->port);
        $this->assertEquals('/', $config->vhost);
        $this->assertFalse($config->useTls);
    }

    #[Test]
    public function testDefaultCredentialsComeFromDockerSecretsOrFallback(): void
    {
        $config = new RabbitMQConfig();

        // Credentials are non-empty (from Docker secret or default)
        $this->assertNotEmpty($config->user);
        $this->assertNotEmpty($config->password);
    }

    // =========================================================================
    // Individual environment variables (host/port/vhost — not overridden by secrets)
    // =========================================================================

    #[RunInSeparateProcess]
    #[Test]
    public function testHostFromEnv(): void
    {
        putenv('RABBITMQ_HOST=my-rabbit');

        $config = new RabbitMQConfig();

        $this->assertEquals('my-rabbit', $config->host);
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testPortFromEnv(): void
    {
        putenv('RABBITMQ_PORT=5673');

        $config = new RabbitMQConfig();

        $this->assertEquals(5673, $config->port);
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testVhostFromEnv(): void
    {
        putenv('RABBITMQ_VHOST=myvhost');

        $config = new RabbitMQConfig();

        $this->assertEquals('myvhost', $config->vhost);
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testNonCredentialEnvVarsApplied(): void
    {
        putenv('RABBITMQ_HOST=custom-rabbit');
        putenv('RABBITMQ_PORT=5673');
        putenv('RABBITMQ_VHOST=production');

        $config = new RabbitMQConfig();

        $this->assertEquals('custom-rabbit', $config->host);
        $this->assertEquals(5673, $config->port);
        $this->assertEquals('production', $config->vhost);
        $this->assertFalse($config->useTls);
    }

    // =========================================================================
    // AMQP URL parsing — credentials in URL bypass secret/env loading
    // =========================================================================

    #[RunInSeparateProcess]
    #[Test]
    public function testAmqpUrlFullyParsed(): void
    {
        putenv('RABBITMQ_URL=amqp://myuser:mypass@myhost:5673/myvhost');

        $config = new RabbitMQConfig();

        $this->assertEquals('myhost', $config->host);
        $this->assertEquals(5673, $config->port);
        $this->assertEquals('myuser', $config->user);
        $this->assertEquals('mypass', $config->password);
        $this->assertEquals('myvhost', $config->vhost);
        $this->assertFalse($config->useTls);
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testAmqpsUrlEnablesTls(): void
    {
        putenv('RABBITMQ_URL=amqps://user:pass@host:5671/');

        $config = new RabbitMQConfig();

        $this->assertTrue($config->useTls);
        $this->assertEquals('host', $config->host);
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testAmqpUrlDefaultPortWithoutTls(): void
    {
        putenv('RABBITMQ_URL=amqp://user:pass@host/vhost');

        $config = new RabbitMQConfig();

        $this->assertEquals(5672, $config->port);
        $this->assertFalse($config->useTls);
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testAmqpsUrlDefaultPort(): void
    {
        putenv('RABBITMQ_URL=amqps://user:pass@host/vhost');

        $config = new RabbitMQConfig();

        $this->assertEquals(5671, $config->port);
        $this->assertTrue($config->useTls);
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testAmqpUrlWithEncodedPassword(): void
    {
        // Password: p@ss:word (URL-encoded: p%40ss%3Aword)
        putenv('RABBITMQ_URL=amqp://user:p%40ss%3Aword@host:5672/');

        $config = new RabbitMQConfig();

        $this->assertEquals('p@ss:word', $config->password);
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testAmqpUrlWithEncodedVhost(): void
    {
        // vhost "my/vhost" encoded as "my%2Fvhost"
        putenv('RABBITMQ_URL=amqp://user:pass@host:5672/my%2Fvhost');

        $config = new RabbitMQConfig();

        $this->assertEquals('my/vhost', $config->vhost);
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testAmqpUrlWithRootVhost(): void
    {
        putenv('RABBITMQ_URL=amqp://user:pass@host:5672/');

        $config = new RabbitMQConfig();

        $this->assertEquals('/', $config->vhost);
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testAmqpUrlWithNoPath(): void
    {
        putenv('RABBITMQ_URL=amqp://user:pass@host:5672');

        $config = new RabbitMQConfig();

        $this->assertEquals('/', $config->vhost);
    }

    #[Test]
    public function testConstructorUrlTakesPrecedenceOverEnvUrl(): void
    {
        $config = new RabbitMQConfig('amqp://ctoruser:ctorpass@ctor-host:5672/');

        $this->assertEquals('ctor-host', $config->host);
        $this->assertEquals('ctoruser', $config->user);
        $this->assertEquals('ctorpass', $config->password);
    }

    #[Test]
    public function testConstructorUrlParsedDirectly(): void
    {
        $config = new RabbitMQConfig('amqp://u:p@myhost:5672/myvhost');

        $this->assertEquals('myhost', $config->host);
        $this->assertEquals('u', $config->user);
        $this->assertEquals('p', $config->password);
        $this->assertEquals('myvhost', $config->vhost);
    }

    // =========================================================================
    // Credential resolution: URL without credentials falls back to secrets/env
    // =========================================================================

    #[RunInSeparateProcess]
    #[Test]
    public function testUrlWithoutCredentialsLoadsCredentialsFromSecretsOrEnv(): void
    {
        // URL has no credentials → secrets (or env) are loaded
        putenv('RABBITMQ_URL=amqp://myhost:5672/myvhost');
        putenv('RABBITMQ_USER=env-user');
        putenv('RABBITMQ_PASSWORD=env-pass');

        $config = new RabbitMQConfig();

        // Host/vhost come from the URL
        $this->assertEquals('myhost', $config->host);
        $this->assertEquals('myvhost', $config->vhost);
        // Credentials come from secrets (in test container) or env fallback
        $this->assertNotEmpty($config->user);
        $this->assertNotEmpty($config->password);
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testUrlCredentialsTakePrecedenceOverSecretsAndEnv(): void
    {
        // URL has credentials → skips secrets and env entirely
        putenv('RABBITMQ_URL=amqp://url-user:url-pass@myhost:5672/');
        putenv('RABBITMQ_USER=env-user');
        putenv('RABBITMQ_PASSWORD=env-pass');

        $config = new RabbitMQConfig();

        $this->assertEquals('url-user', $config->user);
        $this->assertEquals('url-pass', $config->password);
    }

    // =========================================================================
    // Constructor URL takes precedence over RABBITMQ_URL env var
    // =========================================================================

    #[RunInSeparateProcess]
    #[Test]
    public function testConstructorUrlBeatsEnvUrl(): void
    {
        putenv('RABBITMQ_URL=amqp://envuser:envpass@env-host:5672/');

        $config = new RabbitMQConfig('amqp://ctoruser:ctorpass@ctor-host:5672/');

        $this->assertEquals('ctor-host', $config->host);
        $this->assertEquals('ctoruser', $config->user);
    }

    // =========================================================================
    // $_ENV superglobal support (host/port/vhost not overridden by secrets)
    // =========================================================================

    #[RunInSeparateProcess]
    #[Test]
    public function testDollarEnvSuperGlobalForUrl(): void
    {
        $_ENV['RABBITMQ_URL'] = 'amqp://sg-user:sg-pass@sg-host:5672/';

        $config = new RabbitMQConfig();

        $this->assertEquals('sg-host', $config->host);
        $this->assertEquals('sg-user', $config->user);
        $this->assertEquals('sg-pass', $config->password);
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testDollarEnvSuperGlobalForHost(): void
    {
        $_ENV['RABBITMQ_HOST'] = 'sg-rabbit';

        $config = new RabbitMQConfig();

        $this->assertEquals('sg-rabbit', $config->host);
    }

    // =========================================================================
    // useTls data-provider test
    // =========================================================================

    #[DataProvider('amqpSchemeProvider')]
    #[Test]
    public function testTlsDerivedFromAmqpScheme(string $url, bool $expectedTls): void
    {
        $config = new RabbitMQConfig($url);

        $this->assertEquals($expectedTls, $config->useTls);
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function amqpSchemeProvider(): array
    {
        return [
            'amqp (no tls)' => ['amqp://user:pass@host:5672/', false],
            'amqps (tls)'   => ['amqps://user:pass@host:5671/', true],
        ];
    }

    // =========================================================================
    // RABBITMQ_URL takes precedence over individual env vars
    // =========================================================================

    #[RunInSeparateProcess]
    #[Test]
    public function testRabbitMQUrlHostTakesPrecedenceOverIndividualVars(): void
    {
        putenv('RABBITMQ_URL=amqp://url-user:url-pass@url-host:5672/url-vhost');
        putenv('RABBITMQ_HOST=env-host');
        putenv('RABBITMQ_PORT=9999');
        putenv('RABBITMQ_VHOST=env-vhost');

        $config = new RabbitMQConfig();

        $this->assertEquals('url-host', $config->host);
        $this->assertEquals(5672, $config->port);
        $this->assertEquals('url-vhost', $config->vhost);
    }

    // =========================================================================
    // Encoded username in URL
    // =========================================================================

    #[Test]
    public function testAmqpUrlWithEncodedUsername(): void
    {
        // Username: user@domain (URL-encoded: user%40domain)
        $config = new RabbitMQConfig('amqp://user%40domain:pass@host:5672/');

        $this->assertEquals('user@domain', $config->user);
    }

    // =========================================================================
    // Non-standard port in URL
    // =========================================================================

    #[Test]
    public function testAmqpUrlExplicitPort(): void
    {
        $config = new RabbitMQConfig('amqp://user:pass@host:12345/');

        $this->assertEquals(12345, $config->port);
    }
}
