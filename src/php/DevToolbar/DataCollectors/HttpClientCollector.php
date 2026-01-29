<?php

declare(strict_types=1);

namespace DevToolbar\DataCollectors;
use CurlHandle;

/**
 * HTTP Client Collector
 *
 * Tracks all outgoing HTTP requests (cURL, file_get_contents, etc.)
 */
class HttpClientCollector implements CollectorInterface
{
    /** @var array<int, array<string, mixed>> */
    private array $requests  = [];
    private bool $collecting = false;

    /**
     * Start collecting HTTP requests
     */
    public function start(): void
    {
        $this->collecting = true;
        $this->requests   = [];
    }

    /**
     * Stop collecting HTTP requests
     */
    public function stop(): void
    {
        $this->collecting = false;
    }

    /**
     * Get collector name
     */
    public function getName(): string
    {
        return 'http';
    }

    /**
     * Get collected HTTP request data
     */
    public function getData(): array
    {
        $totalTime = array_sum(array_column($this->requests, 'time'));

        return [
            'requests'   => $this->requests,
            'count'      => count($this->requests),
            'total_time' => round($totalTime, 2),
        ];
    }

    /**
     * Track an HTTP request
     *
     * @param string $method HTTP method (GET, POST, etc.)
     * @param string $url Request URL
     * @param float $time Execution time in milliseconds
     * @param int $status HTTP status code
     * @param array<string, mixed> $headers Response headers
     * @param string|false $body Response body
     * @param array<string, mixed> $requestData Request data (headers, body, etc.)
     * @return void
     */
    public function trackRequest(
        string $method,
        string $url,
        float $time,
        int $status,
        array $headers = [],
        string|false $body = '',
        array $requestData = []
    ): void {
        if (!$this->collecting) {
            return;
        }

        $this->requests[] = [
            'method'            => $method,
            'url'               => $url,
            'time'              => round($time, 2),
            'status'            => $status,
            'headers'           => $headers,
            'body'              => $this->filterSensitiveData($body),
            'request_data'      => $this->filterSensitiveData($requestData),
            'backtrace'         => $this->getRelevantBacktrace(),
            'performance_level' => $this->getPerformanceLevel($time),
        ];
    }

    /**
     * Wrapper for file_get_contents
     *
     * @param string $url URL to fetch
     * @param mixed ...$args Additional arguments
     * @return string|false Response content
     */
    public function wrapFileGetContents(string $url, ...$args): string|false
    {
        if (!$this->collecting) {
            return file_get_contents($url, ...$args);
        }

        $start  = hrtime(true);
        $result = file_get_contents($url, ...$args);
        $time   = (hrtime(true) - $start) / 1_000_000; // Convert to milliseconds

        // $http_response_header is set by file_get_contents() in the calling scope
        /** @var array<int, string> $responseHeaders */
        $responseHeaders = $http_response_header;
        $status          = $this->parseHttpStatus($responseHeaders);

        $this->trackRequest(
            'GET',
            $url,
            $time,
            $status,
            $this->parseHeaders($responseHeaders),
            $result
        );

        return $result;
    }

    /**
     * Wrapper for curl_exec
     *
     * @param CurlHandle|resource $ch cURL handle
     * @return string|bool Response content
     */
    public function wrapCurlExec($ch): string|bool
    {
        if (!$this->collecting) {
            return curl_exec($ch);
        }

        $start  = hrtime(true);
        $result = curl_exec($ch);
        $time   = (hrtime(true) - $start) / 1_000_000; // Convert to milliseconds

        $info = curl_getinfo($ch);

        $this->trackRequest(
            $info['http_method'] ?? 'GET',
            $info['url'] ?? 'unknown',
            $time,
            $info['http_code'] ?? 0,
            [],
            is_string($result) ? $result : '',
            ['curl_info' => $info]
        );

        return $result;
    }

    /**
     * Parse HTTP status code from response headers
     *
     * @param array<int, string> $headers Response headers
     * @return int Status code
     */
    private function parseHttpStatus(array $headers): int
    {
        if (empty($headers)) {
            return 0;
        }

        // First header line contains status code
        $statusLine = $headers[0] ?? '';
        if (preg_match('/HTTP\/\d\.\d\s+(\d+)/', $statusLine, $matches)) {
            return (int)$matches[1];
        }

        return 0;
    }

    /**
     * Parse response headers from array to associative array
     *
     * @param array<int, string> $headers Raw response headers
     * @return array<string, mixed> Parsed headers
     */
    private function parseHeaders(array $headers): array
    {
        $parsed = [];

        foreach ($headers as $header) {
            // Skip status line
            if (str_starts_with($header, 'HTTP/')) {
                continue;
            }

            // Parse "Name: Value" format
            if (str_contains($header, ':')) {
                [$name, $value]      = explode(':', $header, 2);
                $parsed[trim($name)] = trim($value);
            }
        }

        return $parsed;
    }

    /**
     * Get relevant backtrace (filter out internal calls)
     *
     * @return array<int, array<string, mixed>> Filtered backtrace
     */
    private function getRelevantBacktrace(): array
    {
        $trace    = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        $relevant = [];

        foreach ($trace as $frame) {
            // Skip internal DevToolbar calls
            if (isset($frame['class']) && str_starts_with($frame['class'], 'DevToolbar\\')) {
                continue;
            }

            // Skip PHP internal functions
            if (!isset($frame['file'])) {
                continue;
            }

            $relevant[] = [
                'file'     => str_replace(getcwd() . '/', '', $frame['file'] ?? ''),
                'line'     => $frame['line'] ?? 0,
                'function' => $frame['function'] ?? '',
                'class'    => $frame['class'] ?? '',
            ];

            // Only keep first relevant frame
            if (count($relevant) >= 1) {
                break;
            }
        }

        return $relevant;
    }

    /**
     * Filter sensitive data from request/response
     *
     * @param mixed $data Data to filter
     * @return mixed Filtered data
     */
    private function filterSensitiveData(mixed $data): mixed
    {
        if ($data === false) {
            return false;
        }

        if (is_string($data)) {
            // Truncate long responses
            if (strlen($data) > 10000) {
                return substr($data, 0, 10000) . '... (truncated)';
            }

            // Filter common sensitive patterns
            $data = preg_replace('/("password"\s*:\s*)"[^"]*"/', '$1"[FILTERED]"', $data);
            $data = preg_replace('/("token"\s*:\s*)"[^"]*"/', '$1"[FILTERED]"', $data);
            $data = preg_replace('/("api_key"\s*:\s*)"[^"]*"/', '$1"[FILTERED]"', $data);
            $data = preg_replace('/("secret"\s*:\s*)"[^"]*"/', '$1"[FILTERED]"', $data);
        }

        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if (in_array(strtolower($key), ['password', 'token', 'api_key', 'secret', 'authorization'])) {
                    $data[$key] = '[FILTERED]';
                } elseif (is_string($value) || is_array($value)) {
                    $data[$key] = $this->filterSensitiveData($value);
                }
            }
        }

        return $data;
    }

    /**
     * Determine performance level based on execution time
     *
     * @param float $time Time in milliseconds
     * @return string Performance level: 'good', 'warning', or 'critical'
     */
    private function getPerformanceLevel(float $time): string
    {
        if ($time < 200) {
            return 'good';
        } elseif ($time < 500) {
            return 'warning';
        } else {
            return 'critical';
        }
    }

    /**
     * Check if collecting is active
     *
     * @return bool True if collecting
     */
    public function isCollecting(): bool
    {
        return $this->collecting;
    }
}
