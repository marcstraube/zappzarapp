<?php
/**
 * Adminer Login Servers Plugin Configuration
 *
 * Pre-configures database server connections for development.
 * User still needs to click Login (no auto-login for security).
 */

require_once('/var/www/html/plugins/login-servers.php');

return new AdminerLoginServers([
    'PostgreSQL (local)' => [
        'server' => 'postgres',
        'driver' => 'pgsql',
    ],
    'MariaDB (local)' => [
        'server' => 'mariadb',
        'driver' => 'server',  // 'server' means MySQL/MariaDB
    ],
]);
