<?php

declare(strict_types=1);

namespace DevToolbar\Guard;

/**
 * Security guard for Developer Toolbar
 *
 * Ensures toolbar only loads in development environment with multiple
 * layers of protection (defense in depth).
 */
class DevToolbarGuard
{
    /**
     * Check if Developer Toolbar should be enabled
     *
     * @return bool True if toolbar should be enabled, false otherwise
     */
    public static function isEnabled(): bool
    {
        // Layer 1: Production safety - never enable in production
        if (getenv('APP_ENV') === 'production') {
            return false;
        }

        // Layer 2: Explicit disable flag
        if (getenv('ENABLE_DEV_TOOLBAR') === 'false') {
            return false;
        }

        // Layer 3: CLI detection - never load in CLI mode
        if (PHP_SAPI === 'cli') {
            return false;
        }

        // Layer 4: Skip for DevToolbar internal AJAX requests (Phase 2.1)
        // These requests should not be tracked/stored
        if (isset($_GET['dev_toolbar_action'])) {
            return false;
        }

        // Layer 5: Optional - Skip for other AJAX requests
        if (self::isAjaxRequest()) {
            return false;
        }

        return true;
    }

    /**
     * Check if current request is an AJAX request
     *
     * @return bool True if AJAX request, false otherwise
     */
    private static function isAjaxRequest(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}
