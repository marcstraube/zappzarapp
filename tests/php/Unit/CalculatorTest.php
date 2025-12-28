<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Utils\Calculator;
use DivisionByZeroError;
use PHPUnit\Framework\TestCase;

/**
 * Example unit test for Calculator utility class
 * (Analog to tests/node/unit/math.test.ts)
 */
class CalculatorTest extends TestCase
{
    private Calculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new Calculator();
    }

    // ===== Addition Tests =====

    public function testAddTwoPositiveNumbers(): void
    {
        $this->assertEquals(5, $this->calculator->add(2, 3));
    }

    public function testAddNegativeNumbers(): void
    {
        $this->assertEquals(-5, $this->calculator->add(-2, -3));
    }

    public function testAddWithZero(): void
    {
        $this->assertEquals(5, $this->calculator->add(5, 0));
        $this->assertEquals(5, $this->calculator->add(0, 5));
    }

    // ===== Multiplication Tests =====

    public function testMultiplyTwoPositiveNumbers(): void
    {
        $this->assertEquals(12, $this->calculator->multiply(3, 4));
    }

    public function testMultiplyByZero(): void
    {
        $this->assertEquals(0, $this->calculator->multiply(5, 0));
    }

    public function testMultiplyNegativeNumbers(): void
    {
        $this->assertEquals(-12, $this->calculator->multiply(-3, 4));
        $this->assertEquals(12, $this->calculator->multiply(-3, -4));
    }

    // ===== Division Tests =====

    public function testDivideTwoNumbers(): void
    {
        $this->assertEquals(5, $this->calculator->divide(10, 2));
    }

    public function testDivideResultingInDecimal(): void
    {
        $this->assertEquals(3.5, $this->calculator->divide(7, 2));
    }

    public function testDivideByZeroThrowsException(): void
    {
        $this->expectException(DivisionByZeroError::class);
        $this->expectExceptionMessage('Division by zero is not allowed');
        $this->calculator->divide(10, 0);
    }

    public function testDivideNegativeNumbers(): void
    {
        $this->assertEquals(-5, $this->calculator->divide(-10, 2));
        $this->assertEquals(5, $this->calculator->divide(-10, -2));
    }

    // ===== isEven Tests =====

    public function testIsEvenReturnsTrueForEvenNumbers(): void
    {
        $this->assertTrue($this->calculator->isEven(2));
        $this->assertTrue($this->calculator->isEven(4));
        $this->assertTrue($this->calculator->isEven(0));
    }

    public function testIsEvenReturnsFalseForOddNumbers(): void
    {
        $this->assertFalse($this->calculator->isEven(1));
        $this->assertFalse($this->calculator->isEven(3));
        $this->assertFalse($this->calculator->isEven(5));
    }

    public function testIsEvenHandlesNegativeNumbers(): void
    {
        $this->assertTrue($this->calculator->isEven(-2));
        $this->assertFalse($this->calculator->isEven(-3));
    }
}
