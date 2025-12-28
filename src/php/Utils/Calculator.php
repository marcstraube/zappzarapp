<?php

declare(strict_types=1);

namespace App\Utils;

use DivisionByZeroError;

/**
 * Example utility class for demonstration purposes
 * (Analog to src/node/utils/math.ts)
 */
class Calculator
{
    public function add(int|float $a, int|float $b): int|float
    {
        return $a + $b;
    }

    public function multiply(int|float $a, int|float $b): int|float
    {
        return $a * $b;
    }

    public function divide(int|float $a, int|float $b): int|float
    {
        if ($b === 0 || $b === 0.0) {
            throw new DivisionByZeroError('Division by zero is not allowed');
        }

        return $a / $b;
    }

    public function isEven(int $num): bool
    {
        return $num % 2 === 0;
    }
}
