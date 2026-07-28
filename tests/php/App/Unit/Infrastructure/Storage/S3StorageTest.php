<?php

declare(strict_types=1);

namespace Tests\App\Unit\Infrastructure\Storage;

use App\Infrastructure\Config\CredentialLoader;
use App\Infrastructure\Storage\S3Storage;
use App\Infrastructure\Storage\StorageConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Unit tests for S3Storage
 *
 * These tests verify the S3Storage logic without a real connection.
 * For integration tests with actual SeaweedFS/S3, see tests/php/App/Feature/.
 *
 * @SuppressWarnings("PHPMD.TooManyPublicMethods")
 */
#[CoversClass(S3Storage::class)]
#[UsesClass(CredentialLoader::class)]
#[UsesClass(StorageConfig::class)]
final class S3StorageTest extends TestCase
{
    private const string TEST_ENDPOINT = 'http://localhost:8333';

    #[Test]
    public function testConstructorAcceptsCustomParameters(): void
    {
        $storage = new S3Storage(
            'https://custom:9000',
            'custom-access',
            'custom-secret',
            'eu-west-1',
            'custom-bucket'
        );

        $reflection     = new ReflectionClass($storage);
        $configProperty = $reflection->getProperty('config');
        $config         = $configProperty->getValue($storage);

        $this->assertEquals('https://custom:9000', $config->endpoint);
        $this->assertEquals('custom-access', $config->accessKey);
        $this->assertEquals('custom-secret', $config->secretKey);
        $this->assertEquals('eu-west-1', $config->region);
        $this->assertEquals('custom-bucket', $config->bucket);
    }

    #[Test]
    public function testConstructorUsesDefaultsWhenNoParametersProvided(): void
    {
        // Clear environment variables
        $_ENV['S3_ENDPOINT_URL']    = '';
        $_ENV['SEAWEEDFS_ENDPOINT'] = '';
        putenv('S3_ENDPOINT_URL=');
        putenv('SEAWEEDFS_ENDPOINT=');

        $storage = new S3Storage();

        $reflection     = new ReflectionClass($storage);
        $configProperty = $reflection->getProperty('config');
        $config         = $configProperty->getValue($storage);

        // Default endpoint is http://seaweedfs:8333
        $this->assertStringContainsString('seaweedfs', $config->endpoint);
        $this->assertEquals('us-east-1', $config->region);
    }

    #[Test]
    public function testGetPublicUrlWithPathStyle(): void
    {
        $storage = new S3Storage(
            'http://seaweedfs:8333',
            'access',
            'secret',
            'us-east-1',
            'mybucket'
        );

        $url = $storage->getPublicUrl('avatars/user-123.jpg');

        $this->assertEquals('http://seaweedfs:8333/mybucket/avatars/user-123.jpg', $url);
    }

    #[Test]
    public function testGetPublicUrlStripsLeadingSlash(): void
    {
        $storage = new S3Storage(
            'http://seaweedfs:8333',
            'access',
            'secret',
            'us-east-1',
            'mybucket'
        );

        $url = $storage->getPublicUrl('/avatars/user-123.jpg');

        $this->assertEquals('http://seaweedfs:8333/mybucket/avatars/user-123.jpg', $url);
    }

    #[Test]
    public function testUploadReturnsNullWhenNotConnected(): void
    {
        $storage = new S3Storage(self::TEST_ENDPOINT, 'access', 'secret');

        $result = $storage->upload('test.txt', 'Hello World', 'text/plain');

        // Without a real server, upload should fail gracefully
        $this->assertNull($result);
    }

    #[Test]
    public function testUploadFileReturnsNullForNonExistentFile(): void
    {
        $storage = new S3Storage(self::TEST_ENDPOINT, 'access', 'secret');

        $result = $storage->uploadFile('test.txt', '/non/existent/file.txt');

        $this->assertNull($result);
    }

    #[Test]
    public function testDownloadReturnsNullWhenNotConnected(): void
    {
        $storage = new S3Storage(self::TEST_ENDPOINT, 'access', 'secret');

        $result = $storage->download('non-existent.txt');

        $this->assertNull($result);
    }

    #[Test]
    public function testDownloadFileReturnsFalseWhenNotConnected(): void
    {
        $storage  = new S3Storage(self::TEST_ENDPOINT, 'access', 'secret');
        $tempFile = sys_get_temp_dir() . '/test-download-' . uniqid() . '.txt';

        $result = $storage->downloadFile('non-existent.txt', $tempFile);

        $this->assertFalse($result);

        // Cleanup
        if (file_exists($tempFile)) {
            unlink($tempFile);
        }
    }

