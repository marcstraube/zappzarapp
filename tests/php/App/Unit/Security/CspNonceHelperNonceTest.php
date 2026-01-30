<?php

declare(strict_types=1);

namespace Tests\App\Unit\Security;

use PHPUnit\Framework\TestCase;
use Zappzarapp\Security\Csp\NonceGenerator;

/**
 * Tests for CspNonceHelper nonce generation and retrieval
 */
class CspNonceHelperNonceTest extends TestCase
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

    public function testGenerateCreatesBase64EncodedString(): void
    {
        $nonce = NonceGenerator::generate();

        $this->assertIsString($nonce);
        $this->assertNotEmpty($nonce);
        // Base64 strings only contain alphanumeric chars, +, /, and =
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9+\/=]+$/', $nonce);
    }

    public function testGenerateReturnsSameNonceForSameRequest(): void
    {
        $nonce1 = NonceGenerator::generate();
        $nonce2 = NonceGenerator::generate();

        $this->assertSame($nonce1, $nonce2);
    }

    public function testGetReturnsGeneratedNonce(): void
    {
        $nonce    = NonceGenerator::generate();
        $getNonce = NonceGenerator::get();

        $this->assertSame($nonce, $getNonce);
    }

    public function testGetGeneratesNonceIfNotExists(): void
    {
        $nonce = NonceGenerator::get();

        $this->assertIsString($nonce);
        $this->assertNotEmpty($nonce);
    }
}
