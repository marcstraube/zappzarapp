<?php

declare(strict_types=1);

namespace Tests\App\Unit\Security;

use App\Security\CspNonceRegistry;
use PHPUnit\Framework\TestCase;
use Zappzarapp\Security\Csp\Directive\CspDirectives;
use Zappzarapp\Security\Csp\HeaderBuilder;

/**
 * Tests for CSP headers in production environment (strict mode)
 */
class CspNonceHelperProductionTest extends TestCase
{
    protected function setUp(): void
    {
        CspNonceRegistry::reset();
    }

    protected function tearDown(): void
    {
        CspNonceRegistry::reset();
    }

    public function testProductionCspContainsDefaultSrc(): void
    {
        $csp = HeaderBuilder::build(CspDirectives::strict(), CspNonceRegistry::generator());

        $this->assertStringContainsString("default-src 'self'", $csp);
    }

    public function testProductionCspContainsNonceInScriptSrc(): void
    {
        $nonce = CspNonceRegistry::get();
        $csp   = HeaderBuilder::build(CspDirectives::strict(), CspNonceRegistry::generator());

        $this->assertStringContainsString(sprintf("'nonce-%s'", $nonce), $csp);
        $this->assertStringContainsString("script-src", $csp);
    }

    public function testProductionCspContainsNonceInStyleSrc(): void
    {
        $nonce = CspNonceRegistry::get();
        $csp   = HeaderBuilder::build(CspDirectives::strict(), CspNonceRegistry::generator());

        $this->assertStringContainsString(sprintf("'nonce-%s'", $nonce), $csp);
        $this->assertStringContainsString("style-src", $csp);
    }

    public function testProductionCspDoesNotContainUnsafeEval(): void
    {
        $csp = HeaderBuilder::build(CspDirectives::strict(), CspNonceRegistry::generator());

        $this->assertStringNotContainsString("'unsafe-eval'", $csp);
    }

    public function testProductionCspDoesNotContainUnsafeInline(): void
    {
        $csp = HeaderBuilder::build(CspDirectives::strict(), CspNonceRegistry::generator());

        $this->assertStringNotContainsString("'unsafe-inline'", $csp);
    }

    public function testProductionCspDoesNotContainUnsafeHashes(): void
    {
        $csp = HeaderBuilder::build(CspDirectives::strict(), CspNonceRegistry::generator());

        $this->assertStringNotContainsString("'unsafe-hashes'", $csp);
    }

    public function testProductionCspContainsStrictDynamic(): void
    {
        $csp = HeaderBuilder::build(CspDirectives::strict(), CspNonceRegistry::generator());

        $this->assertStringContainsString("'strict-dynamic'", $csp);
    }

    public function testProductionCspDoesNotAllowWebSocketConnections(): void
    {
        $csp = HeaderBuilder::build(CspDirectives::strict(), CspNonceRegistry::generator());

        $this->assertStringContainsString("connect-src 'self'", $csp);
        $this->assertStringNotContainsString("wss://localhost:8443", $csp);
    }

    public function testProductionCspContainsSecurityDirectives(): void
    {
        $csp = HeaderBuilder::build(CspDirectives::strict(), CspNonceRegistry::generator());

        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $this->assertStringContainsString("base-uri 'self'", $csp);
        $this->assertStringContainsString("form-action 'self'", $csp);
    }
}
