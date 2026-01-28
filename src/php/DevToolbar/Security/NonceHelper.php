<?php

declare(strict_types=1);

namespace DevToolbar\Security;

/**
 * Nonce Helper for DevToolbar
 *
 * Generates cryptographically secure nonces for inline scripts and styles.
 * Self-contained implementation for CSP compliance.
 *
 * The nonce can be optionally overridden from external sources if the host
 * project has its own CSP implementation.
 */
class NonceHelper
{
    private static ?string $nonce = null;

    /**
     * Generate and store nonce for current request
     *
     * @return string Base64-encoded cryptographically secure nonce
     */
    public static function generate(): string
    {
        if (self::$nonce === null) {
            self::$nonce = base64_encode(random_bytes(16));
        }

        return self::$nonce;
    }

    /**
     * Get current nonce (generates if not exists)
     *
     * @return string Base64-encoded nonce
     */
    public static function get(): string
    {
        return self::$nonce ?? self::generate();
    }

    /**
     * Set nonce from external source
     *
     * Allows host project to override the nonce if it has its own CSP implementation.
     *
     * @param string $nonce External nonce value
     * @return void
     */
    public static function set(string $nonce): void
    {
        self::$nonce = $nonce;
    }

    /**
     * Reset nonce (for testing purposes)
     *
     * @internal
     * @return void
     */
    public static function reset(): void
    {
        self::$nonce = null;
    }
}
