<?php

declare(strict_types=1);

use DevDashboard\Infrastructure\TwigService;
use DevDashboard\Security\CspNonceHelper;

/**
 * DevDashboard DI Container Configuration
 *
 * Factory bindings for DevDashboard-specific services.
 * Loaded by src/php/DevDashboard/routes.php via ContainerBuilder->addDefinitions().
 */
return [
    TwigService::class => function () {
        $isDevelopment = getenv('ENV') === 'development';

        $service = $isDevelopment
            ? TwigService::createForDevelopment(
                __DIR__ . '/../templates',
                __DIR__ . '/../build/cache/twig'
            )
            : TwigService::createForProduction(
                __DIR__ . '/../templates',
                __DIR__ . '/../build/cache/twig'
            );

        // Register CSP nonce function using DevDashboard's CspNonceHelper
        $service->addFunction('nonce', [CspNonceHelper::class, 'get']);

        return $service;
    },
];
