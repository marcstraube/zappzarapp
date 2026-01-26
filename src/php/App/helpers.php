<?php

declare(strict_types=1);

/**
 * Global Helper Functions
 *
 * Convenience functions for common operations.
 * Loaded via composer autoload files.
 */

use App\Security\CspNonceHelper;

if (!function_exists('nonce')) {
    /**
     * Get CSP nonce for inline scripts and styles
     *
     * Usage in templates:
     *   <script nonce="<?= nonce() ?>">console.log('test')</script>
     *   <style nonce="<?= nonce() ?>">body { margin: 0; }</style>
     *
     * @return string Base64-encoded nonce value
     */
    function nonce(): string
    {
        return CspNonceHelper::get();
    }
}
