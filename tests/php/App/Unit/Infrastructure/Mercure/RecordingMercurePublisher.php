<?php

declare(strict_types=1);

namespace Tests\App\Unit\Infrastructure\Mercure;

use App\Infrastructure\Mercure\MercureConfig;
use App\Infrastructure\Mercure\MercurePublisher;
use Override;

/**
 * Test double for MercurePublisher.
 *
 * Records the arguments passed to the request() I/O seam and returns a
 * canned response instead of making a real HTTP call.
 */
final class RecordingMercurePublisher extends MercurePublisher
{
    public ?string $method = null;

    public ?string $url = null;

    public ?string $body = null;

    /** @var array<int, string> */
    public array $headers = [];

    /** @param array{status: int, body: string, error: string|null} $cannedResponse */
    public function __construct(MercureConfig $config, private readonly array $cannedResponse)
    {
        parent::__construct($config);
    }

    /**
     * @param array<int, string> $headers
     * @return array{status: int, body: string, error: string|null}
     */
    #[Override]
    protected function request(string $method, string $url, ?string $body, array $headers): array
    {
        $this->method  = $method;
        $this->url     = $url;
        $this->body    = $body;
        $this->headers = $headers;

        return $this->cannedResponse;
    }
}
