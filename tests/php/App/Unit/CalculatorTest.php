<?php

declare(strict_types=1);

namespace Tests\App\Unit;

use App\Utils\Calculator;
use DivisionByZeroError;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Example unit test for Calculator utility class
 * (Analog to tests/node/unit/math.test.ts)
 *
 * @SuppressWarnings("PHPMD.TooManyPublicMethods")
 */
class CalculatorTest extends TestCase
{
    private Calculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new Calculator();
    }

    // ===== Addition Tests =====

    #[Test]
    public function testAddTwoPositiveNumbers(): void
    {
        $this->assertEquals(5, $this->calculator->add(2, 3));
    }

    #[Test]
    public function testAddNegativeNumbers(): void
    {
        $this->assertEquals(-5, $this->calculator->add(-2, -3));
    }

    #[Test]
    public function testAddWithZero(): void
    {
        $this->assertEquals(5, $this->calculator->add(5, 0));
        $this->assertEquals(5, $this->calculator->add(0, 5));
    }

    // ===== Multiplication Tests =====

    #[Test]
    public function testMultiplyTwoPositiveNumbers(): void
    {
        $this->assertEquals(12, $this->calculator->multiply(3, 4));
    }

    #[Test]
    public function testMultiplyByZero(): void
    {
        $this->assertEquals(0, $this->calculator->multiply(5, 0));
    }

    #[Test]
    public function testMultiplyNegativeNumbers(): void
    {
        $this->assertEquals(-12, $this->calculator->multiply(-3, 4));
        $this->assertEquals(12, $this->calculator->multiply(-3, -4));
    }

    // ===== Division Tests =====

    #[Test]
    public function testDivideTwoNumbers(): void
    {
        $this->assertEquals(5, $this->calculator->divide(10, 2));
    }

    #[Test]
    public function testDivideResultingInDecimal(): void
    {
        $this->assertEquals(3.5, $this->calculator->divide(7, 2));
    }

    #[Test]
    public function testDivideByZeroThrowsException(): void
    {
        $this->expectException(DivisionByZeroError::class);
        $this->expectExceptionMessage('Division by zero is not allowed');
        $this->calculator->divide(10, 0);
    }

    #[Test]
    public function testDivideNegativeNumbers(): void
    {
        $this->assertEquals(-5, $this->calculator->divide(-10, 2));
        $this->assertEquals(5, $this->calculator->divide(-10, -2));
    }

    // ===== isEven Tests =====

    #[Test]
    public function testIsEvenReturnsTrueForEvenNumbers(): void
    {
        $this->assertTrue($this->calculator->isEven(2));
        $this->assertTrue($this->calculator->isEven(4));
        $this->assertTrue($this->calculator->isEven(0));
    }

    #[Test]
    public function testIsEvenReturnsFalseForOddNumbers(): void
    {
        $this->assertFalse($this->calculator->isEven(1));
        $this->assertFalse($this->calculator->isEven(3));
        $this->assertFalse($this->calculator->isEven(5));
    }

    #[Test]
    public function testIsEvenHandlesNegativeNumbers(): void
    {
        $this->assertTrue($this->calculator->isEven(-2));
        $this->assertFalse($this->calculator->isEven(-3));
    }
}
