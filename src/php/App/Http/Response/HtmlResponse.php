<?php

declare(strict_types=1);

namespace App\Http\Response;

final readonly class HtmlResponse implements Response
{
    public function __construct(
        private string $content,
        private int $status = 200,
    ) {}

    public function send(): void
    {
        http_response_code($this->status);
        header('Content-Type: text/html; charset=UTF-8');
        /** @phpstan-ignore-next-line echo is intentional in Response classes (WI-79952) */
        echo $this->content;
    }
}
