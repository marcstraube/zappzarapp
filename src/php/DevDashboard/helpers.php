<?php

declare(strict_types=1);

/**
 * DevDashboard Helper Functions
 *
 * Convenience functions for DevDashboard templates and controllers.
 */

namespace DevDashboard;

use DevDashboard\Security\CspNonceHelper;

if (!function_exists('DevDashboard\nonce')) {
    /**
     * Get CSP nonce for inline scripts and styles in DevDashboard
     *
     * Usage in DevDashboard output:
     *   <script nonce="<?= \DevDashboard\nonce() ?>">console.log('test')</script>
     *   <style nonce="<?= \DevDashboard\nonce() ?>">body { margin: 0; }</style>
     *
     * @return string Base64-encoded nonce value
     */
    function nonce(): string
    {
        return CspNonceHelper::get();
    }
}
