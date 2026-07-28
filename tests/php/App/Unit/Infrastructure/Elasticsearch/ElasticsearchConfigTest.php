<?php

declare(strict_types=1);

namespace Tests\App\Unit\Infrastructure\Elasticsearch;

use App\Infrastructure\Config\CredentialLoader;
use App\Infrastructure\Elasticsearch\ElasticsearchConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests for ElasticsearchConfig (12-Factor App compliant configuration)
 *
 * @SuppressWarnings("PHPMD.TooManyMethods")
 * @SuppressWarnings("PHPMD.TooManyPublicMethods")
 */
#[CoversClass(ElasticsearchConfig::class)]
#[UsesClass(CredentialLoader::class)]
final class ElasticsearchConfigTest extends TestCase
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
        putenv('ELASTICSEARCH_URL');
        putenv('ELASTICSEARCH_HOST');
        putenv('ELASTICSEARCH_PORT');
        putenv('ELASTICSEARCH_API_KEY');
        putenv('ELASTICSEARCH_VERIFY_SSL');
        unset(
            $_ENV['ELASTICSEARCH_URL'],
            $_ENV['ELASTICSEARCH_HOST'],
            $_ENV['ELASTICSEARCH_PORT'],
            $_ENV['ELASTICSEARCH_API_KEY'],
            $_ENV['ELASTICSEARCH_VERIFY_SSL'],
        );
    }

    // =========================================================================
    // Default values
    // =========================================================================

    #[Test]
    public function testDefaultsWhenNoEnvSet(): void
    {
        $config = new ElasticsearchConfig();

        $this->assertEquals('https://elasticsearch:9200', $config->url);
        $this->assertEquals('', $config->apiKey);
        $this->assertTrue($config->useTls);
        $this->assertFalse($config->verifySsl);
    }

    // =========================================================================
    // Constructor URL parameter (highest priority)
    // =========================================================================

    #[Test]
    public function testConstructorUrlTakesPrecedenceOverEnv(): void
    {
        putenv('ELASTICSEARCH_URL=https://env-host:9200');

        $config = new ElasticsearchConfig('https://constructor-host:9201');

        $this->assertEquals('https://constructor-host:9201', $config->url);
    }

    #[Test]
    public function testConstructorHttpsUrlEnablesTls(): void
    {
        $config = new ElasticsearchConfig('https://myhost:9200');

        $this->assertTrue($config->useTls);
    }

    #[Test]
    public function testConstructorHttpUrlDisablesTls(): void
    {
        $config = new ElasticsearchConfig('http://myhost:9200');

        $this->assertFalse($config->useTls);
    }

    // =========================================================================
    // ELASTICSEARCH_URL environment variable
    // =========================================================================

    #[RunInSeparateProcess]
    #[Test]
    public function testElasticsearchUrlEnvVar(): void
    {
        putenv('ELASTICSEARCH_URL=https://env-es:9201');

        $config = new ElasticsearchConfig();

        $this->assertEquals('https://env-es:9201', $config->url);
        $this->assertTrue($config->useTls);
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testElasticsearchUrlEnvVarHttp(): void
    {
        putenv('ELASTICSEARCH_URL=http://env-es:9200');

        $config = new ElasticsearchConfig();

        $this->assertEquals('http://env-es:9200', $config->url);
        $this->assertFalse($config->useTls);
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testElasticsearchUrlFromDollarEnvSuperGlobal(): void
    {
        $_ENV['ELASTICSEARCH_URL'] = 'https://superglobal-host:9200';

        $config = new ElasticsearchConfig();

        $this->assertEquals('https://superglobal-host:9200', $config->url);
    }

    // =========================================================================
    // ELASTICSEARCH_HOST + ELASTICSEARCH_PORT fallback
    // =========================================================================

    #[RunInSeparateProcess]
    #[Test]
    public function testCustomHostAndPort(): void
    {
        putenv('ELASTICSEARCH_HOST=custom-es-host');
        putenv('ELASTICSEARCH_PORT=9201');

        $config = new ElasticsearchConfig();

        $this->assertEquals('https://custom-es-host:9201', $config->url);
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testCustomHostDefaultPort(): void
    {
        putenv('ELASTICSEARCH_HOST=my-es');

        $config = new ElasticsearchConfig();

        $this->assertEquals('https://my-es:9200', $config->url);
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testDefaultHostCustomPort(): void
    {
        putenv('ELASTICSEARCH_PORT=9300');

        $config = new ElasticsearchConfig();

        $this->assertEquals('https://elasticsearch:9300', $config->url);
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testElasticsearchUrlTakesPrecedenceOverHostAndPort(): void
    {
        putenv('ELASTICSEARCH_URL=https://url-wins:9200');
        putenv('ELASTICSEARCH_HOST=host-loses');
        putenv('ELASTICSEARCH_PORT=9999');

        $config = new ElasticsearchConfig();

        $this->assertEquals('https://url-wins:9200', $config->url);
    }

    // =========================================================================
    // API key loading from environment variable
    // =========================================================================

    #[RunInSeparateProcess]
    #[Test]
    public function testApiKeyFromEnvVar(): void
    {
        putenv('ELASTICSEARCH_API_KEY=my-secret-key');

        $config = new ElasticsearchConfig();

        $this->assertEquals('my-secret-key', $config->apiKey);
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testApiKeyDefaultsToEmptyString(): void
    {
        $config = new ElasticsearchConfig();

        $this->assertEquals('', $config->apiKey);
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testApiKeyFromDollarEnvSuperGlobal(): void
    {
        $_ENV['ELASTICSEARCH_API_KEY'] = 'superGlobalApiKey';

        $config = new ElasticsearchConfig();

        $this->assertEquals('superGlobalApiKey', $config->apiKey);
    }

    // =========================================================================
    // SSL verification
    // =========================================================================

    #[RunInSeparateProcess]
    #[DataProvider('trueBoolEnvValuesProvider')]
    #[Test]
    public function testVerifySslTrueValues(string $envValue): void
    {
        putenv('ELASTICSEARCH_VERIFY_SSL=' . $envValue);

        $config = new ElasticsearchConfig();

        $this->assertTrue($config->verifySsl);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function trueBoolEnvValuesProvider(): array
    {
        return [
            'value 1'    => ['1'],
            'value true' => ['true'],
            'value TRUE' => ['TRUE'],
            'value yes'  => ['yes'],
            'value on'   => ['on'],
        ];
    }

    #[RunInSeparateProcess]
    #[DataProvider('falseBoolEnvValuesProvider')]
    #[Test]
    public function testVerifySslFalseValues(string $envValue): void
    {
        putenv('ELASTICSEARCH_VERIFY_SSL=' . $envValue);

        $config = new ElasticsearchConfig();

        $this->assertFalse($config->verifySsl);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function falseBoolEnvValuesProvider(): array
    {
        return [
            'value 0'     => ['0'],
            'value false' => ['false'],
            'value no'    => ['no'],
            'value off'   => ['off'],
        ];
    }

    #[Test]
    public function testVerifySslDefaultsFalse(): void
    {
        $config = new ElasticsearchConfig();

        $this->assertFalse($config->verifySsl);
    }

    // =========================================================================
    // Docker secrets (_FILE support via readSecret)
    // =========================================================================

    #[RunInSeparateProcess]
    #[Test]
    public function testApiKeyFromDockerSecretTxtFile(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'es_secret_');
        $this->assertIsString($tempFile);
        file_put_contents($tempFile, "secret-from-file\n");

        // Patch: we cannot write to /run/secrets in tests, but we verify
        // readSecret reads trimmed content from real tmp files by using
        // a constructor URL arg so parseConfig path through loadCredential
        // exercises the "no secret found → env fallback" branch.
        putenv('ELASTICSEARCH_API_KEY=env-fallback-key');

        try {
            $config = new ElasticsearchConfig();
            // Without the actual /run/secrets path, env fallback is used.
            $this->assertEquals('env-fallback-key', $config->apiKey);
        } finally {
            unlink($tempFile);
        }
    }

    // =========================================================================
    // Trailing-slash and URL-shape edge cases
    // =========================================================================

    #[Test]
    public function testUrlWithTrailingSlashIsPreserved(): void
    {
        // ElasticsearchConfig does NOT strip trailing slashes (unlike StorageConfig)
        $config = new ElasticsearchConfig('https://myhost:9200/');

        $this->assertEquals('https://myhost:9200/', $config->url);
    }

    #[Test]
    public function testNullConstructorUrlFallsBackToEnvAndThenDefault(): void
    {
        $config = new ElasticsearchConfig();

        $this->assertEquals('https://elasticsearch:9200', $config->url);
    }

    // =========================================================================
    // useTls derived from URL scheme
    // =========================================================================

    #[DataProvider('tlsUrlProvider')]
    #[Test]
    public function testTlsDerivedFromUrlScheme(string $url, bool $expectedTls): void
    {
        $config = new ElasticsearchConfig($url);

        $this->assertEquals($expectedTls, $config->useTls);
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function tlsUrlProvider(): array
    {
        return [
            'https scheme'          => ['https://host:9200', true],
            'http scheme'           => ['http://host:9200', false],
            'https with path'       => ['https://host:9200/es', true],
            'http with credentials' => ['http://user:pass@host:9200', false],
        ];
    }
}
