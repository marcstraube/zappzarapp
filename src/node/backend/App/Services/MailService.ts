/**
 * Mail Service for sending emails
 *
 * Thin abstraction over an SMTP mail transport (nodemailer). Swapping the mail
 * library only requires a new implementation of MailServiceInterface.
 *
 * Configuration:
 * - MAIL_DSN / MAILER_DSN: Full SMTP DSN (default: smtp://mailpit:1025)
 * - Or discrete MAIL_HOST / MAIL_PORT / MAIL_USER plus the mail_password
 *   Docker secret / MAIL_PASSWORD environment variable
 * - MAIL_FROM: Default sender address (default: no-reply@localhost)
 *
 * Usage:
 * ```typescript
 * const mailer = new MailService();
 * await mailer.send({
 *   to: 'user@example.com',
 *   subject: 'Welcome',
 *   text: 'Welcome aboard!',
 *   html: '<p>Welcome aboard!</p>',
 * });
 * ```
 */

import nodemailer, { type Transporter } from 'nodemailer';
import { loadCredential } from '../../Shared/Config/credentials.js';

/** Default sender used when a message omits its "from" address */
const DEFAULT_FROM = 'no-reply@localhost';

/**
 * Email to send
 *
 * @public Boilerplate API — shipped for consumers, no in-tree importer expected.
 */
export interface EmailMessage {
  /** Recipient address(es); at least one is required */
  to: string | string[];
  subject: string;
  /** Plain-text body (text and/or html is required) */
  text?: string;
  /** HTML body (text and/or html is required) */
  html?: string;
  /** Sender address (defaults to the configured sender) */
  from?: string;
  cc?: string | string[];
  bcc?: string | string[];
  replyTo?: string;
}

/**
 * MailService constructor options
 *
 * @public Boilerplate API — shipped for consumers, no in-tree importer expected.
 */
export interface MailServiceOptions {
  /** SMTP DSN (default: from env or smtp://mailpit:1025) */
  dsn?: string;
  /** Default sender address (default: from env or no-reply@localhost) */
  defaultFrom?: string;
  /** Pre-built transporter (used in tests) */
  transporter?: Transporter;
}

/**
 * Mailer interface
 *
 * @public Boilerplate API — shipped for consumers, no in-tree importer expected.
 */
export interface MailServiceInterface {
  send(message: EmailMessage): Promise<void>;
}

/**
 * Error thrown when sending an email fails
 *
 * @public Boilerplate API — shipped for consumers, no in-tree importer expected.
 */
export class MailError extends Error {
  constructor(message: string) {
    super(message);
    this.name = 'MailError';
  }
}

/**
 * Resolve the SMTP DSN from environment or discrete parts
 */
function resolveDsn(): string {
  const envDsn = process.env.MAIL_DSN ?? process.env.MAILER_DSN;
  if (envDsn !== undefined && envDsn !== '') {
    return envDsn;
  }

  const host = process.env.MAIL_HOST ?? 'mailpit';
  const port = process.env.MAIL_PORT ?? '1025';
  const user = process.env.MAIL_USER ?? '';

  if (user === '') {
    return `smtp://${host}:${port}`;
  }

  const password = loadCredential('mail_password', 'MAIL_PASSWORD');

  return `smtp://${encodeURIComponent(user)}:${encodeURIComponent(password)}@${host}:${port}`;
}

/**
 * Mail Service
 *
 * Sends emails through an SMTP transport.
 */
export class MailService implements MailServiceInterface {
  private readonly transporter: Transporter;
  private readonly defaultFrom: string;

  constructor(options: MailServiceOptions = {}) {
    this.defaultFrom = options.defaultFrom ?? process.env.MAIL_FROM ?? DEFAULT_FROM;
    this.transporter =
      options.transporter ?? nodemailer.createTransport(options.dsn ?? resolveDsn());
  }

  /**
   * Send an email
   *
   * @throws {MailError} If the message is invalid or cannot be delivered
   */
  async send(message: EmailMessage): Promise<void> {
    const recipients = Array.isArray(message.to) ? message.to : [message.to];
    if (recipients.length === 0 || recipients.every((recipient) => recipient === '')) {
      throw new MailError('An email needs at least one recipient');
    }

    if (message.text === undefined && message.html === undefined) {
      throw new MailError('An email needs a text or HTML body');
    }

    try {
      await this.transporter.sendMail({
        from: message.from ?? this.defaultFrom,
        to: message.to,
        cc: message.cc,
        bcc: message.bcc,
        replyTo: message.replyTo,
        subject: message.subject,
        text: message.text,
        html: message.html,
      });
    } catch (error) {
      throw new MailError(
        `Failed to send email: ${error instanceof Error ? error.message : 'unknown error'}`
      );
    }
  }
}
