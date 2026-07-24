<?php

declare(strict_types=1);

namespace Tests\DevDashboard\Services;

use DevDashboard\Services\HealthCheckService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @covers \DevDashboard\Services\HealthCheckService
 * @SuppressWarnings("PHPMD.TooManyPublicMethods")
 */
class HealthCheckServiceTest extends TestCase
{
    private HealthCheckService $service;

    protected function setUp(): void
    {
        parent::setUp();
        require_once __DIR__ . '/../../../../src/php/DevDashboard/Services/HealthCheckService.php';
        $this->service = new HealthCheckService();
    }

    #[Test]
    public function testGetOverallStatus(): void
    {
        $status = $this->service->getOverallStatus();

        $this->assertArrayHasKey('status', $status);
        $this->assertArrayHasKey('healthy_count', $status);
        $this->assertArrayHasKey('unhealthy_count', $status);
        $this->assertArrayHasKey('timestamp', $status);

        $this->assertContains($status['status'], ['healthy', 'degraded', 'unhealthy']);
        $this->assertIsInt($status['healthy_count']);
        $this->assertIsInt($status['unhealthy_count']);
        $this->assertIsString($status['timestamp']);
    }

    #[Test]
    public function testGetServicesReturnsCategories(): void
    {
        $services = $this->service->getServices();

        $this->assertArrayHasKey('core', $services);
        $this->assertArrayHasKey('data', $services);
        $this->assertArrayHasKey('optional', $services);
    }

    #[Test]
    public function testGetServicesCoreContainsNginx(): void
    {
        $services = $this->service->getServices();

        // Nginx is always in core
        $this->assertArrayHasKey('nginx', $services['core']);

        // Verify service structure
        $nginx = $services['core']['nginx'];
        $this->assertArrayHasKey('name', $nginx);
        $this->assertArrayHasKey('description', $nginx);
        $this->assertArrayHasKey('status', $nginx);
        $this->assertArrayHasKey('port', $nginx);
        $this->assertArrayHasKey('details', $nginx);

        $this->assertContains($nginx['status'], ['running', 'stopped']);
    }

    #[Test]
    public function testGetServicesDataContainsDatabase(): void
    {
        $services = $this->service->getServices();

        // Should have at least one database (postgres or mariadb)
        $dataServices = $services['data'];
        $this->assertNotEmpty($dataServices);

        $hasDatabase = isset($dataServices['postgres']) || isset($dataServices['mariadb']);
        $this->assertTrue($hasDatabase, 'Data services should contain postgres or mariadb');
    }

    #[Test]
    public function testGetConnectionsReturnsDatabase(): void
    {
        $connections = $this->service->getConnections();

        $this->assertArrayHasKey('database', $connections);

        $db = $connections['database'];
        $this->assertArrayHasKey('connected', $db);
        $this->assertArrayHasKey('type', $db);
        $this->assertIsBool($db['connected']);

        if ($db['connected']) {
            $this->assertArrayHasKey('host', $db);
            $this->assertArrayHasKey('port', $db);
            $this->assertArrayHasKey('database', $db);
            $this->assertArrayHasKey('version', $db);
        } else {
            $this->assertArrayHasKey('error', $db);
        }
    }

    #[Test]
    public function testGetConnectionsReturnsRedis(): void
    {
        // Skip if Redis is disabled
        if (getenv('ENABLE_REDIS') === 'false') {
            $this->markTestSkipped('Redis is disabled');
        }

        $connections = $this->service->getConnections();

        $this->assertArrayHasKey('redis', $connections);

        $redis = $connections['redis'];
        $this->assertArrayHasKey('connected', $redis);
        $this->assertArrayHasKey('type', $redis);
        $this->assertEquals('Redis', $redis['type']);
    }

    #[Test]
    public function testGetSslInfoWhenCertificateDoesNotExist(): void
    {
        $ssl = $this->service->getSslInfo();

        $this->assertArrayHasKey('exists', $ssl);

        if (!$ssl['exists']) {
            $this->assertArrayHasKey('message', $ssl);
            $this->assertIsString($ssl['message']);
        }
    }

    #[Test]
    public function testGetSslInfoStructure(): void
    {
        $ssl = $this->service->getSslInfo();

        $this->assertArrayHasKey('exists', $ssl);
        $this->assertIsBool($ssl['exists']);
        $this->assertArrayHasKey('certificates', $ssl);
        $this->assertIsArray($ssl['certificates']);

        if ($ssl['exists']) {
            // If certificates exist, verify structure of each certificate
            foreach ($ssl['certificates'] as $key => $cert) {
                $this->assertIsString($key);
                $this->assertArrayHasKey('name', $cert);
                $this->assertArrayHasKey('valid', $cert);
                $this->assertIsString($cert['name']);
                $this->assertIsBool($cert['valid']);

                if ($cert['valid']) {
                    $this->assertArrayHasKey('subject', $cert);
                    $this->assertArrayHasKey('issuer', $cert);
                    $this->assertArrayHasKey('valid_from', $cert);
                    $this->assertArrayHasKey('valid_to', $cert);
                    $this->assertArrayHasKey('days_until_expiry', $cert);
                    $this->assertArrayHasKey('expires_soon', $cert);

                    $this->assertIsString($cert['subject']);
                    $this->assertIsString($cert['issuer']);
                    $this->assertIsNumeric($cert['days_until_expiry']);
                    $this->assertIsBool($cert['expires_soon']);
                }
            }
        }
    }

    #[Test]
    public function testConnectionWithInvalidCredentials(): void
    {
        // Temporarily set invalid database credentials
        $originalHost = getenv('DB_HOST');
        $originalPort = getenv('DB_PORT');

        putenv('DB_HOST=invalid_host');
        putenv('DB_PORT=9999');

        // Create new service instance to pick up new env vars
        $service     = new HealthCheckService();
        $connections = $service->getConnections();

        // Database should fail to connect
        $this->assertArrayHasKey('database', $connections);
        $this->assertFalse($connections['database']['connected']);
        $this->assertArrayHasKey('error', $connections['database']);

        // Restore original environment
        if ($originalHost !== false) {
            putenv('DB_HOST=' . $originalHost);
        } else {
            putenv('DB_HOST');
        }

        if ($originalPort !== false) {
            putenv('DB_PORT=' . $originalPort);
        } else {
            putenv('DB_PORT');
        }
    }

    #[Test]
    public function testOverallStatusDeterminesHealthCorrectly(): void
    {
        $status = $this->service->getOverallStatus();

        // If no unhealthy services, status should be healthy
        if ($status['unhealthy_count'] === 0) {
            $this->assertEquals('healthy', $status['status']);
        } else {
            $this->assertEquals('degraded', $status['status']);
        }

        // Counts should be non-negative
        $this->assertGreaterThanOrEqual(0, $status['healthy_count']);
        $this->assertGreaterThanOrEqual(0, $status['unhealthy_count']);
    }

    #[Test]
    public function testServiceStructureIsCorrect(): void
    {
        $services = $this->service->getServices();

        foreach ($services as $categoryServices) {
            $this->assertIsArray($categoryServices);

            foreach ($categoryServices as $key => $service) {
                $this->assertIsString($key);
                $this->assertIsArray($service);
                $this->assertArrayHasKey('name', $service);
                $this->assertArrayHasKey('description', $service);
                $this->assertArrayHasKey('status', $service);
                $this->assertContains($service['status'], ['running', 'stopped']);
            }
        }
    }
}
