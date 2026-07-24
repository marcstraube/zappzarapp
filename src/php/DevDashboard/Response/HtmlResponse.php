<?php

declare(strict_types=1);

namespace DevDashboard\Response;

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

    /**
     * Create HtmlResponse from a view file
     *
     * @param array<string, mixed> $data
     * @param string|null          $viewsDir Absolute path to views directory (injectable for tests; defaults to DevDashboard/Views)
     */
    public static function fromView(string $view, array $data = [], int $status = 200, ?string $viewsDir = null): self
    {
        $viewsDir ??= __DIR__ . '/../Views';
        $viewPath   = $viewsDir . '/' . $view . '.php';

        if (!file_exists($viewPath)) {
            return new self('View not found: ' . $view, 404);
        }

        extract($data, EXTR_SKIP);
        ob_start();
        include $viewPath;
        $content = ob_get_clean();

        return new self($content ?: '', $status);
    }
}
