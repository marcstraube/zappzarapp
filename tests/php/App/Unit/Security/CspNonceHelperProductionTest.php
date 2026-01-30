<?php

declare(strict_types=1);

namespace Tests\App\Unit\Security;

use PHPUnit\Framework\TestCase;
use Zappzarapp\Security\Csp\HeaderBuilder;
use Zappzarapp\Security\Csp\NonceGenerator;

/**
 * Tests for CspNonceHelper production environment CSP headers
 */
class CspNonceHelperProductionTest extends TestCase
{
    protected function setUp(): void
    {
        // Reset nonce before each test
        NonceGenerator::reset();
    }

    protected function tearDown(): void
    {
        // Reset nonce after each test
        NonceGenerator::reset();
    }

    public function testProductionCspContainsDefaultSrc(): void
    {
        $csp = HeaderBuilder::buildProduction();

        $this->assertStringContainsString("default-src 'self'", $csp);
    }

    public function testProductionCspContainsNonceInScriptSrc(): void
    {
        $nonce = NonceGenerator::get();
        $csp   = HeaderBuilder::buildProduction();

        $this->assertStringContainsString(sprintf("'nonce-%s'", $nonce), $csp);
        $this->assertStringContainsString("script-src", $csp);
    }

    public function testProductionCspContainsNonceInStyleSrc(): void
    {
        $nonce = NonceGenerator::get();
        $csp   = HeaderBuilder::buildProduction();

        $this->assertStringContainsString(sprintf("'nonce-%s'", $nonce), $csp);
        $this->assertStringContainsString("style-src", $csp);
    }

    public function testProductionCspDoesNotContainUnsafeEval(): void
    {
        $csp = HeaderBuilder::buildProduction();

        $this->assertStringNotContainsString("'unsafe-eval'", $csp);
    }

    public function testProductionCspDoesNotContainUnsafeInline(): void
    {
        $csp = HeaderBuilder::buildProduction();

        $this->assertStringNotContainsString("'unsafe-inline'", $csp);
    }

    public function testProductionCspDoesNotContainUnsafeHashes(): void
    {
        $csp = HeaderBuilder::buildProduction();

        $this->assertStringNotContainsString("'unsafe-hashes'", $csp);
    }

    public function testProductionCspContainsStrictDynamic(): void
    {
        $csp = HeaderBuilder::buildProduction();

        $this->assertStringContainsString("'strict-dynamic'", $csp);
    }

    public function testProductionCspDoesNotAllowWebSocketConnections(): void
    {
        $csp = HeaderBuilder::buildProduction();

        $this->assertStringContainsString("connect-src 'self'", $csp);
        $this->assertStringNotContainsString("wss://localhost:8443", $csp);
    }

    public function testProductionCspContainsSecurityDirectives(): void
    {
        $csp = HeaderBuilder::buildProduction();

        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $this->assertStringContainsString("base-uri 'self'", $csp);
        $this->assertStringContainsString("form-action 'self'", $csp);
    }
}
