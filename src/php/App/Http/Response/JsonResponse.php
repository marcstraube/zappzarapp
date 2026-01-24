<?php

declare(strict_types=1);

namespace App\Http\Response;

final readonly class JsonResponse implements Response
{
    public function __construct(
        private mixed $data,
        private int $status = 200,
        private int $flags = JSON_THROW_ON_ERROR,
    ) {}

    public function send(): void
    {
        http_response_code($this->status);
        header('Content-Type: application/json');
        /** @phpstan-ignore-next-line echo is intentional in Response classes (WI-79952) */
        echo json_encode($this->data, $this->flags);
    }
}
