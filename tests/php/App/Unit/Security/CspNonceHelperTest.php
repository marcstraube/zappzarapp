<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Security\CspNonceHelper;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for CspNonceHelper
 *
 * Verifies CSP header generation for development and production environments
 */
class CspNonceHelperTest extends TestCase
{
    protected function setUp(): void
    {
        // Reset nonce before each test
        CspNonceHelper::reset();
    }

    protected function tearDown(): void
    {
        // Reset nonce after each test
        CspNonceHelper::reset();
    }

    // ===== Nonce Generation Tests =====

    public function testGenerateCreatesBase64EncodedString(): void
    {
        $nonce = CspNonceHelper::generate();

        $this->assertIsString($nonce);
        $this->assertNotEmpty($nonce);
        // Base64 strings only contain alphanumeric chars, +, /, and =
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9+\/=]+$/', $nonce);
    }

    public function testGenerateReturnsSameNonceForSameRequest(): void
    {
        $nonce1 = CspNonceHelper::generate();
        $nonce2 = CspNonceHelper::generate();

        $this->assertSame($nonce1, $nonce2);
    }

    public function testGetReturnsGeneratedNonce(): void
    {
        $nonce    = CspNonceHelper::generate();
        $getNonce = CspNonceHelper::get();

        $this->assertSame($nonce, $getNonce);
    }

    public function testGetGeneratesNonceIfNotExists(): void
    {
        $nonce = CspNonceHelper::get();

        $this->assertIsString($nonce);
        $this->assertNotEmpty($nonce);
    }

    // ===== Development CSP Tests =====

    public function testDevelopmentCspContainsDefaultSrc(): void
    {
        $csp = CspNonceHelper::buildDevelopmentCspHeader();

        $this->assertStringContainsString("default-src 'self'", $csp);
    }

    public function testDevelopmentCspContainsNonceInScriptSrc(): void
    {
        $nonce = CspNonceHelper::get();
        $csp   = CspNonceHelper::buildDevelopmentCspHeader();

        $this->assertStringContainsString("'nonce-{$nonce}'", $csp);
        $this->assertStringContainsString("script-src", $csp);
    }

    public function testDevelopmentCspAllowsUnsafeEvalForViteHmr(): void
    {
        $csp = CspNonceHelper::buildDevelopmentCspHeader();

        $this->assertStringContainsString("'unsafe-eval'", $csp);
        $this->assertStringContainsString("script-src", $csp);
    }

    public function testDevelopmentCspAllowsUnsafeInlineForViteStyles(): void
    {
        $csp = CspNonceHelper::buildDevelopmentCspHeader();

        $this->assertStringContainsString("'unsafe-inline'", $csp);
        $this->assertStringContainsString("style-src", $csp);
    }

    public function testDevelopmentCspDoesNotContainUnsafeHashes(): void
    {
        $csp = CspNonceHelper::buildDevelopmentCspHeader();

        $this->assertStringNotContainsString("'unsafe-hashes'", $csp);
    }

    public function testDevelopmentCspContainsStrictDynamic(): void
    {
        $csp = CspNonceHelper::buildDevelopmentCspHeader();

        $this->assertStringContainsString("'strict-dynamic'", $csp);
    }

    public function testDevelopmentCspAllowsWebSocketConnections(): void
    {
        $csp = CspNonceHelper::buildDevelopmentCspHeader();

        $this->assertStringContainsString("connect-src", $csp);
        $this->assertStringContainsString("wss://localhost:8443", $csp);
        $this->assertStringContainsString("https://localhost:8443", $csp);
    }

    public function testDevelopmentCspContainsSecurityDirectives(): void
    {
        $csp = CspNonceHelper::buildDevelopmentCspHeader();

        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $this->assertStringContainsString("base-uri 'self'", $csp);
        $this->assertStringContainsString("form-action 'self'", $csp);
    }

    // ===== Production CSP Tests =====

    public function testProductionCspContainsDefaultSrc(): void
    {
        $csp = CspNonceHelper::buildProductionCspHeader();

        $this->assertStringContainsString("default-src 'self'", $csp);
    }

    public function testProductionCspContainsNonceInScriptSrc(): void
    {
        $nonce = CspNonceHelper::get();
        $csp   = CspNonceHelper::buildProductionCspHeader();

        $this->assertStringContainsString("'nonce-{$nonce}'", $csp);
        $this->assertStringContainsString("script-src", $csp);
    }

    public function testProductionCspContainsNonceInStyleSrc(): void
    {
        $nonce = CspNonceHelper::get();
        $csp   = CspNonceHelper::buildProductionCspHeader();

        $this->assertStringContainsString("'nonce-{$nonce}'", $csp);
        $this->assertStringContainsString("style-src", $csp);
    }

    public function testProductionCspDoesNotContainUnsafeEval(): void
    {
        $csp = CspNonceHelper::buildProductionCspHeader();

        $this->assertStringNotContainsString("'unsafe-eval'", $csp);
    }

    public function testProductionCspDoesNotContainUnsafeInline(): void
    {
        $csp = CspNonceHelper::buildProductionCspHeader();

        $this->assertStringNotContainsString("'unsafe-inline'", $csp);
    }

    public function testProductionCspDoesNotContainUnsafeHashes(): void
    {
        $csp = CspNonceHelper::buildProductionCspHeader();

        $this->assertStringNotContainsString("'unsafe-hashes'", $csp);
    }

    public function testProductionCspContainsStrictDynamic(): void
    {
        $csp = CspNonceHelper::buildProductionCspHeader();

        $this->assertStringContainsString("'strict-dynamic'", $csp);
    }

    public function testProductionCspDoesNotAllowWebSocketConnections(): void
    {
        $csp = CspNonceHelper::buildProductionCspHeader();

        $this->assertStringContainsString("connect-src 'self'", $csp);
        $this->assertStringNotContainsString("wss://localhost:8443", $csp);
    }

    public function testProductionCspContainsSecurityDirectives(): void
    {
        $csp = CspNonceHelper::buildProductionCspHeader();

        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $this->assertStringContainsString("base-uri 'self'", $csp);
        $this->assertStringContainsString("form-action 'self'", $csp);
    }

    // ===== Environment Detection Tests =====

    public function testBuildCspHeaderDetectsDevelopmentEnvironment(): void
    {
        // Mock ENV environment variable
        putenv('ENV=development');

        $csp = CspNonceHelper::buildCspHeader();

        $this->assertStringContainsString("'unsafe-eval'", $csp);

        // Cleanup
        putenv('ENV');
    }

    public function testBuildCspHeaderDetectsProductionEnvironment(): void
    {
        // Mock ENV environment variable
        putenv('ENV=production');

        $csp = CspNonceHelper::buildCspHeader();

        $this->assertStringNotContainsString("'unsafe-eval'", $csp);
        $this->assertStringNotContainsString("'unsafe-inline'", $csp);

        // Cleanup
        putenv('ENV');
    }

    public function testBuildCspHeaderDefaultsToProductionWhenEnvNotSet(): void
    {
        // Ensure ENV is not set
        putenv('ENV');

        $csp = CspNonceHelper::buildCspHeader();

        // Should use production (strict) CSP when ENV is not set
        $this->assertStringNotContainsString("'unsafe-eval'", $csp);

        // Cleanup
        putenv('ENV');
    }
}
