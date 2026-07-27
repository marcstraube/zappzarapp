/**
 * Tests for MailService
 *
 * The nodemailer transporter is injected, so no real SMTP connection is made.
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import nodemailer, { type Transporter } from 'nodemailer';
import { MailService, MailError } from '@backend/App/Services/MailService';

vi.mock('nodemailer', () => ({
  default: {
    createTransport: vi.fn(() => ({
      sendMail: vi.fn().mockResolvedValue({ messageId: 'mocked' }),
    })),
  },
}));

interface SentMail {
  from?: string;
  to?: string | string[];
  cc?: string | string[];
  bcc?: string | string[];
  replyTo?: string;
  subject?: string;
  text?: string;
  html?: string;
}

/**
 * Build a fake transporter whose sendMail records the payload and resolves.
 */
function recordingTransporter(): { transporter: Transporter; sendMail: ReturnType<typeof vi.fn> } {
  const sendMail = vi.fn().mockResolvedValue({ messageId: 'test-id' });
  return { transporter: { sendMail } as unknown as Transporter, sendMail };
}

const MAIL_ENV_VARS = [
  'MAIL_FROM',
  'MAIL_DSN',
  'MAILER_DSN',
  'MAIL_HOST',
  'MAIL_PORT',
  'MAIL_USER',
  'MAIL_PASSWORD',
];

describe('MailService', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    for (const name of MAIL_ENV_VARS) {
      delete process.env[name];
    }
  });

  it('should send an email with the default sender', async () => {
    const { transporter, sendMail } = recordingTransporter();
    const service = new MailService({ transporter });

    await service.send({
      to: 'user@example.com',
      subject: 'Welcome',
      text: 'Plain',
      html: '<p>Rich</p>',
    });

    expect(sendMail).toHaveBeenCalledTimes(1);
    const payload = sendMail.mock.calls[0]?.[0] as SentMail;
    expect(payload.from).toBe('no-reply@localhost');
    expect(payload.to).toBe('user@example.com');
    expect(payload.subject).toBe('Welcome');
    expect(payload.text).toBe('Plain');
    expect(payload.html).toBe('<p>Rich</p>');
  });

  it('should apply sender, cc, bcc and replyTo', async () => {
    const { transporter, sendMail } = recordingTransporter();
    const service = new MailService({ transporter });

    await service.send({
      to: ['a@example.com', 'b@example.com'],
      subject: 'Subject',
      text: 'Body',
      from: 'sender@example.com',
      cc: ['cc@example.com'],
      bcc: ['bcc@example.com'],
      replyTo: 'reply@example.com',
    });

    const payload = sendMail.mock.calls[0]?.[0] as SentMail;
    expect(payload.from).toBe('sender@example.com');
    expect(payload.to).toEqual(['a@example.com', 'b@example.com']);
    expect(payload.cc).toEqual(['cc@example.com']);
    expect(payload.bcc).toEqual(['bcc@example.com']);
    expect(payload.replyTo).toBe('reply@example.com');
  });

  it('should use the MAIL_FROM environment default', async () => {
    process.env.MAIL_FROM = 'hello@example.com';
    const { transporter, sendMail } = recordingTransporter();
    const service = new MailService({ transporter });

    await service.send({ to: 'user@example.com', subject: 'S', text: 'B' });

    const payload = sendMail.mock.calls[0]?.[0] as SentMail;
    expect(payload.from).toBe('hello@example.com');
  });

  it('should throw when there are no recipients', async () => {
    const { transporter, sendMail } = recordingTransporter();
    const service = new MailService({ transporter });

    await expect(service.send({ to: [], subject: 'S', text: 'B' })).rejects.toBeInstanceOf(
      MailError
    );
    await expect(service.send({ to: '', subject: 'S', text: 'B' })).rejects.toThrow(
      'at least one recipient'
    );
    expect(sendMail).not.toHaveBeenCalled();
  });

  it('should throw when there is no body', async () => {
    const { transporter, sendMail } = recordingTransporter();
    const service = new MailService({ transporter });

    await expect(service.send({ to: 'user@example.com', subject: 'S' })).rejects.toThrow(
      'text or HTML body'
    );
    expect(sendMail).not.toHaveBeenCalled();
  });

  it('should wrap transport failures in a MailError', async () => {
    const sendMail = vi.fn().mockRejectedValue(new Error('smtp connection failed'));
    const service = new MailService({ transporter: { sendMail } as unknown as Transporter });

    await expect(service.send({ to: 'user@example.com', subject: 'S', text: 'B' })).rejects.toThrow(
      'Failed to send email'
    );
  });

  describe('DSN resolution', () => {
    const createTransport = vi.mocked(nodemailer.createTransport);

    it('should default to the Mailpit catch-all', () => {
      new MailService();

      expect(createTransport).toHaveBeenCalledWith('smtp://mailpit:1025');
    });

    it('should prefer an explicit dsn option', () => {
      new MailService({ dsn: 'smtp://given:1025' });

      expect(createTransport).toHaveBeenCalledWith('smtp://given:1025');
    });

    it('should read the dsn from MAIL_DSN', () => {
      process.env.MAIL_DSN = 'smtp://user:pass@smtp.example.com:587';

      new MailService();

      expect(createTransport).toHaveBeenCalledWith('smtp://user:pass@smtp.example.com:587');
    });

    it('should fall back to MAILER_DSN', () => {
      process.env.MAILER_DSN = 'smtp://fallback:1025';

      new MailService();

      expect(createTransport).toHaveBeenCalledWith('smtp://fallback:1025');
    });

    it('should build the dsn from discrete host and port', () => {
      process.env.MAIL_HOST = 'smtp.example.com';
      process.env.MAIL_PORT = '2525';

      new MailService();

      expect(createTransport).toHaveBeenCalledWith('smtp://smtp.example.com:2525');
    });

    it('should build an authenticated dsn from user and password', () => {
      process.env.MAIL_HOST = 'smtp.example.com';
      process.env.MAIL_PORT = '587';
      process.env.MAIL_USER = 'me@example.com';
      process.env.MAIL_PASSWORD = 's3cr3t';

      new MailService();

      expect(createTransport).toHaveBeenCalledWith(
        'smtp://me%40example.com:s3cr3t@smtp.example.com:587'
      );
    });
  });
});
