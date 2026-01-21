<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

/**
 * User Repository Interface
 *
 * Extends RepositoryInterface with user-specific operations.
 * Designed for the users table with TOTP 2FA support.
 *
 * Return Value Conventions:
 * - null: User not found (expected case)
 * - false: Operation failed (error)
 * - data: Operation succeeded
 *
 * Usage:
 * <code>
 * // Find user by email
 * $user = $repository->findByEmail('user@example.com');
 *
 * // Check if email exists
 * if ($repository->emailExists('user@example.com')) {
 *     // Email already taken
 * }
 *
 * // Enable 2FA for user
 * $repository->enableTotp(1, 'JBSWY3DPEHPK3PXP');
 *
 * // Disable 2FA
 * $repository->disableTotp(1);
 * </code>
 *
 * @package Infrastructure\Database
 */
interface UserRepositoryInterface extends RepositoryInterface
{
    /**
     * Find a user by email address
     *
     * @param string $email Email address to search for
     *
     * @return array<string, mixed>|null User data or null if not found
     */
    public function findByEmail(string $email): ?array;

    /**
     * Check if an email address is already registered
     *
     * @param string   $email         Email address to check
     * @param int|null $excludeUserId Exclude this user ID from the check (for updates)
     *
     * @return bool True if email exists (and not excluded)
     */
    public function emailExists(string $email, ?int $excludeUserId = null): bool;

    /**
     * Enable TOTP 2FA for a user
     *
     * The TOTP secret will be encrypted before storage.
     *
     * @param int    $userId User ID
     * @param string $secret TOTP secret key (plaintext, will be encrypted)
     *
     * @return bool True on success
     */
    public function enableTotp(int $userId, string $secret): bool;

    /**
     * Disable TOTP 2FA for a user
     *
     * Clears the encrypted TOTP secret and sets totp_enabled to false.
     *
     * @param int $userId User ID
     *
     * @return bool True on success
     */
    public function disableTotp(int $userId): bool;

    /**
     * Get decrypted TOTP secret for verification
     *
     * @param int $userId User ID
     *
     * @return string|null Decrypted TOTP secret or null if not enabled/found
     */
    public function getTotpSecret(int $userId): ?string;
}
