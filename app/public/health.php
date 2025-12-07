<?php

declare(strict_types=1);

// Health Check Endpoint
if ($_SERVER['REQUEST_URI'] === '/health') {
    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'healthy', 'timestamp' => time()]);
    exit;
}
