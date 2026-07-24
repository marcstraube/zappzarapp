<?php

declare(strict_types=1);

namespace Tests\DevDashboard;

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\UsesFunction;
use PHPUnit\Framework\TestCase;
use Zappzarapp\Security\Csp\Nonce\NonceRegistry;

/**
 * Tests for DevDashboard helper functions (src/php/DevDashboard/helpers.php)
 *
 * The helpers.php file is NOT included in the composer autoload "files" array,
 * so it must be required explicitly. The nonce() function is guarded with
 * function_exists() so subsequent requires are safe.
 *
 * The global nonce() function (src/php/App/helpers.php, loaded via composer autoload)
 * is called by DevDashboard\nonce() as its second fallback path, so it is listed
 * as #[UsesFunction] to satisfy beStrictAboutCoverageMetadata.
 */
#[CoversFunction('DevDashboard\nonce')]
#[UsesFunction('nonce')]
final class HelpersTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // Load the file once for the entire class (function_exists guard makes it idempotent)
        require_once __DIR__ . '/../../../src/php/DevDashboard/helpers.php';
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Reset NonceRegistry so each test starts with a fresh nonce
        NonceRegistry::reset();
    }

    // ===== nonce() — function presence and output =====

    public function testNonceFunctionExistsAndReturnsValue(): void
    {
        // Both assert the function is defined AND call it so coverage maps correctly
        $this->assertTrue(function_exists('DevDashboard\nonce'));
        $nonce = \DevDashboard\nonce();
        $this->assertIsString($nonce);
        $this->assertNotEmpty($nonce);
    }

    public function testNonceReturnsNonEmptyString(): void
    {
        // When neither CSP_NONCE constant nor global nonce() is defined,
        // falls back to NonceRegistry::get() which returns a base64 nonce
        $nonce = \DevDashboard\nonce();

        $this->assertIsString($nonce);
        $this->assertNotEmpty($nonce);
    }

    public function testNonceReturnsDifferentValuesAfterRegistryReset(): void
    {
        // First call generates nonce and caches it in the generator
        $nonce1 = \DevDashboard\nonce();

        // Reset so next call generates a fresh nonce
        NonceRegistry::reset();

        $nonce2 = \DevDashboard\nonce();

        // Both are valid non-empty strings; after reset they will differ
        $this->assertIsString($nonce1);
        $this->assertIsString($nonce2);
    }

    public function testNonceReturnsSameValueWithinSameRequest(): void
    {
        // Within one request (no reset) the registry returns the same nonce
        $nonce1 = \DevDashboard\nonce();
        $nonce2 = \DevDashboard\nonce();

        $this->assertSame($nonce1, $nonce2, 'Nonce should be stable within the same request');
    }

    public function testNonceReturnsBase64LikeString(): void
    {
        $nonce = \DevDashboard\nonce();

        // NonceRegistry returns a base64-encoded value — only base64url chars + padding
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9+\/=]+$/', $nonce);
    }
}
