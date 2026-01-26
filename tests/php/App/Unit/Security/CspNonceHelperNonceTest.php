<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Security\CspNonceHelper;
use PHPUnit\Framework\TestCase;

/**
 * Tests for CspNonceHelper nonce generation and retrieval
 */
class CspNonceHelperNonceTest extends TestCase
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

    public function testGenerateCreatesBase64EncodedString(): void
    {
        $nonce = CspNonceHelper::generate();

        $this->assertIsString($nonce);
        $this->assertNotEmpty($nonce);
        // Base64 strings only contain alphanumeric chars, +, /, and =
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9+\/=]+$/', $nonce);
    }

    public function testGenerateReturnsSameNonceForSameRequest(): void
    {
        $nonce1 = CspNonceHelper::generate();
        $nonce2 = CspNonceHelper::generate();

        $this->assertSame($nonce1, $nonce2);
    }

    public function testGetReturnsGeneratedNonce(): void
    {
        $nonce    = CspNonceHelper::generate();
        $getNonce = CspNonceHelper::get();

        $this->assertSame($nonce, $getNonce);
    }

    public function testGetGeneratesNonceIfNotExists(): void
    {
        $nonce = CspNonceHelper::get();

        $this->assertIsString($nonce);
        $this->assertNotEmpty($nonce);
    }
}
