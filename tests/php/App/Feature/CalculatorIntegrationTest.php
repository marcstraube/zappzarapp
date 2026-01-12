<?php

declare(strict_types=1);

namespace App\Tests\Feature;

use App\Utils\Calculator;
use DivisionByZeroError;
use PHPUnit\Framework\TestCase;

/**
 * Example feature/integration test
 * (Demonstrates testing complex workflows across multiple components)
 *
 * Note: This is a simple example. Real feature tests would test
 * full application workflows (e.g., HTTP requests, database interactions)
 */
class CalculatorIntegrationTest extends TestCase
{
    private Calculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new Calculator();
    }

    public function testComplexMathematicalOperation(): void
    {
        // Test: Calculate (10 + 5) * 2 / 3
        $step1 = $this->calculator->add(10, 5);        // 15
        $step2 = $this->calculator->multiply($step1, 2); // 30
        $step3 = $this->calculator->divide($step2, 3);   // 10

        $this->assertEquals(10, $step3);
    }

    public function testMultipleOperationsWithValidation(): void
    {
        $values = [2, 4, 6, 8, 10];
        $sum    = 0;

        // Add all values
        foreach ($values as $value) {
            $sum = $this->calculator->add($sum, $value);
        }

        $this->assertEquals(30, $sum);

        // Verify sum is even
        $this->assertTrue($this->calculator->isEven((int) $sum));

        // Divide by count
        $average = $this->calculator->divide($sum, count($values));
        $this->assertEquals(6, $average);
    }

    public function testWorkflowWithErrorHandling(): void
    {
        $numerator   = 100;
        $denominator = 0;

        // Test that the workflow properly handles errors
        try {
            $this->calculator->divide($numerator, $denominator);
            $this->fail('Expected DivisionByZeroError was not thrown');
        } catch (DivisionByZeroError $divisionByZeroError) {
            $this->assertStringContainsString('Division by zero', $divisionByZeroError->getMessage());

            // Fallback: Use safe default
            $result = $this->calculator->multiply($numerator, 0);
            $this->assertEquals(0, $result);
        }
    }

    public function testChainedCalculations(): void
    {
        // Start value
        $value = 10;

        // Chain multiple operations
        $value = $this->calculator->multiply($value, 3);  // 30
        $this->assertEquals(30, $value);

        $value = $this->calculator->add($value, 15);      // 45
        $this->assertEquals(45, $value);

        $value = $this->calculator->divide($value, 9);    // 5
        $this->assertEquals(5, $value);

        // Verify final result
        $this->assertTrue($this->calculator->isEven((int) $value) === false);
    }
}
