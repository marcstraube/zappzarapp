<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Security\CspNonceHelper;
use PHPUnit\Framework\TestCase;

/**
 * Tests for CspNonceHelper production environment CSP headers
 */
class CspNonceHelperProductionTest extends TestCase
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

    public function testProductionCspContainsDefaultSrc(): void
    {
        $csp = CspNonceHelper::buildProductionCspHeader();

        $this->assertStringContainsString("default-src 'self'", $csp);
    }

    public function testProductionCspContainsNonceInScriptSrc(): void
    {
        $nonce = CspNonceHelper::get();
        $csp   = CspNonceHelper::buildProductionCspHeader();

        $this->assertStringContainsString(sprintf("'nonce-%s'", $nonce), $csp);
        $this->assertStringContainsString("script-src", $csp);
    }

    public function testProductionCspContainsNonceInStyleSrc(): void
    {
        $nonce = CspNonceHelper::get();
        $csp   = CspNonceHelper::buildProductionCspHeader();

        $this->assertStringContainsString(sprintf("'nonce-%s'", $nonce), $csp);
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
}
