<?php

declare(strict_types=1);

namespace Tests\DevDashboard\Services;

use App\Infrastructure\DatabaseConfig;
use App\Infrastructure\TlsConfig;
use DevDashboard\Services\HealthCheckService;
use Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Redis;

/**
 * Deterministic seam tests for HealthCheckService.
 *
 * All I/O is intercepted via protected-method overrides on anonymous subclasses
 * so that both the "success" and "failure" branches are always exercised,
 * regardless of whether containers are actually running.
 *
 * @SuppressWarnings("PHPMD.TooManyPublicMethods")
 * @SuppressWarnings("PHPMD.ExcessiveClassComplexity")
 */
#[CoversClass(HealthCheckService::class)]
#[UsesClass(DatabaseConfig::class)]
#[UsesClass(TlsConfig::class)]
final class HealthCheckServiceSeamsTest extends TestCase
{
    // =========================================================================
    // checkSocketConnection() — deterministic socket failure (line 648)
    // =========================================================================

    #[Test]
    public function testSocketConnectionFailureReturnsStoppedStatus(): void
    {
        $fake = $this->makeServiceWithSocket(socketReturn: false, errstr: 'Connection refused');

        putenv('ENABLE_REDIS=false');
        putenv('DB_TYPE=postgres');

        $services = $fake->getServices();

        // All socket-checked services should be stopped since socket always fails
        $nginx = $services['core']['nginx'] ?? null;
        $this->assertNotNull($nginx);
        $this->assertSame('stopped', $nginx['status']);

        putenv('ENABLE_REDIS');
        putenv('DB_TYPE');
    }

    #[Test]
    public function testSocketConnectionSuccessReturnsRunningStatus(): void
    {
        $fake = $this->makeServiceWithSocket(socketReturn: true);

        putenv('ENABLE_REDIS=false');
        putenv('DB_TYPE=postgres');

        $services = $fake->getServices();

        $nginx = $services['core']['nginx'] ?? null;
        $this->assertNotNull($nginx);
        $this->assertSame('running', $nginx['status']);

        putenv('ENABLE_REDIS');
        putenv('DB_TYPE');
    }

    // =========================================================================
    // checkRedisDetailed() — connected=false path (line 345)
    // =========================================================================

    #[Test]
    public function testRedisDetailedConnectionFailedReturnsFalse(): void
    {
        $fake = $this->makeServiceWithRedisConnect(connectReturn: false);

        putenv('ENABLE_REDIS=true');
        putenv('REDIS_URL=redis://redis:6379');

        $connections = $fake->getConnections();

        $this->assertArrayHasKey('redis', $connections);
        $redis = $connections['redis'];
        $this->assertFalse($redis['connected']);
        $this->assertSame('Connection failed', $redis['error']);

        putenv('ENABLE_REDIS');
        putenv('REDIS_URL');
    }

    // =========================================================================
    // checkRedisDetailed() — success path (lines 355–367)
    // =========================================================================

    #[Test]
    public function testRedisDetailedSuccessPathReturnsPongTrue(): void
    {
        $stubRedis = $this->createRedisStub(pingReturn: true, version: '7.2.0');
        $fake      = $this->makeServiceWithRedisConnect(connectReturn: true, redisInstance: $stubRedis);

        putenv('ENABLE_REDIS=true');
        putenv('REDIS_URL=redis://redis:6379');

        $connections = $fake->getConnections();

        $this->assertArrayHasKey('redis', $connections);
        $redis = $connections['redis'];
        $this->assertTrue($redis['connected']);
        $this->assertSame('Redis', $redis['type']);
        $this->assertSame('7.2.0', $redis['version']);
        $this->assertFalse($redis['tls']);

        putenv('ENABLE_REDIS');
        putenv('REDIS_URL');
    }

    #[Test]
    public function testRedisDetailedSuccessPathReturnsPongString(): void
    {
        $stubRedis = $this->createRedisStub(pingReturn: '+PONG', version: '6.2.0');
        $fake      = $this->makeServiceWithRedisConnect(connectReturn: true, redisInstance: $stubRedis);

        putenv('ENABLE_REDIS=true');
        putenv('REDIS_URL=redis://redis:6379');

        $connections = $fake->getConnections();

        $redis = $connections['redis'];
        $this->assertTrue($redis['connected']);
        $this->assertSame('6.2.0', $redis['version']);

        putenv('ENABLE_REDIS');
        putenv('REDIS_URL');
    }

    // =========================================================================
    // checkRedisDetailed() — Exception catch path (lines 368–373, CI-only)
    // =========================================================================

    #[Test]
    public function testRedisDetailedExceptionReturnsConnectedFalse(): void
    {
        $fake = $this->makeServiceWithRedisConnectThrowing(new Exception('Redis error'));

        putenv('ENABLE_REDIS=true');
        putenv('REDIS_URL=redis://redis:6379');

        $connections = $fake->getConnections();

        $this->assertArrayHasKey('redis', $connections);
        $redis = $connections['redis'];
        $this->assertFalse($redis['connected']);
        $this->assertSame('Redis error', $redis['error']);

        putenv('ENABLE_REDIS');
        putenv('REDIS_URL');
    }

