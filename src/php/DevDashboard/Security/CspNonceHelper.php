<?php

declare(strict_types=1);

namespace DevDashboard\Security;

/**
 * CSP Nonce Helper for DevDashboard
 *
 * DevDashboard-specific implementation for CSP nonce access.
 * Falls back to global CSP_NONCE constant if available (set by App).
 */
final class CspNonceHelper
{
    /**
     * Get CSP nonce for inline scripts and styles
     *
     * @return string Base64-encoded nonce value
     */
    public static function get(): string
    {
        // Use global CSP_NONCE constant if available (set by public/index.php)
        if (defined('CSP_NONCE')) {
            return constant('CSP_NONCE');
        }

        // Fallback: Use global nonce() function if available
        if (function_exists('nonce')) {
            return nonce();
        }

        // Last resort: Return empty string (scripts will be blocked by CSP)
        // This should never happen in practice
        return '';
    }
}
