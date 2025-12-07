<?php

declare(strict_types=1);

/**
 * =========================================================================
 * SECURITY UPGRADE: CONTENT SECURITY POLICY (CSP)
 * * The Nginx server provides a basic CSP for compatibility.
 * * !!! TO ACHIEVE MAXIMUM SECURITY (Strict-Dynamic CSP):
 * 1. REMOVE the static 'add_header Content-Security-Policy' line from your Nginx config.
 * 2. UNCOMMENT and INTEGRATE this PHP logic into your application's bootstrap
 * BEFORE any output is sent.
 * 3. Use the defined 'CSP_NONCE' constant in all your inline <script> and
 * <style> tags.
 * * See the documentation for a full explanation of the 'strict-dynamic' policy.
 * =========================================================================
 */

// 1. GENERATE NONCE
//$nonce = base64_encode(random_bytes(16));

// 2. CREATE CSP HEADER
/*$csp_directives = [
    "default-src 'self'",
    "script-src 'self' 'nonce-{$nonce}' 'strict-dynamic'",
    "style-src 'self' 'nonce-{$nonce}'",
    "connect-src 'self'",
    "frame-ancestors 'self'",
];
$csp_value = implode('; ', $csp_directives) . ';';*/

// 3. SEND CSP HEADER
//header("Content-Security-Policy: {$csp_value}");

// 4. STORE NONCE FOR ACCESS IN TEMPLATES
//define('CSP_NONCE', $nonce);

echo "<h1>Initial Setup Successful!</h1>";

phpinfo();
