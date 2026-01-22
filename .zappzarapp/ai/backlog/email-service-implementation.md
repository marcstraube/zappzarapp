# Email Service Implementation

**Status:** Open
**Size:** Medium
**Scope:** feature
**Created:** 2026-01-22
**Planning:** Required

## Context

Mailpit is configured as a container service but there is no application code to
send emails. Need PHP and Node implementations to utilize Mailpit for testing.

## Goal

Working email services in both PHP and Node that:

- Send emails via SMTP to Mailpit in development
- Are testable with unit/integration tests
- Follow existing service patterns (Interface, Config, Implementation)

## Before Starting

Use Plan Mode to analyze:

- Existing service patterns (EncryptionService, QueueService as reference)
- SMTP configuration in .env
- Mailpit SMTP port and settings
- Symfony Mailer vs PHPMailer vs native for PHP
- Nodemailer for Node

## Implementation

1. PHP: Create `Infrastructure/Mail/` with MailService, MailConfig, MailInterface
2. Node: Create `services/MailService.ts`
3. Add SMTP configuration to .env.example
4. Add health check endpoint for mail connectivity
5. Create unit tests with mocked SMTP
6. Create integration tests with Mailpit

## Files

- `src/php/App/Infrastructure/Mail/MailService.php`
- `src/php/App/Infrastructure/Mail/MailConfig.php`
- `src/php/App/Infrastructure/Mail/MailInterface.php`
- `src/node/backend/services/MailService.ts`
- `.env.example` (SMTP settings)
- `tests/php/App/Unit/Infrastructure/Mail/`
- `tests/node/backend/unit/services/MailService.test.ts`

## Notes

Mailpit SMTP default: port 1025, no auth required in dev.
