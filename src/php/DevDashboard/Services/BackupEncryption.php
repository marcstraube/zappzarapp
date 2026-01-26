<?php

declare(strict_types=1);

namespace DevDashboard\Services;

use RuntimeException;

/**
 * Backup Encryption Configuration
 *
 * Value object representing backup encryption state
 */
final readonly class BackupEncryption
{
    private function __construct(
        private bool $enabled,
        private ?string $key,
    ) {
        if ($this->enabled && ($this->key === null || $this->key === '')) {
            throw new RuntimeException('Encryption key is required when encryption is enabled');
        }
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getKey(): ?string
    {
        return $this->key;
    }

    public function getFileExtension(): string
    {
        return $this->enabled ? '.sql.gz.enc' : '.sql';
    }

    /**
     * Create unencrypted backup configuration
     */
    public static function disabled(): self
    {
        return new self(false, null);
    }

    /**
     * Create encrypted backup configuration
     */
    public static function enabled(string $key): self
    {
        return new self(true, $key);
    }

    /**
     * Create from boolean flag and optional key
     */
    public static function fromFlag(bool $encrypt, ?string $key): self
    {
        return $encrypt ? self::enabled($key ?? '') : self::disabled();
    }
}
