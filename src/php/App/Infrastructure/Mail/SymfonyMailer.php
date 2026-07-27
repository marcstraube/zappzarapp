<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface as SymfonyMailerContract;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;

/**
 * Mailer implementation backed by symfony/mailer
 *
 * Thin adapter that maps an {@see EmailMessage} to a Symfony {@see Email} and
 * hands it to a Symfony transport. The transport is built from the configured
 * DSN, or injected directly (used in tests).
 *
 * @package Infrastructure\Mail
 */
final readonly class SymfonyMailer implements MailerInterface
{
    private SymfonyMailerContract $mailer;

    public function __construct(
        private MailConfig $config,
        ?TransportInterface $transport = null,
    ) {
        $this->mailer = new Mailer($transport ?? Transport::fromDsn($config->dsn));
    }

    public function send(EmailMessage $message): void
    {
        $email = new Email();
        $email->from($message->from ?? $this->config->defaultFrom);
        $email->to(...$message->to);
        $email->subject($message->subject);

        if ($message->cc !== []) {
            $email->cc(...$message->cc);
        }

        if ($message->bcc !== []) {
            $email->bcc(...$message->bcc);
        }

        if ($message->replyTo !== null) {
            $email->replyTo($message->replyTo);
        }

        if ($message->text !== null) {
            $email->text($message->text);
        }

        if ($message->html !== null) {
            $email->html($message->html);
        }

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $exception) {
            throw new MailException('Failed to send email: ' . $exception->getMessage(), 0, $exception);
        }
    }
}
