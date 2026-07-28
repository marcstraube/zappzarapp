<?php

declare(strict_types=1);

namespace Tests\App\Unit\Infrastructure\Mail;

use App\Infrastructure\Config\CredentialLoader;
use App\Infrastructure\Mail\EmailMessage;
use App\Infrastructure\Mail\MailConfig;
use App\Infrastructure\Mail\MailException;
use App\Infrastructure\Mail\SymfonyMailer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Email;

/**
 * Tests for SymfonyMailer — the transport is injected, so no real SMTP
 * connection is made.
 */
#[CoversClass(SymfonyMailer::class)]
#[UsesClass(CredentialLoader::class)]
#[UsesClass(EmailMessage::class)]
#[UsesClass(MailConfig::class)]
final class SymfonyMailerTest extends TestCase
{
    private function config(): MailConfig
    {
        return new MailConfig('smtp://mailpit:1025');
    }

    #[Test]
    public function itSendsAnEmailThroughTheTransport(): void
    {
        $transport = new RecordingTransport();
        $mailer    = new SymfonyMailer($this->config(), $transport);

        $mailer->send(new EmailMessage(
            to: ['user@example.com'],
            subject: 'Welcome',
            text: 'Plain body',
            html: '<p>Rich body</p>',
        ));

        self::assertInstanceOf(Email::class, $transport->lastMessage);
        $email = $transport->lastMessage;
        self::assertSame('Welcome', $email->getSubject());
        self::assertSame('user@example.com', $email->getTo()[0]->getAddress());
        self::assertSame('no-reply@localhost', $email->getFrom()[0]->getAddress());
        self::assertSame('Plain body', $email->getTextBody());
        self::assertSame('<p>Rich body</p>', $email->getHtmlBody());
    }

    #[Test]
    public function itAppliesSenderCcBccAndReplyTo(): void
    {
        $transport = new RecordingTransport();
        $mailer    = new SymfonyMailer($this->config(), $transport);

        $mailer->send(new EmailMessage(
            to: ['user@example.com'],
            subject: 'Subject',
            text: 'Body',
            from: 'sender@example.com',
            cc: ['cc@example.com'],
            bcc: ['bcc@example.com'],
            replyTo: 'reply@example.com',
        ));

        self::assertInstanceOf(Email::class, $transport->lastMessage);
        $email = $transport->lastMessage;
        self::assertSame('sender@example.com', $email->getFrom()[0]->getAddress());
        self::assertSame('cc@example.com', $email->getCc()[0]->getAddress());
        self::assertSame('bcc@example.com', $email->getBcc()[0]->getAddress());
        self::assertSame('reply@example.com', $email->getReplyTo()[0]->getAddress());
    }

    #[Test]
    public function itWrapsTransportFailuresInAMailException(): void
    {
        $mailer = new SymfonyMailer($this->config(), new FailingTransport());

        $this->expectException(MailException::class);
        $this->expectExceptionMessage('Failed to send email');

        $mailer->send(new EmailMessage(to: ['user@example.com'], subject: 'Subject', text: 'Body'));
    }
}
