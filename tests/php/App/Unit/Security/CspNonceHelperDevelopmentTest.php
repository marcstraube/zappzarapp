<?php

declare(strict_types=1);

namespace Tests\App\Unit\Security;

use PHPUnit\Framework\TestCase;
use Zappzarapp\Security\Csp\HeaderBuilder;
use Zappzarapp\Security\Csp\NonceGenerator;

/**
 * Tests for CspNonceHelper development environment CSP headers
 */
class CspNonceHelperDevelopmentTest extends TestCase
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

    public function testDevelopmentCspContainsDefaultSrc(): void
    {
        $csp = HeaderBuilder::buildDevelopment();

        $this->assertStringContainsString("default-src 'self'", $csp);
    }

    public function testDevelopmentCspContainsNonceInScriptSrc(): void
    {
        $nonce = NonceGenerator::get();
        $csp   = HeaderBuilder::buildDevelopment();

        $this->assertStringContainsString(sprintf("'nonce-%s'", $nonce), $csp);
        $this->assertStringContainsString("script-src", $csp);
    }

    public function testDevelopmentCspAllowsUnsafeEvalForViteHmr(): void
    {
        $csp = HeaderBuilder::buildDevelopment();

        $this->assertStringContainsString("'unsafe-eval'", $csp);
        $this->assertStringContainsString("script-src", $csp);
    }

    public function testDevelopmentCspAllowsUnsafeInlineForViteStyles(): void
    {
        $csp = HeaderBuilder::buildDevelopment();

        $this->assertStringContainsString("'unsafe-inline'", $csp);
        $this->assertStringContainsString("style-src", $csp);
    }

    public function testDevelopmentCspDoesNotContainUnsafeHashes(): void
    {
        $csp = HeaderBuilder::buildDevelopment();

        $this->assertStringNotContainsString("'unsafe-hashes'", $csp);
    }

    public function testDevelopmentCspContainsStrictDynamic(): void
    {
        $csp = HeaderBuilder::buildDevelopment();

        $this->assertStringContainsString("'strict-dynamic'", $csp);
    }

    public function testDevelopmentCspAllowsWebSocketConnections(): void
    {
        $csp = HeaderBuilder::buildDevelopment();

        $this->assertStringContainsString("connect-src", $csp);
        $this->assertStringContainsString("wss://localhost:8443", $csp);
        $this->assertStringContainsString("https://localhost:8443", $csp);
    }

    public function testDevelopmentCspContainsSecurityDirectives(): void
    {
        $csp = HeaderBuilder::buildDevelopment();

        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $this->assertStringContainsString("base-uri 'self'", $csp);
        $this->assertStringContainsString("form-action 'self'", $csp);
    }
}
