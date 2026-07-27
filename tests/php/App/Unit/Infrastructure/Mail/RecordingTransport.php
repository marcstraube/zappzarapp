<?php

declare(strict_types=1);

namespace Tests\App\Unit\Infrastructure\Mail;

use Override;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;

/**
 * Test transport that records the last message instead of sending it.
 */
final class RecordingTransport implements TransportInterface
{
    public ?RawMessage $lastMessage = null;

    /**
     * @SuppressWarnings("PHPMD.UnusedFormalParameter") $envelope is fixed by TransportInterface but not needed to record the message.
     */
    #[Override]
    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        $this->lastMessage = $message;

        return null;
    }

    #[Override]
    public function __toString(): string
    {
        return 'recording://';
    }
}
