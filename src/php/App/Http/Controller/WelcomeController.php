<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Http\Response\HtmlResponse;
use App\Http\Response\Response;
use App\Infrastructure\HealthCheck;
use App\Infrastructure\TwigService;
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
        private TwigService $twig,
    ) {}

    /**
     * Display the welcome page
     */
    public function index(): Response
    {
        $env    = $this->health->getEnvironment();
        $status = $this->health->checkAll();

        // Check for API documentation (only in development)
        $docsBase             = '/var/www/html/docs/api';
        $phpDocsExist         = file_exists($docsBase . '/php/index.html');
        $nodeBackendDocsExist = file_exists($docsBase . '/node-backend/index.html');
        $hasPhpSource         = is_dir('/var/www/html/src/php');
        $hasNodeBackendSource = is_dir('/var/www/html/src/node/backend');
        $docsMissing          = ($hasPhpSource && !$phpDocsExist) || ($hasNodeBackendSource && !$nodeBackendDocsExist);

        $html = $this->twig->render('app/welcome.html.twig', [
            'vite'                  => $this->vite,
            'env'                   => $env,
            'status'                => $status,
            'phpDocsExist'          => $phpDocsExist,
            'nodeBackendDocsExist'  => $nodeBackendDocsExist,
            'hasPhpSource'          => $hasPhpSource,
            'hasNodeBackendSource'  => $hasNodeBackendSource,
            'docsMissing'           => $docsMissing,
        ]);

        return new HtmlResponse($html);
    }
}
