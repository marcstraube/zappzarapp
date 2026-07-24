<?php

declare(strict_types=1);

namespace Tests\App\Unit\Infrastructure\Storage;

use App\Infrastructure\Storage\StorageConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * Tests for StorageConfig (12-Factor App compliant configuration)
 *
 * NOTE: The PHP container mounts real Docker secrets at /run/secrets/.
 * Secrets take priority over env vars in the credential resolution chain.
 * Tests that verify credential loading from env vars must use constructor
 * parameters (highest priority, bypasses both secrets and env vars) or
 * explicitly assert on the secret value.
 *
 * @SuppressWarnings("PHPMD.TooManyMethods")
 * @SuppressWarnings("PHPMD.TooManyPublicMethods")
 */
#[CoversClass(StorageConfig::class)]
final class StorageConfigTest extends TestCase
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
        putenv('S3_ENDPOINT_URL');
        putenv('SEAWEEDFS_ENDPOINT');
        putenv('S3_ACCESS_KEY');
        putenv('S3_SECRET_KEY');
        putenv('SEAWEEDFS_S3_ACCESS_KEY');
        putenv('SEAWEEDFS_S3_SECRET_KEY');
        putenv('S3_REGION');
        putenv('SEAWEEDFS_REGION');
        putenv('S3_BUCKET');
        putenv('SEAWEEDFS_BUCKET');
        putenv('S3_VERIFY_SSL');
        putenv('S3_USE_PATH_STYLE');
        unset(
            $_ENV['S3_ENDPOINT_URL'],
            $_ENV['SEAWEEDFS_ENDPOINT'],
            $_ENV['S3_ACCESS_KEY'],
            $_ENV['S3_SECRET_KEY'],
            $_ENV['SEAWEEDFS_S3_ACCESS_KEY'],
            $_ENV['SEAWEEDFS_S3_SECRET_KEY'],
            $_ENV['S3_REGION'],
            $_ENV['SEAWEEDFS_REGION'],
            $_ENV['S3_BUCKET'],
            $_ENV['SEAWEEDFS_BUCKET'],
            $_ENV['S3_VERIFY_SSL'],
            $_ENV['S3_USE_PATH_STYLE'],
        );
    }

    // =========================================================================
    // Default values (non-credential fields; credentials come from secrets)
    // =========================================================================

    public function testDefaultNonCredentialValues(): void
    {
        $config = new StorageConfig();

        // Endpoint, region, bucket, and flag defaults are not overridden by secrets
        $this->assertEquals('http://seaweedfs:8333', $config->endpoint);
        $this->assertEquals('us-east-1', $config->region);
        $this->assertEquals('default', $config->bucket);
        $this->assertFalse($config->useTls);
        $this->assertFalse($config->verifySsl);
        $this->assertTrue($config->usePathStyle);
    }

    public function testDefaultCredentialsComeFromDockerSecretsOrFallback(): void
    {
        $config = new StorageConfig();

        // Credentials are non-empty (from Docker secret or 'admin' default)
        $this->assertNotEmpty($config->accessKey);
        $this->assertNotEmpty($config->secretKey);
    }

    // =========================================================================
    // Constructor parameters (highest priority — bypasses secrets and env vars)
    // =========================================================================

    public function testConstructorEndpointTakesPrecedence(): void
    {
        putenv('S3_ENDPOINT_URL=http://env-endpoint:8333');

        $config = new StorageConfig(endpoint: 'http://ctor-endpoint:9000');

        $this->assertEquals('http://ctor-endpoint:9000', $config->endpoint);
    }

    public function testConstructorAccessKeyTakesPrecedence(): void
    {
        $config = new StorageConfig(accessKey: 'ctor-key');

        $this->assertEquals('ctor-key', $config->accessKey);
    }

    public function testConstructorSecretKeyTakesPrecedence(): void
    {
        $config = new StorageConfig(secretKey: 'ctor-secret');

        $this->assertEquals('ctor-secret', $config->secretKey);
    }

    public function testConstructorRegionTakesPrecedence(): void
    {
        putenv('S3_REGION=eu-west-1');

        $config = new StorageConfig(region: 'ap-southeast-1');

        $this->assertEquals('ap-southeast-1', $config->region);
    }

    public function testConstructorBucketTakesPrecedence(): void
    {
        putenv('S3_BUCKET=env-bucket');

        $config = new StorageConfig(bucket: 'ctor-bucket');

        $this->assertEquals('ctor-bucket', $config->bucket);
    }

    public function testAllConstructorParamsTogether(): void
    {
        $config = new StorageConfig(
            endpoint: 'https://s3.example.com',
            accessKey: 'AKIAIOSFODNN7',
            secretKey: 'wJalrXUtnFEMI',
            region: 'eu-central-1',
            bucket: 'my-app-bucket',
        );

        $this->assertEquals('https://s3.example.com', $config->endpoint);
        $this->assertEquals('AKIAIOSFODNN7', $config->accessKey);
        $this->assertEquals('wJalrXUtnFEMI', $config->secretKey);
        $this->assertEquals('eu-central-1', $config->region);
        $this->assertEquals('my-app-bucket', $config->bucket);
        $this->assertTrue($config->useTls);
    }

    // =========================================================================
    // S3_ENDPOINT_URL environment variable (second priority)
    // =========================================================================

    #[RunInSeparateProcess]
    public function testS3EndpointUrlEnvVar(): void
    {
        putenv('S3_ENDPOINT_URL=https://s3.amazonaws.com');

        $config = new StorageConfig();

        $this->assertEquals('https://s3.amazonaws.com', $config->endpoint);
        $this->assertTrue($config->useTls);
    }

    #[RunInSeparateProcess]
    public function testS3EndpointUrlTakesPrecedenceOverSeaweedfsEndpoint(): void
    {
        putenv('S3_ENDPOINT_URL=http://s3-wins:9000');
        putenv('SEAWEEDFS_ENDPOINT=http://seaweedfs-loses:8333');

        $config = new StorageConfig();

        $this->assertEquals('http://s3-wins:9000', $config->endpoint);
    }

    // =========================================================================
    // SEAWEEDFS_ENDPOINT fallback
    // =========================================================================

    #[RunInSeparateProcess]
    public function testSeaweedfsFallbackEndpoint(): void
    {
        putenv('SEAWEEDFS_ENDPOINT=http://custom-seaweedfs:9000');

        $config = new StorageConfig();

        $this->assertEquals('http://custom-seaweedfs:9000', $config->endpoint);
    }

    // =========================================================================
    // Trailing slash is stripped from endpoint
    // =========================================================================

    public function testTrailingSlashStrippedFromEndpoint(): void
    {
        $config = new StorageConfig(endpoint: 'http://seaweedfs:8333/');

        $this->assertEquals('http://seaweedfs:8333', $config->endpoint);
    }

    public function testMultipleTrailingSlashesStripped(): void
    {
        // rtrim strips all trailing slashes
        $config = new StorageConfig(endpoint: 'http://seaweedfs:8333///');

        $this->assertEquals('http://seaweedfs:8333', $config->endpoint);
    }

    #[RunInSeparateProcess]
    public function testTrailingSlashStrippedFromEnvEndpoint(): void
    {
        putenv('S3_ENDPOINT_URL=http://env-host:9000/');

        $config = new StorageConfig();

        $this->assertEquals('http://env-host:9000', $config->endpoint);
    }

    // =========================================================================
    // Credential env var ordering (tested via constructor params to verify
    // the priority logic without secrets interfering)
    // =========================================================================

    public function testConstructorAccessKeyBeatsEnvAndSecrets(): void
    {
        putenv('S3_ACCESS_KEY=should-be-ignored');

        $config = new StorageConfig(accessKey: 'explicit-key');

        $this->assertEquals('explicit-key', $config->accessKey);
    }

    public function testConstructorSecretKeyBeatsEnvAndSecrets(): void
    {
        putenv('S3_SECRET_KEY=should-be-ignored');

        $config = new StorageConfig(secretKey: 'explicit-secret');

        $this->assertEquals('explicit-secret', $config->secretKey);
    }

    // =========================================================================
    // Region env vars
    // =========================================================================

    #[RunInSeparateProcess]
    public function testS3RegionEnvVar(): void
    {
        putenv('S3_REGION=eu-west-1');

        $config = new StorageConfig();

        $this->assertEquals('eu-west-1', $config->region);
    }

    #[RunInSeparateProcess]
    public function testSeaweedfsRegionFallback(): void
    {
        putenv('SEAWEEDFS_REGION=ap-east-1');

        $config = new StorageConfig();

        $this->assertEquals('ap-east-1', $config->region);
    }

    #[RunInSeparateProcess]
    public function testS3RegionTakesPrecedenceOverSeaweedfsRegion(): void
    {
        putenv('S3_REGION=s3-region');
        putenv('SEAWEEDFS_REGION=seaweedfs-region');

        $config = new StorageConfig();

        $this->assertEquals('s3-region', $config->region);
    }

    // =========================================================================
    // Bucket env vars
    // =========================================================================

    #[RunInSeparateProcess]
    public function testS3BucketEnvVar(): void
    {
        putenv('S3_BUCKET=my-bucket');

        $config = new StorageConfig();

        $this->assertEquals('my-bucket', $config->bucket);
    }

    #[RunInSeparateProcess]
    public function testSeaweedfsBucketFallback(): void
    {
        putenv('SEAWEEDFS_BUCKET=sw-bucket');

        $config = new StorageConfig();

        $this->assertEquals('sw-bucket', $config->bucket);
    }

    #[RunInSeparateProcess]
    public function testS3BucketTakesPrecedenceOverSeaweedfsBucket(): void
    {
        putenv('S3_BUCKET=s3-bucket');
        putenv('SEAWEEDFS_BUCKET=seaweedfs-bucket');

        $config = new StorageConfig();

        $this->assertEquals('s3-bucket', $config->bucket);
    }

    // =========================================================================
    // useTls derived from endpoint scheme
    // =========================================================================

    #[DataProvider('tlsEndpointProvider')]
    public function testTlsDerivedFromEndpointScheme(string $endpoint, bool $expectedTls): void
    {
        $config = new StorageConfig(endpoint: $endpoint);

        $this->assertEquals($expectedTls, $config->useTls);
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function tlsEndpointProvider(): array
    {
        return [
            'https scheme' => ['https://host:9000', true],
            'http scheme'  => ['http://host:8333', false],
        ];
    }

    // =========================================================================
    // SSL verification
    // =========================================================================

    #[RunInSeparateProcess]
    #[DataProvider('trueBoolEnvValuesProvider')]
    public function testVerifySslTrueValues(string $envValue): void
    {
        putenv('S3_VERIFY_SSL=' . $envValue);

        $config = new StorageConfig();

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
    public function testVerifySslFalseValues(string $envValue): void
    {
        putenv('S3_VERIFY_SSL=' . $envValue);

        $config = new StorageConfig();

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

    public function testVerifySslDefaultsFalse(): void
    {
        $config = new StorageConfig();

        $this->assertFalse($config->verifySsl);
    }

    // =========================================================================
    // Path-style access
    // =========================================================================

    #[RunInSeparateProcess]
    public function testUsePathStyleDefaultsTrue(): void
    {
        $config = new StorageConfig();

        $this->assertTrue($config->usePathStyle);
    }

    #[RunInSeparateProcess]
    #[DataProvider('falsePathStyleValuesProvider')]
    public function testUsePathStyleCanBeDisabled(string $envValue): void
    {
        putenv('S3_USE_PATH_STYLE=' . $envValue);

        $config = new StorageConfig();

        $this->assertFalse($config->usePathStyle);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function falsePathStyleValuesProvider(): array
    {
        return [
            'value 0'     => ['0'],
            'value false' => ['false'],
            'value no'    => ['no'],
            'value off'   => ['off'],
        ];
    }

    #[RunInSeparateProcess]
    public function testUsePathStyleCanBeEnabledExplicitly(): void
    {
        putenv('S3_USE_PATH_STYLE=true');

        $config = new StorageConfig();

        $this->assertTrue($config->usePathStyle);
    }

    // =========================================================================
    // $_ENV superglobal support (non-credential fields)
    // =========================================================================

    #[RunInSeparateProcess]
    public function testDollarEnvSuperGlobalForEndpoint(): void
    {
        $_ENV['S3_ENDPOINT_URL'] = 'https://s3-superglobal:9000';

        $config = new StorageConfig();

        $this->assertEquals('https://s3-superglobal:9000', $config->endpoint);
    }

    #[RunInSeparateProcess]
    public function testDollarEnvSuperGlobalForRegion(): void
    {
        $_ENV['S3_REGION'] = 'eu-north-1';

        $config = new StorageConfig();

        $this->assertEquals('eu-north-1', $config->region);
    }

    // =========================================================================
    // Docker secrets (env fallback path; secrets are always present in the
    // test container, so env vars for credentials are overshadowed)
    // =========================================================================

    public function testCredentialsAreNonEmptyFromSecretsOrDefault(): void
    {
        // Docker secrets exist → seaweedfs credentials loaded from /run/secrets/
        $config = new StorageConfig();

        $this->assertIsString($config->accessKey);
        $this->assertIsString($config->secretKey);
        $this->assertNotEmpty($config->accessKey);
        $this->assertNotEmpty($config->secretKey);
    }
}
