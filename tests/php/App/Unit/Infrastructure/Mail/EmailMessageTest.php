<?php

declare(strict_types=1);

namespace Tests\App\Unit\Infrastructure\Mail;

use App\Infrastructure\Mail\EmailMessage;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the EmailMessage value object
 */
#[CoversClass(EmailMessage::class)]
final class EmailMessageTest extends TestCase
{
    #[Test]
    public function itExposesTheProvidedValues(): void
    {
        $message = new EmailMessage(
            to: ['user@example.com'],
            subject: 'Hello',
            text: 'Plain',
            html: '<p>Rich</p>',
            from: 'sender@example.com',
            cc: ['cc@example.com'],
            bcc: ['bcc@example.com'],
            replyTo: 'reply@example.com',
        );

        self::assertSame(['user@example.com'], $message->to);
        self::assertSame('Hello', $message->subject);
        self::assertSame('Plain', $message->text);
        self::assertSame('<p>Rich</p>', $message->html);
        self::assertSame('sender@example.com', $message->from);
        self::assertSame(['cc@example.com'], $message->cc);
        self::assertSame(['bcc@example.com'], $message->bcc);
        self::assertSame('reply@example.com', $message->replyTo);
    }

    #[Test]
    public function itThrowsWithoutRecipients(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('at least one recipient');

        new EmailMessage(to: [], subject: 'Hello', text: 'Body');
    }

    #[Test]
    public function itThrowsWithoutABody(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('text or HTML body');

        new EmailMessage(to: ['user@example.com'], subject: 'Hello');
    }

    #[Test]
    public function itAllowsAnHtmlOnlyBody(): void
    {
        $message = new EmailMessage(to: ['user@example.com'], subject: 'Hello', html: '<p>Hi</p>');

        self::assertNull($message->text);
        self::assertSame('<p>Hi</p>', $message->html);
    }
}
