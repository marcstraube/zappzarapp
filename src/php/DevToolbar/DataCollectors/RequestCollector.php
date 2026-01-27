<?php

declare(strict_types=1);

namespace DevToolbar\DataCollectors;

/**
 * Collects HTTP request/response data
 *
 * Tracks request method, headers, body, session, cookies with
 * sensitive data filtering.
 */
class RequestCollector implements CollectorInterface
{
    private const SENSITIVE_PATTERNS = [
        'password', 'passwd', 'pwd',
        'secret', 'token', 'api_key', 'apikey',
        'private_key', 'access_token', 'refresh_token',
        'session', 'cookie', 'authorization',
        'credit_card', 'cvv', 'ssn',
    ];

    private array $data = [];
    private float $startTime;
    private int $startMemory;

    public function start(): void
    {
        $this->startTime = microtime(true);
        $this->startMemory = memory_get_usage();

        $this->data = [
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'uri' => $_SERVER['REQUEST_URI'] ?? '/',
            'protocol' => $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1',
            'headers' => $this->collectHeaders(),
            'get' => $this->filterSensitiveData($_GET),
            'post' => $this->filterSensitiveData($_POST),
            'server' => $this->filterSensitiveData($_SERVER),
            'cookies' => $this->filterSensitiveData($_COOKIE),
        ];
    }

    public function stop(): void
    {
        $this->data['execution_time'] = round((microtime(true) - $this->startTime) * 1000, 2);
        $this->data['memory_peak'] = round(memory_get_peak_usage() / 1024 / 1024, 2);
        $this->data['memory_usage'] = round((memory_get_usage() - $this->startMemory) / 1024 / 1024, 2);

        // Collect response headers if available
        if (function_exists('headers_list')) {
            $this->data['response_headers'] = $this->parseResponseHeaders(headers_list());
        }

        // Collect HTTP status code
        $this->data['status_code'] = http_response_code();
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    public function getName(): string
    {
        return 'request';
    }

    /**
     * Collect request headers
     *
     * @return array<string, string>
     */
    private function collectHeaders(): array
    {
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $header = str_replace('_', '-', substr($key, 5));
                $headers[$header] = $value;
            }
        }

        return $this->filterSensitiveData($headers);
    }

    /**
     * Parse response headers from headers_list()
     *
     * @param array<int, string> $headersList
     * @return array<string, string>
     */
    private function parseResponseHeaders(array $headersList): array
    {
        $headers = [];

        foreach ($headersList as $header) {
            $parts = explode(':', $header, 2);
            if (count($parts) === 2) {
                $headers[trim($parts[0])] = trim($parts[1]);
            }
        }

        return $headers;
    }

    /**
     * Filter sensitive data from arrays
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function filterSensitiveData(array $data): array
    {
        $filtered = [];

        foreach ($data as $key => $value) {
            $keyLower = strtolower((string)$key);
            $isSensitive = false;

            foreach (self::SENSITIVE_PATTERNS as $pattern) {
                if (str_contains($keyLower, $pattern)) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                $filtered[$key] = '[FILTERED]';
            } elseif (is_array($value)) {
                $filtered[$key] = self::filterSensitiveData($value);
            } else {
                $filtered[$key] = $value;
            }
        }

        return $filtered;
    }
}
