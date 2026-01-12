<?php

declare(strict_types=1);

namespace App\Infrastructure;

/**
 * Health Status Enum for service health checks
 *
 * Used by HealthCheck class to represent service states.
 */
enum HealthStatus: string
{
    case OK       = 'ok';
    case DEGRADED = 'degraded';
    case ERROR    = 'error';
    case DISABLED = 'disabled';
    case UNKNOWN  = 'unknown';

    /**
     * Check if the status indicates a healthy service
     */
    public function isHealthy(): bool
    {
        return $this === self::OK;
    }

    /**
     * Check if the status indicates an operational service (ok or degraded)
     */
    public function isOperational(): bool
    {
        return $this === self::OK || $this === self::DEGRADED;
    }

    /**
     * Get a human-readable label
     */
    public function label(): string
    {
        return match ($this) {
            self::OK       => 'Healthy',
            self::DEGRADED => 'Degraded',
            self::ERROR    => 'Error',
            self::DISABLED => 'Disabled',
            self::UNKNOWN  => 'Unknown',
        };
    }

    /**
     * Get a color code for UI display
     */
    public function color(): string
    {
        return match ($this) {
            self::OK       => 'green',
            self::DEGRADED => 'yellow',
            self::ERROR    => 'red',
            self::DISABLED => 'gray',
            self::UNKNOWN  => 'gray',
        };
    }
}
