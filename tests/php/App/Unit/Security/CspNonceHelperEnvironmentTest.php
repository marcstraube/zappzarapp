<?php

declare(strict_types=1);

namespace Tests\App\Unit\Security;

use PHPUnit\Framework\TestCase;
use Zappzarapp\Security\Csp\HeaderBuilder;
use Zappzarapp\Security\Csp\NonceGenerator;

/**
 * Tests for CspNonceHelper environment detection
 */
class CspNonceHelperEnvironmentTest extends TestCase
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

    public function testBuildCspHeaderDetectsDevelopmentEnvironment(): void
    {
        // Mock ENV environment variable
        putenv('ENV=development');

        $csp = HeaderBuilder::build();

        $this->assertStringContainsString("'unsafe-eval'", $csp);

        // Cleanup
        putenv('ENV');
    }

    public function testBuildCspHeaderDetectsProductionEnvironment(): void
    {
        // Mock ENV environment variable
        putenv('ENV=production');

        $csp = HeaderBuilder::build();

        $this->assertStringNotContainsString("'unsafe-eval'", $csp);
        $this->assertStringNotContainsString("'unsafe-inline'", $csp);

        // Cleanup
        putenv('ENV');
    }

    public function testBuildCspHeaderDefaultsToProductionWhenEnvNotSet(): void
    {
        // Ensure ENV is not set
        putenv('ENV');

        $csp = HeaderBuilder::build();

        // Should use production (strict) CSP when ENV is not set
        $this->assertStringNotContainsString("'unsafe-eval'", $csp);

        // Cleanup
        putenv('ENV');
    }
}
