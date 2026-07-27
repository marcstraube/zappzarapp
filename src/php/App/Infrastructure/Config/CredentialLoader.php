<?php

declare(strict_types=1);

namespace App\Infrastructure\Config;

use Zappzarapp\Security\Secrets\Exception\SecretLoadException;
use Zappzarapp\Security\Secrets\SecretLoader;
use Zappzarapp\Security\Secrets\SecretValue;

/**
 * Resolves service credentials with a single, explicit priority chain
 *
 * Priority:
 * 1. Docker secret (zappzarapp/security SecretLoader, "<name>" or
 *    "<name>.txt" below /run/secrets)
 * 2. Environment variables (explicit list, because several services use
 *    env names that differ from their secret name, e.g. S3_ACCESS_KEY
 *    for the seaweedfs_access_key secret)
 * 3. An insecure development default, only via the loadWithInsecureDefault()
 *    opt-in - production setups provide the secret file
 *
 * A secret file that exists but is empty or unreadable throws
 * (SecretLoadException, fail-closed): that is a misconfiguration, not an
 * absent secret, and must not fall through to a default.
 *
 * @SuppressWarnings("PHPMD.Superglobals") Required for environment variable access
 */
final readonly class CredentialLoader
{
    public function __construct(private SecretLoader $secrets)
    {
    }

    /**
     * Create a loader for Docker secrets under /run/secrets
     */
    public static function docker(): self
    {
        return new self(SecretLoader::docker());
    }

    /**
     * Resolve an optional credential: secret, then env vars, then null
     *
     * Use for credentials where "not configured" is a valid state
     * (e.g. services that run without authentication in development).
     *
     * @throws SecretLoadException If the secret file exists but is empty or unreadable
     */
    public function tryLoad(string $secretName, string ...$envNames): ?string
    {
        $secret = $this->secrets->tryLoad($secretName);

        if ($secret instanceof SecretValue) {
            return $secret->reveal();
        }

        foreach ($envNames as $envName) {
            $value = $this->getEnvString($envName);

            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Resolve a credential, falling back to an insecure development default
     *
     * The default is an explicit opt-in for development setups without
     * generated secrets. Never rely on it in production - provide the
     * secret file instead.
     *
     * @param list<string> $envNames Environment variable names to try (in order)
     *
     * @throws SecretLoadException If the secret file exists but is empty or unreadable
     */
    public function loadWithInsecureDefault(
        string $secretName,
        array $envNames,
        string $insecureDefault,
    ): string {
        return $this->tryLoad($secretName, ...$envNames) ?? $insecureDefault;
    }

    /**
     * Get a non-empty string value from the environment
     */
    private function getEnvString(string $name): ?string
    {
        $value = $_ENV[$name] ?? getenv($name);

        if (is_string($value) && $value !== '') {
            return $value;
        }

        return null;
    }
}
