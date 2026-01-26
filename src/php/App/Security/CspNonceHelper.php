<?php

declare(strict_types=1);

namespace App\Security;

use Random\RandomException;

/**
 * CSP Nonce Helper
 *
 * Manages Content Security Policy nonces for inline scripts and styles.
 * Generates cryptographically secure nonces per request and builds CSP headers.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/CSP
 */
final class CspNonceHelper
{
    private static ?string $nonce = null;

    /**
     * Generate and store nonce for current request
     *
     * @return string Base64-encoded cryptographically secure nonce
     * @throws RandomException If no suitable random source is available
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
     * @throws RandomException If no suitable random source is available
     */
    public static function get(): string
    {
        return self::$nonce ?? self::generate();
    }

    /**
     * Build Content-Security-Policy header with nonce
     *
     * Automatically detects environment (development/production) from ENV variable.
     * Use buildDevelopmentCspHeader() or buildProductionCspHeader() for explicit control.
     *
     * @return string Complete CSP header value
     * @throws RandomException If no suitable random source is available
     */
    public static function buildCspHeader(): string
    {
        $isDevelopment = getenv('ENV') === 'development';

        return $isDevelopment
            ? self::buildDevelopmentCspHeader()
            : self::buildProductionCspHeader();
    }

    /**
     * Build development CSP header
     *
     * Allows unsafe-eval for Vite HMR, unsafe-inline for Vite-injected styles,
     * and WebSocket connections for hot reload.
     *
     * @return string Complete CSP header value
     * @throws RandomException If no suitable random source is available
     */
    public static function buildDevelopmentCspHeader(): string
    {
        $nonce = self::get();

        $directives = [
            "default-src 'self'",
            sprintf("script-src 'self' 'nonce-%s' 'strict-dynamic' 'unsafe-eval'", $nonce),
            "style-src 'self' 'unsafe-inline' 'unsafe-hashes'", // No nonce - allows Vite's dynamic styles and inline style attributes
            "img-src 'self' data:",
            "font-src 'self'",
            "connect-src 'self' wss://localhost:8443 https://localhost:8443",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self'",
        ];

        return implode('; ', $directives);
    }

    /**
     * Build production CSP header
     *
     * Strict CSP without unsafe-* directives.
     *
     * @return string Complete CSP header value
     * @throws RandomException If no suitable random source is available
     */
    public static function buildProductionCspHeader(): string
    {
        $nonce = self::get();

        $directives = [
            "default-src 'self'",
            sprintf("script-src 'self' 'nonce-%s' 'strict-dynamic'", $nonce),
            sprintf("style-src 'self' 'nonce-%s'", $nonce),
            "img-src 'self' data:",
            "font-src 'self'",
            "connect-src 'self'",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self'",
        ];

        return implode('; ', $directives);
    }

    /**
     * Reset nonce (for testing purposes)
     *
     * @internal
     */
    public static function reset(): void
    {
        self::$nonce = null;
    }
}