    #[Test]
    public function testExistsReturnsFalseWhenNotConnected(): void
    {
        $storage = new S3Storage(self::TEST_ENDPOINT, 'access', 'secret');

        $result = $storage->exists('non-existent.txt');

        $this->assertFalse($result);
    }

    #[Test]
    public function testDeleteReturnsFalseWhenNotConnected(): void
    {
        $storage = new S3Storage(self::TEST_ENDPOINT, 'access', 'secret');

        $result = $storage->delete('non-existent.txt');

        $this->assertFalse($result);
    }

    #[Test]
    public function testDeleteMultipleReturnsZeroWhenNotConnected(): void
    {
        $storage = new S3Storage(self::TEST_ENDPOINT, 'access', 'secret');

        $result = $storage->deleteMultiple(['file1.txt', 'file2.txt']);

        $this->assertEquals(0, $result);
    }

    #[Test]
    public function testListReturnsEmptyArrayWhenNotConnected(): void
    {
        $storage = new S3Storage(self::TEST_ENDPOINT, 'access', 'secret');

        $result = $storage->list('prefix/');

        $this->assertEquals([], $result);
    }

    #[Test]
    public function testCopyReturnsFalseWhenNotConnected(): void
    {
        $storage = new S3Storage(self::TEST_ENDPOINT, 'access', 'secret');

        $result = $storage->copy('source.txt', 'dest.txt');

        $this->assertFalse($result);
    }

    #[Test]
    public function testMoveReturnsFalseWhenCopyFails(): void
    {
        $storage = new S3Storage(self::TEST_ENDPOINT, 'access', 'secret');

        $result = $storage->move('source.txt', 'dest.txt');

        $this->assertFalse($result);
    }

    #[Test]
    public function testGetMetadataReturnsNullWhenNotConnected(): void
    {
        $storage = new S3Storage(self::TEST_ENDPOINT, 'access', 'secret');

        $result = $storage->getMetadata('non-existent.txt');

        $this->assertNull($result);
    }

    #[Test]
    public function testGetPresignedUrlGeneratesValidUrl(): void
    {
        $storage = new S3Storage(
            'http://seaweedfs:8333',
            'test-access-key-id-12345',
            'test-secret-access-key-67890abcdef',
            'us-east-1',
            'mybucket'
        );

        $url = $storage->getPresignedUrl('test.txt');

        $this->assertNotNull($url);
        $this->assertStringContainsString('X-Amz-Algorithm=AWS4-HMAC-SHA256', $url);
        $this->assertStringContainsString('X-Amz-Credential=', $url);
        $this->assertStringContainsString('X-Amz-Signature=', $url);
        $this->assertStringContainsString('X-Amz-Expires=3600', $url);
    }

    #[Test]
    public function testCreateBucketReturnsFalseWhenNotConnected(): void
    {
        $storage = new S3Storage(self::TEST_ENDPOINT, 'access', 'secret');

        $result = $storage->createBucket('new-bucket');

        $this->assertFalse($result);
    }

    #[Test]
    public function testBucketExistsReturnsFalseWhenNotConnected(): void
    {
        $storage = new S3Storage(self::TEST_ENDPOINT, 'access', 'secret');

        $result = $storage->bucketExists('non-existent-bucket');

        $this->assertFalse($result);
    }

    #[Test]
    public function testIsPresignedSupportedReturnsTrue(): void
    {
        $storage = new S3Storage(self::TEST_ENDPOINT, 'access', 'secret');

        $this->assertTrue($storage->isPresignedSupported());
    }

    #[Test]
    public function testIsAvailableReturnsFalseWhenNotConnected(): void
    {
        $storage = new S3Storage(self::TEST_ENDPOINT, 'access', 'secret');

        $result = $storage->isAvailable();

        $this->assertFalse($result);
    }

    #[Test]
    public function testConfigDetectsHttpsUseTls(): void
    {
        $storage = new S3Storage('https://secure:8333', 'access', 'secret');

        $reflection     = new ReflectionClass($storage);
        $configProperty = $reflection->getProperty('config');
        $config         = $configProperty->getValue($storage);

        $this->assertTrue($config->useTls);
    }

    #[Test]
    public function testConfigDetectsHttpNoTls(): void
    {
        $storage = new S3Storage('http://local:8333', 'access', 'secret');

        $reflection     = new ReflectionClass($storage);
        $configProperty = $reflection->getProperty('config');
        $config         = $configProperty->getValue($storage);

        $this->assertFalse($config->useTls);
    }

    #[Test]
    public function testConfigUsesPathStyleByDefault(): void
    {
        $storage = new S3Storage(self::TEST_ENDPOINT, 'access', 'secret');

        $reflection     = new ReflectionClass($storage);
        $configProperty = $reflection->getProperty('config');
        $config         = $configProperty->getValue($storage);

        $this->assertTrue($config->usePathStyle);
    }
}
