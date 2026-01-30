<?php

declare(strict_types=1);

namespace App\Security;

use Random\RandomException;
use Zappzarapp\Security\Csp\Nonce\NonceGenerator;

/**
 * CSP Nonce Registry
 *
 * Provides a singleton-like access to the NonceGenerator instance.
 * Ensures the same nonce is used throughout a single request.
 *
 * This registry pattern is necessary because:
 * - The zappzarapp/security package uses instance-based NonceGenerator
 * - Many parts of the app need nonce access (helpers, templates, DevToolbar)
 * - The nonce must be consistent within a single request
 *
 * Usage:
 *   $nonce = CspNonceRegistry::generator()->get();
 *
 * For long-running processes (Swoole, RoadRunner):
 *   CspNonceRegistry::reset(); // at start of each request
 */
final class CspNonceRegistry
{
    private static ?NonceGenerator $generator = null;

    /**
     * Get the shared NonceGenerator instance
     *
     * Creates instance on first access (lazy initialization).
     */
    public static function generator(): NonceGenerator
    {
        if (self::$generator === null) {
            self::$generator = new NonceGenerator();
        }

        return self::$generator;
    }

    /**
     * Get the current nonce value
     *
     * Convenience method - equivalent to generator()->get()
     *
     * @throws RandomException If no suitable random source is available
     */
    public static function get(): string
    {
        return self::generator()->get();
    }

    /**
     * Set nonce from external source
     *
     * Use when CSP header has already been set with a known nonce.
     */
    public static function set(string $nonce): void
    {
        self::generator()->set($nonce);
    }

    /**
     * Reset for new request (long-running processes)
     *
     * Call at the start of each request in Swoole/RoadRunner/etc.
     */
    public static function reset(): void
    {
        if (self::$generator !== null) {
            self::$generator->reset();
        }

        self::$generator = null;
    }
}
