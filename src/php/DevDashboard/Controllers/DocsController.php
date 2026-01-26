<?php

declare(strict_types=1);

namespace DevDashboard\Controllers;

use DevDashboard\Response\JsonResponse;
use DevDashboard\Response\Response;
use DevDashboard\Services\DocsService;
use Exception;

/**
 * Documentation Controller
 *
 * Handles API documentation generation endpoints
 */
readonly class DocsController
{
    public function __construct(
        private DocsService $docsService,
    ) {}

    /**
     * API: Generate PHP documentation (JSON)
     *
     * @throws Exception If documentation generation fails
     */
    public function apiGenerateDocs(): Response
    {
        $type = $_GET['type'] ?? 'php';

        if ($type !== 'php') {
            return new JsonResponse([
                'success' => false,
                'message' => 'Only PHP docs can be generated from dashboard. Run "make docs-node" for Node docs.',
            ], 400);
        }

        $result = $this->docsService->generatePhpDocs();

        return new JsonResponse($result, $result['success'] ? 200 : 500);
    }
}
