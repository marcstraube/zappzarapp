<?php

declare(strict_types=1);

namespace DevDashboard\Response;

final readonly class JsonResponse implements Response
{
    /**
     * @param array<string, mixed>|object $data
     */
    public function __construct(
        private array|object $data,
        private int $status = 200,
    ) {}

    public function send(): void
    {
        http_response_code($this->status);
        header('Content-Type: application/json');
        /** @phpstan-ignore-next-line echo is intentional in Response classes (WI-79952) */
        echo json_encode($this->data, JSON_THROW_ON_ERROR);
    }
}
