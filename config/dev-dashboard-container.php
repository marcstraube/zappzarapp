<?php

declare(strict_types=1);

use App\Infrastructure\ViteHelper;
use DevDashboard\Infrastructure\TwigService;
use Zappzarapp\Security\Csp\Nonce\NonceRegistry;

/**
 * DevDashboard DI Container Configuration
 *
 * Factory bindings for DevDashboard-specific services.
 * Loaded by src/php/DevDashboard/routes.php via ContainerBuilder->addDefinitions().
 */
return [
    TwigService::class => function () {
        $isDevelopment = getenv('ZAPPZARAPP_ENV') === 'development';

        $service = $isDevelopment
            ? TwigService::createForDevelopment(
                __DIR__ . '/../templates',
                __DIR__ . '/../build/cache/twig'
            )
            : TwigService::createForProduction(
                __DIR__ . '/../templates',
                __DIR__ . '/../build/cache/twig'
            );

        // Register CSP nonce function
        $service->addFunction('nonce', NonceRegistry::get(...));

        // Vite asset helper for pages with extracted TS modules (e.g. logs)
        $service->addGlobal('vite', new ViteHelper());

        return $service;
    },
];
