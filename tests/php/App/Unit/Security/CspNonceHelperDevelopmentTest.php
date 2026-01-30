<?php

declare(strict_types=1);

namespace Tests\App\Unit\Security;

use App\Security\CspNonceRegistry;
use PHPUnit\Framework\TestCase;
use Zappzarapp\Security\Csp\Directive\CspDirectives;
use Zappzarapp\Security\Csp\HeaderBuilder;

/**
 * Tests for CSP headers in development environment (lenient mode)
 */
class CspNonceHelperDevelopmentTest extends TestCase
{
    private const WS_HOST = 'localhost:8443';

    protected function setUp(): void
    {
        CspNonceRegistry::reset();
    }

    protected function tearDown(): void
    {
        CspNonceRegistry::reset();
    }

    public function testDevelopmentCspContainsDefaultSrc(): void
    {
        $csp = HeaderBuilder::build(
            CspDirectives::development(self::WS_HOST),
            CspNonceRegistry::generator()
        );

        $this->assertStringContainsString("default-src 'self'", $csp);
    }

    public function testDevelopmentCspContainsNonceInScriptSrc(): void
    {
        $nonce = CspNonceRegistry::get();
        $csp   = HeaderBuilder::build(
            CspDirectives::development(self::WS_HOST),
            CspNonceRegistry::generator()
        );

        $this->assertStringContainsString(sprintf("'nonce-%s'", $nonce), $csp);
        $this->assertStringContainsString("script-src", $csp);
    }

    public function testDevelopmentCspAllowsUnsafeEvalForViteHmr(): void
    {
        $csp = HeaderBuilder::build(
            CspDirectives::development(self::WS_HOST),
            CspNonceRegistry::generator()
        );

        $this->assertStringContainsString("'unsafe-eval'", $csp);
        $this->assertStringContainsString("script-src", $csp);
    }

    public function testDevelopmentCspAllowsUnsafeInlineForViteStyles(): void
    {
        $csp = HeaderBuilder::build(
            CspDirectives::development(self::WS_HOST),
            CspNonceRegistry::generator()
        );

        $this->assertStringContainsString("'unsafe-inline'", $csp);
        $this->assertStringContainsString("style-src", $csp);
    }

    public function testDevelopmentCspDoesNotContainUnsafeHashes(): void
    {
        $csp = HeaderBuilder::build(
            CspDirectives::development(self::WS_HOST),
            CspNonceRegistry::generator()
        );

        $this->assertStringNotContainsString("'unsafe-hashes'", $csp);
    }

    public function testDevelopmentCspContainsStrictDynamic(): void
    {
        $csp = HeaderBuilder::build(
            CspDirectives::development(self::WS_HOST),
            CspNonceRegistry::generator()
        );

        $this->assertStringContainsString("'strict-dynamic'", $csp);
    }

    public function testDevelopmentCspAllowsWebSocketConnections(): void
    {
        $csp = HeaderBuilder::build(
            CspDirectives::development(self::WS_HOST),
            CspNonceRegistry::generator()
        );

        $this->assertStringContainsString("connect-src", $csp);
        $this->assertStringContainsString("wss://localhost:8443", $csp);
        $this->assertStringContainsString("https://localhost:8443", $csp);
    }

    public function testDevelopmentCspContainsSecurityDirectives(): void
    {
        $csp = HeaderBuilder::build(
            CspDirectives::development(self::WS_HOST),
            CspNonceRegistry::generator()
        );

        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $this->assertStringContainsString("base-uri 'self'", $csp);
        $this->assertStringContainsString("form-action 'self'", $csp);
    }
}
