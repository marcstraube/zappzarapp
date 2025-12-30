<?php

declare(strict_types=1);

namespace App\Http\Controller;

/**
 * Example Controller
 *
 * Demonstrates basic PHP MVC pattern.
 */
class ExampleController
{
    public function index(): void
    {
        header('Content-Type: application/json');
        echo json_encode([
            'message'     => 'Hello from PHP!',
            'timestamp'   => time(),
            'php_version' => PHP_VERSION,
            'server'      => 'PHP-FPM 8.4',
        ], JSON_THROW_ON_ERROR);
    }

    public function health(): void
    {
        header('Content-Type: application/json');
        http_response_code(200);
        echo json_encode([
            'status'    => 'ok',
            'service'   => 'php-backend',
            'timestamp' => date('c'),
        ], JSON_THROW_ON_ERROR);
    }
}
