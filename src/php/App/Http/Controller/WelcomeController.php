<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Infrastructure\ViteHelper;
use App\Infrastructure\HealthCheck;

/**
 * Welcome Controller
 *
 * Displays the main landing page with service status dashboard
 */
class WelcomeController
{
    /**
     * Display the welcome page.
     *
     * @SuppressWarnings("PHPMD.UnusedLocalVariable") Variables are used in the template
     */
    public function index(): void
    {
        $vite   = new ViteHelper();
        $health = new HealthCheck();
        $env    = $health->getEnvironment();
        $status = $health->checkAll();

        // Set header for HTML
        header('Content-Type: text/html; charset=utf-8');

        // Render template
        include __DIR__ . '/../../../../../templates/welcome.php';
    }
}
