<?php

declare(strict_types=1);

namespace App\Infrastructure\Mercure;

use RuntimeException;

/**
 * Thrown when publishing to the Mercure hub fails
 *
 * Covers missing configuration (no JWT key), transport errors (hub not
 * reachable) and non-2xx responses from the hub.
 *
 * @package Infrastructure\Mercure
 */
final class MercureException extends RuntimeException
{
}