    // =========================================================================
    // getSslInfo() + parseCertificate() — no certs exist (lines 562–566, CI-only)
    // =========================================================================

    #[Test]
    public function testSslInfoNoCertsReturnsExistsFalse(): void
    {
        $fake = $this->makeServiceWithCertFile(fileExists: false);

        $ssl = $fake->getSslInfo();

        $this->assertFalse($ssl['exists']);
        $this->assertSame('No SSL certificates found. Run make ssl-selfsigned or make ssl-internal.', $ssl['message']);
        $this->assertSame([], $ssl['certificates']);
    }

    // =========================================================================
    // getSslInfo() — certs present, exists=true (lines 556–557, 569–572)
    // =========================================================================

    #[Test]
    public function testSslInfoWithValidCertReturnsExistsTrue(): void
    {
        $certData = $this->makeCertData(daysFromNow: 90);
        $fake     = $this->makeServiceWithCertFile(
            fileExists: true,
            contents: '---CERT---',
            parsedData: $certData,
        );

        $ssl = $fake->getSslInfo();

        $this->assertTrue($ssl['exists']);
        $this->assertNotEmpty($ssl['certificates']);
    }

    // =========================================================================
    // parseCertificate() — file_get_contents returns false (lines 586–587)
    // =========================================================================

    #[Test]
    public function testParseCertificateReadFailureReturnsInvalidEntry(): void
    {
        $fake = $this->makeServiceWithCertFile(fileExists: true, contents: false);

        $ssl = $fake->getSslInfo();

        // The certificate entry exists but is marked invalid
        $this->assertTrue($ssl['exists']);
        $cert = array_values($ssl['certificates'])[0];
        $this->assertFalse($cert['valid']);
        $this->assertSame('Could not read certificate file', $cert['message']);
    }

    // =========================================================================
    // parseCertificate() — openssl_x509_parse returns false (lines 595–596)
    // =========================================================================

    #[Test]
    public function testParseCertificateInvalidFormatReturnsInvalidEntry(): void
    {
        $fake = $this->makeServiceWithCertFile(
            fileExists: true,
            contents: 'not-a-cert',
            parsedData: false,
        );

        $ssl = $fake->getSslInfo();

        $this->assertTrue($ssl['exists']);
        $cert = array_values($ssl['certificates'])[0];
        $this->assertFalse($cert['valid']);
        $this->assertSame('Invalid certificate format', $cert['message']);
    }

    // =========================================================================
    // parseCertificate() — full success (lines 604–618)
    // =========================================================================

    #[Test]
    public function testParseCertificateSuccessReturnsFullCertInfo(): void
    {
        $certData = $this->makeCertData(daysFromNow: 45);
        $fake     = $this->makeServiceWithCertFile(
            fileExists: true,
            contents: '---CERT---',
            parsedData: $certData,
        );

        $ssl = $fake->getSslInfo();

        $this->assertTrue($ssl['exists']);
        $cert = array_values($ssl['certificates'])[0];
        $this->assertSame('test.local', $cert['subject']);
        $this->assertSame('Test CA', $cert['issuer']);
        $this->assertArrayHasKey('valid_from', $cert);
        $this->assertArrayHasKey('valid_to', $cert);
        $this->assertArrayHasKey('days_until_expiry', $cert);
        $this->assertIsBool($cert['expires_soon']);
        $this->assertFalse($cert['expires_soon']);  // 45 days > 30
    }

    #[Test]
    public function testParseCertificateExpiresSoonWhenUnder30Days(): void
    {
        $certData = $this->makeCertData(daysFromNow: 10);
        $fake     = $this->makeServiceWithCertFile(
            fileExists: true,
            contents: '---CERT---',
            parsedData: $certData,
        );

        $ssl = $fake->getSslInfo();

        $cert = array_values($ssl['certificates'])[0];
        $this->assertTrue($cert['expires_soon']);
    }

    // =========================================================================
    // Factory helpers
    // =========================================================================

    /**
     * Build a fake HealthCheckService that controls fsockopen results.
     */
    private function makeServiceWithSocket(bool $socketReturn, string $errstr = ''): HealthCheckService
    {
        return new class($socketReturn, $errstr) extends HealthCheckService {
            public function __construct(private readonly bool $socketReturn, private readonly string $errstr)
            {
            }

            /**
             * @noinspection PhpMixedReturnTypeCanBeReducedInspection
             * @param-out int    $errno
             * @param-out string $errstr
             */
            protected function safeSocketOpen(string $host, int $port, ?int &$errno, ?string &$errstr): mixed
            {
                unset($host, $port);
                $errno  = $this->socketReturn ? 0 : 111;
                $errstr = $this->socketReturn ? '' : $this->errstr;

                if ($this->socketReturn) {
                    return fopen('php://memory', 'r');
                }

                return false;
            }
        };
    }

