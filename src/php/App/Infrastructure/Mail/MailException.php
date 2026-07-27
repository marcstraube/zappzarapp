<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use RuntimeException;

/**
 * Thrown when sending an email fails
 *
 * Wraps transport-level failures (SMTP unreachable, auth rejected, …) so
 * callers depend on this module's exception rather than the underlying mailer.
 *
 * @package Infrastructure\Mail
 */
final class MailException extends RuntimeException
{
}
