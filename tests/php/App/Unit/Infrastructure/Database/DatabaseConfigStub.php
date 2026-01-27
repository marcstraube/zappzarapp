<?php

declare(strict_types=1);

namespace Tests\App\Unit\Infrastructure\Database;

use App\Infrastructure\DatabaseConfigInterface;

/**
 * Test stub for DatabaseConfigInterface
 *
 * Provides concrete implementations of property hooks for unit testing.
 * PHPUnit's createMock() cannot mock PHP 8.4 property hooks.
 */
class DatabaseConfigStub implements DatabaseConfigInterface
{
    public private(set) string $type;

    public private(set) string $host = 'localhost';

    public private(set) int $port;

    public private(set) string $name = 'test';

    public private(set) string $sslCa = '';

    public private(set) bool $sslVerify = false;

    public function __construct(
        private readonly bool $postgres = false,
        public readonly string $user = 'test',
        public readonly string $password = 'test'
    ) {
        $this->type = $this->postgres ? 'postgres' : 'mysql';
        $this->port = $this->postgres ? 5432 : 3306;
    }

    public function getDsn(): string
    {
        return $this->postgres
            ? 'pgsql:host=localhost;dbname=test'
            : 'mysql:host=localhost;dbname=test';
    }

    public function getPostgresSslMode(): string
    {
        return '';
    }

    public function getUrl(): string
    {
        $driver = $this->postgres ? 'postgresql' : 'mysql';

        return sprintf('%s://%s:%s@%s:%d/%s', $driver, $this->user, $this->password, $this->host, $this->port, $this->name);
    }

    public function isPostgres(): bool
    {
        return $this->postgres;
    }

    public function isMariaDb(): bool
    {
        return !$this->postgres;
    }

    public function hasSsl(): bool
    {
        return false;
    }

    /**
     * @return array<int, mixed>
     */
    public function getPdoSslOptions(): array
    {
        return [];
    }
}
