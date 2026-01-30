<?php

declare(strict_types=1);

namespace Tests\App\Unit\Security;

use App\Security\CspNonceRegistry;
use PHPUnit\Framework\TestCase;
use Zappzarapp\Security\Csp\Directive\CspDirectives;
use Zappzarapp\Security\Csp\HeaderBuilder;

/**
 * Tests for CSP Header building with environment-based configuration
 */
class CspNonceHelperEnvironmentTest extends TestCase
{
    protected function setUp(): void
    {
        // Reset nonce before each test
        CspNonceRegistry::reset();
    }

    protected function tearDown(): void
    {
        // Reset nonce after each test
        CspNonceRegistry::reset();
    }

    public function testBuildCspHeaderWithDevelopmentMode(): void
    {
        $cspDirectives = CspDirectives::development('localhost:5173');
        $csp           = HeaderBuilder::build($cspDirectives, CspNonceRegistry::generator());

        $this->assertStringContainsString("'unsafe-eval'", $csp);
    }

    public function testBuildCspHeaderWithProductionMode(): void
    {
        $cspDirectives = CspDirectives::strict();
        $csp           = HeaderBuilder::build($cspDirectives, CspNonceRegistry::generator());

        $this->assertStringNotContainsString("'unsafe-eval'", $csp);
        $this->assertStringNotContainsString("'unsafe-inline'", $csp);
    }

    public function testBuildCspHeaderWithEnvironmentDetection(): void
    {
        // Test that application code can use ENV to determine mode
        putenv('ENV=development');
        $isDevelopment = getenv('ENV') === 'development';

        $cspDirectives = $isDevelopment
            ? CspDirectives::development('localhost:5173')
            : CspDirectives::strict();

        $csp = HeaderBuilder::build($cspDirectives, CspNonceRegistry::generator());

        $this->assertStringContainsString("'unsafe-eval'", $csp);

        // Cleanup
        putenv('ENV');
    }

    public function testCspNonceRegistryReturnsSameNonce(): void
    {
        $nonce1 = CspNonceRegistry::get();
        $nonce2 = CspNonceRegistry::get();

        $this->assertSame($nonce1, $nonce2, 'Nonce should be consistent within a request');
    }

    public function testCspNonceRegistryResetGeneratesNewNonce(): void
    {
        $nonce1 = CspNonceRegistry::get();
        CspNonceRegistry::reset();
        $nonce2 = CspNonceRegistry::get();

        $this->assertNotSame($nonce1, $nonce2, 'Nonce should change after reset');
    }
}
