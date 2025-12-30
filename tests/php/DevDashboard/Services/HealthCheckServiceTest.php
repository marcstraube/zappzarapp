<?php

declare(strict_types=1);

namespace Tests\DevDashboard\Services;

use DevDashboard\Services\HealthCheckService;
use PHPUnit\Framework\TestCase;

/**
 * @covers \DevDashboard\Services\HealthCheckService
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

    public function testGetOverallStatus(): void
    {
        $status = $this->service->getOverallStatus();

        $this->assertIsArray($status);
        $this->assertArrayHasKey('status', $status);
        $this->assertArrayHasKey('healthy_count', $status);
        $this->assertArrayHasKey('unhealthy_count', $status);
        $this->assertArrayHasKey('timestamp', $status);

        $this->assertContains($status['status'], ['healthy', 'degraded', 'unhealthy']);
        $this->assertIsInt($status['healthy_count']);
        $this->assertIsInt($status['unhealthy_count']);
        $this->assertIsString($status['timestamp']);
    }

    public function testGetContainerStatus(): void
    {
        $containers = $this->service->getContainerStatus();

        $this->assertIsArray($containers);

        // Verify nginx is always checked
        $this->assertArrayHasKey('nginx', $containers);

        // Verify container structure
        foreach ($containers as $name => $container) {
            $this->assertIsArray($container);
            $this->assertArrayHasKey('name', $container);
            $this->assertArrayHasKey('status', $container);
            $this->assertArrayHasKey('health', $container);

            $this->assertEquals($name, $container['name']);
        }

        // Should have at least nginx + other enabled services
        $this->assertGreaterThanOrEqual(1, count($containers));
    }

    public function testGetDatabaseStatus(): void
    {
        $databases = $this->service->getDatabaseStatus();

        $this->assertIsArray($databases);

        // Only checks the configured database type (DB_TYPE environment variable)
        // Should have at least one database checked
        $this->assertGreaterThanOrEqual(1, count($databases));

        foreach ($databases as $dbName => $db) {
            $this->assertIsArray($db);
            $this->assertArrayHasKey('connected', $db);
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
    }

    public function testGetServiceStatus(): void
    {
        $services = $this->service->getServiceStatus();

        $this->assertIsArray($services);
        $this->assertArrayHasKey('php_fpm', $services);
        $this->assertArrayHasKey('node', $services);
        $this->assertArrayHasKey('nginx', $services);

        // Verify PHP-FPM service
        $phpFpm = $services['php_fpm'];
        $this->assertArrayHasKey('running', $phpFpm);
        $this->assertArrayHasKey('sapi', $phpFpm);
        $this->assertArrayHasKey('version', $phpFpm);

        $this->assertIsBool($phpFpm['running']);
        $this->assertEquals(PHP_SAPI, $phpFpm['sapi']);
        $this->assertEquals(PHP_VERSION, $phpFpm['version']);
    }

    public function testGetSslInfoWhenCertificateDoesNotExist(): void
    {
        $ssl = $this->service->getSslInfo();

        $this->assertIsArray($ssl);
        $this->assertArrayHasKey('exists', $ssl);

        if (!$ssl['exists']) {
            $this->assertArrayHasKey('message', $ssl);
            $this->assertIsString($ssl['message']);
        }
    }

    public function testGetSslInfoStructure(): void
    {
        $ssl = $this->service->getSslInfo();

        $this->assertIsArray($ssl);
        $this->assertArrayHasKey('exists', $ssl);
        $this->assertIsBool($ssl['exists']);

        if ($ssl['exists']) {
            // If certificate exists, verify full structure
            $this->assertArrayHasKey('valid', $ssl);
            $this->assertIsBool($ssl['valid']);

            if ($ssl['valid']) {
                $this->assertArrayHasKey('subject', $ssl);
                $this->assertArrayHasKey('issuer', $ssl);
                $this->assertArrayHasKey('valid_from', $ssl);
                $this->assertArrayHasKey('valid_to', $ssl);
                $this->assertArrayHasKey('days_until_expiry', $ssl);
                $this->assertArrayHasKey('expires_soon', $ssl);

                $this->assertIsString($ssl['subject']);
                $this->assertIsString($ssl['issuer']);
                $this->assertIsNumeric($ssl['days_until_expiry']);
                $this->assertIsBool($ssl['expires_soon']);
            }
        }
    }

    public function testDatabaseConnectionWithInvalidCredentials(): void
    {
        // Temporarily set invalid database credentials
        $originalHost = getenv('DB_HOST');
        $originalPort = getenv('DB_PORT');

        putenv('DB_HOST=invalid_host');
        putenv('DB_PORT=9999');

        $databases = $this->service->getDatabaseStatus();

        // Both PostgreSQL and MariaDB should fail to connect
        foreach ($databases as $db) {
            $this->assertFalse($db['connected']);
            $this->assertArrayHasKey('error', $db);
        }

        // Restore original environment
        if ($originalHost !== false) {
            putenv("DB_HOST={$originalHost}");
        } else {
            putenv('DB_HOST');
        }

        if ($originalPort !== false) {
            putenv("DB_PORT={$originalPort}");
        } else {
            putenv('DB_PORT');
        }
    }

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
}
