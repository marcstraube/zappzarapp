<?php

declare(strict_types=1);

namespace Tests\App\Unit\Security;

use App\Security\CspNonceRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Tests for CspNonceRegistry nonce generation and retrieval
 */
class CspNonceHelperNonceTest extends TestCase
{
    protected function setUp(): void
    {
        CspNonceRegistry::reset();
    }

    protected function tearDown(): void
    {
        CspNonceRegistry::reset();
    }

    public function testGetCreatesBase64EncodedString(): void
    {
        $nonce = CspNonceRegistry::get();

        $this->assertIsString($nonce);
        $this->assertNotEmpty($nonce);
        // Base64 strings only contain alphanumeric chars, +, /, and =
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9+\/=]+$/', $nonce);
    }

    public function testGetReturnsSameNonceForSameRequest(): void
    {
        $nonce1 = CspNonceRegistry::get();
        $nonce2 = CspNonceRegistry::get();

        $this->assertSame($nonce1, $nonce2);
    }

    public function testGeneratorReturnsSameInstance(): void
    {
        $generator1 = CspNonceRegistry::generator();
        $generator2 = CspNonceRegistry::generator();

        $this->assertSame($generator1, $generator2);
    }

    public function testResetCreatesNewNonce(): void
    {
        $nonce1 = CspNonceRegistry::get();
        CspNonceRegistry::reset();
        $nonce2 = CspNonceRegistry::get();

        $this->assertNotSame($nonce1, $nonce2);
    }

    public function testSetOverridesGeneratedNonce(): void
    {
        $customNonce = 'Y3VzdG9tLW5vbmNl'; // 'custom-nonce' in base64

        CspNonceRegistry::set($customNonce);
        $retrievedNonce = CspNonceRegistry::get();

        $this->assertSame($customNonce, $retrievedNonce);
    }
}
