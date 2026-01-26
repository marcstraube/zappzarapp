<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Security\CspNonceHelper;
use PHPUnit\Framework\TestCase;

/**
 * Tests for CspNonceHelper development environment CSP headers
 */
class CspNonceHelperDevelopmentTest extends TestCase
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

    public function testDevelopmentCspContainsDefaultSrc(): void
    {
        $csp = CspNonceHelper::buildDevelopmentCspHeader();

        $this->assertStringContainsString("default-src 'self'", $csp);
    }

    public function testDevelopmentCspContainsNonceInScriptSrc(): void
    {
        $nonce = CspNonceHelper::get();
        $csp   = CspNonceHelper::buildDevelopmentCspHeader();

        $this->assertStringContainsString(sprintf("'nonce-%s'", $nonce), $csp);
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
}
