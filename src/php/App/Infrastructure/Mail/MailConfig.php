<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use App\Infrastructure\Config\CredentialLoader;

/**
 * Mail Configuration
 *
 * Resolves the SMTP transport DSN and the default sender address from:
 * - Direct constructor parameter (DSN)
 * - MAIL_DSN / MAILER_DSN environment variables (full symfony/mailer DSN)
 * - Individual MAIL_HOST / MAIL_PORT / MAIL_USER variables plus the
 *   mail_password Docker secret (via CredentialLoader)
 *
 * Priority order for the DSN:
 * 1. DSN parameter (constructor)
 * 2. MAIL_DSN environment variable
 * 3. MAILER_DSN environment variable
 * 4. Built from MAIL_HOST/MAIL_PORT/MAIL_USER (+ secret), default smtp://mailpit:1025
 *
 * @package Infrastructure\Mail
 *
 * @SuppressWarnings("PHPMD.Superglobals") Required for environment variable access
 */
final readonly class MailConfig
{
    /** Default SMTP host (the dev Mailpit catch-all) */
    private const string DEFAULT_HOST = 'mailpit';

    /** Default SMTP port (Mailpit SMTP listener) */
    private const string DEFAULT_PORT = '1025';

    /** Default sender used when a message omits its "from" address */
    private const string DEFAULT_FROM = 'no-reply@localhost';

    public string $dsn;

    public string $defaultFrom;

    public function __construct(?string $dsn = null, ?CredentialLoader $credentials = null)
    {
        $credentials ??= CredentialLoader::docker();

        $this->dsn         = $dsn ?? $this->resolveDsn($credentials);
        $this->defaultFrom = $this->getEnv('MAIL_FROM', self::DEFAULT_FROM);
    }

    /**
     * Resolve the transport DSN from environment or discrete parts
     */
    private function resolveDsn(CredentialLoader $credentials): string
    {
        $envDsn = $this->getEnv('MAIL_DSN', '');
        if ($envDsn === '') {
            $envDsn = $this->getEnv('MAILER_DSN', '');
        }

        if ($envDsn !== '') {
            return $envDsn;
        }

        $host = $this->getEnv('MAIL_HOST', self::DEFAULT_HOST);
        $port = $this->getEnv('MAIL_PORT', self::DEFAULT_PORT);
        $user = $this->getEnv('MAIL_USER', '');

        if ($user === '') {
            return sprintf('smtp://%s:%s', $host, $port);
        }

        $password = $credentials->tryLoad('mail_password', 'MAIL_PASSWORD') ?? '';

        return sprintf(
            'smtp://%s:%s@%s:%s',
            rawurlencode($user),
            rawurlencode($password),
            $host,
            $port,
        );
    }

    /**
     * Get a non-empty string value from the environment
     */
    private function getEnv(string $name, string $default): string
    {
        $value = $_ENV[$name] ?? getenv($name);

        if (is_string($value) && $value !== '') {
            return $value;
        }

        return $default;
    }
}
