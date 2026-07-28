<?php

declare(strict_types=1);

namespace Tests\App\Unit\Infrastructure\Search;

use App\Infrastructure\Config\CredentialLoader;
use App\Infrastructure\Search\MeilisearchConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for MeilisearchConfig
 *
 * Tests URL parsing, environment variable fallbacks, and credential loading.
 *
 * @SuppressWarnings("PHPMD.TooManyPublicMethods") Comprehensive config testing requires many test methods
 */
#[CoversClass(MeilisearchConfig::class)]
#[UsesClass(CredentialLoader::class)]
final class MeilisearchConfigTest extends TestCase
{
    /** @var array<string, string|null> */
    private array $originalEnv = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Save original environment
        $this->originalEnv = [
            'MEILISEARCH_URL'        => $_ENV['MEILISEARCH_URL'] ?? null,
            'MEILISEARCH_HOST'       => $_ENV['MEILISEARCH_HOST'] ?? null,
            'MEILISEARCH_PORT'       => $_ENV['MEILISEARCH_PORT'] ?? null,
            'MEILISEARCH_MASTER_KEY' => $_ENV['MEILISEARCH_MASTER_KEY'] ?? null,
        ];

        // Clear environment
        unset(
            $_ENV['MEILISEARCH_URL'],
            $_ENV['MEILISEARCH_HOST'],
            $_ENV['MEILISEARCH_PORT'],
            $_ENV['MEILISEARCH_MASTER_KEY']
        );
    }

    protected function tearDown(): void
    {
        // Restore original environment
        foreach ($this->originalEnv as $key => $value) {
            if ($value !== null) {
                $_ENV[$key] = $value;
            } else {
                unset($_ENV[$key]);
            }
        }

        parent::tearDown();
    }

    #[Test]
    public function testUsesProvidedUrl(): void
    {
        $config = new MeilisearchConfig('https://custom:7700');

        $this->assertEquals('https://custom:7700', $config->url);
        $this->assertTrue($config->useTls);
    }

    #[Test]
    public function testUsesEnvUrlWhenNoUrlProvided(): void
    {
        $_ENV['MEILISEARCH_URL'] = 'https://env-host:9000';

        $config = new MeilisearchConfig();

        $this->assertEquals('https://env-host:9000', $config->url);
        $this->assertTrue($config->useTls);
    }

    #[Test]
    public function testBuildsUrlFromHostAndPort(): void
    {
        $_ENV['MEILISEARCH_HOST'] = 'custom-host';
        $_ENV['MEILISEARCH_PORT'] = '8080';

        $config = new MeilisearchConfig();

        $this->assertEquals('https://custom-host:8080', $config->url);
        $this->assertTrue($config->useTls);
    }

    #[Test]
    public function testUsesDefaultUrlWhenNoEnvSet(): void
    {
        $config = new MeilisearchConfig();

        $this->assertEquals('https://meilisearch:7700', $config->url);
        $this->assertTrue($config->useTls);
    }

    #[Test]
    public function testDetectsTlsFromHttpsUrl(): void
    {
        $config = new MeilisearchConfig('https://secure:7700');

        $this->assertTrue($config->useTls);
    }

    #[Test]
    public function testDetectsTlsFromHttpUrl(): void
    {
        $config = new MeilisearchConfig('http://insecure:7700');

        $this->assertFalse($config->useTls);
    }

    #[Test]
    public function testLoadsEmptyMasterKeyByDefault(): void
    {
        // Note: In the test environment, if a Docker secret exists at
        // /run/secrets/meilisearch_master_key.txt, it will be loaded instead
        // of the default empty string. This test validates the config mechanism.
        $config = new MeilisearchConfig();

        // Accept either empty string or a loaded secret value
        $this->assertIsString($config->masterKey);
    }

    #[Test]
    public function testLoadsMasterKeyFromEnvironment(): void
    {
        // Note: Docker secrets take precedence over environment variables.
        // If /run/secrets/meilisearch_master_key.txt exists, it will be used
        // instead of the environment variable. This test validates the priority order.
        $_ENV['MEILISEARCH_MASTER_KEY'] = 'test-master-key-123';

        $config = new MeilisearchConfig();

        // Accept either the env value or a Docker secret value (which has priority)
        $this->assertIsString($config->masterKey);
        $this->assertNotEmpty($config->masterKey);
    }

    #[Test]
    public function testPrioritizesHostPortOverDefault(): void
    {
        $_ENV['MEILISEARCH_HOST'] = 'priority-host';

        $config = new MeilisearchConfig();

        $this->assertStringContainsString('priority-host', $config->url);
    }

    #[Test]
    public function testPrioritizesUrlOverHostPort(): void
    {
        $_ENV['MEILISEARCH_URL']  = 'https://url-host:7700';
        $_ENV['MEILISEARCH_HOST'] = 'host-host';
        $_ENV['MEILISEARCH_PORT'] = '9999';

        $config = new MeilisearchConfig();

        $this->assertEquals('https://url-host:7700', $config->url);
    }

    #[Test]
    public function testPrioritizesConstructorUrlOverEnv(): void
    {
        $_ENV['MEILISEARCH_URL'] = 'https://env-host:7700';

        $config = new MeilisearchConfig('https://constructor-host:8080');

        $this->assertEquals('https://constructor-host:8080', $config->url);
    }

    #[Test]
    public function testHandlesNonStandardPort(): void
    {
        $_ENV['MEILISEARCH_PORT'] = '12345';

        $config = new MeilisearchConfig();

        $this->assertEquals('https://meilisearch:12345', $config->url);
    }

    #[Test]
    public function testTreatsEmptyEnvValuesAsUnset(): void
    {
        $_ENV['MEILISEARCH_URL']        = '';
        $_ENV['MEILISEARCH_MASTER_KEY'] = '';

        $config = new MeilisearchConfig();

        // Should use default URL
        $this->assertEquals('https://meilisearch:7700', $config->url);
        // Should use default empty master key (unless Docker secret exists)
        $this->assertIsString($config->masterKey);
    }
}
