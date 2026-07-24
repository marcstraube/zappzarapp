<?php

declare(strict_types=1);

namespace Tests\App\Unit\Infrastructure;

use App\Infrastructure\HealthStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests for HealthStatus enum
 */
#[CoversClass(HealthStatus::class)]
final class HealthStatusTest extends TestCase
{
    // =========================================================================
    // isHealthy()
    // =========================================================================

    public function testOkIsHealthy(): void
    {
        $this->assertTrue(HealthStatus::OK->isHealthy());
    }

    public function testDegradedIsNotHealthy(): void
    {
        $this->assertFalse(HealthStatus::DEGRADED->isHealthy());
    }

    public function testErrorIsNotHealthy(): void
    {
        $this->assertFalse(HealthStatus::ERROR->isHealthy());
    }

    public function testDisabledIsNotHealthy(): void
    {
        $this->assertFalse(HealthStatus::DISABLED->isHealthy());
    }

    public function testUnknownIsNotHealthy(): void
    {
        $this->assertFalse(HealthStatus::UNKNOWN->isHealthy());
    }

    // =========================================================================
    // isOperational()
    // =========================================================================

    public function testOkIsOperational(): void
    {
        $this->assertTrue(HealthStatus::OK->isOperational());
    }

    public function testDegradedIsOperational(): void
    {
        $this->assertTrue(HealthStatus::DEGRADED->isOperational());
    }

    public function testErrorIsNotOperational(): void
    {
        $this->assertFalse(HealthStatus::ERROR->isOperational());
    }

    public function testDisabledIsNotOperational(): void
    {
        $this->assertFalse(HealthStatus::DISABLED->isOperational());
    }

    public function testUnknownIsNotOperational(): void
    {
        $this->assertFalse(HealthStatus::UNKNOWN->isOperational());
    }

    // =========================================================================
    // label()
    // =========================================================================

    #[DataProvider('labelProvider')]
    public function testLabel(HealthStatus $status, string $expectedLabel): void
    {
        $this->assertSame($expectedLabel, $status->label());
    }

    /**
     * @return array<string, array{HealthStatus, string}>
     */
    public static function labelProvider(): array
    {
        return [
            'ok label'       => [HealthStatus::OK, 'Healthy'],
            'degraded label' => [HealthStatus::DEGRADED, 'Degraded'],
            'error label'    => [HealthStatus::ERROR, 'Error'],
            'disabled label' => [HealthStatus::DISABLED, 'Disabled'],
            'unknown label'  => [HealthStatus::UNKNOWN, 'Unknown'],
        ];
    }

    // =========================================================================
    // color()
    // =========================================================================

    #[DataProvider('colorProvider')]
    public function testColor(HealthStatus $status, string $expectedColor): void
    {
        $this->assertSame($expectedColor, $status->color());
    }

    /**
     * @return array<string, array{HealthStatus, string}>
     */
    public static function colorProvider(): array
    {
        return [
            'ok color'       => [HealthStatus::OK, 'green'],
            'degraded color' => [HealthStatus::DEGRADED, 'yellow'],
            'error color'    => [HealthStatus::ERROR, 'red'],
            'disabled color' => [HealthStatus::DISABLED, 'gray'],
            'unknown color'  => [HealthStatus::UNKNOWN, 'gray'],
        ];
    }

    // =========================================================================
    // Enum backing values
    // =========================================================================

    #[DataProvider('backingValueProvider')]
    public function testBackingValues(HealthStatus $status, string $expectedValue): void
    {
        $this->assertSame($expectedValue, $status->value);
    }

    /**
     * @return array<string, array{HealthStatus, string}>
     */
    public static function backingValueProvider(): array
    {
        return [
            'ok value'       => [HealthStatus::OK, 'ok'],
            'degraded value' => [HealthStatus::DEGRADED, 'degraded'],
            'error value'    => [HealthStatus::ERROR, 'error'],
            'disabled value' => [HealthStatus::DISABLED, 'disabled'],
            'unknown value'  => [HealthStatus::UNKNOWN, 'unknown'],
        ];
    }

    public function testFromReturnsCorrectCase(): void
    {
        $this->assertSame(HealthStatus::OK, HealthStatus::from('ok'));
        $this->assertSame(HealthStatus::DEGRADED, HealthStatus::from('degraded'));
        $this->assertSame(HealthStatus::ERROR, HealthStatus::from('error'));
        $this->assertSame(HealthStatus::DISABLED, HealthStatus::from('disabled'));
        $this->assertSame(HealthStatus::UNKNOWN, HealthStatus::from('unknown'));
    }

    public function testTryFromReturnsNullForUnknownValue(): void
    {
        $this->assertNull(HealthStatus::tryFrom('nonexistent'));
    }

    public function testCasesReturnsAllFive(): void
    {
        $this->assertCount(5, HealthStatus::cases());
    }
}
