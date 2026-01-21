<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Infrastructure\HealthCheck;
use App\Infrastructure\ViteHelper;

/**
 * Welcome Controller
 *
 * Displays the main landing page with service status dashboard
 */
readonly class WelcomeController
{
    public function __construct(
        private ViteHelper $vite,
        private HealthCheck $health,
    ) {}

    /**
     * Display the welcome page.
     *
     * @SuppressWarnings("PHPMD.UnusedLocalVariable") Variables are used in the template
     */
    public function index(): void
    {
        $vite   = $this->vite;
        $health = $this->health;
        $env    = $health->getEnvironment();
        $status = $health->checkAll();

        // Set header for HTML
        header('Content-Type: text/html; charset=utf-8');

        // Render template
        include __DIR__ . '/../../../../../templates/app/welcome.php';
    }
}