    /**
     * Build a fake HealthCheckService that controls Redis connect() result.
     */
    private function makeServiceWithRedisConnect(
        bool $connectReturn,
        ?Redis $redisInstance = null,
    ): HealthCheckService {
        return new class($connectReturn, $redisInstance) extends HealthCheckService {
            private readonly bool $connectReturn;

            private readonly ?Redis $fakeRedis;

            public function __construct(bool $connectReturn, ?Redis $fakeRedis)
            {
                $this->connectReturn = $connectReturn;
                $this->fakeRedis     = $fakeRedis;
            }

            protected function newRedisInstance(): Redis
            {
                return $this->fakeRedis ?? parent::newRedisInstance();
            }

            /** @param array<string, mixed>|null $context */
            protected function safeRedisConnect(Redis $redis, string $host, int $port, ?array $context = null): bool
            {
                unset($redis, $host, $port, $context);

                return $this->connectReturn;
            }

            /**
             * @noinspection PhpMixedReturnTypeCanBeReducedInspection
             * @param-out int    $errno
             * @param-out string $errstr
             */
            protected function safeSocketOpen(string $host, int $port, ?int &$errno, ?string &$errstr): mixed
            {
                unset($host, $port);
                $errno  = 111;
                $errstr = 'not reachable';

                return false;
            }
        };
    }

    /**
     * Build a fake HealthCheckService whose safeRedisConnect throws.
     */
    private function makeServiceWithRedisConnectThrowing(Exception $exception): HealthCheckService
    {
        return new class($exception) extends HealthCheckService {
            public function __construct(private readonly Exception $exception)
            {
            }

            /** @param array<string, mixed>|null $context */
            protected function safeRedisConnect(Redis $redis, string $host, int $port, ?array $context = null): bool
            {
                unset($redis, $host, $port, $context);
                throw $this->exception;
            }

            /**
             * @noinspection PhpMixedReturnTypeCanBeReducedInspection
             * @param-out int    $errno
             * @param-out string $errstr
             */
            protected function safeSocketOpen(string $host, int $port, ?int &$errno, ?string &$errstr): mixed
            {
                unset($host, $port);
                $errno  = 111;
                $errstr = 'not reachable';

                return false;
            }
        };
    }

    /**
     * Build a fake HealthCheckService that controls certificate I/O.
     *
     * @param string|false                    $contents   File contents returned by fileGetContents()
     * @param array<string, mixed>|false|null $parsedData openssl_x509_parse() return value (null = not called)
     * @SuppressWarnings("PHPMD.BooleanArgumentFlag") Named arguments make intent clear at each call-site
     */
    private function makeServiceWithCertFile(
        bool $fileExists,
        string|false $contents = false,
        array|false|null $parsedData = null,
    ): HealthCheckService {
        return new class($fileExists, $contents, $parsedData) extends HealthCheckService {
            private readonly bool $fileExistsResult;

            private readonly string|false $contentsResult;

            /** @var array<string, mixed>|false|null */
            private readonly array|false|null $parsedDataResult;

            /** @param array<string, mixed>|false|null $parsedData */
            public function __construct(
                bool $fileExists,
                string|false $contents,
                array|false|null $parsedData,
            ) {
                $this->fileExistsResult = $fileExists;
                $this->contentsResult   = $contents;
                $this->parsedDataResult = $parsedData;
            }

            protected function certFileExists(string $path): bool
            {
                unset($path);

                return $this->fileExistsResult;
            }

            protected function fileGetContents(string $path): string|false
            {
                unset($path);

                return $this->contentsResult;
            }

            /** @return array<string, mixed>|false */
            protected function opensslX509Parse(string $certContent): array|false
            {
                unset($certContent);

                return $this->parsedDataResult ?? false;
            }

            /**
             * @noinspection PhpMixedReturnTypeCanBeReducedInspection
             * @param-out int    $errno
             * @param-out string $errstr
             */
            protected function safeSocketOpen(string $host, int $port, ?int &$errno, ?string &$errstr): mixed
            {
                unset($host, $port);
                $errno  = 111;
                $errstr = 'not reachable';

                return false;
            }
        };
    }

    /**
     * Build a fake Redis stub for ping/info/close.
     */
    private function createRedisStub(string|bool $pingReturn, string $version): Redis
    {
        $stub = $this->createStub(Redis::class);
        $stub->method('ping')->willReturn($pingReturn);
        $stub->method('info')->willReturn(['redis_version' => $version]);
        $stub->method('close')->willReturn(true);

        return $stub;
    }

    /**
     * Build minimal openssl_x509_parse data for a cert valid N days from now.
     *
     * @return array<string, mixed>
     */
    private function makeCertData(int $daysFromNow): array
    {
        $now  = time();
        $from = $now - 86400;  // valid since yesterday
        $to   = $now + ($daysFromNow * 86400);

        return [
            'validFrom_time_t' => $from,
            'validTo_time_t'   => $to,
            'subject'          => ['CN' => 'test.local'],
            'issuer'           => ['CN' => 'Test CA'],
        ];
    }
}
