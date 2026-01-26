<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Security\CspNonceHelper;
use PHPUnit\Framework\TestCase;

/**
 * Tests for CspNonceHelper environment detection
 */
class CspNonceHelperEnvironmentTest extends TestCase
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
