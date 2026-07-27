<?php

declare(strict_types=1);

namespace Tests\App\Unit\Infrastructure\Mail;

use Override;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;

/**
 * Test transport that always fails, to exercise error handling.
 */
final class FailingTransport implements TransportInterface
{
    /**
     * @SuppressWarnings("PHPMD.UnusedFormalParameter") Parameters are fixed by TransportInterface; this double ignores them and always fails.
     */
    #[Override]
    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        throw new TransportException('smtp connection failed');
    }

    #[Override]
    public function __toString(): string
    {
        return 'failing://';
    }
}
