<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure;

use App\Infrastructure\DatabaseConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * Tests for DatabaseConfig (12-Factor App compliant configuration)
 */
#[CoversClass(DatabaseConfig::class)]
final class DatabaseConfigTest extends TestCase
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
        putenv('DATABASE_URL');
        putenv('DB_TYPE');
        putenv('DB_HOST');
        putenv('DB_PORT');
        putenv('DB_NAME');
        putenv('DB_USER');
        putenv('DB_PASSWORD');
        putenv('DB_PASSWORD_FILE');
    }

    #[RunInSeparateProcess]
    public function testParsePostgresUrl(): void
    {
        putenv('DATABASE_URL=postgresql://myuser:mypass@dbhost:5432/mydb');

        $config = new DatabaseConfig();

        $this->assertEquals('postgres', $config->getType());
        $this->assertEquals('dbhost', $config->getHost());
        $this->assertEquals(5432, $config->getPort());
        $this->assertEquals('mydb', $config->getName());
        $this->assertEquals('myuser', $config->getUser());
        $this->assertEquals('mypass', $config->getPassword());
        $this->assertTrue($config->isPostgres());
        $this->assertFalse($config->isMariaDb());
    }

    #[RunInSeparateProcess]
    public function testParsePostgresUrlWithShortScheme(): void
    {
        putenv('DATABASE_URL=postgres://user:pass@host:5432/db');

        $config = new DatabaseConfig();

        $this->assertEquals('postgres', $config->getType());
        $this->assertTrue($config->isPostgres());
    }

    #[RunInSeparateProcess]
    public function testParseMysqlUrl(): void
    {
        putenv('DATABASE_URL=mysql://myuser:mypass@dbhost:3306/mydb');

        $config = new DatabaseConfig();

        $this->assertEquals('mysql', $config->getType());
        $this->assertEquals('dbhost', $config->getHost());
        $this->assertEquals(3306, $config->getPort());
        $this->assertEquals('mydb', $config->getName());
        $this->assertEquals('myuser', $config->getUser());
        $this->assertEquals('mypass', $config->getPassword());
        $this->assertFalse($config->isPostgres());
        $this->assertTrue($config->isMariaDb());
    }

    #[RunInSeparateProcess]
    public function testParseUrlWithEncodedPassword(): void
    {
        // Password: p@ss:word/123 (URL-encoded: p%40ss%3Aword%2F123)
        putenv('DATABASE_URL=postgresql://user:p%40ss%3Aword%2F123@host:5432/db');

        $config = new DatabaseConfig();

        $this->assertEquals('p@ss:word/123', $config->getPassword());
    }

    #[RunInSeparateProcess]
    public function testParseUrlWithDefaultPort(): void
    {
        putenv('DATABASE_URL=postgresql://user:pass@host/db');

        $config = new DatabaseConfig();

        $this->assertEquals(5432, $config->getPort());
    }

    #[RunInSeparateProcess]
    public function testParseUrlWithDefaultUser(): void
    {
        putenv('DATABASE_URL=postgresql://:pass@host:5432/db');

        $config = new DatabaseConfig();

        $this->assertEquals('app', $config->getUser());
    }

    #[RunInSeparateProcess]
    public function testLoadFromIndividualVarsPostgres(): void
    {
        putenv('DB_TYPE=postgres');
        putenv('DB_HOST=myhost');
        putenv('DB_PORT=5433');
        putenv('DB_NAME=mydb');
        putenv('DB_USER=myuser');
        putenv('DB_PASSWORD=mypass');

        $config = new DatabaseConfig();

        $this->assertEquals('postgres', $config->getType());
        $this->assertEquals('myhost', $config->getHost());
        $this->assertEquals(5433, $config->getPort());
        $this->assertEquals('mydb', $config->getName());
        $this->assertEquals('myuser', $config->getUser());
        $this->assertEquals('mypass', $config->getPassword());
        $this->assertTrue($config->isPostgres());
    }

    #[RunInSeparateProcess]
    public function testLoadFromIndividualVarsMariadb(): void
    {
        putenv('DB_TYPE=mariadb');
        putenv('DB_HOST=myhost');
        putenv('DB_PORT=3307');
        putenv('DB_NAME=mydb');
        putenv('DB_USER=myuser');
        putenv('DB_PASSWORD=mypass');

        $config = new DatabaseConfig();

        $this->assertEquals('mariadb', $config->getType());
        $this->assertEquals('myhost', $config->getHost());
        $this->assertEquals(3307, $config->getPort());
        $this->assertTrue($config->isMariaDb());
        $this->assertFalse($config->isPostgres());
    }

    #[RunInSeparateProcess]
    public function testDefaultValuesPostgres(): void
    {
        // No env vars set - should use defaults
        $config = new DatabaseConfig();

        $this->assertEquals('postgres', $config->getType());
        $this->assertEquals('postgres', $config->getHost());
        $this->assertEquals(5432, $config->getPort());
        $this->assertEquals('app', $config->getName());
        $this->assertEquals('app', $config->getUser());
        $this->assertEquals('secret', $config->getPassword());
    }

    #[RunInSeparateProcess]
    public function testDefaultHostForMariadb(): void
    {
        putenv('DB_TYPE=mariadb');

        $config = new DatabaseConfig();

        $this->assertEquals('mariadb', $config->getHost());
        $this->assertEquals(3306, $config->getPort());
    }

    #[RunInSeparateProcess]
    public function testDatabaseUrlTakesPrecedence(): void
    {
        // Set both DATABASE_URL and individual vars - URL should win
        putenv('DATABASE_URL=postgresql://urluser:urlpass@urlhost:5555/urldb');
        putenv('DB_TYPE=mariadb');
        putenv('DB_HOST=individualhost');
        putenv('DB_PORT=3306');
        putenv('DB_NAME=individualdb');
        putenv('DB_USER=individualuser');
        putenv('DB_PASSWORD=individualpass');

        $config = new DatabaseConfig();

        $this->assertEquals('postgres', $config->getType());
        $this->assertEquals('urlhost', $config->getHost());
        $this->assertEquals(5555, $config->getPort());
        $this->assertEquals('urldb', $config->getName());
        $this->assertEquals('urluser', $config->getUser());
        $this->assertEquals('urlpass', $config->getPassword());
    }

    #[RunInSeparateProcess]
    public function testGetDsnPostgres(): void
    {
        putenv('DB_TYPE=postgres');
        putenv('DB_HOST=myhost');
        putenv('DB_PORT=5432');
        putenv('DB_NAME=mydb');

        $config = new DatabaseConfig();

        $this->assertEquals('pgsql:host=myhost;port=5432;dbname=mydb', $config->getDsn());
    }

    #[RunInSeparateProcess]
    public function testGetDsnMysql(): void
    {
        putenv('DB_TYPE=mysql');
        putenv('DB_HOST=myhost');
        putenv('DB_PORT=3306');
        putenv('DB_NAME=mydb');

        $config = new DatabaseConfig();

        $this->assertEquals('mysql:host=myhost;port=3306;dbname=mydb;charset=utf8mb4', $config->getDsn());
    }

    #[RunInSeparateProcess]
    public function testGetUrlPostgres(): void
    {
        putenv('DB_TYPE=postgres');
        putenv('DB_HOST=myhost');
        putenv('DB_PORT=5432');
        putenv('DB_NAME=mydb');
        putenv('DB_USER=myuser');
        putenv('DB_PASSWORD=mypass');

        $config = new DatabaseConfig();

        $this->assertEquals('postgresql://myuser:mypass@myhost:5432/mydb', $config->getUrl());
    }

    #[RunInSeparateProcess]
    public function testGetUrlMysql(): void
    {
        putenv('DB_TYPE=mysql');
        putenv('DB_HOST=myhost');
        putenv('DB_PORT=3306');
        putenv('DB_NAME=mydb');
        putenv('DB_USER=myuser');
        putenv('DB_PASSWORD=mypass');

        $config = new DatabaseConfig();

        $this->assertEquals('mysql://myuser:mypass@myhost:3306/mydb', $config->getUrl());
    }

    #[RunInSeparateProcess]
    public function testGetUrlEncodesSpecialCharactersInPassword(): void
    {
        putenv('DB_TYPE=postgres');
        putenv('DB_HOST=host');
        putenv('DB_PORT=5432');
        putenv('DB_NAME=db');
        putenv('DB_USER=user');
        putenv('DB_PASSWORD=p@ss:word/123');

        $config = new DatabaseConfig();

        $url = $config->getUrl();
        $this->assertStringContainsString('p%40ss%3Aword%2F123', $url);
    }

    #[RunInSeparateProcess]
    public function testInvalidDatabaseUrlThrowsException(): void
    {
        putenv('DATABASE_URL=invalid-url-without-scheme');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid DATABASE_URL format');

        new DatabaseConfig();
    }

    #[RunInSeparateProcess]
    #[DataProvider('typeVariationsProvider')]
    public function testIsPostgresWithVariousTypes(string $type, bool $expectedPostgres): void
    {
        putenv("DB_TYPE=$type");

        $config = new DatabaseConfig();

        $this->assertEquals($expectedPostgres, $config->isPostgres());
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function typeVariationsProvider(): array
    {
        return [
            'postgres'   => ['postgres', true],
            'postgresql' => ['postgresql', true],
            'mysql'      => ['mysql', false],
            'mariadb'    => ['mariadb', false],
        ];
    }

    #[RunInSeparateProcess]
    #[DataProvider('mariadbTypeVariationsProvider')]
    public function testIsMariaDbWithVariousTypes(string $type, bool $expectedMariaDb): void
    {
        putenv("DB_TYPE=$type");

        $config = new DatabaseConfig();

        $this->assertEquals($expectedMariaDb, $config->isMariaDb());
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function mariadbTypeVariationsProvider(): array
    {
        return [
            'mariadb'    => ['mariadb', true],
            'mysql'      => ['mysql', true],
            'postgres'   => ['postgres', false],
            'postgresql' => ['postgresql', false],
        ];
    }

    #[RunInSeparateProcess]
    public function testSslConfigFromEnvVar(): void
    {
        putenv('DB_TYPE=mariadb');
        putenv('DB_SSL_CA=/custom/path/to/ca.crt');
        putenv('DB_SSL_VERIFY=true');

        $config = new DatabaseConfig();

        $this->assertEquals('/custom/path/to/ca.crt', $config->getSslCa());
        $this->assertTrue($config->getSslVerify());
    }

    #[RunInSeparateProcess]
    public function testSslVerifyDefaultsToFalse(): void
    {
        putenv('DB_TYPE=postgres');

        $config = new DatabaseConfig();

        $this->assertFalse($config->getSslVerify());
    }

    #[RunInSeparateProcess]
    public function testHasSslReturnsFalseWhenCaNotSet(): void
    {
        putenv('DB_TYPE=postgres');

        $config = new DatabaseConfig();

        $this->assertFalse($config->hasSsl());
    }

    #[RunInSeparateProcess]
    public function testGetPdoSslOptionsReturnsEmptyForPostgres(): void
    {
        putenv('DB_TYPE=postgres');
        putenv('DB_SSL_CA=/some/path');

        $config = new DatabaseConfig();

        $this->assertEmpty($config->getPdoSslOptions());
    }

    #[RunInSeparateProcess]
    public function testGetPdoSslOptionsReturnsOptionsForMariadb(): void
    {
        putenv('DB_TYPE=mariadb');
        putenv('DB_SSL_CA=' . __FILE__); // Use this file as it exists

        $config = new DatabaseConfig();

        $options = $config->getPdoSslOptions();

        $this->assertArrayHasKey(\PDO::MYSQL_ATTR_SSL_CA, $options);
        $this->assertArrayHasKey(\PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT, $options);
        $this->assertEquals(__FILE__, $options[\PDO::MYSQL_ATTR_SSL_CA]);
        $this->assertFalse($options[\PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT]);
    }

    #[RunInSeparateProcess]
    public function testMariadbAutoDetectsInternalCert(): void
    {
        putenv('DB_TYPE=mariadb');
        // No DB_SSL_CA set - should auto-detect internal cert if it exists

        $config = new DatabaseConfig();

        // The internal cert path is /etc/ssl/db-certs/cert.crt
        // In test environment, it may or may not exist
        $internalCertPath = '/etc/ssl/db-certs/cert.crt';
        if (file_exists($internalCertPath)) {
            $this->assertEquals($internalCertPath, $config->getSslCa());
            $this->assertTrue($config->hasSsl());
        } else {
            // If internal cert doesn't exist, SSL CA should be empty
            $this->assertEquals('', $config->getSslCa());
        }
    }

    #[RunInSeparateProcess]
    public function testPostgresDoesNotAutoDetectInternalCert(): void
    {
        putenv('DB_TYPE=postgres');
        // No DB_SSL_CA set - PostgreSQL should NOT auto-detect

        $config = new DatabaseConfig();

        $this->assertEquals('', $config->getSslCa());
        $this->assertFalse($config->hasSsl());
    }

    #[RunInSeparateProcess]
    public function testSystemCaBundleOption(): void
    {
        putenv('DB_TYPE=mysql');
        putenv('DB_SSL_CA=system');

        $config = new DatabaseConfig();

        // Should resolve to system CA bundle path if it exists
        $systemCaPath = '/etc/ssl/certs/ca-certificates.crt';
        if (file_exists($systemCaPath)) {
            $this->assertEquals($systemCaPath, $config->getSslCa());
            $this->assertTrue($config->hasSsl());
        } else {
            $this->assertEquals('', $config->getSslCa());
        }
    }

    #[RunInSeparateProcess]
    public function testSystemCaBundleOptionCaseInsensitive(): void
    {
        putenv('DB_TYPE=postgres');
        putenv('DB_SSL_CA=SYSTEM');

        $config = new DatabaseConfig();

        $systemCaPath = '/etc/ssl/certs/ca-certificates.crt';
        if (file_exists($systemCaPath)) {
            $this->assertEquals($systemCaPath, $config->getSslCa());
        }
    }

    #[RunInSeparateProcess]
    public function testSystemCaBundleWorksWithPostgres(): void
    {
        putenv('DB_TYPE=postgres');
        putenv('DB_SSL_CA=system');

        $config = new DatabaseConfig();

        // PostgreSQL with "system" should also get the CA path
        // (even though PostgreSQL doesn't need it in PDO options)
        $systemCaPath = '/etc/ssl/certs/ca-certificates.crt';
        if (file_exists($systemCaPath)) {
            $this->assertEquals($systemCaPath, $config->getSslCa());
        }
    }

    #[RunInSeparateProcess]
    public function testPostgresSslModeEmptyByDefault(): void
    {
        putenv('DB_TYPE=postgres');
        // No SSL CA set

        $config = new DatabaseConfig();

        $this->assertEquals('', $config->getPostgresSslMode());
        $this->assertEquals('pgsql:host=postgres;port=5432;dbname=app', $config->getDsn());
    }

    #[RunInSeparateProcess]
    public function testPostgresSslModeVerifyFullWhenSslVerifyTrue(): void
    {
        putenv('DB_TYPE=postgres');
        putenv('DB_SSL_CA=system');
        putenv('DB_SSL_VERIFY=true');

        $config = new DatabaseConfig();

        $systemCaPath = '/etc/ssl/certs/ca-certificates.crt';
        if (file_exists($systemCaPath)) {
            $this->assertEquals('verify-full', $config->getPostgresSslMode());
            $this->assertStringContainsString('sslmode=verify-full', $config->getDsn());
        }
    }

    #[RunInSeparateProcess]
    public function testPostgresSslModeRequireWhenSslVerifyFalse(): void
    {
        putenv('DB_TYPE=postgres');
        putenv('DB_SSL_CA=system');
        putenv('DB_SSL_VERIFY=false');

        $config = new DatabaseConfig();

        $systemCaPath = '/etc/ssl/certs/ca-certificates.crt';
        if (file_exists($systemCaPath)) {
            $this->assertEquals('require', $config->getPostgresSslMode());
            $this->assertStringContainsString('sslmode=require', $config->getDsn());
        }
    }

    #[RunInSeparateProcess]
    public function testMariadbDsnNotAffectedBySslMode(): void
    {
        putenv('DB_TYPE=mariadb');
        putenv('DB_SSL_CA=system');
        putenv('DB_SSL_VERIFY=true');

        $config = new DatabaseConfig();

        // MariaDB DSN should not contain sslmode
        $this->assertStringNotContainsString('sslmode', $config->getDsn());
        $this->assertStringContainsString('mysql:', $config->getDsn());
    }

    // =========================================================================
    // Docker Secrets (_FILE) Support Tests
    // =========================================================================

    #[RunInSeparateProcess]
    public function testPasswordFromFile(): void
    {
        $tempFile = sys_get_temp_dir() . '/db_password_test_' . uniqid() . '.txt';
        file_put_contents($tempFile, "secret_from_file\n");

        try {
            putenv("DB_PASSWORD_FILE=$tempFile");

            $config = new DatabaseConfig();

            $this->assertEquals('secret_from_file', $config->getPassword());
        } finally {
            unlink($tempFile);
        }
    }

    #[RunInSeparateProcess]
    public function testPasswordFileTrimsWhitespace(): void
    {
        $tempFile = sys_get_temp_dir() . '/db_password_test_' . uniqid() . '.txt';
        file_put_contents($tempFile, "  password_with_spaces  \n\n");

        try {
            putenv("DB_PASSWORD_FILE=$tempFile");

            $config = new DatabaseConfig();

            $this->assertEquals('password_with_spaces', $config->getPassword());
        } finally {
            unlink($tempFile);
        }
    }

    #[RunInSeparateProcess]
    public function testPasswordFileTakesPrecedenceOverEnvVar(): void
    {
        $tempFile = sys_get_temp_dir() . '/db_password_test_' . uniqid() . '.txt';
        file_put_contents($tempFile, 'from_file');

        try {
            putenv("DB_PASSWORD_FILE=$tempFile");
            putenv('DB_PASSWORD=from_env');

            $config = new DatabaseConfig();

            $this->assertEquals('from_file', $config->getPassword());
        } finally {
            unlink($tempFile);
        }
    }

    #[RunInSeparateProcess]
    public function testFallbackToEnvVarWhenFileNotExists(): void
    {
        putenv('DB_PASSWORD_FILE=/nonexistent/path/to/password.txt');
        putenv('DB_PASSWORD=fallback_password');

        $config = new DatabaseConfig();

        $this->assertEquals('fallback_password', $config->getPassword());
    }

    #[RunInSeparateProcess]
    public function testFallbackToDefaultWhenFileNotExistsAndNoEnvVar(): void
    {
        putenv('DB_PASSWORD_FILE=/nonexistent/path/to/password.txt');

        $config = new DatabaseConfig();

        $this->assertEquals('secret', $config->getPassword());
    }

    #[RunInSeparateProcess]
    public function testEmptyPasswordFilePathFallsBackToEnvVar(): void
    {
        putenv('DB_PASSWORD_FILE=');
        putenv('DB_PASSWORD=env_password');

        $config = new DatabaseConfig();

        $this->assertEquals('env_password', $config->getPassword());
    }
}
