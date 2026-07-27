<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

/**
 * Mailer Interface for sending emails
 *
 * Provides a thin, type-safe abstraction over an SMTP mail transport. The
 * concrete implementation wraps a mail library; swapping the library only
 * requires a new implementation of this interface.
 *
 * When to use:
 * - Transactional email (sign-up, password reset, receipts)
 * - Notifications triggered by application events
 *
 * When NOT to use:
 * - Bulk/marketing campaigns (use a dedicated ESP)
 * - Real-time in-app updates (use Mercure)
 *
 * Usage:
 * <code>
 * // Get from DI container
 * $mailer = $container->get(MailerInterface::class);
 *
 * $mailer->send(new EmailMessage(
 *     to: ['user@example.com'],
 *     subject: 'Welcome',
 *     html: '<p>Welcome aboard!</p>',
 *     text: 'Welcome aboard!',
 * ));
 * </code>
 *
 * @package Infrastructure\Mail
 */
interface MailerInterface
{
    /**
     * Send an email
     *
     * @throws MailException If the message cannot be delivered to the transport
     */
    public function send(EmailMessage $message): void;
}
