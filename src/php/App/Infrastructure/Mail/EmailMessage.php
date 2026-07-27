<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use InvalidArgumentException;

/**
 * Immutable value object describing an email to send
 *
 * At least one recipient and at least one body (text or HTML) are required.
 * The sender defaults to the configured "from" address when omitted.
 *
 * @package Infrastructure\Mail
 */
final readonly class EmailMessage
{
    /**
     * @param list<string> $to Recipient addresses (at least one)
     * @param list<string> $cc Carbon-copy addresses
     * @param list<string> $bcc Blind-carbon-copy addresses
     */
    public function __construct(
        public array $to,
        public string $subject,
        public ?string $text = null,
        public ?string $html = null,
        public ?string $from = null,
        public array $cc = [],
        public array $bcc = [],
        public ?string $replyTo = null,
    ) {
        if ($to === []) {
            throw new InvalidArgumentException('An email needs at least one recipient');
        }

        if ($text === null && $html === null) {
            throw new InvalidArgumentException('An email needs a text or HTML body');
        }
    }
}
