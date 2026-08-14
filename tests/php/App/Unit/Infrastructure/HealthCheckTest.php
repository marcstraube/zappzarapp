<?php

declare(strict_types=1);

namespace Tests\App\Unit\Infrastructure;

use App\Infrastructure\DatabaseConfig;
use App\Infrastructure\HealthCheck;
use App\Infrastructure\TlsConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Redis;
use RuntimeException;

/**
 * Tests for HealthCheck — all I/O is intercepted via protected seam overrides.
 *
 * No real network connections, PDO connections or Redis connections are made.
 *
 * @SuppressWarnings("PHPMD.TooManyMethods")
 * @SuppressWarnings("PHPMD.TooManyPublicMethods")
 * @SuppressWarnings("PHPMD.ExcessiveClassComplexity")
 * @SuppressWarnings("PHPMD.ExcessiveClassLength")
 */
#[CoversClass(HealthCheck::class)]
#[UsesClass(TlsConfig::class)]
#[UsesClass(DatabaseConfig::class)]
final class HealthCheckTest extends TestCase
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
        foreach ([
            'ZAPPZARAPP_ENV',
            'ENABLE_PHP',
            'ENABLE_NODE',
            'ENABLE_DATABASE',
            'ENABLE_REDIS',
            'ENABLE_MERCURE',
            'ENABLE_MEILISEARCH',
            'ENABLE_ELASTICSEARCH',
            'ENABLE_MAILPIT',
            'ENABLE_RABBITMQ',
            'ENABLE_SEAWEEDFS',
            'DB_TYPE',
            'DB_HOST',
            'DB_PORT',
            'DB_NAME',
            'DB_USER',
            'DB_PASSWORD',
            'NODE_MODE',
            'REDIS_URL',
        ] as $var) {
            putenv($var);
            unset($_ENV[$var]);
        }

        // Default to development so that existing detailed-message assertions keep
        // passing after safeErrorMessage() was introduced (production returns generic
        // 'Connection failed'; tests run in development to see real messages).
        // Only putenv, not $_ENV: individual tests override ZAPPZARAPP_ENV via putenv (e.g.
        // 'staging', 'production'), and the constructor prefers $_ENV over getenv —
        // setting $_ENV here would shadow those per-test putenv overrides.
        putenv('ZAPPZARAPP_ENV=development');
    }

    // =========================================================================
    // constructor / parseBool / getEnvironment()
    // =========================================================================

    #[RunInSeparateProcess]
    #[Test]
    public function testDefaultEnvironmentValues(): void
    {
        // Unset ZAPPZARAPP_ENV explicitly to exercise the constructor's 'production' fallback
        // (clearEnvVars sets development as the test baseline for message assertions)
        putenv('ZAPPZARAPP_ENV');
        unset($_ENV['ZAPPZARAPP_ENV']);

        $check = new HealthCheck();
        $env   = $check->getEnvironment();

        $this->assertSame('production', $env['ZAPPZARAPP_ENV']);
        $this->assertTrue($env['ENABLE_PHP']);
        $this->assertFalse($env['ENABLE_NODE']);
        $this->assertFalse($env['ENABLE_DATABASE']);
        $this->assertFalse($env['ENABLE_REDIS']);
        $this->assertFalse($env['ENABLE_MERCURE']);
        $this->assertFalse($env['ENABLE_MEILISEARCH']);
        $this->assertFalse($env['ENABLE_ELASTICSEARCH']);
        $this->assertFalse($env['ENABLE_MAILPIT']);
        $this->assertFalse($env['ENABLE_RABBITMQ']);
        $this->assertFalse($env['ENABLE_SEAWEEDFS']);
        $this->assertNull($env['DB_TYPE']);
        $this->assertSame('none', $env['NODE_MODE']);
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testParseBoolTrueValues(): void
    {
        putenv('ENABLE_NODE=true');
        putenv('ENABLE_REDIS=TRUE');

        $check = new HealthCheck();
        $env   = $check->getEnvironment();

        $this->assertTrue($env['ENABLE_NODE']);
        // strtolower('TRUE') === 'true', so uppercase TRUE also maps to true
        $this->assertTrue($env['ENABLE_REDIS']);
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testParseBoolFalseValues(): void
    {
        putenv('ENABLE_NODE=false');
        putenv('ENABLE_REDIS=0');
        putenv('ENABLE_MERCURE=1');
        putenv('ENABLE_MAILPIT=yes');

        $check = new HealthCheck();
        $env   = $check->getEnvironment();

        $this->assertFalse($env['ENABLE_NODE']);
        $this->assertFalse($env['ENABLE_REDIS']);
        // '1' and 'yes' are not 'true' — parseBool only accepts the exact string 'true'
        $this->assertFalse($env['ENABLE_MERCURE']);
        $this->assertFalse($env['ENABLE_MAILPIT']);
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testEnvVarDbTypeIsRead(): void
    {
        putenv('DB_TYPE=postgres');

        $check = new HealthCheck();
        $env   = $check->getEnvironment();

        $this->assertSame('postgres', $env['DB_TYPE']);
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testEnvVarNodeModeIsRead(): void
    {
        putenv('NODE_MODE=api');

        $check = new HealthCheck();
        $env   = $check->getEnvironment();

        $this->assertSame('api', $env['NODE_MODE']);
    }

    // =========================================================================
    // checkAll() — all services disabled (default)
    // =========================================================================

    #[RunInSeparateProcess]
    #[Test]
    public function testCheckAllWithAllServicesDisabled(): void
    {
        $check  = new HealthCheck();
        $result = $check->checkAll();

        $this->assertSame('ok', $result['overall_status']);
        $this->assertSame('development', $result['environment']);
        $this->assertArrayHasKey('timestamp', $result);
        $this->assertArrayHasKey('services', $result);
        $this->assertArrayHasKey('features', $result);

        // php-fpm is always present
        $this->assertArrayHasKey('php-fpm', $result['services']);
        $this->assertSame('ok', $result['services']['php-fpm']['status']);
        $this->assertSame(PHP_VERSION, $result['services']['php-fpm']['version']);
        $this->assertTrue($result['services']['php-fpm']['enabled']);

        // Core services are disabled by default
        $this->assertSame('disabled', $result['services']['node-backend']['status']);
        $this->assertFalse($result['services']['node-backend']['enabled']);
        $this->assertSame('disabled', $result['services']['redis']['status']);
        $this->assertFalse($result['services']['redis']['enabled']);
        $this->assertSame('disabled', $result['services']['database']['status']);
        $this->assertFalse($result['services']['database']['enabled']);

        // Optional services should not appear when disabled
        $this->assertArrayNotHasKey('mercure', $result['services']);
        $this->assertArrayNotHasKey('meilisearch', $result['services']);
        $this->assertArrayNotHasKey('elasticsearch', $result['services']);
        $this->assertArrayNotHasKey('mailpit', $result['services']);
        $this->assertArrayNotHasKey('rabbitmq', $result['services']);
        $this->assertArrayNotHasKey('seaweedfs', $result['services']);
    }

    // =========================================================================
    // checkAll() — Node backend enabled via fake
    // =========================================================================

    #[RunInSeparateProcess]
    #[Test]
    public function testCheckAllNodeBackendHealthy(): void
    {
        putenv('ENABLE_NODE=true');
        putenv('NODE_MODE=api');

        $fake = $this->makeFakeHealthCheck(fetchUrlReturn: '{"status":"ok","node_version":"20.0.0","uptime":123,"environment":"production"}');

        $result = $fake->checkAll();

        $this->assertSame('ok', $result['overall_status']);
        $nodeService = $result['services']['node-backend'];
        $this->assertSame('ok', $nodeService['status']);
        $this->assertSame('20.0.0', $nodeService['version']);
        $this->assertSame('api', $nodeService['mode']);
        $this->assertTrue($nodeService['enabled']);
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testCheckAllNodeBackendNotReachable(): void
    {
        putenv('ENABLE_NODE=true');
        putenv('NODE_MODE=backend');

        $fake = $this->makeFakeHealthCheck(fetchUrlReturn: false);

        $result = $fake->checkAll();

        $this->assertSame('degraded', $result['overall_status']);
        $nodeService = $result['services']['node-backend'];
        $this->assertSame('error', $nodeService['status']);
        $this->assertSame('Node backend not reachable', $nodeService['message']);
        $this->assertTrue($nodeService['enabled']);
    }

    // =========================================================================
    // checkAll() — Redis enabled via fake
    // =========================================================================

    #[RunInSeparateProcess]
    #[Test]
    public function testCheckAllRedisExtensionNotInstalled(): void
    {
        putenv('ENABLE_REDIS=true');

        // Real HealthCheck - when redis extension is NOT loaded it records error without I/O
        $check = new HealthCheck();

        if (extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension is loaded; this test requires it to be absent.');
        }

        $result = $check->checkAll();

        $this->assertSame('degraded', $result['overall_status']);
        $redisService = $result['services']['redis'];
        $this->assertSame('error', $redisService['status']);
        $this->assertStringContainsString('Redis PHP extension not installed', $redisService['message']);
        $this->assertTrue($redisService['enabled']);
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testCheckAllRedisConnectionFails(): void
    {
        putenv('ENABLE_REDIS=true');

        $fake = $this->makeFakeHealthCheck(redisConnectionReturn: [
            'redis'  => null,
            'host'   => 'redis',
            'port'   => 6379,
            'useTls' => true,
            'error'  => 'Could not connect to Redis: connection refused',
        ]);

        $result = $fake->checkAll();

        $this->assertSame('degraded', $result['overall_status']);
        $redisService = $result['services']['redis'];
        $this->assertSame('error', $redisService['status']);
        $this->assertSame('Could not connect to Redis: connection refused', $redisService['message']);
        $this->assertTrue($redisService['enabled']);
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testCheckAllRedisHealthy(): void
    {
        putenv('ENABLE_REDIS=true');

        $fakeRedis = $this->createFakeRedis(pingReturn: '+PONG', redisVersion: '7.0.0');

        $fake = $this->makeFakeHealthCheck(redisConnectionReturn: [
            'redis'  => $fakeRedis,
            'host'   => 'redis',
            'port'   => 6379,
            'useTls' => false,
            'error'  => null,
        ]);

        $result = $fake->checkAll();

        $this->assertSame('ok', $result['overall_status']);
        $redisService = $result['services']['redis'];
        $this->assertSame('ok', $redisService['status']);
        $this->assertSame('7.0.0', $redisService['version']);
        $this->assertTrue($redisService['enabled']);
        $this->assertFalse($redisService['tls']);
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testCheckAllRedisPongTrueIsHealthy(): void
    {
        putenv('ENABLE_REDIS=true');

        // Some Redis clients return true instead of '+PONG' — both must be treated as ok
        $fakeRedis = $this->createFakeRedis(pingReturn: true, redisVersion: '6.2.0');

        $fake = $this->makeFakeHealthCheck(redisConnectionReturn: [
            'redis'  => $fakeRedis,
            'host'   => 'redis',
            'port'   => 6379,
            'useTls' => true,
            'error'  => null,
        ]);

        $result = $fake->checkAll();

        $this->assertSame('ok', $result['overall_status']);
        $this->assertSame('ok', $result['services']['redis']['status']);
        $this->assertTrue($result['services']['redis']['tls']);
    }

    // =========================================================================
    // checkAll() — Database enabled via env (no real connection needed)
    // =========================================================================

    #[RunInSeparateProcess]
    #[Test]
    public function testCheckAllDatabasePostgresExtensionNotInstalled(): void
    {
        putenv('ENABLE_DATABASE=true');
        putenv('DB_TYPE=postgres');
        putenv('DB_PASSWORD=testpass');

        if (extension_loaded('pdo_pgsql')) {
            $this->markTestSkipped('pdo_pgsql extension is loaded; this test requires it to be absent.');
        }

        $check  = new HealthCheck();
        $result = $check->checkAll();

        $this->assertSame('degraded', $result['overall_status']);
        $dbService = $result['services']['database'];
        $this->assertSame('error', $dbService['status']);
        $this->assertSame('postgres', $dbService['type']);
        $this->assertStringContainsString('extension not installed', $dbService['message']);
        $this->assertTrue($dbService['enabled']);
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testCheckAllDatabaseMysqlConnectionError(): void
    {
        putenv('ENABLE_DATABASE=true');
        putenv('DB_TYPE=mysql');
        putenv('DB_PASSWORD=testpass');
        putenv('DB_HOST=127.0.0.1');
        putenv('DB_PORT=1');  // Unreachable port — PDO throws immediately

        if (!extension_loaded('pdo_mysql')) {
            $this->markTestSkipped('pdo_mysql extension is not loaded; this test requires it.');
        }

        $check  = new HealthCheck();
        $result = $check->checkAll();

        // PDO will throw because no MySQL server runs at port 1
        $this->assertSame('degraded', $result['overall_status']);
        $dbService = $result['services']['database'];
        $this->assertSame('error', $dbService['status']);
        $this->assertSame('mysql', $dbService['type']);
        $this->assertTrue($dbService['enabled']);
    }

    // =========================================================================
    // checkAll() — Optional services via fake fetchUrl
    // =========================================================================

    #[RunInSeparateProcess]
    #[Test]
    public function testCheckAllMercureEnabled(): void
    {
        putenv('ENABLE_MERCURE=true');

        // TCP check via safeSocketOpen returning false → error status
        $fake = $this->makeFakeHealthCheck(socketReturn: false);

        $result = $fake->checkAll();

        $this->assertSame('degraded', $result['overall_status']);
        $this->assertArrayHasKey('mercure', $result['services']);
        $this->assertSame('error', $result['services']['mercure']['status']);
        $this->assertTrue($result['services']['mercure']['enabled']);
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testCheckAllMercureHealthy(): void
    {
        putenv('ENABLE_MERCURE=true');

        // safeSocketOpen returning a fake socket resource → connected = true
        $fake = $this->makeFakeHealthCheck(socketReturn: true);

        $result = $fake->checkAll();

        $this->assertSame('ok', $result['overall_status']);
        $this->assertArrayHasKey('mercure', $result['services']);
        $this->assertSame('ok', $result['services']['mercure']['status']);
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testCheckAllMeilisearchAvailable(): void
    {
        putenv('ENABLE_MEILISEARCH=true');

        $fake = $this->makeFakeHealthCheck(fetchUrlReturn: '{"status":"available"}');

        $result = $fake->checkAll();

        $this->assertSame('ok', $result['overall_status']);
        $this->assertArrayHasKey('meilisearch', $result['services']);
        $this->assertSame('ok', $result['services']['meilisearch']['status']);
        $this->assertTrue($result['services']['meilisearch']['enabled']);
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testCheckAllMeilisearchNotAvailable(): void
    {
        putenv('ENABLE_MEILISEARCH=true');

        $fake = $this->makeFakeHealthCheck(fetchUrlReturn: '{"status":"unavailable"}');

        $result = $fake->checkAll();

        $this->assertSame('degraded', $result['overall_status']);
        $this->assertSame('error', $result['services']['meilisearch']['status']);
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testCheckAllMeilisearchNotReachable(): void
    {
        putenv('ENABLE_MEILISEARCH=true');

        $fake = $this->makeFakeHealthCheck(fetchUrlReturn: false);

        $result = $fake->checkAll();

        $this->assertSame('degraded', $result['overall_status']);
        $meilisearch = $result['services']['meilisearch'];
        $this->assertSame('error', $meilisearch['status']);
        $this->assertSame('Meilisearch not reachable', $meilisearch['message']);
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testCheckAllElasticsearchGreenStatus(): void
    {
        putenv('ENABLE_ELASTICSEARCH=true');

        $fake = $this->makeFakeHealthCheck(fetchUrlReturn: '{"status":"green"}');

        $result = $fake->checkAll();

        $this->assertSame('ok', $result['overall_status']);
        $es = $result['services']['elasticsearch'];
        $this->assertSame('ok', $es['status']);
        $this->assertSame('green', $es['cluster_status']);
        $this->assertTrue($es['enabled']);
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testCheckAllElasticsearchYellowStatus(): void
    {
        putenv('ENABLE_ELASTICSEARCH=true');

        $fake = $this->makeFakeHealthCheck(fetchUrlReturn: '{"status":"yellow"}');

        $result = $fake->checkAll();

        // Yellow is treated as ok
        $this->assertSame('ok', $result['overall_status']);
        $this->assertSame('ok', $result['services']['elasticsearch']['status']);
        $this->assertSame('yellow', $result['services']['elasticsearch']['cluster_status']);
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testCheckAllElasticsearchRedStatus(): void
    {
        putenv('ENABLE_ELASTICSEARCH=true');

        $fake = $this->makeFakeHealthCheck(fetchUrlReturn: '{"status":"red"}');

        $result = $fake->checkAll();

        $this->assertSame('degraded', $result['overall_status']);
        $this->assertSame('error', $result['services']['elasticsearch']['status']);
        $this->assertSame('red', $result['services']['elasticsearch']['cluster_status']);
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testCheckAllElasticsearchNotReachable(): void
    {
        putenv('ENABLE_ELASTICSEARCH=true');

        $fake = $this->makeFakeHealthCheck(fetchUrlReturn: false);

        $result = $fake->checkAll();

        $this->assertSame('degraded', $result['overall_status']);
        $es = $result['services']['elasticsearch'];
        $this->assertSame('error', $es['status']);
        $this->assertSame('Elasticsearch not reachable', $es['message']);
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testCheckAllMailpitEnabled(): void
    {
        putenv('ENABLE_MAILPIT=true');

        $fake = $this->makeFakeHealthCheck(socketReturn: false);

        $result = $fake->checkAll();

        $this->assertSame('degraded', $result['overall_status']);
        $this->assertArrayHasKey('mailpit', $result['services']);
        $this->assertSame('error', $result['services']['mailpit']['status']);
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testCheckAllMailpitHealthy(): void
    {
        putenv('ENABLE_MAILPIT=true');

        $fake = $this->makeFakeHealthCheck(socketReturn: true);

        $result = $fake->checkAll();

        $this->assertSame('ok', $result['overall_status']);
        $this->assertSame('ok', $result['services']['mailpit']['status']);
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testCheckAllRabbitmqEnabled(): void
    {
        putenv('ENABLE_RABBITMQ=true');

        $fake = $this->makeFakeHealthCheck(socketReturn: false);

        $result = $fake->checkAll();

        $this->assertSame('degraded', $result['overall_status']);
        $this->assertArrayHasKey('rabbitmq', $result['services']);
        $this->assertSame('error', $result['services']['rabbitmq']['status']);
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testCheckAllRabbitmqHealthy(): void
    {
        putenv('ENABLE_RABBITMQ=true');

        $fake = $this->makeFakeHealthCheck(socketReturn: true);

        $result = $fake->checkAll();

        $this->assertSame('ok', $result['overall_status']);
        $this->assertSame('ok', $result['services']['rabbitmq']['status']);
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testCheckAllSeaweedfsAvailable(): void
    {
        putenv('ENABLE_SEAWEEDFS=true');

        $fake = $this->makeFakeHealthCheck(fetchUrlReturn: '{"IsLeader":true}');

        $result = $fake->checkAll();

        $this->assertSame('ok', $result['overall_status']);
        $this->assertArrayHasKey('seaweedfs', $result['services']);
        $this->assertSame('ok', $result['services']['seaweedfs']['status']);
        $this->assertTrue($result['services']['seaweedfs']['enabled']);
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testCheckAllSeaweedfsResponseMissingIsLeader(): void
    {
        putenv('ENABLE_SEAWEEDFS=true');

        $fake = $this->makeFakeHealthCheck(fetchUrlReturn: '{"other":"value"}');

        $result = $fake->checkAll();

        $this->assertSame('degraded', $result['overall_status']);
        $this->assertSame('error', $result['services']['seaweedfs']['status']);
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testCheckAllSeaweedfsNotReachable(): void
    {
        putenv('ENABLE_SEAWEEDFS=true');

        $fake = $this->makeFakeHealthCheck(fetchUrlReturn: false);

        $result = $fake->checkAll();

        $this->assertSame('degraded', $result['overall_status']);
        $seaweed = $result['services']['seaweedfs'];
        $this->assertSame('error', $seaweed['status']);
        $this->assertSame('SeaweedFS not reachable', $seaweed['message']);
    }

    // =========================================================================
    // updateOverallStatus() — degraded propagation
    // =========================================================================

    #[RunInSeparateProcess]
    #[Test]
    public function testOverallStatusRemainsOkWhenAllServicesDisabled(): void
    {
        $check  = new HealthCheck();
        $result = $check->checkAll();

        $this->assertSame('ok', $result['overall_status']);
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testOverallStatusBecomesDeградedOnFirstError(): void
    {
        putenv('ENABLE_NODE=true');
        putenv('NODE_MODE=api');

        $fake   = $this->makeFakeHealthCheck(fetchUrlReturn: false);
        $result = $fake->checkAll();

        $this->assertSame('degraded', $result['overall_status']);
    }

    // =========================================================================
    // checkReadiness() — all services disabled
    // =========================================================================

    #[RunInSeparateProcess]
    #[Test]
    public function testCheckReadinessAllDisabled(): void
    {
        $check  = new HealthCheck();
        $result = $check->checkReadiness();

        $this->assertSame('ok', $result['status']);
        $this->assertSame('php-backend', $result['service']);
        $this->assertSame('development', $result['environment']);
        $this->assertArrayHasKey('timestamp', $result);
        $this->assertArrayHasKey('uptime', $result);
        $this->assertIsInt($result['uptime']);
        $this->assertGreaterThanOrEqual(0, $result['uptime']);

        $this->assertSame('disabled', $result['checks']['database']['status']);
        $this->assertSame('disabled', $result['checks']['redis']['status']);
        $this->assertSame('disabled', $result['checks']['node-backend']['status']);
        $this->assertSame('disabled', $result['checks']['node-frontend']['status']);
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testCheckReadinessOmitsDeploymentDetailsInProduction(): void
    {
        putenv('ZAPPZARAPP_ENV=production');

        $check  = new HealthCheck();
        $result = $check->checkReadiness();

        $this->assertSame('ok', $result['status']);
        $this->assertArrayHasKey('checks', $result);
        $this->assertArrayNotHasKey('environment', $result);
        $this->assertArrayNotHasKey('uptime', $result);
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testCheckReadinessWithNodeBackendEnabled(): void
    {
        putenv('ENABLE_NODE=true');
        putenv('NODE_MODE=api');

        $fake = $this->makeFakeHealthCheck(fetchUrlReturn: '{"status":"ok"}');

        $result = $fake->checkReadiness();

        $this->assertSame('ok', $result['status']);
        $nodeCheck = $result['checks']['node-backend'];
        $this->assertSame('ok', $nodeCheck['status']);
        $this->assertArrayHasKey('latency_ms', $nodeCheck);
        $this->assertIsInt($nodeCheck['latency_ms']);
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testCheckReadinessNodeBackendUnreachable(): void
    {
        putenv('ENABLE_NODE=true');
        putenv('NODE_MODE=backend');

        $fake = $this->makeFakeHealthCheck(fetchUrlReturn: false);

        $result = $fake->checkReadiness();

        $this->assertSame('degraded', $result['status']);
        $this->assertSame('unhealthy', $result['checks']['node-backend']['status']);
        $this->assertSame('Node backend not reachable', $result['checks']['node-backend']['message']);
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testCheckReadinessWithNodeFrontendEnabled(): void
    {
        putenv('ENABLE_NODE=true');
        putenv('NODE_MODE=framework');

        $fake = $this->makeFakeHealthCheck(fetchUrlReturn: '');

        $result = $fake->checkReadiness();

        // node-backend is disabled (framework mode has no backend endpoint)
        $this->assertSame('disabled', $result['checks']['node-backend']['status']);

        // node-frontend is enabled (framework mode)
        $frontendCheck = $result['checks']['node-frontend'];
        $this->assertSame('ok', $frontendCheck['status']);
        $this->assertArrayHasKey('latency_ms', $frontendCheck);
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testCheckReadinessNodeFrontendUnreachable(): void
    {
        putenv('ENABLE_NODE=true');
        putenv('NODE_MODE=framework');

        $fake = $this->makeFakeHealthCheck(fetchUrlReturn: false);

        $result = $fake->checkReadiness();

        $this->assertSame('degraded', $result['status']);
        $this->assertSame('unhealthy', $result['checks']['node-frontend']['status']);
        $this->assertSame('Node frontend not reachable', $result['checks']['node-frontend']['message']);
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testCheckReadinessWithRedisExtensionMissing(): void
    {
        putenv('ENABLE_REDIS=true');

        if (extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension is loaded; this test requires it to be absent.');
        }

        $check  = new HealthCheck();
        $result = $check->checkReadiness();

        $this->assertSame('degraded', $result['status']);
        $this->assertSame('unhealthy', $result['checks']['redis']['status']);
        $this->assertSame('Redis PHP extension not installed', $result['checks']['redis']['message']);
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testCheckReadinessWithRedisConnectionFailure(): void
    {
        putenv('ENABLE_REDIS=true');

        $fake = $this->makeFakeHealthCheck(redisConnectionReturn: [
            'redis'  => null,
            'host'   => 'redis',
            'port'   => 6379,
            'useTls' => false,
            'error'  => 'Connection refused',
        ]);

        $result = $fake->checkReadiness();

        $this->assertSame('degraded', $result['status']);
        $this->assertSame('unhealthy', $result['checks']['redis']['status']);
        $this->assertSame('Connection refused', $result['checks']['redis']['message']);
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testCheckReadinessWithRedisHealthy(): void
    {
        putenv('ENABLE_REDIS=true');

        $fakeRedis = $this->createFakeRedis(pingReturn: '+PONG', redisVersion: '7.0.0');

        $fake = $this->makeFakeHealthCheck(redisConnectionReturn: [
            'redis'  => $fakeRedis,
            'host'   => 'redis',
            'port'   => 6379,
            'useTls' => false,
            'error'  => null,
        ]);

        $result = $fake->checkReadiness();

        $this->assertSame('ok', $result['status']);
        $redisCheck = $result['checks']['redis'];
        $this->assertSame('ok', $redisCheck['status']);
        $this->assertArrayHasKey('latency_ms', $redisCheck);
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testCheckReadinessDatabasePostgresExtensionMissing(): void
    {
        putenv('ENABLE_DATABASE=true');
        putenv('DB_TYPE=postgres');
        putenv('DB_PASSWORD=testpass');

        if (extension_loaded('pdo_pgsql')) {
            $this->markTestSkipped('pdo_pgsql extension is loaded; this test requires it to be absent.');
        }

        $check  = new HealthCheck();
        $result = $check->checkReadiness();

        $this->assertSame('degraded', $result['status']);
        $dbCheck = $result['checks']['database'];
        $this->assertSame('unhealthy', $dbCheck['status']);
        $this->assertStringContainsString('extension not installed', $dbCheck['message']);
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testCheckReadinessDatabaseMysqlConnectionError(): void
    {
        putenv('ENABLE_DATABASE=true');
        putenv('DB_TYPE=mysql');
        putenv('DB_PASSWORD=testpass');
        putenv('DB_HOST=127.0.0.1');
        putenv('DB_PORT=1');

        if (!extension_loaded('pdo_mysql')) {
            $this->markTestSkipped('pdo_mysql extension is not loaded; this test requires it.');
        }

        $check  = new HealthCheck();
        $result = $check->checkReadiness();

        $this->assertSame('degraded', $result['status']);
        $this->assertSame('unhealthy', $result['checks']['database']['status']);
    }

    // =========================================================================
    // isNodeBackendEnabled() / isNodeFrontendEnabled() via checkReadiness()
    // =========================================================================

    #[DataProvider('nodeBackendModeProvider')]
    #[RunInSeparateProcess]
    #[Test]
    public function testNodeBackendEnabledModes(string $mode, bool $backendEnabled, bool $frontendEnabled): void
    {
        putenv('ENABLE_NODE=true');
        putenv('NODE_MODE=' . $mode);

        $fake = $this->makeFakeHealthCheck(fetchUrlReturn: '{"status":"ok"}');

        $result = $fake->checkReadiness();

        $backendStatus  = $result['checks']['node-backend']['status'];
        $frontendStatus = $result['checks']['node-frontend']['status'];

        if ($backendEnabled) {
            $this->assertNotSame('disabled', $backendStatus, "Expected backend enabled for mode '{$mode}'");
        } else {
            $this->assertSame('disabled', $backendStatus, "Expected backend disabled for mode '{$mode}'");
        }

        if ($frontendEnabled) {
            $this->assertNotSame('disabled', $frontendStatus, "Expected frontend enabled for mode '{$mode}'");
        } else {
            $this->assertSame('disabled', $frontendStatus, "Expected frontend disabled for mode '{$mode}'");
        }
    }

    /**
     * @return array<string, array{string, bool, bool}>
     */
    public static function nodeBackendModeProvider(): array
    {
        return [
            'api mode'           => ['api', true, false],
            'backend mode'       => ['backend', true, false],
            'assets-api mode'    => ['assets-api', true, false],
            'framework-api mode' => ['framework-api', true, true],
            'framework mode'     => ['framework', false, true],
            'none mode'          => ['none', false, false],
            'assets mode'        => ['assets', false, false],
        ];
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testNodeBackendDisabledWhenEnableNodeFalse(): void
    {
        putenv('ENABLE_NODE=false');
        putenv('NODE_MODE=api');

        $check  = new HealthCheck();
        $result = $check->checkReadiness();

        $this->assertSame('disabled', $result['checks']['node-backend']['status']);
        $this->assertSame('disabled', $result['checks']['node-frontend']['status']);
    }

    // =========================================================================
    // getEnvironment() — ZAPPZARAPP_ENV variable
    // =========================================================================

    #[RunInSeparateProcess]
    #[Test]
    public function testGetEnvironmentReturnsCorrectEnv(): void
    {
        putenv('ZAPPZARAPP_ENV=staging');

        $check = new HealthCheck();
        $env   = $check->getEnvironment();

        $this->assertSame('staging', $env['ZAPPZARAPP_ENV']);
    }

    // =========================================================================
    // Uptime
    // =========================================================================

    #[RunInSeparateProcess]
    #[Test]
    public function testCheckReadinessUptimeIsNonNegative(): void
    {
        $check  = new HealthCheck();
        $result = $check->checkReadiness();

        $this->assertIsInt($result['uptime']);
        $this->assertGreaterThanOrEqual(0, $result['uptime']);
    }

    // =========================================================================
    // Socket error message propagation (Mercure via safeSocketOpen)
    // =========================================================================

    #[RunInSeparateProcess]
    #[Test]
    public function testMercureSocketErrorMessagePropagated(): void
    {
        putenv('ENABLE_MERCURE=true');

        $fake = new class extends HealthCheck {
            /**
             * @param-out int $errno
             * @param-out string $errstr
             */
            protected function safeSocketOpen(
                string $host,
                int $port,
                ?int &$errno,
                ?string &$errstr,
                int $timeout = 1,
            ): mixed {
                unset($host, $port, $timeout);
                $errno  = 111;
                $errstr = 'Connection refused';

                return false;
            }

            protected function fetchUrl(string $url, mixed $context = null): string|false
            {
                unset($url, $context);

                return false;
            }
        };

        $result = $fake->checkAll();

        $this->assertSame('degraded', $result['overall_status']);
        $mercure = $result['services']['mercure'];
        $this->assertSame('error', $mercure['status']);
        $this->assertStringContainsString('Connection refused', $mercure['message']);
        $this->assertStringContainsString('111', $mercure['message']);
    }

    // =========================================================================
    // Real safeSocketOpen — exercises the actual fsockopen wrapper
    // =========================================================================

    #[RunInSeparateProcess]
    #[Test]
    public function testRealSafeSocketOpenWithUnreachablePort(): void
    {
        putenv('ENABLE_MERCURE=true');

        // Use only fetchUrl override; safeSocketOpen stays real so its lines are covered.
        $fake = new class extends HealthCheck {
            protected function fetchUrl(string $url, mixed $context = null): string|false
            {
                unset($url, $context);

                return false;
            }
        };

        $result = $fake->checkAll();

        // mercure TCP check will fail (no real mercure host) through real safeSocketOpen
        $this->assertArrayHasKey('mercure', $result['services']);
        $this->assertSame('error', $result['services']['mercure']['status']);
    }

    // =========================================================================
    // Real fetchUrl — exercises the actual file_get_contents wrapper
    // =========================================================================

    #[RunInSeparateProcess]
    #[Test]
    public function testRealFetchUrlWithUnreachableUrl(): void
    {
        putenv('ENABLE_MEILISEARCH=true');

        // Use only safeSocketOpen override; fetchUrl stays real so its lines are covered.
        $fake = new class extends HealthCheck {
            /**
             * @param-out int|null $errno
             * @param-out string|null $errstr
             */
            protected function safeSocketOpen(
                string $host,
                int $port,
                ?int &$errno,
                ?string &$errstr,
                int $timeout = 1,
            ): mixed {
                unset($host, $port, $errno, $errstr, $timeout);

                return false;
            }
        };

        $result = $fake->checkAll();

        // meilisearch HTTP check will fail (no real meilisearch host) through real fetchUrl
        $this->assertArrayHasKey('meilisearch', $result['services']);
        $this->assertSame('error', $result['services']['meilisearch']['status']);
    }

    // =========================================================================
    // Exception path in checkNodeBackend (catch block)
    // =========================================================================

    #[RunInSeparateProcess]
    #[Test]
    public function testCheckAllNodeBackendExceptionCaughtAsError(): void
    {
        putenv('ENABLE_NODE=true');
        putenv('NODE_MODE=api');

        // fetchUrl throws — triggers the catch(Exception $exception) in checkNodeBackend
        $fake = new class extends HealthCheck {
            protected function fetchUrl(string $url, mixed $context = null): string|false
            {
                unset($url, $context);
                throw new RuntimeException('simulated network failure');
            }
        };

        $result = $fake->checkAll();

        $this->assertSame('degraded', $result['overall_status']);
        $nodeService = $result['services']['node-backend'];
        $this->assertSame('error', $nodeService['status']);
        $this->assertSame('simulated network failure', $nodeService['message']);
    }

    // =========================================================================
    // Exception path in checkRedis (catch block around ping/info)
    // =========================================================================

    #[RunInSeparateProcess]
    #[Test]
    public function testCheckAllRedisExceptionDuringPing(): void
    {
        putenv('ENABLE_REDIS=true');

        $throwingRedis = $this->createStub(Redis::class);
        $throwingRedis->method('ping')->willThrowException(new RuntimeException('Connection lost'));

        $fake = $this->makeFakeHealthCheck(redisConnectionReturn: [
            'redis'  => $throwingRedis,
            'host'   => 'redis',
            'port'   => 6379,
            'useTls' => false,
            'error'  => null,
        ]);

        $result = $fake->checkAll();

        $this->assertSame('degraded', $result['overall_status']);
        $redisService = $result['services']['redis'];
        $this->assertSame('error', $redisService['status']);
        $this->assertSame('Connection lost', $redisService['message']);
    }

    // =========================================================================
    // Exception path in checkNodeBackendWithLatency (catch block)
    // =========================================================================

    #[RunInSeparateProcess]
    #[Test]
    public function testCheckReadinessNodeBackendExceptionCaught(): void
    {
        putenv('ENABLE_NODE=true');
        putenv('NODE_MODE=backend');

        $fake = new class extends HealthCheck {
            protected function fetchUrl(string $url, mixed $context = null): string|false
            {
                unset($url, $context);
                throw new RuntimeException('latency check failed');
            }
        };

        $result = $fake->checkReadiness();

        $this->assertSame('degraded', $result['status']);
        $this->assertSame('unhealthy', $result['checks']['node-backend']['status']);
        $this->assertSame('latency check failed', $result['checks']['node-backend']['message']);
    }

    // =========================================================================
    // Exception path in checkNodeFrontendWithLatency (catch block)
    // =========================================================================

    #[RunInSeparateProcess]
    #[Test]
    public function testCheckReadinessNodeFrontendExceptionCaught(): void
    {
        putenv('ENABLE_NODE=true');
        putenv('NODE_MODE=framework');

        $fake = new class extends HealthCheck {
            protected function fetchUrl(string $url, mixed $context = null): string|false
            {
                unset($url, $context);
                throw new RuntimeException('frontend check failed');
            }
        };

        $result = $fake->checkReadiness();

        $this->assertSame('degraded', $result['status']);
        $this->assertSame('unhealthy', $result['checks']['node-frontend']['status']);
        $this->assertSame('frontend check failed', $result['checks']['node-frontend']['message']);
    }

    // =========================================================================
    // Exception path in checkRedisWithLatency (catch block)
    // =========================================================================

    #[RunInSeparateProcess]
    #[Test]
    public function testCheckReadinessRedisExceptionDuringPing(): void
    {
        putenv('ENABLE_REDIS=true');

        $throwingRedis = $this->createStub(Redis::class);
        $throwingRedis->method('ping')->willThrowException(new RuntimeException('ping failed'));

        $fake = $this->makeFakeHealthCheck(redisConnectionReturn: [
            'redis'  => $throwingRedis,
            'host'   => 'redis',
            'port'   => 6379,
            'useTls' => false,
            'error'  => null,
        ]);

        $result = $fake->checkReadiness();

        $this->assertSame('degraded', $result['status']);
        $this->assertSame('unhealthy', $result['checks']['redis']['status']);
        $this->assertSame('ping failed', $result['checks']['redis']['message']);
    }

    // =========================================================================
    // Production mode — safeErrorMessage() returns generic 'Connection failed'
    // =========================================================================

    #[RunInSeparateProcess]
    #[Test]
    public function testProductionNodeBackendExceptionReturnsGenericMessage(): void
    {
        putenv('ZAPPZARAPP_ENV=production');
        $_ENV['ZAPPZARAPP_ENV'] = 'production';
        putenv('ENABLE_NODE=true');
        putenv('NODE_MODE=api');

        $fake = new class extends HealthCheck {
            protected function fetchUrl(string $url, mixed $context = null): string|false
            {
                unset($url, $context);
                throw new RuntimeException('redis://internal-host:6379 TLS handshake failed');
            }

            protected function logError(string $message): void
            {
                unset($message);
            }
        };

        $result = $fake->checkAll();

        $this->assertSame('degraded', $result['overall_status']);
        $nodeService = $result['services']['node-backend'];
        $this->assertSame('error', $nodeService['status']);
        $this->assertSame('Connection failed', $nodeService['message']);
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testProductionNodeBackendWithLatencyExceptionReturnsGenericMessage(): void
    {
        putenv('ZAPPZARAPP_ENV=production');
        $_ENV['ZAPPZARAPP_ENV'] = 'production';
        putenv('ENABLE_NODE=true');
        putenv('NODE_MODE=backend');

        $fake = new class extends HealthCheck {
            protected function fetchUrl(string $url, mixed $context = null): string|false
            {
                unset($url, $context);
                throw new RuntimeException('10.0.0.5:3000 connection refused');
            }

            protected function logError(string $message): void
            {
                unset($message);
            }
        };

        $result = $fake->checkReadiness();

        $this->assertSame('degraded', $result['status']);
        $nodeCheck = $result['checks']['node-backend'];
        $this->assertSame('unhealthy', $nodeCheck['status']);
        $this->assertSame('Connection failed', $nodeCheck['message']);
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testProductionRedisExceptionReturnsGenericMessage(): void
    {
        putenv('ZAPPZARAPP_ENV=production');
        $_ENV['ZAPPZARAPP_ENV'] = 'production';
        putenv('ENABLE_REDIS=true');

        $throwingRedis = $this->createStub(Redis::class);
        $throwingRedis->method('ping')->willThrowException(new RuntimeException('redis://10.0.0.3:6379 auth failed'));

        $fake = $this->makeFakeHealthCheck(redisConnectionReturn: [
            'redis'  => $throwingRedis,
            'host'   => 'redis',
            'port'   => 6379,
            'useTls' => false,
            'error'  => null,
        ]);

        $result = $fake->checkAll();

        $this->assertSame('degraded', $result['overall_status']);
        $redisService = $result['services']['redis'];
        $this->assertSame('error', $redisService['status']);
        $this->assertSame('Connection failed', $redisService['message']);
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testProductionRedisConnectionNullReturnsGenericMessage(): void
    {
        putenv('ZAPPZARAPP_ENV=production');
        $_ENV['ZAPPZARAPP_ENV'] = 'production';
        putenv('ENABLE_REDIS=true');

        $fake = $this->makeFakeHealthCheck(redisConnectionReturn: [
            'redis'  => null,
            'host'   => 'redis',
            'port'   => 6379,
            'useTls' => true,
            'error'  => 'Could not connect to Redis: 10.0.0.3 TLS verify failed',
        ]);

        $result = $fake->checkAll();

        $this->assertSame('degraded', $result['overall_status']);
        $redisService = $result['services']['redis'];
        $this->assertSame('error', $redisService['status']);
        $this->assertSame('Connection failed', $redisService['message']);
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testProductionRedisWithLatencyConnectionNullReturnsGenericMessage(): void
    {
        putenv('ZAPPZARAPP_ENV=production');
        $_ENV['ZAPPZARAPP_ENV'] = 'production';
        putenv('ENABLE_REDIS=true');

        $fake = $this->makeFakeHealthCheck(redisConnectionReturn: [
            'redis'  => null,
            'host'   => 'redis',
            'port'   => 6379,
            'useTls' => false,
            'error'  => 'Could not connect to Redis: 10.0.0.3 connection refused',
        ]);

        $result = $fake->checkReadiness();

        $this->assertSame('degraded', $result['status']);
        $redisCheck = $result['checks']['redis'];
        $this->assertSame('unhealthy', $redisCheck['status']);
        $this->assertSame('Connection failed', $redisCheck['message']);
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testProductionDatabaseExceptionReturnsGenericMessage(): void
    {
        putenv('ZAPPZARAPP_ENV=production');
        $_ENV['ZAPPZARAPP_ENV'] = 'production';
        putenv('ENABLE_DATABASE=true');
        putenv('DB_TYPE=mysql');
        putenv('DB_PASSWORD=testpass');
        putenv('DB_HOST=127.0.0.1');
        putenv('DB_PORT=1');

        if (!extension_loaded('pdo_mysql')) {
            $this->markTestSkipped('pdo_mysql extension is not loaded; this test requires it.');
        }

        $check = new class extends HealthCheck {
            protected function logError(string $message): void
            {
                unset($message);
            }
        };
        $result = $check->checkAll();

        $this->assertSame('degraded', $result['overall_status']);
        $dbService = $result['services']['database'];
        $this->assertSame('error', $dbService['status']);
        // In production the PDO exception message (which contains host/port) is hidden
        $this->assertSame('Connection failed', $dbService['message']);
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testProductionTcpSocketErrorReturnsGenericMessage(): void
    {
        putenv('ZAPPZARAPP_ENV=production');
        $_ENV['ZAPPZARAPP_ENV'] = 'production';
        putenv('ENABLE_MERCURE=true');

        $fake = new class extends HealthCheck {
            /**
             * @param-out int $errno
             * @param-out string $errstr
             */
            protected function safeSocketOpen(
                string $host,
                int $port,
                ?int &$errno,
                ?string &$errstr,
                int $timeout = 1,
            ): mixed {
                unset($host, $port, $timeout);
                $errno  = 111;
                $errstr = 'Connection refused to 10.0.0.7';

                return false;
            }

            protected function fetchUrl(string $url, mixed $context = null): string|false
            {
                unset($url, $context);

                return false;
            }
        };

        $result = $fake->checkAll();

        $this->assertSame('degraded', $result['overall_status']);
        $mercure = $result['services']['mercure'];
        $this->assertSame('error', $mercure['status']);
        // In production the OS-level errstr (containing internal IP) must be hidden
        $this->assertSame('Connection failed', $mercure['message']);
    }

    // =========================================================================
    // Helpers
    // =========================================================================
    /**
     * Build a HealthCheck subclass that intercepts I/O seams.
     *
     * @param array{redis: Redis|null, host: string, port: int, useTls: bool, error: string|null}|null $redisConnectionReturn
     */
    private function makeFakeHealthCheck(
        string|false|null $fetchUrlReturn = null,
        ?array $redisConnectionReturn = null,
        bool|null $socketReturn = null,
    ): HealthCheck {
        $capturedFetch  = $fetchUrlReturn;
        $capturedRedis  = $redisConnectionReturn;
        $capturedSocket = $socketReturn;

        return new class($capturedFetch, $capturedRedis, $capturedSocket) extends HealthCheck {
            /** @param array{redis: Redis|null, host: string, port: int, useTls: bool, error: string|null}|null $redisConnectionReturn */
            public function __construct(
                private readonly string|false|null $fetchUrlReturn,
                private readonly ?array $redisConnectionReturn,
                private readonly bool|null $socketReturn,
            ) {
                parent::__construct();
            }

            protected function fetchUrl(string $url, mixed $context = null): string|false
            {
                unset($url, $context);

                return $this->fetchUrlReturn ?? false;
            }

            /** @return array{redis: Redis|null, host: string, port: int, useTls: bool, error: string|null} */
            protected function createRedisConnection(): array
            {
                if ($this->redisConnectionReturn !== null) {
                    return $this->redisConnectionReturn;
                }

                return [
                    'redis'  => null,
                    'host'   => 'redis',
                    'port'   => 6379,
                    'useTls' => false,
                    'error'  => 'Not configured',
                ];
            }

            /**
             * @param-out null $errno
             * @param-out null $errstr
             */
            protected function safeSocketOpen(
                string $host,
                int $port,
                ?int &$errno,
                ?string &$errstr,
                int $timeout = 1,
            ): mixed {
                unset($host, $port, $timeout);
                $errno  = null;
                $errstr = null;

                if ($this->socketReturn === true) {
                    // Return a real in-memory stream so fclose() works
                    return fopen('php://memory', 'r');
                }

                return false;
            }

            // No-op: keep the production error_log() out of the test output
            // (beStrictAboutOutputDuringTests).
            protected function logError(string $message): void
            {
                unset($message);
            }
        };
    }

    /**
     * Build a partial fake Redis that satisfies the ping/info/close interface.
     */
    private function createFakeRedis(string|bool $pingReturn, string $redisVersion): Redis
    {
        $stub = $this->createStub(Redis::class);
        $stub->method('ping')->willReturn($pingReturn);
        $stub->method('info')->willReturn(['redis_version' => $redisVersion]);
        $stub->method('close')->willReturn(true);

        return $stub;
    }
}
