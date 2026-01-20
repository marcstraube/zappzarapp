<?php

declare(strict_types=1);

/**
 * Dependency Injection Container Configuration
 *
 * php-di supports auto-wiring, so most classes don't need explicit definitions.
 * Add definitions here only when:
 * - You need to bind an interface to a concrete implementation
 * - You need to configure constructor parameters that can't be auto-wired
 * - You want to use factories for complex object creation
 *
 * @see https://php-di.org/doc/php-definitions.html
 */

use App\Infrastructure\Cache\CacheInterface;
use App\Infrastructure\Cache\RedisCache;
use App\Infrastructure\Session\RedisSession;
use App\Infrastructure\Session\SessionInterface;

use function DI\autowire;

return [
    // Cache: Interface to Redis implementation
    CacheInterface::class => autowire(RedisCache::class),

    // Session: Interface to Redis implementation (uses CacheInterface internally)
    SessionInterface::class => autowire(RedisSession::class),

    // Example: Factory definition (uncomment when needed)
    // PDO::class => function () {
    //     return new PDO(
    //         getenv('DATABASE_URL') ?: 'sqlite::memory:',
    //     );
    // },
];
